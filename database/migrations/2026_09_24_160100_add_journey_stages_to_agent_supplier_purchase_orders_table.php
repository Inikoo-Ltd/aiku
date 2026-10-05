<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('agent_supplier_purchase_orders', function (Blueprint $table) {
            $table->timestampTz('sample_approved_at')->nullable();
            $table->timestampTz('produced_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('agent_supplier_purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['sample_approved_at', 'produced_at']);
        });
    }
};
