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
        Schema::table('webpages', function (Blueprint $table) {
            $table->unsignedInteger('seo_image_id')->nullable();
            $table->foreign('seo_image_id')->references('id')->on('media')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('webpages', function (Blueprint $table) {
            $table->dropForeign(['seo_image_id']);
            $table->dropColumn('seo_image_id');
        });
    }
};
