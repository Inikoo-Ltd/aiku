<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 17 May 2025 22:05:41 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {

        Schema::table('customer_sales_channels', function (Blueprint $table) {
            $table->string('platform_user_type')->nullable()->index();
            $table->unsignedInteger('platform_user_id')->nullable();
            $table->index(['platform_user_type', 'platform_user_id','customer_id']);
        });

        Schema::table('shopify_users', function (Blueprint $table) {
            $table->dropColumn('source_id');
            $table->unsignedBigInteger('platform_id')->nullable()->index();
            $table->foreign('platform_id')->references('id')->on('platforms')->nullOnDelete();
            $table->unsignedBigInteger('customer_sales_channel_id')->nullable()->index();
            $table->foreign('customer_sales_channel_id')->references('id')->on('customer_sales_channels')->nullOnDelete();

        });


        Schema::table('tiktok_users', function (Blueprint $table) {

            $table->dropColumn('source_id');
            $table->unsignedBigInteger('platform_id')->nullable()->index();
            $table->foreign('platform_id')->references('id')->on('platforms')->nullOnDelete();
            $table->unsignedBigInteger('customer_sales_channel_id')->nullable()->index();
            $table->foreign('customer_sales_channel_id')->references('id')->on('customer_sales_channels')->nullOnDelete();
        });




        Schema::table('woo_commerce_users', function (Blueprint $table) {

            $table->unsignedBigInteger('platform_id')->nullable()->index();
            $table->foreign('platform_id')->references('id')->on('platforms')->nullOnDelete();
            $table->unsignedBigInteger('customer_sales_channel_id')->nullable()->index();
            $table->foreign('customer_sales_channel_id')->references('id')->on('customer_sales_channels')->nullOnDelete();
        });


    }


    public function down(): void
    {
        Schema::table('woo_commerce_users', function (Blueprint $table) {
            $table->dropForeign(['platform_id']);
            $table->dropForeign(['customer_sales_channel_id']);
            $table->dropColumn('platform_id');
            $table->dropColumn('customer_sales_channel_id');
        });

        Schema::table('tiktok_users', function (Blueprint $table) {
            $table->dropForeign(['platform_id']);
            $table->dropForeign(['customer_sales_channel_id']);
            $table->dropColumn('platform_id');
            $table->dropColumn('customer_sales_channel_id');
        });

        Schema::table('shopify_users', function (Blueprint $table) {
            $table->dropForeign(['platform_id']);
            $table->dropForeign(['customer_sales_channel_id']);
            $table->dropColumn('platform_id');
            $table->dropColumn('customer_sales_channel_id');
        });

        Schema::table('customer_sales_channels', function (Blueprint $table) {
            $table->dropIndex(['platform_user_type', 'platform_user_id','customer_id']);
            $table->dropColumn('platform_user_type');
            $table->dropColumn('platform_user_id');
        });
    }
};
