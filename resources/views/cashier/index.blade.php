@extends('layouts.app', ['title' => 'Caja Regional — Cobros y Aranceles'])

@section('page_title', 'Módulo de Cajas Regionales y Liquidación')

@section('content')
<div class="space-y-6">

  <!-- Header -->
  <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
    <div>
      <h3 class="text-xl font-bold text-white flex items-center gap-2.5">
        <i class="fa-solid fa-cash-register text-emerald-400"></i>
        <span>Caja Regional y Recaudación Multimoneda</span>
      </h3>
      <p class="text-xs text-slate-400 mt-0.5">
        Liquidación de aranceles aduanales y servicios de flete en USD y CUP. Tasa oficial activa: 
        <span class="font-bold text-emerald-400 font-mono">1 USD = {{ number_format($activeRate, 2) }} CUP</span>
      </p>
    </div>

    @if($activeShift)
      <div class="flex items-center gap-3">
        <div class="px-3.5 py-1.5 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-xs font-bold flex items-center gap-2">
          <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
          <span>Turno Abierto: {{ $activeShift->cashRegister->code }}</span>
        </div>
      </div>
    @endif
  </div>

  @if(!$activeShift)
    <!-- Banner de Apertura de Caja Requerida -->
    <div class="p-6 bg-slate-900 border border-amber-500/30 rounded-2xl shadow-xl">
      <div class="max-w-2xl">
        <div class="flex items-center gap-3 text-amber-400 mb-2">
          <i class="fa-solid fa-triangle-exclamation text-lg"></i>
          <h4 class="font-bold text-base">Debes Abrir un Turno de Caja para Operar</h4>
        </div>
        <p class="text-xs text-slate-300 mb-5 leading-relaxed">
          Para realizar cobros, registrar recaudación o entregar equipos, es obligatorio declarar el fondo inicial en gaveta y aperturar tu sesión de caja en el almacén asignado.
        </p>

        <form method="POST" action="{{ route('cashier.shift.open') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          @csrf
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">Seleccionar Caja *</label>
            <select name="cash_register_id" required class="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-xl text-xs text-slate-100 focus:ring-2 focus:ring-emerald-500">
              @foreach($cashRegisters as $reg)
                <option value="{{ $reg->id }}" {{ $reg->isOpen() ? 'disabled' : '' }}>
                  {{ $reg->code }} — {{ $reg->name }} {{ $reg->isOpen() ? '(Ocupada)' : '(Disponible)' }}
                </option>
              @endforeach
            </select>
          </div>

          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">Fondo Inicial USD</label>
            <input type="number" step="0.01" min="0" name="initial_usd" value="0.00" class="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-xl text-xs text-slate-100 focus:ring-2 focus:ring-emerald-500 font-mono" />
          </div>

          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">Fondo Inicial CUP</label>
            <input type="number" step="0.01" min="0" name="initial_cup" value="0.00" class="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-xl text-xs text-slate-100 focus:ring-2 focus:ring-emerald-500 font-mono" />
          </div>

          <div class="sm:col-span-3 flex justify-end mt-2">
            <button type="submit" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-emerald-600/30 flex items-center gap-2">
              <i class="fa-solid fa-key"></i>
              <span>Abrir Turno de Caja</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  @else
    <!-- Panel Operativo: Balance en Vivo y Buscador de Equipos -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

      <!-- Columna 1: Estado del Turno y Arqueo -->
      <div class="space-y-4">
        <div class="p-5 bg-slate-900 border border-slate-800 rounded-2xl shadow-sm">
          <div class="flex items-center justify-between pb-3 border-b border-slate-800">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Arqueo en Gaveta</span>
            <span class="text-[10px] font-bold text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded-full border border-emerald-500/30">En Línea</span>
          </div>

          <div class="grid grid-cols-2 gap-3 mt-4">
            <div class="p-3 bg-slate-950 rounded-xl border border-slate-800/80">
              <span class="text-[10px] text-slate-400 font-semibold block">Total Recaudado USD</span>
              <span class="text-lg font-bold text-emerald-400 font-mono">${{ number_format((float) $activeShift->total_collected_usd, 2) }}</span>
            </div>
            <div class="p-3 bg-slate-950 rounded-xl border border-slate-800/80">
              <span class="text-[10px] text-slate-400 font-semibold block">Total Recaudado CUP</span>
              <span class="text-lg font-bold text-teal-400 font-mono">${{ number_format((float) $activeShift->total_collected_cup, 2) }}</span>
            </div>
          </div>

          <div class="mt-4 pt-3 border-t border-slate-800 text-[11px] text-slate-400 space-y-1">
            <div class="flex justify-between">
              <span>Cajero Responsable:</span>
              <span class="font-bold text-slate-200">{{ $activeShift->cashier->name }}</span>
            </div>
            <div class="flex justify-between">
              <span>Apertura:</span>
              <span class="font-mono text-slate-300">{{ $activeShift->opened_at->format('H:i d/m/Y') }}</span>
            </div>
          </div>

          <!-- Botón Cerrar Turno -->
          <button 
            type="button" 
            onclick="document.getElementById('modalCloseShift').classList.remove('hidden')" 
            class="w-full mt-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-2 border border-slate-700"
          >
            <i class="fa-solid fa-lock"></i>
            <span>Cerrar Turno de Caja</span>
          </button>
        </div>
      </div>

      <!-- Columna 2 y 3: Buscador de Equipos y Terminal de Cobro -->
      <div class="lg:col-span-2 space-y-4">
        
        <!-- Buscador por PIN o VIN -->
        <div class="p-5 bg-slate-900 border border-slate-800 rounded-2xl shadow-sm">
          <h4 class="text-sm font-bold text-white mb-2 flex items-center gap-2">
            <i class="fa-solid fa-magnifying-glass text-indigo-400"></i>
            <span>Consultar Equipo para Cobro o Entrega</span>
          </h4>
          <p class="text-xs text-slate-400 mb-4">
            Ingresa o escanea el PIN de rastreo, VIN serial o código de manifiesto del equipo.
          </p>

          <div class="flex gap-2">
            <div class="relative flex-1">
              <input 
                type="text" 
                id="searchCodeInput" 
                placeholder="Ej: BK-78492 o VIN-2026-..." 
                class="w-full px-4 py-3 bg-slate-950 border border-slate-700 rounded-xl text-sm font-mono text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 uppercase"
                onkeypress="if(event.key === 'Enter') lookupEquipment()"
              />
            </div>
            <button 
              type="button" 
              onclick="lookupEquipment()" 
              class="px-5 py-3 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-emerald-600/30 flex items-center gap-2"
            >
              <i class="fa-solid fa-barcode"></i>
              <span>Consultar</span>
            </button>
          </div>

          <!-- Spinner de Carga -->
          <div id="lookupSpinner" class="hidden text-center py-6 text-slate-400 text-xs">
            <i class="fa-solid fa-circle-notch fa-spin text-lg text-emerald-400 mb-2"></i>
            <p>Consultando base de datos central y validando aranceles...</p>
          </div>

          <!-- Resultado del Equipo -->
          <div id="equipmentResultCard" class="hidden mt-5 p-4 bg-slate-950 rounded-xl border border-slate-800 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-slate-800">
              <div>
                <h5 id="eqTitle" class="text-base font-bold text-white">Yadea G5 Pro</h5>
                <p id="eqSubtitle" class="text-xs text-slate-400 font-mono">PIN: BK-78492 | VIN: 123456</p>
              </div>
              <div id="eqStatusBadge"></div>
            </div>

            <!-- Banner Financiero Dinámico -->
            <div id="financialBanner"></div>

            <!-- Formulario de Cobro (Se muestra si tiene saldo pendiente) -->
            <div id="paymentFormSection" class="hidden pt-3 border-t border-slate-800">
              <h6 class="text-xs font-bold uppercase tracking-wider text-slate-300 mb-3">Registrar Cobro en Ventanilla</h6>
              
              <form method="POST" action="{{ route('cashier.payment.store') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="equipment_id" id="formEquipmentId" />
                <input type="hidden" name="cash_shift_id" value="{{ $activeShift->id }}" />

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div>
                    <label class="block text-[11px] font-semibold text-slate-400 mb-1">Monto Abonado en USD</label>
                    <input type="number" step="0.01" min="0" name="amount_usd" id="formAmountUsd" value="0.00" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white font-mono" />
                  </div>
                  <div>
                    <label class="block text-[11px] font-semibold text-slate-400 mb-1">Monto Abonado en CUP</label>
                    <input type="number" step="0.01" min="0" name="amount_cup" id="formAmountCup" value="0.00" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white font-mono" />
                  </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div>
                    <label class="block text-[11px] font-semibold text-slate-400 mb-1">Método de Pago *</label>
                    <select name="payment_method" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white">
                      <option value="cash_usd">Efectivo USD</option>
                      <option value="cash_cup">Efectivo CUP</option>
                      <option value="cash_mixed" selected>Mixto (Efectivo USD + CUP)</option>
                      <option value="transfer_cup">Transferencia CUP (EnZona / Transfermóvil)</option>
                      <option value="transfer_mlc">Transferencia MLC</option>
                    </select>
                  </div>
                  <div>
                    <label class="block text-[11px] font-semibold text-slate-400 mb-1">DNI / Carnet del Cliente</label>
                    <input type="text" name="client_id_card" placeholder="No. Identidad" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white" />
                  </div>
                </div>

                <button type="submit" class="w-full py-3 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-emerald-600/30 flex items-center justify-center gap-2">
                  <i class="fa-solid fa-receipt"></i>
                  <span>Liquidar Cobro y Emitir Comprobante</span>
                </button>
              </form>
            </div>

            <!-- Botón Entrega Directa (Se muestra si está 100% Prepagado o ya liquidado) -->
            <div id="deliverSection" class="hidden pt-3 border-t border-slate-800">
              <form method="POST" id="deliverForm" action="">
                @csrf
                <div class="p-3 bg-emerald-500/10 border border-emerald-500/30 rounded-xl mb-3 text-xs text-emerald-300">
                  <i class="fa-solid fa-circle-check mr-1"></i>
                  El equipo no presenta deuda pendiente. Puedes proceder a entregarlo físicamente al cliente.
                </div>
                <button type="submit" class="w-full py-3 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-indigo-600/30 flex items-center justify-center gap-2">
                  <i class="fa-solid fa-handshake"></i>
                  <span>Confirmar Entrega Física al Cliente</span>
                </button>
              </form>
            </div>

          </div>
        </div>

      </div>

    </div>

    <!-- Modal Cierre de Turno -->
    <div id="modalCloseShift" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-xs p-4">
      <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl">
        <h4 class="text-base font-bold text-white mb-2 flex items-center gap-2">
          <i class="fa-solid fa-lock text-rose-400"></i>
          <span>Cierre de Turno y Arqueo de Caja</span>
        </h4>
        <p class="text-xs text-slate-400 mb-4">
          Cuenta el efectivo físico presente en gaveta e ingresa los valores reales para registrar diferencias de arqueo.
        </p>

        <form method="POST" action="{{ route('cashier.shift.close', $activeShift) }}" class="space-y-4">
          @csrf
          <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1">Efectivo Físico en Gaveta USD *</label>
            <input type="number" step="0.01" min="0" name="closing_usd" required value="{{ $activeShift->expected_balance_usd }}" class="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-xl text-xs text-white font-mono" />
            <span class="text-[10px] text-slate-500">Esperado en sistema: ${{ number_format((float) $activeShift->expected_balance_usd, 2) }} USD</span>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1">Efectivo Físico en Gaveta CUP *</label>
            <input type="number" step="0.01" min="0" name="closing_cup" required value="{{ $activeShift->expected_balance_cup }}" class="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-xl text-xs text-white font-mono" />
            <span class="text-[10px] text-slate-500">Esperado en sistema: ${{ number_format((float) $activeShift->expected_balance_cup, 2) }} CUP</span>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1">Notas u Observaciones del Cierre</label>
            <textarea name="notes" rows="2" class="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-xl text-xs text-white" placeholder="Ej: Arqueo conforme..."></textarea>
          </div>

          <div class="flex items-center justify-end gap-2 pt-2">
            <button type="button" onclick="document.getElementById('modalCloseShift').classList.add('hidden')" class="px-4 py-2 bg-slate-800 text-slate-300 rounded-xl text-xs font-semibold">Cancelar</button>
            <button type="submit" class="px-5 py-2 bg-rose-600 hover:bg-rose-500 text-white rounded-xl text-xs font-bold">Confirmar Cierre</button>
          </div>
        </form>
      </div>
    </div>
  @endif

  <!-- Tabla de Recibos Emitidos en el Turno -->
  @if($activeShift && $recentPayments->count() > 0)
    <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
      <div class="p-4 border-b border-slate-800 flex items-center justify-between">
        <h4 class="text-sm font-bold text-white flex items-center gap-2">
          <i class="fa-solid fa-clock-rotate-left text-slate-400"></i>
          <span>Cobros Registrados en este Turno</span>
        </h4>
        <span class="text-xs text-slate-400">{{ $recentPayments->count() }} recibo(s)</span>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs text-slate-300">
          <thead class="bg-slate-950 text-slate-400 uppercase text-[10px] font-bold">
            <tr>
              <th class="px-4 py-3">No. Recibo</th>
              <th class="px-4 py-3">Equipo / PIN</th>
              <th class="px-4 py-3">Cliente</th>
              <th class="px-4 py-3">Abono USD</th>
              <th class="px-4 py-3">Abono CUP</th>
              <th class="px-4 py-3">Fecha y Hora</th>
              <th class="px-4 py-3 text-right">Comprobante</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800">
            @foreach($recentPayments as $pay)
              <tr class="hover:bg-slate-800/40">
                <td class="px-4 py-3 font-mono font-bold text-emerald-400">{{ $pay->receipt_number }}</td>
                <td class="px-4 py-3 font-mono text-white">{{ $pay->equipment->tracking_pin }}</td>
                <td class="px-4 py-3">{{ $pay->client_name }}</td>
                <td class="px-4 py-3 font-mono">${{ number_format((float) $pay->amount_paid_usd, 2) }}</td>
                <td class="px-4 py-3 font-mono">${{ number_format((float) $pay->amount_paid_cup, 2) }}</td>
                <td class="px-4 py-3 text-slate-400">{{ $pay->paid_at->format('H:i d/m') }}</td>
                <td class="px-4 py-3 text-right">
                  <a href="{{ route('cashier.receipt', $pay) }}" target="_blank" class="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-lg text-xs font-semibold inline-flex items-center gap-1.5">
                    <i class="fa-solid fa-receipt text-[10px]"></i>
                    <span>Ver</span>
                  </a>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endif

