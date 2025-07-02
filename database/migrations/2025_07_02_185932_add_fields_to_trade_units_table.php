<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 02 Jul 2025 19:59:39 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('trade_units', function (Blueprint $table) {
            $table->renameColumn('dimensions', 'marketing_dimensions');
        });

        Schema::table('group_goods_stats', function (Blueprint $table) {
            $table->renameColumn('number_trade_units_with_dimensions', 'number_trade_units_with_marketing_dimensions');
        });
    }


    public function down(): void
    {
        Schema::table('trade_units', function (Blueprint $table) {
            $table->renameColumn('marketing_dimensions', 'dimensions');
        });
        Schema::table('group_goods_stats', function (Blueprint $table) {
            $table->renameColumn('number_trade_units_with_marketing_dimensions', 'number_trade_units_with_dimensions');
        });
    }
};
