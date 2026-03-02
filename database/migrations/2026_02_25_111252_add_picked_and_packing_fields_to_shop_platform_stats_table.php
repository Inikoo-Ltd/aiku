<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Feb 2026 11:12:52 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('shop_platform_stats', function (Blueprint $table) {
            if (! Schema::hasColumn('shop_platform_stats', 'number_orders_state_picked')) {
                $table->unsignedInteger('number_orders_state_picked')->default(0)->after('number_orders_state_handling_blocked');
            }
            if (! Schema::hasColumn('shop_platform_stats', 'number_orders_state_packing')) {
                $table->unsignedInteger('number_orders_state_packing')->default(0)->after('number_orders_state_picked');
            }
        });
    }


    public function down(): void
    {
        Schema::table('shop_platform_stats', function (Blueprint $table) {
            if (Schema::hasColumn('shop_platform_stats', 'number_orders_state_picked')) {
                $table->dropColumn('number_orders_state_picked');
            }
            if (Schema::hasColumn('shop_platform_stats', 'number_orders_state_packing')) {
                $table->dropColumn('number_orders_state_packing');
            }
        });
    }
};
