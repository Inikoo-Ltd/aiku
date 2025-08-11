<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Thu, 01 Sept 2022 18:55:29 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

use App\Enums\Catalogue\Asset\AssetStateEnum;
use App\Stubs\Migrations\HasAssetCodeDescription;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasAssetCodeDescription;
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->nullable();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->unsignedInteger('master_asset_id')->nullable()->index();
            $table->foreign('master_asset_id')->references('id')->on('master_assets')->nullOnDelete();
            $table->string('slug')->unique()->collation('und_ns');
            $table->boolean('is_main')->default(true)->index();
            $table->string('type')->index();
            $table->string('model_type')->index();
            $table->unsignedInteger('model_id')->index()->nullable();
            $table->unsignedInteger('current_historic_asset_id')->index()->nullable();
            $table->string('state')->default(AssetStateEnum::IN_PROCESS)->index();
            $table->boolean('status')->default(true)->index();
            $table->string('code')->index()->collation('und_ns')->comment('mirror of the asset model');
            $table->string('name', 255)->nullable()->comment('mirror of the asset model');
            $table->decimal('price', 18)->nullable()->comment('mirror of the asset model');
            $table->decimal('units', 9, 3)->comment('mirror of the asset model');
            $table->string('unit')->nullable()->comment('mirror of the asset model');


            $table->unsignedSmallInteger('currency_id');
            $table->foreign('currency_id')->references('id')->on('currencies');
            $table->jsonb('data');
            $table->timestampsTz();
            $table->softDeletesTz();

        });
    }


    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
