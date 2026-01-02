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
        Schema::table('shopify_users', function (Blueprint $table) {
            $table->unsignedBigInteger('external_shop_id')->index()->nullable();
            $table->foreign('external_shop_id')->references('id')->on('shops');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('shopify_users', function (Blueprint $table) {
            $table->dropColumn('external_shop_id');
        });
    }
};
