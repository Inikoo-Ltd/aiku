<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    private const NULLABLE_COLUMNS = [
        'cost_items',
        'cost_extra',
        'cost_shipping',
        'cost_duties',
    ];

    private const ZERO_COLUMNS = [
        'cost_tax',
        'cost_total',
    ];

    public function up(): void
    {
        Schema::table('stock_delivery_items', function (Blueprint $table) {
            foreach (self::NULLABLE_COLUMNS as $column) {
                if (!Schema::hasColumn('stock_delivery_items', $column)) {
                    $table->decimal($column, 16)->default(null)->nullable();
                }
            }

            foreach (self::ZERO_COLUMNS as $column) {
                if (!Schema::hasColumn('stock_delivery_items', $column)) {
                    $table->decimal($column, 16)->default(0);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('stock_delivery_items', function (Blueprint $table) {
            foreach (array_merge(self::NULLABLE_COLUMNS, self::ZERO_COLUMNS) as $column) {
                if (Schema::hasColumn('stock_delivery_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
