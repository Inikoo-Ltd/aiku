<?php

/*
 * Author: Oggie Sutrisna
 * Created: Sun, 22 Dec 2025 11:45:00 Makassar Time
 * Description: Add platform_id and customer_sales_channel_id to returns table
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('returns', function (Blueprint $table) {
            $table->unsignedInteger('platform_id')->nullable()->index()->after('customer_client_id');
            $table->foreign('platform_id')->references('id')->on('platforms')->nullOnDelete();
            $table->unsignedBigInteger('customer_sales_channel_id')->nullable()->index()->after('platform_id');
            $table->foreign('customer_sales_channel_id')->references('id')->on('customer_sales_channels')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('returns', function (Blueprint $table) {
            $table->dropForeign(['customer_sales_channel_id']);
            $table->dropColumn('customer_sales_channel_id');
            $table->dropForeign(['platform_id']);
            $table->dropColumn('platform_id');
        });
    }
};
