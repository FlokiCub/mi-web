@extends('layouts.app')

@section('title', 'Módulo de Aduana - Semáforo Portuario')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-slate-900/60 p-6 rounded-2xl border border-slate-800">
        <div>
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-amber-500 to-orange-600 flex items-center justify-center text-white font-bold shadow-lg shadow-amber-500/20">
                    <i class="fa-solid fa-anchor text-lg"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-white">Inspección Aduanal — Puerto Mariel</h1>
                    <p class="text-sm text-slate-400">Control de permanencia portuaria, semáforos 48h / 72h y libramientos aduanales.</p>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                Operador: {{ auth()->user()->name }} ({{ auth()->user()->role->label() }})
            </span>
        </div>
    </div>

    <!-- KPI Semáforos -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Total en Puerto -->
        <a href="{{ route('customs.index') }}" class="p-5 rounded-2xl bg-slate-900 border {{ empty($semaphoreFilter) ? 'border-indigo-500 ring-2 ring-indigo-500/20' : 'border-slate-800' }} hover:border-slate-700 transition-all group">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Total en Puerto / Aduana</span>
                <span class="p-2 rounded-xl bg-slate-800 text-indigo-400"><i class="fa-solid fa-boxes-stacked"></i></span>
            </div>
            <div class="mt-3 flex items-baseline gap-2">
                <span class="text-3xl font-extrabold text-white">{{ $metrics['total_in_customs'] }}</span>
                <span class="text-xs text-slate-400">equipos</span>
            </div>
            <p class="mt-1 text-xs text-slate-400">En inspección o arribo</p>
        </a>

        <!-- Normal (<48h) -->
        <a href="{{ route('customs.index', ['semaphore' => 'green']) }}" class="p-5 rounded-2xl bg-slate-900 border {{ $semaphoreFilter === 'green' ? 'border-emerald-500 ring-2 ring-emerald-500/20' : 'border-slate-800' }} hover:border-slate-700 transition-all group">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-emerald-400">🟢 Normal (&lt;48h)</span>
                <span class="p-2 rounded-xl bg-emerald-500/10 text-emerald-400"><i class="fa-solid fa-circle-check"></i></span>
            </div>
            <div class="mt-3 flex items-baseline gap-2">
                <span class="text-3xl font-extrabold text-emerald-400">{{ $metrics['green_count'] }}</span>
                <span class="text-xs text-slate-400">en tiempo</span>
            </div>
            <p class="mt-1 text-xs text-slate-400">Flujo estándar de despacho</p>
        </a>

        <!-- Preventivo (48h-72h) -->
        <a href="{{ route('customs.index', ['semaphore' => 'yellow']) }}" class="p-5 rounded-2xl bg-slate-900 border {{ $semaphoreFilter === 'yellow' ? 'border-amber-500 ring-2 ring-amber-500/20' : 'border-slate-800' }} hover:border-slate-700 transition-all group">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-amber-400">🟡 Preventivo (48h - 72h)</span>
                <span class="p-2 rounded-xl bg-amber-500/10 text-amber-400"><i class="fa-solid fa-triangle-exclamation"></i></span>
            </div>
            <div class="mt-3 flex items-baseline gap-2">
                <span class="text-3xl font-extrabold text-amber-400">{{ $metrics['yellow_count'] }}</span>
                <span class="text-xs text-slate-400">alerta</span>
            </div>
            <p class="mt-1 text-xs text-slate-400">Riesgo de retraso portuario</p>
        </a>

        <!-- Crítico (>72h) -->
        <a href="{{ route('customs.index', ['semaphore' => 'red']) }}" class="p-5 rounded-2xl bg-slate-900 border {{ $semaphoreFilter === 'red' ? 'border-rose-500 ring-2 ring-rose-500/20' : 'border-slate-800' }} hover:border-slate-700 transition-all group">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-rose-400">🔴 Crítico (&gt;72h)</span>
                <span class="p-2 rounded-xl bg-rose-500/10 text-rose-400"><i class="fa-solid fa-radiation"></i></span>
            </div>
            <div class="mt-3 flex items-baseline gap-2">
                <span class="text-3xl font-extrabold text-rose-400">{{ $metrics['red_count'] }}</span>
                <span class="text-xs text-slate-400">retención crítica</span>
            </div>
            <p class="mt-1 text-xs text-rose-400/80">Requiere intervención inmediata</p>
        </a>
    </div>

    <!-- Filtros y Búsqueda -->
    <div class="bg-slate-900 p-4 rounded-2xl border border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-4">
        <form action="{{ route('customs.index') }}" method="GET" class="w-full sm:w-96 flex gap-2">
            <input type="hidden" name="semaphore" value="{{ $semaphoreFilter }}">
            <div class="relative flex-1">
                <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500 text-sm"></i>
                <input type="text" name="search" value="{{ $search }}" placeholder="Buscar por PIN, VIN, marca o modelo..."
                       class="w-full pl-10 pr-4 py-2 bg-slate-950 border border-slate-800 rounded-xl text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">
            </div>
            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold rounded-xl transition-all">
                Buscar
            </button>
            @if($search || $semaphoreFilter)
                <a href="{{ route('customs.index') }}" class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-sm font-semibold rounded-xl transition-all" title="Limpiar filtros">
                    <i class="fa-solid fa-rotate-left"></i>
                </a>
            @endif
        </form>

        <div class="text-xs text-slate-400">
            Mostrando <span class="font-bold text-white">{{ $equipments->total() }}</span> equipos en cola de aduana
        </div>
    </div>

    <!-- Tabla de Equipos -->
    <div class="bg-slate-900 rounded-2xl border border-slate-800 overflow-hidden shadow-xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-300">
                <thead class="bg-slate-950/80 text-xs uppercase tracking-wider text-slate-400 border-b border-slate-800">
                    <tr>
                        <th class="px-5 py-4">Semáforo / Tiempo</th>
                        <th class="px-5 py-4">Equipo / PIN</th>
                        <th class="px-5 py-4">Tipo / Modelo Negocio</th>
                        <th class="px-5 py-4">Estado Aduanal</th>
                        <th class="px-5 py-4">Valor Declarado</th>
                        <th class="px-5 py-4 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    @forelse($equipments as $eq)
                        @php
                            $sem = $eq->getCustomsSemaphore();
                            $hours = $eq->getCustomsStayHours();
                        @endphp
                        <tr class="hover:bg-slate-800/40 transition-all">
                            <!-- Semáforo -->
                            <td class="px-5 py-4 whitespace-nowrap">
                                @if($sem === 'red')
                                    <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-extrabold bg-rose-500/10 text-rose-400 border border-rose-500/30 animate-pulse">
                                        <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                                        {{ $hours }}h en puerto (Crítico)
                                    </div>
                                @elseif($sem === 'yellow')
                                    <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-500/10 text-amber-400 border border-amber-500/30">
                                        <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                                        {{ $hours }}h en puerto (Alerta)
                                    </div>
                                @else
                                    <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/30">
                                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                        {{ $hours }}h (Normal)
                                    </div>
                                @endif
                                <div class="text-[11px] text-slate-500 mt-1">
                                    Arribo: {{ $eq->customs_entry_at ? $eq->customs_entry_at->format('d/m/Y H:i') : $eq->created_at->format('d/m/Y H:i') }}
                                </div>
                            </td>

                            <!-- Equipo / PIN -->
                            <td class="px-5 py-4">
                                <div class="font-bold text-white">{{ $eq->brand }} {{ $eq->model }}</div>
                                <div class="text-xs font-mono text-indigo-400 font-semibold">{{ $eq->tracking_pin }}</div>
                                <div class="text-[11px] font-mono text-slate-500">{{ $eq->vin_serial }}</div>
                            </td>

                            <!-- Tipo / Negocio -->
                            <td class="px-5 py-4 whitespace-nowrap">
                                <span class="px-2 py-0.5 rounded text-xs font-semibold {{ $eq->business_type->value === 'B2B' ? 'bg-purple-500/10 text-purple-400 border border-purple-500/20' : 'bg-blue-500/10 text-blue-400 border border-blue-500/20' }}">
                                    {{ $eq->business_type->value }}
                                </span>
                                <div class="text-xs text-slate-400 mt-1">{{ $eq->equipment_type->label() }}</div>
                            </td>

                            <!-- Estado Aduana -->
                            <td class="px-5 py-4 whitespace-nowrap">
                                <span class="px-2.5 py-1 rounded-lg text-xs font-semibold {{ $eq->current_status->badgeClass() }}">
                                    {{ $eq->current_status->label() }}
                                </span>
                                @if($eq->customs_status === 'held_in_customs')
                                    <div class="mt-1 text-[11px] font-bold text-rose-400">
                                        <i class="fa-solid fa-hand"></i> Retenido en Aduana
                                    </div>
                                @endif
                            </td>

                            <!-- Valor -->
                            <td class="px-5 py-4 whitespace-nowrap font-mono text-xs">
                                <div class="font-bold text-white">${{ number_format($eq->declared_value_usd, 2) }} USD</div>
                                @if($eq->duty_paid_abroad)
                                    <span class="text-[10px] text-emerald-400 font-semibold">Arancel Prepagado</span>
                                @else
                                    <span class="text-[10px] text-amber-400">Pendiente: ${{ number_format($eq->duty_amount_usd, 2) }} USD</span>
                                @endif
                            </td>

                            <!-- Acciones -->
                            <td class="px-5 py-4 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('customs.show', $eq) }}" class="p-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold transition-all" title="Ver Detalle">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>

                                    @if($eq->current_status === \App\Enums\EquipmentStatus::PORT_ARRIVAL)
                                        <form action="{{ route('customs.start-inspection', $eq) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="px-3 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-500 text-white text-xs font-bold transition-all shadow-md shadow-amber-600/20" title="Iniciar Inspección">
                                                <i class="fa-solid fa-magnifying-glass-arrow-right mr-1"></i> Inspeccionar
                                            </button>
                                        </form>
                                    @endif

                                    @if($eq->current_status === \App\Enums\EquipmentStatus::CUSTOMS_INSPECTION)
                                        <form action="{{ route('customs.clear', $eq) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition-all shadow-md shadow-emerald-600/20" title="Aprobar Libramiento">
                                                <i class="fa-solid fa-check-double mr-1"></i> Liberar
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-slate-500">
                                <i class="fa-solid fa-circle-check text-4xl text-slate-700 mb-3 block"></i>
                                No hay equipos pendientes con los criterios seleccionados en el recinto aduanal.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($equipments->hasPages())
            <div class="px-6 py-4 border-t border-slate-800 bg-slate-950/50">
                {{ $equipments->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
