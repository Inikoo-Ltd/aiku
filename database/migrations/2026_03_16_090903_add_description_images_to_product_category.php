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
        Schema::table('master_product_categories', function (Blueprint $table) {
            $table->string('desc_video_url')->nullable();
            $table->unsignedInteger('desc_art1')->nullable();
            $table->unsignedInteger('desc_art2')->nullable();
            $table->unsignedInteger('desc_art3')->nullable();
            $table->unsignedInteger('desc_art4')->nullable();
            $table->unsignedInteger('desc_art5')->nullable();
            $table->unsignedInteger('extra_desc_art1')->nullable();
        });


        Schema::table('product_categories', function (Blueprint $table) {
            $table->string('desc_video_url')->nullable();
            $table->unsignedInteger('desc_art1')->nullable();
            $table->unsignedInteger('desc_art2')->nullable();
            $table->unsignedInteger('desc_art3')->nullable();
            $table->unsignedInteger('desc_art4')->nullable();
            $table->unsignedInteger('desc_art5')->nullable();
            $table->unsignedInteger('extra_desc_art1')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('master_product_categories', function (Blueprint $table) {
            $table->dropColumn([
                'desc_video_url',
                'desc_art1',
                'desc_art2',
                'desc_art3',
                'desc_art4',
                'desc_art5',
                'extra_desc_art1',
            ]);
        });

        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropColumn([
                'desc_video_url',
                'desc_art1',
                'desc_art2',
                'desc_art3',
                'desc_art4',
                'desc_art5',
                'extra_desc_art1',
            ]);
        });
    }
};
