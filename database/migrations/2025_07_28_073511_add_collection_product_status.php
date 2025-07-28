<?php

use App\Enums\Catalogue\Collection\CollectionProductStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->string('product_status')->index()->default(CollectionProductStatusEnum::NORMAL->value);
        });

        Schema::table('group_catalogue_stats', function (Blueprint $table) {
            foreach (CollectionProductStatusEnum::cases() as $state) {
                $table->unsignedSmallInteger('number_collections_product_status_'.$state->snake())->default(0);
            }
        });

        Schema::table('organisation_catalogue_stats', function (Blueprint $table) {
            foreach (CollectionProductStatusEnum::cases() as $state) {
                $table->unsignedSmallInteger('number_collections_product_status_'.$state->snake())->default(0);
            }
        });

        Schema::table('shop_stats', function (Blueprint $table) {
            foreach (CollectionProductStatusEnum::cases() as $state) {
                $table->unsignedSmallInteger('number_collections_product_status_'.$state->snake())->default(0);
            }
        });

        Schema::table('product_category_stats', function (Blueprint $table) {
            foreach (CollectionProductStatusEnum::cases() as $state) {
                $table->unsignedSmallInteger('number_collections_product_status_'.$state->snake())->default(0);
            }
        });
    }


    public function down(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->dropColumn('product_status');
        });

        Schema::table('group_catalogue_stats', function (Blueprint $table) {
            foreach (CollectionProductStatusEnum::cases() as $state) {
                $table->dropColumn('number_collections_product_status_'.$state->snake());
            }
        });

        Schema::table('organisation_catalogue_stats', function (Blueprint $table) {
            foreach (CollectionProductStatusEnum::cases() as $state) {
                $table->dropColumn('number_collections_product_status_'.$state->snake());
            }
        });

        Schema::table('shop_stats', function (Blueprint $table) {
            foreach (CollectionProductStatusEnum::cases() as $state) {
                $table->dropColumn('number_collections_product_status_'.$state->snake());
            }
        });

        Schema::table('product_category_stats', function (Blueprint $table) {
            foreach (CollectionProductStatusEnum::cases() as $state) {
                $table->dropColumn('number_collections_product_status_'.$state->snake());
            }
        });
    }
};
