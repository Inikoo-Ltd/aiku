<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    private array $tables = [
        'customer_stats',
        'shop_stats',
        'warehouse_stats',
        'organisation_procurement_stats',
        'group_procurement_stats',
    ];

    private array $columns = [
        'number_return_delivery_notes_type_return',
        'number_return_delivery_notes_type_cancellation',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                foreach ($this->columns as $column) {
                    $table->unsignedInteger($column)->default(0);
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
