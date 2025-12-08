<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopify_user_has_products', function (Blueprint $table) {
            $table->id();

            $table->unsignedSmallInteger('shopify_user_id');
            $table->foreign('shopify_user_id')->references('id')->on('shopify_users')->onDelete('cascade');

            $table->unsignedSmallInteger('product_id');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->unsignedBigInteger('shopify_product_id');

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopify_user_has_products');
    }
};
