<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('warehouse_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_locations_stock_slots_all_empty')->default(0);
            $table->unsignedInteger('number_locations_stock_slots_partial_empty')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_stats', function (Blueprint $table) {
            $table->dropColumn(['number_locations_stock_slots_all_empty', 'number_locations_stock_slots_partial_empty']);
        });
    }
};
