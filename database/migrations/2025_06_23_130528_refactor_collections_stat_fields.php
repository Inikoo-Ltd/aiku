<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 23 Jun 2025 21:05:55 Malaysia Time, Sheffield, United Kingdom
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Enums\Catalogue\Collection\CollectionStateEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('group_catalogue_stats', function (Blueprint $table) {
            $table->dropColumn('number_collection_categories');
            $table->unsignedSmallInteger('number_current_collections')->default(0)->comment('state=active+discontinuing');
            foreach (CollectionStateEnum::cases() as $state) {
                $table->unsignedSmallInteger('number_collections_state_'.$state->snake())->default(0);
            }
        });

        Schema::table('organisation_catalogue_stats', function (Blueprint $table) {
            $table->dropColumn('number_collection_categories');
            $table->unsignedSmallInteger('number_current_collections')->default(0)->comment('state=active+discontinuing');
            foreach (CollectionStateEnum::cases() as $state) {
                $table->unsignedSmallInteger('number_collections_state_'.$state->snake())->default(0);
            }
        });

        Schema::table('shop_stats', function (Blueprint $table) {
            $table->dropColumn('number_collection_categories');
            $table->unsignedSmallInteger('number_current_collections')->default(0)->comment('state=active+discontinuing');
            foreach (CollectionStateEnum::cases() as $state) {
                $table->unsignedSmallInteger('number_collections_state_'.$state->snake())->default(0);
            }
        });

        Schema::table('product_category_stats', function (Blueprint $table) {
            $table->unsignedSmallInteger('number_current_collections')->default(0)->comment('state=active+discontinuing');
            foreach (CollectionStateEnum::cases() as $state) {
                $table->unsignedSmallInteger('number_collections_state_'.$state->snake())->default(0);
            }
        });
    }


    public function down(): void
    {
        Schema::table('group_catalogue_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_collection_categories')->default(0);
            $table->dropColumn('number_current_collections');
            foreach (CollectionStateEnum::cases() as $state) {
                $table->dropColumn('number_collections_state_'.$state->snake());
            }
        });

        Schema::table('organisation_catalogue_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_collection_categories')->default(0);
            $table->dropColumn('number_current_collections');
            foreach (CollectionStateEnum::cases() as $state) {
                $table->dropColumn('number_collections_state_'.$state->snake());
            }
        });

        Schema::table('shop_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_collection_categories')->default(0);
            $table->dropColumn('number_current_collections');
            foreach (CollectionStateEnum::cases() as $state) {
                $table->dropColumn('number_collections_state_'.$state->snake());
            }
        });

        Schema::table('product_category_stats', function (Blueprint $table) {
            $table->dropColumn('number_current_collections');
            foreach (CollectionStateEnum::cases() as $state) {
                $table->dropColumn('number_collections_state_'.$state->snake());
            }
        });
    }
};
