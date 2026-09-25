<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    private array $tables = [
        'purchase_orders',
        'purchase_order_transactions',
        'agent_supplier_purchase_orders',
        'stock_deliveries',
        'stock_delivery_items',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->decimal('grp_exchange', 22, 10)->nullable()->change();
                $table->decimal('org_exchange', 22, 10)->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->decimal('grp_exchange', 16, 4)->nullable()->change();
                $table->decimal('org_exchange', 16, 4)->nullable()->change();
            });
        }
    }
};
