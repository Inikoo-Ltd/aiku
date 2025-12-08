<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Tue, 18 Apr 2023 14:56:30 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Enums\Ordering\ShippingZoneSchema\ShippingZoneSchemaStateEnum;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('shipping_zone_schemas', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->string('state')->index()->default(ShippingZoneSchemaStateEnum::IN_PROCESS->value);
            $table->string('slug')->unique()->collation('und_ns');
            $table->string('name');

            $table->boolean('is_current')->default(false);
            $table->boolean('is_current_discount')->default(false);

            $table->datetimeTz('live_at')->nullable();
            $table->datetimeTz('decommissioned_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->datetimeTz('fetched_at')->nullable();
            $table->datetimeTz('last_fetched_at')->nullable();
            $table->string('source_id')->nullable()->unique();
        });

        Schema::table('shops', function ($table) {
            $table->foreign('shipping_zone_schema_id')->references('id')->on('shipping_zone_schemas')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('discount_shipping_zone_schema_id')->references('id')->on('shipping_zone_schemas')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_zone_schemas');
    }
};
