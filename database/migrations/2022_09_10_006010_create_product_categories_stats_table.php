<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Thu, 20 Oct 2022 18:36:25 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasBackInStockReminderStats;
use App\Stubs\Migrations\HasCatalogueStats;
use App\Stubs\Migrations\HasFavouritesStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasBackInStockReminderStats;
    use HasCatalogueStats;
    use HasFavouritesStats;

    public function up(): void
    {
        Schema::create('product_category_stats', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('product_category_id')->index();
            $table->foreign('product_category_id')->references('id')->on('product_categories')->onDelete('cascade')->onUpdate('cascade');
            $table = $this->catalogueFamilyStats($table);
            $table = $this->catalogueProductsStats($table);
            $table = $this->topSellersStats($table);
            $table = $this->getCustomersWhoFavouritedStatsFields($table);
            $table = $this->getCustomersWhoRemindedStatsFields($table);

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_category_stats');
    }
};
