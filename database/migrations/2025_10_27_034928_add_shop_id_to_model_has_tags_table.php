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
        Schema::table('model_has_tags', function (Blueprint $table) {
            $table->unsignedBigInteger('shop_id')->nullable()->index();
            $table->foreign('shop_id')->references('id')->on('shops');

            $table->boolean('is_for_sale')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('model_has_tags', function (Blueprint $table) {
            $table->dropColumn('shop_id');
            $table->dropForeign('shop_id');

            $table->dropColumn('is_for_sale');
        });
    }
};
