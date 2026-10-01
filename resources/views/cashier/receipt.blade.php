@extends('layouts.app', ['title' => 'Comprobante de Pago — ' . $payment->receipt_number])

@section('page_title', 'Recibo Oficial de Pago')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">

  <!-- Botones de Acción -->
  <div class="flex items-center justify-between no-print">
    <a href="{{ route('cashier.index') }}" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-semibold inline-flex items-center gap-2">
      <i class="fa-solid fa-arrow-left"></i>
      <span>Volver a Caja</span>
    </a>

    <button onclick="window.print()" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold inline-flex items-center gap-2 shadow-lg shadow-emerald-600/30">
      <i class="fa-solid fa-print"></i>
      <span>Imprimir Comprobante</span>
    </button>
  </div>

  <!-- Comprobante Imprimible -->
  <div class="bg-white text-slate-900 rounded-2xl p-8 shadow-2xl border border-slate-200 print:border-none print:shadow-none print:m-0 font-sans">
    
    <!-- Encabezado Oficial -->
    <div class="flex items-center justify-between border-b-2 border-slate-900 pb-5 mb-5">
      <div>
        <h2 class="text-2xl font-black tracking-tight text-slate-950">BLANKISOL S.R.L.</h2>
        <p class="text-xs text-slate-600 font-medium">Sistema de Control y Gestión de Importaciones (SCGI)</p>
        <p class="text-[11px] text-slate-500">Recibo Oficial de Liquidación Arancelaria y Fletes</p>
      </div>
      <div class="text-right">
        <span class="inline-block px-3 py-1 bg-emerald-100 text-emerald-800 border border-emerald-300 rounded-lg text-xs font-black font-mono">
          {{ $payment->receipt_number }}
        </span>
        <p class="text-[11px] text-slate-500 mt-1">{{ $payment->paid_at->format('d/m/Y H:i:s') }}</p>
      </div>
    </div>

    <!-- Sucursal y Operador -->
    <div class="grid grid-cols-2 gap-4 text-xs mb-6 p-3 bg-slate-50 rounded-xl border border-slate-200">
      <div>
        <span class="text-slate-500 block">Sucursal / Almacén:</span>
        <span class="font-bold text-slate-800">{{ $payment->cashShift->cashRegister->regionalWarehouse->name ?? 'Sede Central' }}</span>
        <span class="block text-[10px] text-slate-500">{{ $payment->cashShift->cashRegister->regionalWarehouse->province ?? '' }}</span>
      </div>
      <div>
        <span class="text-slate-500 block">Cajero Receptor:</span>
        <span class="font-bold text-slate-800">{{ $payment->cashier->name }}</span>
        <span class="block text-[10px] text-slate-500 font-mono">Caja: {{ $payment->cashShift->cashRegister->code }}</span>
      </div>
    </div>

    <!-- Datos del Cliente y Equipo -->
    <div class="space-y-4 mb-6 text-xs">
      <div class="border-b border-slate-200 pb-3">
        <span class="text-slate-500 text-[10px] uppercase font-bold tracking-wider block">Titular del Envío</span>
        <p class="text-sm font-bold text-slate-900 mt-0.5">{{ $payment->client_name }}</p>
        @if($payment->client_id_card)
          <p class="text-slate-600 font-mono">DNI / Carnet: {{ $payment->client_id_card }}</p>
        @endif
        @if($payment->client_phone)
          <p class="text-slate-600 font-mono">Teléfono: {{ $payment->client_phone }}</p>
        @endif
      </div>

      <div class="border-b border-slate-200 pb-3">
        <span class="text-slate-500 text-[10px] uppercase font-bold tracking-wider block">Datos del Equipo</span>
        <div class="flex items-center justify-between mt-1">
          <div>
            <p class="font-bold text-slate-900 text-sm">{{ $payment->equipment->brand }} {{ $payment->equipment->model }}</p>
            <p class="text-slate-600 font-mono text-[11px]">VIN / Serial: {{ $payment->equipment->vin_serial }}</p>
          </div>
          <div class="text-right">
            <span class="px-2 py-1 bg-slate-100 rounded text-slate-800 font-mono font-bold text-xs">
              PIN: {{ $payment->equipment->tracking_pin }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Desglose Financiero -->
    <div class="mb-6">
      <table class="w-full text-xs">
        <thead>
          <tr class="border-b border-slate-300 text-slate-500 uppercase text-[10px]">
            <th class="py-2 text-left">Concepto</th>
            <th class="py-2 text-right">Moneda Origen</th>
            <th class="py-2 text-right">Monto Liquidado</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200">
          <tr>
            <td class="py-2.5 font-medium text-slate-800">Arancel Aduanal (Aduana de Cuba)</td>
            <td class="py-2.5 text-right font-mono text-slate-600">USD</td>
            <td class="py-2.5 text-right font-mono font-bold text-slate-900">${{ number_format((float) $payment->duty_amount_usd, 2) }}</td>
          </tr>
          <tr>
            <td class="py-2.5 font-medium text-slate-800">Servicio de Flete y Envío Provincial</td>
            <td class="py-2.5 text-right font-mono text-slate-600">USD</td>
            <td class="py-2.5 text-right font-mono font-bold text-slate-900">${{ number_format((float) $payment->shipping_fee_usd, 2) }}</td>
          </tr>
        </tbody>
        <tfoot>
          <tr class="border-t-2 border-slate-900 font-bold text-sm">
            <td class="py-3 text-slate-950">TOTAL LIQUIDADO EN USD</td>
            <td colspan="2" class="py-3 text-right font-mono text-slate-950">${{ number_format((float) $payment->amount_due_usd, 2) }} USD</td>
          </tr>
        </tfoot>
      </table>
    </div>

    <!-- Desglose del Pago Recibido -->
    <div class="p-4 bg-emerald-50 rounded-xl border border-emerald-200 text-xs mb-6">
      <span class="text-emerald-900 text-[10px] uppercase font-bold tracking-wider block mb-2">Cobro Efectuado en Ventanilla</span>
      <div class="grid grid-cols-2 gap-3 text-slate-800">
        <div>
          <span class="text-slate-500 block">Efectivo USD Cobrado:</span>
          <span class="font-mono font-bold text-emerald-800 text-sm">${{ number_format((float) $payment->amount_paid_usd, 2) }} USD</span>
        </div>
        <div>
          <span class="text-slate-500 block">Efectivo CUP Cobrado:</span>
          <span class="font-mono font-bold text-emerald-800 text-sm">${{ number_format((float) $payment->amount_paid_cup, 2) }} CUP</span>
        </div>
        <div class="col-span-2 pt-2 border-t border-emerald-200/60 flex items-center justify-between text-[11px]">
          <span>Tasa de Cambio Oficial Aplicada:</span>
          <span class="font-mono font-bold">1 USD = {{ number_format((float) $payment->exchange_rate_applied, 2) }} CUP</span>
        </div>
      </div>
    </div>

    <!-- Firmas -->
    <div class="grid grid-cols-2 gap-10 pt-10 border-t border-slate-300 text-center text-xs text-slate-600">
      <div>
        <div class="border-b border-slate-400 pb-8 mb-2"></div>
        <p class="font-bold text-slate-800">{{ $payment->cashier->name }}</p>
        <p class="text-[10px] text-slate-500">Firma y Cuño del Cajero</p>
      </div>
      <div>
        <div class="border-b border-slate-400 pb-8 mb-2"></div>
        <p class="font-bold text-slate-800">{{ $payment->client_name }}</p>
        <p class="text-[10px] text-slate-500">Firma del Cliente Receptor</p>
      </div>
    </div>

    <!-- Pie de página legal -->
    <div class="mt-8 pt-4 border-t border-slate-200 text-center text-[10px] text-slate-400">
      <p>Comprobante válido para el retiro de equipos importados. Conservar para cualquier reclamación de garantía.</p>
      <p class="font-mono mt-0.5">Hash Auditoría: {{ strtoupper(md5($payment->id . $payment->receipt_number)) }}</p>
    </div>

  </div>

</div>
@endsection
