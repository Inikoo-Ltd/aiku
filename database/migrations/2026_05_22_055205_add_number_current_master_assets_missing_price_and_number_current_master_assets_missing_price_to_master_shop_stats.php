<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 22 May 2026 16:21:20 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('master_shop_stats', function (Blueprint $table) {
            $table->unsignedBigInteger('number_current_master_assets_missing_price_or_rrp')->default(0);
        });
    }


    public function down(): void
    {
        Schema::table('master_shop_stats', function (Blueprint $table) {
            $table->dropColumn([
                'number_current_master_assets_missing_price_or_rrp',
            ]);
        });
    }
};
