<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 01 Jun 2024 21:14:16 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
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
        Schema::create('product_stats', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('product_id')->index();
            $table->foreign('product_id')->references('id')->on('products');

            $table = $this->productVariantFields($table);
            $table = $this->getCustomersWhoFavouritedStatsFields($table);
            $table = $this->getCustomersWhoRemindedStatsFields($table);

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_stats');
    }
};
