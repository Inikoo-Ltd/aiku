<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 16 Apr 2024 17:02:34 Malaysia Time, Kuala Lumpur , Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Enums\Billables\Rental\RentalStateEnum;
use App\Stubs\Migrations\HasAssetModel;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasAssetModel;
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('rentals', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->nullable();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->unsignedSmallInteger('fulfilment_id')->nullable();
            $table->foreign('fulfilment_id')->references('id')->on('fulfilments');
            $table->unsignedInteger('asset_id')->nullable();
            $table->foreign('asset_id')->references('id')->on('assets')->cascadeOnDelete();

            $table->string('auto_assign_asset')->nullable()->comment('Used for auto assign this rent to this asset');
            $table->string('auto_assign_asset_type')->nullable()->comment('Used for auto assign this rent to this asset type');

            $table->boolean('status')->default(false)->index();
            $table->string('state')->default(RentalStateEnum::IN_PROCESS)->index();

            $table = $this->assetModelFields($table);

            $table->timestampsTz();
            $table->softDeletes();
            $table->string('source_id')->nullable()->unique();
            $table->string('historic_source_id')->nullable()->unique();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rentals');
    }
};
