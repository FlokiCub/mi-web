<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Añadir estado financiero explícito en la tabla equipments
        Schema::table('equipments', function (Blueprint $table) {
            $table->string('financial_status', 30)->default('pending_payment')->after('customs_status')->index();
            $table->boolean('is_fully_paid')->default(false)->after('financial_status')->index();
            $table->timestamp('settled_at')->nullable()->after('is_fully_paid');
            $table->foreignId('settled_by_user_id')->nullable()->after('settled_at')->constrained('users')->nullOnDelete();
        });

        // 2. Tabla de Cajas Regionales (una o más por almacén provincial)
        Schema::create('cash_registers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('regional_warehouse_id')->constrained('regional_warehouses')->cascadeOnDelete();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->string('status', 20)->default('closed')->index(); // 'open', 'closed'
            $table->uuid('current_cash_shift_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 3. Tabla de Turnos / Sesiones de Caja (Aperturas, arqueos y cierres con balance USD/CUP)
        Schema::create('cash_shifts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cash_register_id')->constrained('cash_registers')->cascadeOnDelete();
            $table->foreignId('cashier_user_id')->constrained('users')->cascadeOnDelete();

            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();

            // Balances en USD
            $table->decimal('opening_balance_usd', 12, 2)->default(0.00);
            $table->decimal('total_collected_usd', 12, 2)->default(0.00);
            $table->decimal('expected_balance_usd', 12, 2)->default(0.00);
            $table->decimal('closing_balance_usd', 12, 2)->nullable();
            $table->decimal('difference_usd', 12, 2)->default(0.00);

            // Balances en CUP
            $table->decimal('opening_balance_cup', 12, 2)->default(0.00);
            $table->decimal('total_collected_cup', 12, 2)->default(0.00);
            $table->decimal('expected_balance_cup', 12, 2)->default(0.00);
            $table->decimal('closing_balance_cup', 12, 2)->nullable();
            $table->decimal('difference_cup', 12, 2)->default(0.00);

            $table->string('status', 20)->default('open')->index(); // 'open', 'closed'
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 4. Tabla de Pagos y Liquidaciones de Equipos (Multimoneda USD y CUP)
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('receipt_number', 40)->unique()->index();
            $table->foreignUuid('equipment_id')->constrained('equipments')->cascadeOnDelete();
            $table->foreignUuid('cash_shift_id')->constrained('cash_shifts')->cascadeOnDelete();
            $table->foreignId('cashier_user_id')->constrained('users')->cascadeOnDelete();

            $table->string('concept', 50)->default('duty_and_delivery'); // 'duty_and_delivery', 'customs_duty', 'home_delivery'
            
            // Deuda liquidada
            $table->decimal('amount_due_usd', 12, 2);
            $table->decimal('duty_amount_usd', 12, 2)->default(0.00);
            $table->decimal('shipping_fee_usd', 12, 2)->default(0.00);

            // Desglose de cobro recibido
            $table->decimal('amount_paid_usd', 12, 2)->default(0.00);
            $table->decimal('amount_paid_cup', 12, 2)->default(0.00);
            $table->decimal('exchange_rate_applied', 12, 4)->default(350.0000);
            $table->decimal('equivalent_total_usd', 12, 2); // Suma de USD + (CUP / Tasa)

            $table->string('payment_method', 30)->default('cash_mixed'); // 'cash_usd', 'cash_cup', 'cash_mixed', 'transfer_cup', 'transfer_mlc'
            $table->string('status', 20)->default('completed')->index(); // 'completed', 'cancelled'
            
            // Datos del cliente que abona
            $table->string('client_name', 120);
            $table->string('client_id_card', 40)->nullable();
            $table->string('client_phone', 40)->nullable();

            $table->string('transaction_reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('paid_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('cash_shifts');
        Schema::dropIfExists('cash_registers');

        Schema::table('equipments', function (Blueprint $table) {
            $table->dropForeign(['settled_by_user_id']);
            $table->dropColumn(['settled_by_user_id', 'settled_at', 'is_fully_paid', 'financial_status']);
        });
    }
};
