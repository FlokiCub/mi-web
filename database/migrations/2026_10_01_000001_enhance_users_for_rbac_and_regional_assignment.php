<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 40)->default('auditor')->change();
            $table->string('phone', 30)->nullable()->after('email');
            $table->foreignUuid('regional_warehouse_id')
                ->nullable()
                ->after('role')
                ->constrained('regional_warehouses')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['regional_warehouse_id']);
            $table->dropColumn(['regional_warehouse_id', 'phone']);
            $table->string('role')->default('user')->change();
        });
    }
};
