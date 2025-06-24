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
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedSmallInteger('top_seller')->default(0)->index();
        });
        Schema::table('product_categories', function (Blueprint $table) {
            $table->unsignedSmallInteger('top_seller')->default(0)->index();
        });


    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('top_seller');
        });
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropColumn('top_seller');
        });
    }
};
