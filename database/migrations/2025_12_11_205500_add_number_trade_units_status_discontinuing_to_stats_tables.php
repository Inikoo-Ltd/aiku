<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 11 Dec 2025 20:56:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('group_goods_stats', function (Blueprint $table): void {
            if (!Schema::hasColumn('group_goods_stats', 'number_trade_units_status_discontinuing')) {
                $table->unsignedMediumInteger('number_trade_units_status_discontinuing')->default(0);
            }
        });

        Schema::table('trade_unit_family_stats', function (Blueprint $table): void {
            if (!Schema::hasColumn('trade_unit_family_stats', 'number_trade_units_status_discontinuing')) {
                $table->unsignedMediumInteger('number_trade_units_status_discontinuing')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('group_goods_stats', function (Blueprint $table): void {
            if (Schema::hasColumn('group_goods_stats', 'number_trade_units_status_discontinuing')) {
                $table->dropColumn('number_trade_units_status_discontinuing');
            }
        });

        Schema::table('trade_unit_family_stats', function (Blueprint $table): void {
            if (Schema::hasColumn('trade_unit_family_stats', 'number_trade_units_status_discontinuing')) {
                $table->dropColumn('number_trade_units_status_discontinuing');
            }
        });
    }
};
