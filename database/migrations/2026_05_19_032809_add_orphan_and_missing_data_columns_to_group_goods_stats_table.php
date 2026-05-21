<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('group_goods_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_orphan_trade_units')->default(0)->after('number_trade_units');
            $table->unsignedInteger('number_trade_units_without_marketing_weight')->default(0)->after('number_trade_units_with_marketing_weight');
            $table->unsignedInteger('number_trade_units_without_marketing_dimensions')->default(0)->after('number_trade_units_with_marketing_dimensions');
        });
    }

    public function down(): void
    {
        Schema::table('group_goods_stats', function (Blueprint $table) {
            $table->dropColumn([
                'number_orphan_trade_units',
                'number_trade_units_without_marketing_weight',
                'number_trade_units_without_marketing_dimensions',
            ]);
        });
    }
};
