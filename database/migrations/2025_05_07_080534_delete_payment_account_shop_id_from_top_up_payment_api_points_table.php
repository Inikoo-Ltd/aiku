<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('top_up_payment_api_points', function (Blueprint $table) {
            $table->dropForeign(['payment_account_shop_id']);
            $table->dropColumn('payment_account_shop_id');
        });
        Schema::table('order_payment_api_points', function (Blueprint $table) {
            $table->dropForeign(['payment_account_shop_id']);
            $table->dropColumn('payment_account_shop_id');
        });
    }

    public function down(): void
    {
        Schema::table('top_up_payment_api_points', function (Blueprint $table) {
            $table->unsignedInteger('payment_account_shop_id')->nullable();
            $table->foreign('payment_account_shop_id')->references('id')->on('payment_account_shop');
        });
        Schema::table('order_payment_api_points', function (Blueprint $table) {
            $table->unsignedInteger('payment_account_shop_id')->nullable();
            $table->foreign('payment_account_shop_id')->references('id')->on('payment_account_shop');
        });
    }
};
