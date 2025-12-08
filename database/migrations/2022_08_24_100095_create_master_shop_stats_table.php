<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Dec 2024 02:36:08 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasCatalogueStats;
use App\Stubs\Migrations\HasGoodsStats;
use App\Stubs\Migrations\HasHelpersStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasCatalogueStats;
    use HasGoodsStats;
    use HasHelpersStats;

    public function up(): void
    {
        Schema::create('master_shop_stats', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('master_shop_id')->index();
            $table->foreign('master_shop_id')->references('id')->on('master_shops');

            $table = $this->shopsStatsFields($table);
            $table = $this->masterProductCategoriesStatsFields($table);
            $table = $this->masterAssetsStatsFields($table);

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_shop_stats');
    }
};
