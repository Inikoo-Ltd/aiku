<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    private array $tables = ['group_inventory_stats', 'organisation_inventory_stats', 'warehouse_stats', 'org_stock_stats'];

    private array $columns = [
        'number_org_stock_movements_type_cancel_purchase',
        'number_org_stock_movements_type_cancel_return_picked',
        'number_org_stock_movements_type_cancel_picked',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                foreach ($this->columns as $column) {
                    if (!Schema::hasColumn($tableName, $column)) {
                        $table->unsignedBigInteger($column)->default(0);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn($this->columns);
            });
        }
    }
};
