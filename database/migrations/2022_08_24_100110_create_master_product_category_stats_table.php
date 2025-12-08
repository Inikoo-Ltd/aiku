<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Dec 2024 02:32:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasCatalogueStats;
use App\Stubs\Migrations\HasGoodsStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasCatalogueStats;
    use HasGoodsStats;

    public function up(): void
    {
        Schema::create('master_product_category_stats', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('master_product_category_id')->index();
            $table->foreign('master_product_category_id')->references('id')->on('master_product_categories');

            $table = $this->masterAssetsStatsFields($table);
            $table = $this->catalogueDepartmentStatsFields($table);
            $table = $this->catalogueFamilyStats($table);
            $table = $this->catalogueProductsStats($table);

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_product_category_stats');
    }
};
