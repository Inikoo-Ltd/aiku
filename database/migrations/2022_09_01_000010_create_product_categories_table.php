<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Thu, 20 Oct 2022 18:35:32 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Stubs\Migrations\HasAssetCodeDescription;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use App\Stubs\Migrations\HasSoftDeletes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasAssetCodeDescription;
    use HasGroupOrganisationRelationship;
    use HasSoftDeletes;

    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->increments('id');
            $table->string('type')->index();
            $table->string('state')->index()->default(ProductCategoryStateEnum::IN_PROCESS->value);
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->nullable();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->unsignedSmallInteger('master_product_category_id')->nullable()->index();
            $table->foreign('master_product_category_id')->references('id')->on('master_product_categories')->nullOnDelete();
            $table->unsignedInteger('department_id')->nullable()->index();
            $table->unsignedInteger('sub_department_id')->nullable()->index();
            $table->unsignedInteger('parent_id')->nullable()->index();
            $table->string('slug')->unique()->collation('und_ns');
            $table = $this->assertCodeDescription($table);
            $table->unsignedInteger('image_id')->nullable();
            $table->jsonb('data');
            $table->dateTimeTz('activated_at')->nullable();
            $table->dateTimeTz('discontinuing_at')->nullable();
            $table->dateTimeTz('discontinued_at')->nullable();
            $table->timestampstz();
            $table->datetimeTz('fetched_at')->nullable();
            $table->datetimeTz('last_fetched_at')->nullable();
            $table = $this->softDeletes($table);
            $table->string('source_department_id')->nullable()->unique();
            $table->string('source_family_id')->nullable()->unique();
        });
        DB::statement('CREATE INDEX ON product_categories USING gin (name gin_trgm_ops) ');

        Schema::table('product_categories', function (Blueprint $table) {
            $table->foreign('department_id')->references('id')->on('product_categories');
            $table->foreign('sub_department_id')->references('id')->on('product_categories');
            $table->foreign('parent_id')->references('id')->on('product_categories');

        });
    }

    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropForeign('department_id_foreign');
            $table->dropForeign('product_category_id_foreign');
        });
        Schema::dropIfExists('product_categories');
    }
};
