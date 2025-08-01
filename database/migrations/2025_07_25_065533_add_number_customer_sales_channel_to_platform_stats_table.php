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
        Schema::table('platform_stats', function (Blueprint $table) {
            $table->bigInteger('number_customer_sales_channels')->default(0);
            $table->bigInteger('number_customer_sales_channel_broken')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('platform_stats', function (Blueprint $table) {
            $table->dropColumn('number_customer_sales_channels');
            $table->dropColumn('number_customer_sales_channel_broken');
        });
    }
};
