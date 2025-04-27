<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 26 Apr 2025 12:16:23 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('master_product_category_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_master_product_categories')->default(0);
            $table->unsignedInteger('number_current_master_product_categories')->default(0)->comment('status=true');

            $table->unsignedInteger('number_master_product_categories_type_sub_department')->default(0);
            $table->unsignedInteger('number_current_master_product_categories_type_sub_department')->default(0);


            $table->unsignedInteger('number_master_product_categories_type_family')->default(0);
            $table->unsignedInteger('number_current_master_product_categories_type_family')->default(0);
        });
    }


    public function down(): void
    {
        Schema::table('master_product_category_stats', function (Blueprint $table) {
            $table->dropColumn([
                'number_master_product_categories',
                'number_current_master_product_categories',
                'number_master_product_categories_type_sub_department',
                'number_current_master_product_categories_type_sub_department',
                'number_master_product_categories_type_family',
                'number_current_master_product_categories_type_family',
            ]);
        });
    }
};
