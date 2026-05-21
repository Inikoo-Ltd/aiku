<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('group_goods_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_trade_units_without_weight')->default(0)->after('number_trade_units_with_gross_weight');
        });
    }

    public function down(): void
    {
        Schema::table('group_goods_stats', function (Blueprint $table) {
            $table->dropColumn('number_trade_units_without_weight');
        });
    }
};
