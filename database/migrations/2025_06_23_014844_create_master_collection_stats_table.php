<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Jun 2025 20:59:18 Malaysia Time, Sheffield, United Kingdom
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Enums\Catalogue\Collection\CollectionStateEnum;
use App\Stubs\Migrations\HasCatalogueStats;
use App\Stubs\Migrations\HasGoodsStats;
use App\Stubs\Migrations\HasHelpersStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasHelpersStats;
    use HasCatalogueStats;
    use HasGoodsStats;

    public function up(): void
    {
        Schema::create('master_collection_stats', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('master_collection_id')->index();
            $table->foreign('master_collection_id')->references('id')->on('master_collections');

            $table->unsignedInteger('number_collections')->default(0);

            foreach (CollectionStateEnum::cases() as $case) {
                $table->unsignedInteger('number_collections_state_'.$case->snake())->default(0);
            }

            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('master_collection_stats');
    }
};
