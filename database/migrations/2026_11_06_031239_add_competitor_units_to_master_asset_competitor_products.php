<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Nov 2026 11:12:39 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('master_asset_competitor_products', function (Blueprint $table) {
            $table->decimal('competitor_units', 12, 3)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('master_asset_competitor_products', function (Blueprint $table) {
            $table->dropColumn('competitor_units');
        });
    }
};
