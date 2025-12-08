<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Apr 2024 06:27:24 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Stubs\Migrations\HasCatalogueStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasCatalogueStats;

    public function up(): void
    {
        Schema::create('collection_stats', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedInteger('collection_id')->index();
            $table->foreign('collection_id')->references('id')->on('collections');
            $table = $this->catalogueProductsStats($table);
            $table->unsignedInteger('number_collections')->default(0);
            $table->unsignedInteger('number_families')->default(0);
            $table->unsignedSmallInteger('number_current_families')->default(0)->comment('state: active+discontinuing');
            foreach (ProductCategoryStateEnum::cases() as $familyState) {
                $table->unsignedInteger('number_families_state_'.$familyState->snake())->default(0);
            }
            $table->unsignedInteger('number_departments')->default(0);
            $table->unsignedSmallInteger('number_current_departments')->default(0)->comment('state: active+discontinuing');
            foreach (ProductCategoryStateEnum::cases() as $departmentState) {
                $table->unsignedInteger('number_departments_state_'.$departmentState->snake())->default(0);
            }
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_stats');
    }
};
