<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 03 Jul 2024 12:34:13 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Enums\Billables\Service\ServiceStateEnum;
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
        Schema::create('services', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->nullable();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->unsignedInteger('asset_id')->nullable();
            $table->foreign('asset_id')->references('id')->on('assets');
            $table->boolean('is_auto_assign')->default(false)->index();
            $table->string('auto_assign_trigger')->nullable()->comment('What trigger this automation');
            $table->string('auto_assign_subject')->nullable()->comment('Used for auto assign this service to a action');
            $table->string('auto_assign_subject_type')->nullable()->comment('Used for auto assign this service to an action type');
            $table->boolean('auto_assign_status')->default(false);
            $table->boolean('status')->default(false)->index();
            $table->string('state')->default(ServiceStateEnum::IN_PROCESS)->index();
            $table = $this->assetModelFields($table);
            $table->timestampsTz();
            $table->softDeletes();
            $table->string('source_id')->nullable()->unique();
            $table->string('historic_source_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
