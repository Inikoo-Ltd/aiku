<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Feb 2026 10:53:33 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('master_asset_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_assets_including_closed_shops')->default(0);
            $table->unsignedInteger('number_assets_forced_not_for_sale')->default(0);
            $table->unsignedInteger('number_assets_from_closed_shops')->default(0);

        });
    }


    public function down(): void
    {
        Schema::table('master_asset_stats', function (Blueprint $table) {
            $table->dropColumn('number_assets_including_closed_shops');
            $table->dropColumn('number_assets_forced_not_for_sale');
            $table->dropColumn('number_assets_from_closed_shops');
        });
    }
};
