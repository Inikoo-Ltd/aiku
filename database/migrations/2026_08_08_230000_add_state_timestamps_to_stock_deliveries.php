<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    private const COLUMNS = ['confirmed_at', 'ready_to_ship_at', 'booking_in_at', 'booked_in_at'];

    public function up(): void
    {
        Schema::table('stock_deliveries', function (Blueprint $table) {
            foreach (self::COLUMNS as $column) {
                if (!Schema::hasColumn('stock_deliveries', $column)) {
                    $table->timestampTz($column)->nullable();
                }
            }
        });

        foreach (self::COLUMNS as $column) {
            DB::statement("
                update stock_deliveries
                set {$column} = (data->>'{$column}')::timestamptz
                where data->>'{$column}' is not null
            ");
        }
    }

    public function down(): void
    {
        Schema::table('stock_deliveries', function (Blueprint $table) {
            foreach (self::COLUMNS as $column) {
                if (Schema::hasColumn('stock_deliveries', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
