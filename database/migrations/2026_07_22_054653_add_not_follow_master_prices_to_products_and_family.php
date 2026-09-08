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
            $table->boolean('not_follow_master_prices')->default(false)->index();
        });

        Schema::table('product_categories', function (Blueprint $table) {
            $table->boolean('not_follow_master_prices')->default(false)->index();
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
            $table->dropColumn([
                'not_follow_master_prices'
            ]);
        });

        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropColumn([
                'not_follow_master_prices'
            ]);
        });
    }
};
