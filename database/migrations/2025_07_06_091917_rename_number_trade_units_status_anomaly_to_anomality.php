<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 06 Jul 2025 11:03:58 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('group_goods_stats', function (Blueprint $table) {
            $table->renameColumn('number_trade_units_status_anomaly', 'number_trade_units_status_anomality');
        });
    }


    public function down(): void
    {
        Schema::table('group_goods_stats', function (Blueprint $table) {
            $table->renameColumn('number_trade_units_status_anomality', 'number_trade_units_status_anomaly');
        });
    }
};
