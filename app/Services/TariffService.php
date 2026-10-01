<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BusinessType;
use App\Models\Equipment;
use App\Models\ExchangeRate;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TariffService
{
    public const B2C_DUTY_PERCENTAGE = 0.10; // 10%
    public const B2B_DUTY_PERCENTAGE = 0.00; // 0% con exención aduanal válida
    public const HOME_DELIVERY_FIXED_FEE_USD = 50.00; // $50 USD

    /**
     * Calcula la liquidación financiera determinando si los conceptos ya fueron prepagados en el exterior.
     * Implementa la regla de negocio: B2B con exención XML = 0% arancel, B2C = 10% arancel.
     *
     * @param float $declaredValueUsd Valor declarado en USD
     * @param BusinessType|string $businessType Modelo B2B o B2C
     * @param bool $dutyPaidAbroad Aranceles aduanales y comisiones pagadas en origen (Exterior)
     * @param bool $deliveryPaidAbroad Servicio de envío a domicilio pagado en origen (Exterior)
     * @param bool $homeDeliveryRequested Si el cliente solicitó entrega a domicilio
     * @param string|null $targetCurrency Moneda objetivo para la conversión ('USD' o 'CUP')
     * @param array|null $customsExemptionData Datos de exención aduanal para B2B (XML parseado)
     * @return array Desglose financiero detallado
     */
    public function calculateLiquidation(
        float $declaredValueUsd,
        BusinessType|string $businessType,
        bool $dutyPaidAbroad = false,
        bool $deliveryPaidAbroad = false,
        bool $homeDeliveryRequested = false,
        ?string $targetCurrency = 'USD',
        ?array $customsExemptionData = null
    ): array {
        $bType = is_string($businessType) ? BusinessType::from($businessType) : $businessType;

        // 1. Arancel y comisiones aduanales
        if ($dutyPaidAbroad) {
            // Pagado desde el exterior: Cero cobro de aranceles en Cuba
            $dutyAmountUsd = 0.00;
            $dutyStatusNote = 'Prepagado en el Exterior (Exento de cobro en Cuba)';
            $dutyExemptionReason = 'prepaid_abroad';
        } else {
            if ($bType === BusinessType::B2B) {
                // B2B: Validar exención aduanal mediante XML/documentación
                $exemptionResult = $this->validateB2BCustomsExemption($customsExemptionData);

                if ($exemptionResult['is_exempt']) {
                    $dutyAmountUsd = 0.00;
                    $dutyStatusNote = 'Exento por documentación aduanal B2B: ' . $exemptionResult['reason'];
                    $dutyExemptionReason = 'b2b_exemption_xml';
                } else {
                    // B2B sin exención válida: aplicar 10% como fallback
                    $dutyAmountUsd = round($declaredValueUsd * self::B2C_DUTY_PERCENTAGE, 2);
                    $dutyStatusNote = 'B2B sin exención válida - Aplicado 10% (B2C fallback)';
                    $dutyExemptionReason = 'b2b_no_exemption_fallback';

                    Log::warning("Equipo B2B sin exención aduanal válida, aplicando tarifa B2C 10%", [
                        'declared_value_usd' => $declaredValueUsd,
                        'exemption_data' => $customsExemptionData,
                        'exemption_result' => $exemptionResult,
                    ]);
                }
            } else {
                // B2C: Siempre 10% arancelario
                $dutyAmountUsd = round($declaredValueUsd * self::B2C_DUTY_PERCENTAGE, 2);
                $dutyStatusNote = 'Pendiente de cobro en Cuba (10% arancelario B2C)';
                $dutyExemptionReason = 'b2c_standard';
            }
        }

        // 2. Servicio de Entrega a Domicilio
        if (!$homeDeliveryRequested) {
            $shippingFeeUsd = 0.00;
            $deliveryStatusNote = 'Retiro en Almacén Regional (Sin costo de envío)';
            $deliveryExemptionReason = 'warehouse_pickup';
        } elseif ($deliveryPaidAbroad) {
            $shippingFeeUsd = 0.00;
            $deliveryStatusNote = 'Envío a Domicilio Prepagado en el Exterior ($0.00 en Cuba)';
            $deliveryExemptionReason = 'prepaid_abroad';
        } else {
            $shippingFeeUsd = self::HOME_DELIVERY_FIXED_FEE_USD;
            $deliveryStatusNote = 'Envío a Domicilio a cobrar en Cuba ($50.00 USD)';
            $deliveryExemptionReason = 'pending_collection';
        }

        // 3. Total en USD a cobrar en Cuba
        $totalToCollectUsd = round($dutyAmountUsd + $shippingFeeUsd, 2);

        // 4. Conversión a CUP con tasa activa
        $currentRate = $this->getActiveExchangeRate('USD', 'CUP');
        $totalToCollectCup = round($totalToCollectUsd * $currentRate, 2);

        $isTotallyPrepaid = ($totalToCollectUsd <= 0.00);

        return [
            'declared_value_usd' => round($declaredValueUsd, 2),
            'business_type' => $bType->value,
            'duty_paid_abroad' => $dutyPaidAbroad,
            'delivery_paid_abroad' => $deliveryPaidAbroad,
            'duty_amount_usd' => $dutyAmountUsd,
            'duty_status_note' => $dutyStatusNote,
            'duty_exemption_reason' => $dutyExemptionReason,
            'shipping_fee_usd' => $shippingFeeUsd,
            'delivery_status_note' => $deliveryStatusNote,
            'delivery_exemption_reason' => $deliveryExemptionReason,
            'total_to_collect_usd' => $totalToCollectUsd,
            'exchange_rate' => $currentRate,
            'total_to_collect_cup' => $totalToCollectCup,
            'is_totally_prepaid' => $isTotallyPrepaid, // Flag: Nada que cobrar en Cuba
            'summary_label' => $isTotallyPrepaid
                ? '✓ 100% Prepagado en Exterior (Sin cobro en Cuba)'
                : 'Pendiente de liquidación en Cuba',
            'calculated_at' => Carbon::now()->toISOString(),
        ];
    }

    /**
     * Valida la exención aduanal para clientes B2B basándose en XML de SolveCargo o documentación adjunta.
     *
     * @param array|null $customsExemptionData Datos parseados del XML de manifiesto aduanal
     * @return array ['is_exempt' => bool, 'reason' => string, 'details' => array]
     */
    protected function validateB2BCustomsExemption(?array $customsExemptionData): array
    {
        // Si no hay datos de exención, no hay exención
        if (empty($customsExemptionData)) {
            return [
                'is_exempt' => false,
                'reason' => 'Sin documentación de exención aduanal adjunta',
                'details' => [],
            ];
        }

        // Validaciones requeridas para exención B2B según normativa aduanal cubana
        $requiredFields = [
            'customs_declaration_number' => 'Número de declaración aduanal',
            'exemption_certificate_number' => 'Número de certificado de exención',
            'exemption_date' => 'Fecha de exención',
            'tariff_heading' => 'Partida arancelaria',
            'exemption_basis' => 'Fundamento legal de exención',
        ];

        $missingFields = [];
        foreach ($requiredFields as $field => $label) {
            if (empty($customsExemptionData[$field])) {
                $missingFields[] = $label;
            }
        }

        if (!empty($missingFields)) {
            return [
                'is_exempt' => false,
                'reason' => 'Documentación incompleta: ' . implode(', ', $missingFields),
                'details' => ['missing_fields' => $missingFields],
            ];
        }

        // Validar que la exención no esté vencida (vigencia típica: 1 año)
        try {
            $exemptionDate = Carbon::parse($customsExemptionData['exemption_date']);
            if ($exemptionDate->addYear()->isPast()) {
                return [
                    'is_exempt' => false,
                    'reason' => 'Certificado de exención vencido (vigencia 1 año)',
                    'details' => ['exemption_date' => $exemptionDate->toDateString()],
                ];
            }
        } catch (\Throwable $e) {
            return [
                'is_exempt' => false,
                'reason' => 'Fecha de exención inválida',
                'details' => ['error' => $e->getMessage()],
            ];
        }

        // Validar partida arancelaria permitida para exención (capítulos 84, 85, 87, 90 típicos)
        $allowedChapters = ['84', '85', '87', '90', '8471', '8517', '8703'];
        $tariffHeading = (string) ($customsExemptionData['tariff_heading'] ?? '');
        $chapterMatch = false;

        foreach ($allowedChapters as $chapter) {
            if (str_starts_with($tariffHeading, $chapter)) {
                $chapterMatch = true;
                break;
            }
        }

        if (!$chapterMatch && !empty($tariffHeading)) {
            Log::info("Partida arancelaria no estándar para exención B2B", [
                'tariff_heading' => $tariffHeading,
                'allowed_chapters' => $allowedChapters,
            ]);
            // No bloqueamos, solo alertamos - la autoridad aduanal decide
        }

        return [
            'is_exempt' => true,
            'reason' => 'Exención aduanal válida (XML/Documentación verificada)',
            'details' => [
                'customs_declaration' => $customsExemptionData['customs_declaration_number'],
                'exemption_certificate' => $customsExemptionData['exemption_certificate_number'],
                'tariff_heading' => $tariffHeading,
                'exemption_basis' => $customsExemptionData['exemption_basis'],
            ],
        ];
    }

    /**
     * Calcula liquidación directamente desde un modelo Equipment (usa sus campos persistidos).
     */
    public function calculateFromEquipment(Equipment $equipment, ?array $customsExemptionData = null): array
    {
        return $this->calculateLiquidation(
            declaredValueUsd: (float) $equipment->declared_value_usd,
            businessType: $equipment->business_type,
            dutyPaidAbroad: $equipment->duty_paid_abroad,
            deliveryPaidAbroad: $equipment->delivery_paid_abroad,
            homeDeliveryRequested: $equipment->home_delivery_requested,
            customsExemptionData: $customsExemptionData
        );
    }

    /**
     * Obtiene la tasa de cambio activa.
     */
    public function getActiveExchangeRate(string $from = 'USD', string $to = 'CUP'): float
    {
        $rate = ExchangeRate::where('currency_from', $from)
            ->where('currency_to', $to)
            ->where('is_active', true)
            ->latest()
            ->value('rate');

        return (float) ($rate ?: 350.00);
    }

    /**
     * Procesa y valida un archivo XML de manifiesto aduanal para extraer datos de exención B2B.
     *
     * @param string $xmlContent Contenido del archivo XML
     * @return array Datos extraídos para validación de exención
     */
    public function parseCustomsXmlForExemption(string $xmlContent): array
    {
        try {
            $xml = simplexml_load_string($xmlContent);

            if ($xml === false) {
                throw new \InvalidArgumentException('XML de manifiesto aduanal inválido');
            }

            // Namespace típico de aduana cubana / SolveCargo
            $namespaces = $xml->getNamespaces(true);

            $data = [];

            // Extraer campos clave del XML (adaptar según estructura real de SolveCargo)
            $data['customs_declaration_number'] = (string) ($xml->DeclarationNumber ?? $xml->declaration_number ?? '');
            $data['exemption_certificate_number'] = (string) ($xml->ExemptionCertificate ?? $xml->exemption_certificate ?? '');
            $data['exemption_date'] = (string) ($xml->ExemptionDate ?? $xml->exemption_date ?? '');
            $data['tariff_heading'] = (string) ($xml->TariffHeading ?? $xml->tariff_heading ?? $xml->HSCode ?? '');
            $data['exemption_basis'] = (string) ($xml->ExemptionBasis ?? $xml->exemption_basis ?? $xml->legal_basis ?? '');
            $data['total_value_usd'] = (float) ($xml->TotalValueUSD ?? $xml->total_value_usd ?? 0);
            $data['items'] = [];

            // Parsear items del manifiesto si existen
            if (isset($xml->Items->Item)) {
                foreach ($xml->Items->Item as $item) {
                    $data['items'][] = [
                        'description' => (string) ($item->Description ?? ''),
                        'quantity' => (int) ($item->Quantity ?? 1),
                        'value_usd' => (float) ($item->ValueUSD ?? 0),
                        'tariff_heading' => (string) ($item->TariffHeading ?? $item->HSCode ?? ''),
                    ];
                }
            }

            Log::info('XML de manifiesto aduanal parseado para exención B2B', [
                'declaration_number' => $data['customs_declaration_number'],
                'items_count' => count($data['items']),
            ]);

            return $data;

        } catch (\Throwable $e) {
            Log::error('Error parseando XML de manifiesto aduanal', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'parse_error' => true,
                'error_message' => $e->getMessage(),
            ];
        }
    }
}