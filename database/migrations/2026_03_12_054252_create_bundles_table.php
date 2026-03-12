<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('bundles', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('customer_id');
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->unsignedBigInteger('customer_sales_channel_id');
            $table->foreign('customer_sales_channel_id')->references('id')->on('customer_sales_channels');

            $table->morphs('bundleable');
            $table->boolean('has_valid_platform_product_id')->default(false);
            $table->boolean('exist_in_platform')->default(false);
            $table->boolean('platform_status')->default(false);
            $table->json('data');
            $table->json('settings');

            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('bundles');
    }
};
