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
        Schema::table('shop_stats', function (Blueprint $table) {
            $table->unsignedInteger('pending_back_in_stock_products_count')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('shop_stats', function (Blueprint $table) {
            $table->dropColumn('pending_back_in_stock_products_count');
        });
    }
};
