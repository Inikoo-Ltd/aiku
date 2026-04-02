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
        Schema::table('shop_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_products_with_rrp_violation')->default(0)->comment('Number of products with faulty RRP configuration');
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
            $table->dropColumn([
                'number_products_with_rrp_violation'
            ]);
        });
    }
};
