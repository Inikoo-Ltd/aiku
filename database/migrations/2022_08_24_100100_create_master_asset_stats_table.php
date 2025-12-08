<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 31 Dec 2024 21:51:41 Malaysia Time, Kuala Lumpur, Malaysia
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
        Schema::create('master_asset_stats', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('master_asset_id')->index();
            $table->foreign('master_asset_id')->references('id')->on('master_assets')->onDelete('cascade');
            $table = $this->productVariantFields($table);
            $table = $this->getCustomersWhoFavouritedStatsFields($table);
            $table = $this->getCustomersWhoRemindedStatsFields($table);
            $table = $this->assetStats($table);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_asset_stats');
    }
};
