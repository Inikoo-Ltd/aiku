<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('group_goods_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_trade_units_without_description')->default(0)->after('number_trade_units_without_weight');
            $table->unsignedInteger('number_trade_units_without_brand')->default(0)->after('number_trade_units_without_description');
        });
    }

    public function down(): void
    {
        Schema::table('group_goods_stats', function (Blueprint $table) {
            $table->dropColumn(['number_trade_units_without_description', 'number_trade_units_without_brand']);
        });
    }
};