</div>

<script>
function lookupEquipment() {
  const code = document.getElementById('searchCodeInput').value.trim();
  if (!code) return;

  const spinner = document.getElementById('lookupSpinner');
  const card = document.getElementById('equipmentResultCard');
  
  spinner.classList.remove('hidden');
  card.classList.add('hidden');

  fetch(`{{ route('cashier.lookup') }}?code=${encodeURIComponent(code)}`)
    .then(r => r.json())
    .then(data => {
      spinner.classList.add('hidden');
      if (!data.success) {
        alert(data.message || 'Equipo no encontrado');
        return;
      }

      const eq = data.equipment;
      card.classList.remove('hidden');

      document.getElementById('eqTitle').innerText = `${eq.brand} ${eq.model}`;
      document.getElementById('eqSubtitle').innerText = `PIN: ${eq.tracking_pin} | VIN: ${eq.vin_serial} | Almacén: ${eq.warehouse || 'En Tránsito'}`;

      document.getElementById('formEquipmentId').value = eq.id;
      document.getElementById('deliverForm').action = `/cashier/equipment/${eq.id}/deliver`;

      const banner = document.getElementById('financialBanner');
      const payForm = document.getElementById('paymentFormSection');
      const deliverSec = document.getElementById('deliverSection');

      if (eq.is_totally_prepaid || eq.is_fully_paid) {
        banner.innerHTML = `
          <div class="p-4 bg-emerald-500/10 border border-emerald-500/30 rounded-xl">
            <div class="flex items-center gap-2 text-emerald-400 font-bold text-sm">
              <i class="fa-solid fa-circle-check text-base"></i>
              <span>${eq.is_totally_prepaid ? 'TOTALMENTE PREPAGADO EN EL EXTERIOR' : 'LIQUIDADO EN CUBA'}</span>
            </div>
            <p class="text-xs text-slate-300 mt-1">Este producto no tiene cargos pendientes en Cuba. Arancel y flete exentos de cobro.</p>
          </div>
        `;
        payForm.classList.add('hidden');
        deliverSec.classList.remove('hidden');
      } else {
        banner.innerHTML = `
          <div class="p-4 bg-amber-500/10 border border-amber-500/30 rounded-xl space-y-2">
            <div class="flex items-center justify-between">
              <span class="text-amber-400 font-bold text-sm flex items-center gap-2">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span>SALDO PENDIENTE DE COBRO</span>
              </span>
              <span class="text-lg font-bold font-mono text-amber-300">$${eq.outstanding_usd.toFixed(2)} USD</span>
            </div>
            <div class="text-xs text-slate-300 flex justify-between border-t border-amber-500/20 pt-2">
              <span>Arancel: $${eq.duty_amount_usd.toFixed(2)} USD | Envío: $${eq.shipping_fee_usd.toFixed(2)} USD</span>
              <span class="font-mono font-bold text-teal-300">Equivalente CUP: $${eq.outstanding_cup.toFixed(2)} CUP</span>
            </div>
          </div>
        `;
        document.getElementById('formAmountUsd').value = eq.outstanding_usd.toFixed(2);
        document.getElementById('formAmountCup').value = "0.00";
        payForm.classList.remove('hidden');
        deliverSec.classList.add('hidden');
      }
    })
    .catch(err => {
      spinner.classList.add('hidden');
      alert('Error al consultar el equipo.');
    });
}
</script>
@endsection
