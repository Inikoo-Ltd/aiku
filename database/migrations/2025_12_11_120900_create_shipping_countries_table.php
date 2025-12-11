<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 11 Dec 2025 15:35:15 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;
    public function up(): void
    {
        Schema::create('shipping_countries', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table = $this->groupOrgRelationship($table);

            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops')->cascadeOnDelete();

            $table->unsignedSmallInteger('country_id')->index();
            $table->foreign('country_id')->references('id')->on('countries')->cascadeOnDelete();

            $table->jsonb('territories')->nullable();

            $table->timestampsTz();

            $table->unique(['shop_id', 'country_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_countries');
    }
};
