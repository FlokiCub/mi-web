<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tracking_pin', 12)->unique()->index();
            $table->string('vin_serial', 80)->unique()->index();
            $table->string('solve_cargo_tracking_id', 100)->nullable()->index();

            $table->string('equipment_type', 30)->default('vehicle');
            $table->string('business_type', 10)->default('B2C');
            $table->string('brand', 60);
            $table->string('model', 80);
            $table->string('color', 40)->nullable();

            $table->string('current_status', 40)->default('supplier_dispatched')->index();
            $table->string('customs_status', 40)->default('pending_manifest')->index();

            $table->decimal('declared_value_usd', 12, 2)->default(0.00);
            $table->decimal('duty_amount_usd', 12, 2)->default(0.00);
            $table->decimal('shipping_fee_usd', 12, 2)->default(0.00);
            $table->boolean('home_delivery_requested')->default(false);
            $table->boolean('duty_paid_abroad')->default(false);
            $table->boolean('delivery_paid_abroad')->default(false);

            $table->timestamp('customs_entry_at')->nullable()->index();
            $table->timestamp('customs_cleared_at')->nullable()->index();
            $table->timestamp('pvp_certified_at')->nullable();
            $table->timestamp('delivered_at')->nullable()->index();

            $table->jsonb('client_data')->nullable();
            $table->jsonb('technical_specs')->nullable();

            $table->foreignUuid('current_warehouse_id')->nullable()->constrained('regional_warehouses')->nullOnDelete();
            $table->string('current_location_note', 150)->nullable();

            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipments');
    }
};
