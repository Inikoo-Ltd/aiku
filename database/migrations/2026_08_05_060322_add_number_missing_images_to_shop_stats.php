<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('shop_stats', function (Blueprint $table) {
            $table->unsignedSmallInteger('number_families_no_images')->default(0);
            $table->unsignedSmallInteger('number_products_no_images')->default(0);
        });

        Schema::table('master_shop_stats', function (Blueprint $table) {
            $table->unsignedSmallInteger('number_missing_images_master_families')->default(0);
            $table->unsignedSmallInteger('number_missing_images_master_asset')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('shop_stats', function (Blueprint $table) {
            $table->dropColumn([
                'number_families_no_images',
                'number_products_no_images'
            ]);
        });


        Schema::table('master_shop_stats', function (Blueprint $table) {
            $table->dropColumn([
                'number_missing_images_master_families',
                'number_missing_images_master_asset'
            ]);
        });
    }
};
