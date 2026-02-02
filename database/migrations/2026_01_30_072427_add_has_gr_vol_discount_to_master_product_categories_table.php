<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Feb 2026 15:49:31 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('master_product_categories', function (Blueprint $table) {
            $table->boolean('has_gr_vol_discount')->default(false);
        });
    }


    public function down(): void
    {
        Schema::table('master_product_categories', function (Blueprint $table) {
            $table->dropColumn('has_gr_vol_discount');
        });
    }
};
