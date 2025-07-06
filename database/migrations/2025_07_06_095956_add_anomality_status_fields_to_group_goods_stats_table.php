<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 06 Jul 2025 11:03:18 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('group_goods_stats', function (Blueprint $table) {
            $table->unsignedMediumInteger('number_trade_units_anomality_status_in_process')->default(0);
            $table->unsignedMediumInteger('number_trade_units_anomality_status_active')->default(0);
            $table->unsignedMediumInteger('number_trade_units_anomality_status_discontinued')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('group_goods_stats', function (Blueprint $table) {
            $table->dropColumn([
                'number_trade_units_anomality_status_in_process',
                'number_trade_units_anomality_status_active',
                'number_trade_units_anomality_status_discontinued'
            ]);
        });
    }
};
