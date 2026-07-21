<?php

/*
 * Author: ekayudinata <dev@aw-advantage.com>
 * Created: Mon, 20 Jul 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;
    public function up(): void
    {
        Schema::create('preferred_shippings', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);

            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops')->cascadeOnDelete();

            $table->unsignedSmallInteger('country_id')->nullable()->index();
            $table->foreign('country_id')->references('id')->on('countries')->nullOnDelete();

            $table->string('postcode')->nullable();

            $table->unsignedSmallInteger('shipper_id')->index();
            $table->foreign('shipper_id')->references('id')->on('shippers')->cascadeOnDelete();

            $table->boolean('important')->default(false);

            $table->timestampsTz();

            // $table->unique(['shop_id', 'country_id', 'postcode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preferred_shippings');
    }
};
