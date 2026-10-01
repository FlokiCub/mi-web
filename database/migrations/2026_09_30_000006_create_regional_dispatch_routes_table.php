<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regional_dispatch_routes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('route_code', 30)->unique()->index();
            
            $table->foreignUuid('origin_warehouse_id')->constrained('regional_warehouses')->cascadeOnDelete();
            $table->foreignUuid('destination_warehouse_id')->constrained('regional_warehouses')->cascadeOnDelete();

            $table->string('driver_name', 120);
            $table->string('driver_phone', 40)->nullable();
            $table->string('truck_license_plate', 30);

            $table->string('status', 30)->default('draft');

            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('dispatch_route_equipments', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('dispatch_route_id')->constrained('regional_dispatch_routes')->cascadeOnDelete();
            $table->foreignUuid('equipment_id')->constrained('equipments')->cascadeOnDelete();
            $table->string('reception_status', 30)->default('in_manifest');
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispatch_route_equipments');
        Schema::dropIfExists('regional_dispatch_routes');
    }
};
