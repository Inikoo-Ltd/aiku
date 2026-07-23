<?php

/*
 * Author Louis Perez
 * Created on 22-07-2026-16h-19m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

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
        Schema::table('product_category_stats', function (Blueprint $table) {
            $table->unsignedInteger('total_products_rebel_prices')->default(0);
        });

        Schema::table('master_asset_stats', function (Blueprint $table) {
            $table->unsignedInteger('total_products_rebel_prices')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('product_category_stats', function (Blueprint $table) {
            $table->dropColumn([
                'total_products_rebel_prices'
            ]);
        });

        Schema::table('master_asset_stats', function (Blueprint $table) {
            $table->dropColumn([
                'total_products_rebel_prices'
            ]);
        });
    }
};
