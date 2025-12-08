<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopify_user_has_fulfilments', function (Blueprint $table) {
            $table->id();

            $table->unsignedSmallInteger('shopify_user_id');
            $table->foreign('shopify_user_id')->references('id')->on('shopify_users')->onDelete('cascade');

            $table->unsignedSmallInteger('order_id');
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');

            $table->unsignedBigInteger('shopify_fulfilment_id')->nullable();
            $table->unsignedBigInteger('shopify_order_id')->nullable();

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopify_user_has_orders');
    }
};
