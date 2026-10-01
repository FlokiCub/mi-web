<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('equipment_id')->constrained('equipments')->cascadeOnDelete();
            $table->string('recipient_phone', 40)->index();
            $table->string('recipient_name', 120)->nullable();
            
            $table->string('event_trigger', 50);
            $table->text('message_body');
            $table->string('status', 30)->default('sent');
            $table->string('gateway_message_id', 100)->nullable();
            $table->text('error_details')->nullable();

            $table->timestamp('sent_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_notifications');
    }
};
