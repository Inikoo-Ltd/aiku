<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Sept 2024 21:39:19 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasDiscountsStats;
use App\Stubs\Migrations\HasUsageStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasDiscountsStats;
    use HasUsageStats;
    public function up(): void
    {
        Schema::create('shop_discounts_stats', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops');

            $table = $this->usageBaseStats($table);
            $table = $this->offerCampaignsStats($table);
            $table = $this->offersStats($table);
            $table = $this->offerAllowancesStats($table);

            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('shop_discounts_stats');
    }
};
