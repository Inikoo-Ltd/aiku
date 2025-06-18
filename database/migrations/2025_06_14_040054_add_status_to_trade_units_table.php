<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 14 Jun 2025 12:02:08 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Enums\Goods\TradeUnit\TradeUnitStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('trade_units', function (Blueprint $table) {
            $table->string('status')->default(TradeUnitStatusEnum::IN_PROCESS->value);
        });

        Schema::table('group_goods_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_trade_units_status_in_process')->default(0);
            $table->unsignedInteger('number_trade_units_status_active')->default(0);
            $table->unsignedInteger('number_trade_units_status_discontinued')->default(0);
            $table->unsignedInteger('number_trade_units_status_anomaly')->default(0);
        });
    }


    public function down(): void
    {
        Schema::table('trade_units', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('group_goods_stats', function (Blueprint $table) {
            $table->dropColumn([
                'number_trade_units_status_in_process',
                'number_trade_units_status_active',
                'number_trade_units_status_discontinued',
                'number_trade_units_status_anomaly'
            ]);
        });
    }
};
