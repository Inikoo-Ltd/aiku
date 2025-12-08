<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tiktok_user_has_products', function (Blueprint $table) {
            $table->increments('id');

            $table->unsignedBigInteger('tiktok_user_id')->index();
            $table->foreign('tiktok_user_id')->references('id')->on('tiktok_users')->onDelete('cascade');

            $table->morphs('productable');
            $table->string('tiktok_product_id');

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiktok_user_has_products');
    }
};
