<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 20 Apr 2023 10:14:19 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historic_asset_stats', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('historic_asset_id')->index();
            $table->foreign('historic_asset_id')->references('id')->on('historic_assets');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historic_asset_stats');
    }
};
