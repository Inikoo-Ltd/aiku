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
        Schema::table('customer_stats', function (Blueprint $table) {
            $table->smallInteger('number_orders_sales_channel_type_showroom')->default(0);
            $table->smallInteger('number_orders_sales_channel_type_phone')->default(0);
            $table->smallInteger('number_orders_sales_channel_type_email')->default(0);
            $table->smallInteger('number_orders_sales_channel_type_other')->default(0);
            $table->smallInteger('number_orders_sales_channel_type_website')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('customer_stats', function (Blueprint $table) {
            $table->dropColumn('number_orders_sales_channel_type_showroom');
            $table->dropColumn('number_orders_sales_channel_type_phone');
            $table->dropColumn('number_orders_sales_channel_type_email');
            $table->dropColumn('number_orders_sales_channel_type_other');
            $table->dropColumn('number_orders_sales_channel_type_website');
        });
    }
};
