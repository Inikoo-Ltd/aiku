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
        Schema::table('magento_users', function (Blueprint $table) {
            $table->unsignedBigInteger('platform_id')->nullable()->index();
            $table->foreign('platform_id')->references('id')->on('platforms')->nullOnDelete();
            $table->unsignedBigInteger('customer_sales_channel_id')->nullable()->index();
            $table->foreign('customer_sales_channel_id')->references('id')->on('customer_sales_channels')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('magento_users', function (Blueprint $table) {
            $table->dropForeign(['platform_id']);
            $table->dropForeign(['customer_sales_channel_id']);
            $table->dropIndex(['platform_id']);
            $table->dropIndex(['customer_sales_channel_id']);
            $table->dropColumn('platform_id');
            $table->dropColumn('customer_sales_channel_id');
        });
    }
};
