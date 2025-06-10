<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('webpage_has_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('webpage_id')->index();
            $table->foreign('webpage_id')->references('id')->on('webpages');
            $table->unsignedInteger('product_id')->index();
            $table->foreign('product_id')->references('id')->on('products');
            $table->string('type')->index();
            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('webpage_has_products');
    }
};
