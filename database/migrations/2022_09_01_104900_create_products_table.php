<?php
/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Thu, 01 Sept 2022 18:55:29 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

use App\Enums\Market\Product\ProductStateEnum;
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
        Schema::create('products', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->nullable();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->string('slug')->unique()->collation('und_ns');
            $table = $this->assertCodeDescription($table);
            $table->string('type')->index();
            $table->string('owner_type');
            $table->unsignedInteger('owner_id');
            $table->string('parent_type');
            $table->unsignedInteger('parent_id');
            $table->unsignedInteger('current_historic_outer_id')->index()->nullable();
            $table->string('state')->default(ProductStateEnum::IN_PROCESS)->index();
            $table->boolean('status')->default(true)->index();


            $table->string('unit_relationship_type')->index();

            $table->unsignedInteger('main_outer_id')->nullable()->index();
            $table->unsignedDecimal('main_outer_units', 12, 3)->nullable()->comment('units per outer');
            $table->unsignedDecimal('main_outer_price', 18)->nullable()->comment('unit price');
            $table->unsignedInteger('main_outer_available')->default(0)->nullable();


            $table->unsignedInteger('image_id')->nullable();
            $table->unsignedDecimal('rrp', 12, 3)->nullable()->comment('RRP per outer');


            $table->jsonb('settings');
            $table->jsonb('data');
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->string('source_id')->nullable()->unique();
            $table->index(['owner_id','owner_type']);
            $table->index(['parent_id','parent_type']);
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
