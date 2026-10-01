<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_status_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('equipment_id')->constrained('equipments')->cascadeOnDelete();
            
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->string('location', 150)->nullable();
            $table->text('notes')->nullable();
            $table->json('snapshot')->nullable();
            
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recorded_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_status_histories');
    }
};
