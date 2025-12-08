<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Tue, 18 Apr 2023 14:59:23 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('shipping_zones', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->unsignedSmallInteger('shipping_zone_schema_id')->index();
            $table->foreign('shipping_zone_schema_id')->references('id')->on('shipping_zone_schemas');
            $table->boolean('status');
            $table->string('slug')->unique()->collation('und_ns');
            $table->boolean('is_failover')->default(false);
            $table->string('code');
            $table->string('name');
            $table->jsonb('price');
            $table->jsonb('territories');
            $table->unsignedSmallInteger('position');
            $table->unsignedSmallInteger('currency_id');
            $table->foreign('currency_id')->references('id')->on('currencies');
            $table->unsignedInteger('asset_id')->nullable();
            $table->foreign('asset_id')->references('id')->on('assets');
            $table->unsignedInteger('current_historic_asset_id')->index()->nullable();
            $table->foreign('current_historic_asset_id')->references('id')->on('historic_assets');
            $table->timestampsTz();
            $table->datetimeTz('fetched_at')->nullable();
            $table->datetimeTz('last_fetched_at')->nullable();
            $table->softDeletesTz();
            $table->string('source_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_zones');
    }
};
