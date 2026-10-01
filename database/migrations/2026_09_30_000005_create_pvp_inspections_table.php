<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pvp_inspections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('equipment_id')->constrained('equipments')->cascadeOnDelete();
            
            $table->boolean('battery_voltage_verified')->default(false);
            $table->boolean('electrical_system_tested')->default(false);
            $table->boolean('accessories_installed')->default(false);
            $table->boolean('cosmetic_inspection_passed')->default(false);
            
            $table->string('result_status', 30)->default('pending');
            $table->string('battery_tested_voltage', 20)->nullable();
            $table->json('bom_checklist_details')->nullable();
            
            $table->text('technician_notes')->nullable();
            $table->foreignId('technician_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('inspected_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pvp_inspections');
    }
};
