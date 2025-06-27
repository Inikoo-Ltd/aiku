<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Jun 2025 08:41:06 British Summer Time, Sheffield, United Kingdom
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('magento_users', function (Blueprint $table) {
            $table->string('username', 255)->index();
            $table->string('password', 255);
            $table->unsignedBigInteger('platform_id')->nullable()->index();
            $table->foreign('platform_id')->references('id')->on('platforms')->nullOnDelete();
            $table->unsignedBigInteger('customer_sales_channel_id')->nullable()->index();
            $table->foreign('customer_sales_channel_id')->references('id')->on('customer_sales_channels')->nullOnDelete();

        });
    }


    public function down(): void
    {
        Schema::table('magento_users', function (Blueprint $table) {
            $table->dropColumn('username');
            $table->dropColumn('password');
            $table->dropForeign(['platform_id']);
            $table->dropForeign(['customer_sales_channel_id']);
            $table->dropIndex(['platform_id']);
            $table->dropIndex(['customer_sales_channel_id']);
            $table->dropColumn('platform_id');
            $table->dropColumn('customer_sales_channel_id');
        });
    }
};
