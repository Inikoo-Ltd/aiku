<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 15 May 2026 18:17:35 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('master_product_categories', function (Blueprint $table) {
            $table->decimal('gr_vol_discount_percentage', 6, 4)->nullable();
            $table->unsignedSmallInteger('gr_vol_discount_quantity')->nullable();
        });
    }


    public function down(): void
    {
        Schema::table('master_product_categories', function (Blueprint $table) {
            $table->dropColumn(['gr_vol_discount_percentage', 'gr_vol_discount_quantity']);
        });
    }
};
