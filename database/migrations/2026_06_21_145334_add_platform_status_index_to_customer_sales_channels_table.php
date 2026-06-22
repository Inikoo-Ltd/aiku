<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('customer_sales_channels', function (Blueprint $table) {
            $table->index('platform_status');
        });
    }


    public function down(): void
    {
        Schema::table('customer_sales_channels', function (Blueprint $table) {
            $table->dropIndex(['platform_status']);
        });
    }
};
