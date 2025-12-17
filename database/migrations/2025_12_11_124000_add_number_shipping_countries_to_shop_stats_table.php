<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 11 Dec 2025 15:35:15 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 11 Dec 2025 12:40:00 Malaysia Time, Kuala Lumpur, Malaysia
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('shop_stats', function (Blueprint $table) {
            if (!Schema::hasColumn('shop_stats', 'number_shipping_countries')) {
                $table->unsignedSmallInteger('number_shipping_countries')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('shop_stats', function (Blueprint $table) {
            if (Schema::hasColumn('shop_stats', 'number_shipping_countries')) {
                $table->dropColumn('number_shipping_countries');
            }
        });
    }
};
