<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 18 May 2025 13:58:06 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('customer_sales_channel_id')->nullable()->index();
            $table->foreign('customer_sales_channel_id')->references('id')->on('customer_sales_channels')->nullOnDelete();
        });

        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->unsignedBigInteger('platform_id')->nullable()->index();
            $table->foreign('platform_id')->references('id')->on('platforms')->nullOnDelete();
            $table->unsignedBigInteger('customer_sales_channel_id')->nullable()->index();
            $table->foreign('customer_sales_channel_id')->references('id')->on('customer_sales_channels')->nullOnDelete();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('platform_id')->nullable()->index();
            $table->foreign('platform_id')->references('id')->on('platforms')->nullOnDelete();
            $table->unsignedBigInteger('customer_sales_channel_id')->nullable()->index();
            $table->foreign('customer_sales_channel_id')->references('id')->on('customer_sales_channels')->nullOnDelete();
        });

        Schema::table('pallet_returns', function (Blueprint $table) {
            $table->unsignedBigInteger('customer_sales_channel_id')->nullable()->index();
            $table->foreign('customer_sales_channel_id')->references('id')->on('customer_sales_channels')->nullOnDelete();
        });

        Schema::table('customer_clients', function (Blueprint $table) {
            $table->unsignedBigInteger('customer_sales_channel_id')->nullable()->index();
           $table->foreign('customer_sales_channel_id')->references('id')->on('customer_sales_channels')->nullOnDelete();
        });

        Schema::table('portfolios', function (Blueprint $table) {
            $table->unsignedBigInteger('customer_sales_channel_id')->nullable()->index();
            $table->foreign('customer_sales_channel_id')->references('id')->on('customer_sales_channels')->nullOnDelete();
        });
    }


    public function down(): void
    {
        Schema::table('pallet_returns', function (Blueprint $table) {
            $table->dropForeign(['customer_sales_channel_id']);
            $table->dropColumn('customer_sales_channel_id');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['platform_id']);
            $table->dropForeign(['customer_sales_channel_id']);
            $table->dropColumn('platform_id');
            $table->dropColumn('customer_sales_channel_id');
        });

        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->dropForeign(['platform_id']);
            $table->dropForeign(['customer_sales_channel_id']);
            $table->dropColumn('platform_id');
            $table->dropColumn('customer_sales_channel_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['customer_sales_channel_id']);
            $table->dropColumn('customer_sales_channel_id');
        });

        Schema::table('customer_clients', function (Blueprint $table) {
            $table->dropForeign(['customer_sales_channel_id']);
            $table->dropColumn('customer_sales_channel_id');
        });

        Schema::table('portfolios', function (Blueprint $table) {
            $table->dropForeign(['customer_sales_channel_id']);
            $table->dropColumn('customer_sales_channel_id');
        });
    }
};
