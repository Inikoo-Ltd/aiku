<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    private const COLUMNS = [
        'number_stock_delivery_items_under_delivered' => 'unit_quantity_checked < unit_quantity',
        'number_stock_delivery_items_over_delivered'  => 'unit_quantity_checked > unit_quantity',
    ];

    public function up(): void
    {
        Schema::table('stock_deliveries', function (Blueprint $table) {
            foreach (self::COLUMNS as $column => $comment) {
                if (!Schema::hasColumn('stock_deliveries', $column)) {
                    $table->unsignedInteger($column)->default(0)->comment($comment);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('stock_deliveries', function (Blueprint $table) {
            foreach (array_keys(self::COLUMNS) as $column) {
                if (Schema::hasColumn('stock_deliveries', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
