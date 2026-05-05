<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 05 May 2026 11:22:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('product_category_has_related_products', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('product_category_id')->index();
            $table->foreign('product_category_id', 'pc_hrp_product_category_fk')->references('id')->on('product_categories')->cascadeOnDelete();
            $table->unsignedInteger('product_id')->index();
            $table->foreign('product_id', 'pc_hrp_product_fk')->references('id')->on('products')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->index()->default(0);
            $table->timestampsTz();
            $table->unique(['product_category_id', 'product_id'], 'pc_hrp_product_category_product_unique');
        });

        Schema::create('master_product_category_has_related_assets', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('master_product_category_id')->index();
            $table->foreign('master_product_category_id', 'mpc_hra_master_product_category_fk')->references('id')->on('master_product_categories')->cascadeOnDelete();
            $table->unsignedInteger('master_asset_id')->index();
            $table->foreign('master_asset_id', 'mpc_hra_master_asset_fk')->references('id')->on('master_assets')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->index()->default(0);
            $table->timestampsTz();
            $table->unique(['master_product_category_id', 'master_asset_id'], 'mpc_hra_master_product_category_asset_unique');
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('master_product_category_has_related_assets');
        Schema::dropIfExists('product_category_has_related_products');
    }
};
