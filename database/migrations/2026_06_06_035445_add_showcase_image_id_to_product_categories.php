<?php

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
        Schema::table('product_categories', function (Blueprint $table) {
            $table->unsignedInteger('showcase_image_id')->nullable();
            $table->foreign('showcase_image_id')->on('media')->references('id');
        });

        Schema::table('master_product_categories', function (Blueprint $table) {
            $table->unsignedInteger('showcase_image_id')->nullable();
            $table->foreign('showcase_image_id')->on('media')->references('id');
            $table->foreign('desc_art1')->on('media')->references('id');
            $table->foreign('desc_art2')->on('media')->references('id');
            $table->foreign('desc_art3')->on('media')->references('id');
            $table->foreign('desc_art4')->on('media')->references('id');
            $table->foreign('desc_art5')->on('media')->references('id');
            $table->foreign('extra_desc_art1')->on('media')->references('id');
            $table->foreign('extra_desc_art2')->on('media')->references('id');
            $table->foreign('extra_desc_art3')->on('media')->references('id');
            $table->foreign('extra_desc_art4')->on('media')->references('id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropForeign('showcase_image_id');
            $table->dropColumn([
                'showcase_image_id'
            ]);
        });

        Schema::table('master_product_categories', function (Blueprint $table) {
            $table->dropForeign('showcase_image_id');
            $table->dropForeign('desc_art1');
            $table->dropForeign('desc_art2');
            $table->dropForeign('desc_art3');
            $table->dropForeign('desc_art4');
            $table->dropForeign('desc_art5');
            $table->dropForeign('extra_desc_art1');
            $table->dropForeign('extra_desc_art2');
            $table->dropForeign('extra_desc_art3');
            $table->dropForeign('extra_desc_art4');
            $table->dropColumn([
                'showcase_image_id'
            ]);
        });
    }
};
