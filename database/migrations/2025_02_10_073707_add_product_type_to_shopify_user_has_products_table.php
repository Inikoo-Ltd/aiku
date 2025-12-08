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
        Schema::table('shopify_user_has_products', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->string('product_type')->after('product_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('shopify_user_has_products', function (Blueprint $table) {
            $table->dropColumn('product_type');
            $table->foreign('product_id')->on('products')->references('id');
        });
    }
};
