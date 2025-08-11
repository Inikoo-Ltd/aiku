<?php

use App\Enums\Catalogue\Collection\CollectionProductsStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->string('products_status')->index()->default(CollectionProductsStatusEnum::NORMAL->value);
        });

        Schema::table('group_catalogue_stats', function (Blueprint $table) {
            foreach (CollectionProductsStatusEnum::cases() as $state) {
                $table->unsignedSmallInteger('number_collections_products_status_'.$state->snake())->default(0);
            }
        });

        Schema::table('organisation_catalogue_stats', function (Blueprint $table) {
            foreach (CollectionProductsStatusEnum::cases() as $state) {
                $table->unsignedSmallInteger('number_collections_products_status_'.$state->snake())->default(0);
            }
        });

        Schema::table('shop_stats', function (Blueprint $table) {
            foreach (CollectionProductsStatusEnum::cases() as $state) {
                $table->unsignedSmallInteger('number_collections_products_status_'.$state->snake())->default(0);
            }
        });

        Schema::table('product_category_stats', function (Blueprint $table) {
            foreach (CollectionProductsStatusEnum::cases() as $state) {
                $table->unsignedSmallInteger('number_collections_products_status_'.$state->snake())->default(0);
            }
        });
    }


    public function down(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->dropColumn('products_status');
        });

        Schema::table('group_catalogue_stats', function (Blueprint $table) {
            foreach (CollectionProductsStatusEnum::cases() as $state) {
                $table->dropColumn('number_collections_products_status_'.$state->snake());
            }
        });

        Schema::table('organisation_catalogue_stats', function (Blueprint $table) {
            foreach (CollectionProductsStatusEnum::cases() as $state) {
                $table->dropColumn('number_collections_products_status_'.$state->snake());
            }
        });

        Schema::table('shop_stats', function (Blueprint $table) {
            foreach (CollectionProductsStatusEnum::cases() as $state) {
                $table->dropColumn('number_collections_products_status_'.$state->snake());
            }
        });

        Schema::table('product_category_stats', function (Blueprint $table) {
            foreach (CollectionProductsStatusEnum::cases() as $state) {
                $table->dropColumn('number_collections_products_status_'.$state->snake());
            }
        });
    }
};
