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
            $table->unsignedInteger('number_orders_state_picked')->default(0)->after('number_orders_state_handling_blocked');
            $table->unsignedInteger('number_orders_state_packing')->default(0)->after('number_orders_state_picked');
        });
    }


    public function down(): void
    {
        Schema::table('shop_platform_stats', function (Blueprint $table) {
            $table->dropColumn(['number_orders_state_picked', 'number_orders_state_packing']);
        });
    }
};
