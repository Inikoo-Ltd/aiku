<?php

use App\Enums\Catalogue\Collection\CollectionProductsStatusEnum;
use App\Enums\Catalogue\Collection\CollectionStateEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('master_shop_stats', function (Blueprint $table) {
             foreach (CollectionProductsStatusEnum::cases() as $state) {
                $table->unsignedInteger('number_collections_products_status_'.$state->snake())->default(0);
            }
        });
        Schema::table('master_product_category_stats', function (Blueprint $table) {
            foreach (CollectionStateEnum::cases() as $state) {
                $table->unsignedInteger('number_collections_state_'.$state->snake())->default(0);
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('master_shop_stats', function (Blueprint $table) {
            foreach (CollectionProductsStatusEnum::cases() as $state) {
                $table->dropColumn('number_collections_products_status_'.$state->snake())->default(0);
            }
        });
        Schema::table('master_product_category_stats', function (Blueprint $table) {
            foreach (CollectionStateEnum::cases() as $state) {
                $table->dropColumn('number_collections_state_'.$state->snake());
            }
        });
    }
};
