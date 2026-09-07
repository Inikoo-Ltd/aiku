<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_orders', 'deposit_amount')) {
                $table->decimal('deposit_amount', 16, 2)->nullable();
            }
            if (!Schema::hasColumn('purchase_orders', 'deposit_paid_at')) {
                $table->timestampTz('deposit_paid_at')->nullable();
            }
            if (!Schema::hasColumn('purchase_orders', 'balance_paid_at')) {
                $table->timestampTz('balance_paid_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            foreach (['deposit_amount', 'deposit_paid_at', 'balance_paid_at'] as $column) {
                if (Schema::hasColumn('purchase_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
