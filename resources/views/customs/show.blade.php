@extends('layouts.app')

@section('title', 'Expediente Aduanal - ' . $equipment->tracking_pin)

@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between bg-slate-900/60 p-6 rounded-2xl border border-slate-800">
        <div class="flex items-center gap-4">
            <a href="{{ route('customs.index') }}" class="p-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 transition-all">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-bold text-white">{{ $equipment->brand }} {{ $equipment->model }}</h1>
                    <span class="px-2.5 py-0.5 rounded text-xs font-mono font-bold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                        {{ $equipment->tracking_pin }}
                    </span>
                </div>
                <p class="text-xs text-slate-400 mt-1">VIN / Serial: <span class="font-mono text-slate-300">{{ $equipment->vin_serial }}</span></p>
            </div>
        </div>

        <div class="flex items-center gap-3">
            @php
                $sem = $equipment->getCustomsSemaphore();
                $hours = $equipment->getCustomsStayHours();
            @endphp
            @if($sem === 'red')
                <span class="px-3 py-1.5 rounded-xl text-xs font-extrabold bg-rose-500/10 text-rose-400 border border-rose-500/30 animate-pulse">
                    🔴 {{ $hours }}h en Puerto (Retención Crítica)
                </span>
            @elseif($sem === 'yellow')
                <span class="px-3 py-1.5 rounded-xl text-xs font-bold bg-amber-500/10 text-amber-400 border border-amber-500/30">
                    🟡 {{ $hours }}h en Puerto (Alerta Preventiva)
                </span>
            @else
                <span class="px-3 py-1.5 rounded-xl text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/30">
                    🟢 {{ $hours }}h en Puerto (Normal)
                </span>
            @endif
        </div>
    </div>

    <!-- Main Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Columna Izquierda: Información y Acciones Aduanales -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Acciones de Aduana -->
            <div class="bg-slate-900 p-6 rounded-2xl border border-slate-800 space-y-4">
                <h2 class="text-base font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-stamp text-amber-400"></i> Acciones del Inspector de Aduana
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                    @if($equipment->current_status === \App\Enums\EquipmentStatus::PORT_ARRIVAL)
                        <form action="{{ route('customs.start-inspection', $equipment) }}" method="POST" class="p-4 rounded-xl bg-slate-950 border border-slate-800 space-y-3">
                            @csrf
                            <div class="font-bold text-sm text-white flex items-center gap-2">
                                <i class="fa-solid fa-magnifying-glass text-amber-400"></i> Iniciar Inspección
                            </div>
                            <p class="text-xs text-slate-400">Mueve el equipo a la bahía de aforo e inspección física.</p>
                            <input type="text" name="notes" placeholder="Notas de apertura de bulto..." class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-lg text-xs text-white">
                            <button type="submit" class="w-full py-2 bg-amber-600 hover:bg-amber-500 text-white text-xs font-bold rounded-lg transition-all shadow-md shadow-amber-600/20">
                                Confirmar Inspección
                            </button>
                        </form>
                    @endif

                    @if($equipment->current_status === \App\Enums\EquipmentStatus::CUSTOMS_INSPECTION)
                        <form action="{{ route('customs.clear', $equipment) }}" method="POST" class="p-4 rounded-xl bg-slate-950 border border-slate-800 space-y-3">
                            @csrf
                            <div class="font-bold text-sm text-emerald-400 flex items-center gap-2">
                                <i class="fa-solid fa-check-double"></i> Conceder Libramiento
                            </div>
                            <p class="text-xs text-slate-400">Autoriza la salida del recinto portuario hacia {{ $equipment->equipment_type->value === 'vehicle' ? 'Patio PVP' : 'Despacho' }}.</p>
                            <input type="text" name="customs_declaration_number" placeholder="No. Declaración Aduanal (Ej: DEC-2026-99)" class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-lg text-xs text-white">
                            <input type="text" name="clearance_note" placeholder="Dictamen favorable..." class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-lg text-xs text-white">
                            <button type="submit" class="w-full py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-lg transition-all shadow-md shadow-emerald-600/20">
                                Aprobar Libramiento
                            </button>
                        </form>
                    @endif

                    <form action="{{ route('customs.hold', $equipment) }}" method="POST" class="p-4 rounded-xl bg-slate-950 border border-rose-900/30 space-y-3">
                        @csrf
                        <div class="font-bold text-sm text-rose-400 flex items-center gap-2">
                            <i class="fa-solid fa-hand"></i> Retener en Aduana
                        </div>
                        <p class="text-xs text-slate-400">Suspende el despacho por discrepancia física o documental.</p>
                        <input type="text" name="reason" required placeholder="Motivo de la retención..." class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-lg text-xs text-white">
                        <button type="submit" class="w-full py-2 bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold rounded-lg transition-all shadow-md shadow-rose-600/20">
                            Registrar Retención
                        </button>
                    </form>
                </div>
            </div>

            <!-- Bitácora de Trazabilidad -->
            <div class="bg-slate-900 p-6 rounded-2xl border border-slate-800 space-y-4">
                <h2 class="text-base font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-timeline text-indigo-400"></i> Historial Inmutable de Auditoría
                </h2>

                <div class="relative border-l-2 border-slate-800 ml-4 space-y-6 py-2">
                    @forelse($equipment->statusHistories as $history)
                        <div class="relative pl-6">
                            <div class="absolute -left-2 top-1 w-4 h-4 rounded-full bg-indigo-500 ring-4 ring-slate-900"></div>
                            <div class="text-xs text-slate-400">{{ $history->recorded_at->format('d/m/Y H:i:s') }}</div>
                            <div class="font-bold text-sm text-white mt-0.5">
                                {{ \App\Enums\EquipmentStatus::tryFrom($history->to_status)?->label() ?? $history->to_status }}
                            </div>
                            <div class="text-xs text-slate-400 mt-1">
                                <span class="text-indigo-400 font-semibold">{{ $history->changedBy?->name ?? 'Sistema' }}</span> — {{ $history->location }}
                            </div>
                            @if($history->notes)
                                <div class="mt-1.5 p-2.5 rounded-lg bg-slate-950 text-xs text-slate-300 border border-slate-800/80">
                                    {{ $history->notes }}
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="pl-6 text-xs text-slate-500">Sin historial registrado.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Columna Derecha: Datos Financieros y Cliente -->
        <div class="space-y-6">
            <!-- Ficha Técnica y Financiera -->
            <div class="bg-slate-900 p-6 rounded-2xl border border-slate-800 space-y-4">
                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-400">Liquidación & Aranceles</h3>
                
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between py-2 border-b border-slate-800">
                        <span class="text-slate-400">Valor Declarado:</span>
                        <span class="font-bold text-white">${{ number_format($equipment->declared_value_usd, 2) }} USD</span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-slate-800">
                        <span class="text-slate-400">Modelo Negocio:</span>
                        <span class="font-bold text-purple-400">{{ $equipment->business_type->value }}</span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-slate-800">
                        <span class="text-slate-400">Arancel Aduanal:</span>
                        <span class="font-bold {{ $equipment->duty_paid_abroad ? 'text-emerald-400' : 'text-amber-400' }}">
                            {{ $equipment->duty_paid_abroad ? 'Prepagado en Exterior ($0.00)' : '$' . number_format($equipment->duty_amount_usd, 2) . ' USD' }}
                        </span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-slate-800">
                        <span class="text-slate-400">Envío Domicilio:</span>
                        <span class="font-bold {{ $equipment->home_delivery_requested ? ($equipment->delivery_paid_abroad ? 'text-emerald-400' : 'text-amber-400') : 'text-slate-500' }}">
                            {{ $equipment->home_delivery_requested ? ($equipment->delivery_paid_abroad ? 'Prepagado ($0.00)' : '$50.00 USD') : 'No solicitado' }}
                        </span>
                    </div>
                    <div class="flex justify-between py-2">
                        <span class="text-slate-400">Saldo Pendiente Cuba:</span>
                        <span class="font-extrabold text-base text-white font-mono">${{ number_format($equipment->getOutstandingBalanceUsd(), 2) }} USD</span>
                    </div>
                </div>
            </div>

            <!-- Cliente -->
            <div class="bg-slate-900 p-6 rounded-2xl border border-slate-800 space-y-4">
                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-400">Datos del Consignatario</h3>
                
                @php $client = $equipment->client_data ?? []; @endphp
                <div class="space-y-2 text-xs">
                    <div>
                        <span class="text-slate-500 block">Nombre Completo:</span>
                        <span class="font-bold text-white text-sm">{{ $client['name'] ?? 'No especificado' }}</span>
                    </div>
                    <div>
                        <span class="text-slate-500 block">Teléfono / WhatsApp:</span>
                        <span class="font-mono text-emerald-400 font-semibold">{{ $client['phone'] ?? 'No registrado' }}</span>
                    </div>
                    <div>
                        <span class="text-slate-500 block">Documento Identidad (CI/DNI):</span>
                        <span class="font-mono text-slate-300">{{ $client['ci'] ?? $client['document_id'] ?? 'N/A' }}</span>
                    </div>
                    <div>
                        <span class="text-slate-500 block">Dirección de Entrega:</span>
                        <span class="text-slate-300">{{ $client['address'] ?? 'Retiro en almacén' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
