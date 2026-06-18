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
        Schema::table('product_categories', function (Blueprint $table) {
            $table->unsignedBigInteger('trade_unit_family_id')->nullable();
            $table->foreign('trade_unit_family_id')->references('id')->on('trade_unit_families');
        });

        Schema::table('master_product_categories', function (Blueprint $table) {
            $table->unsignedBigInteger('trade_unit_family_id')->nullable();
            $table->foreign('trade_unit_family_id')->references('id')->on('trade_unit_families');
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
            $table->dropForeign('trade_unit_family_id');
            $table->dropColumn([
                'trade_unit_family_id'
            ]);
        });

        Schema::table('master_product_categories', function (Blueprint $table) {
            $table->dropForeign('trade_unit_family_id');
            $table->dropColumn([
                'trade_unit_family_id'
            ]);
        });
    }
};
