<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wc_user_has_products', function (Blueprint $table) {
            $table->id();

            $table->unsignedSmallInteger('woo_commerce_user_id');
            $table->foreign('woo_commerce_user_id')->references('id')->on('woo_commerce_users')->onDelete('cascade');

            $table->unsignedSmallInteger('product_id');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->unsignedInteger('portfolio_id')->index()->after('product_id')->nullable();
            $table->foreign('portfolio_id')->references('id')->on('portfolios');

            $table->unsignedBigInteger('woo_commerce_product_id');

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wc_user_has_products');
    }
};
