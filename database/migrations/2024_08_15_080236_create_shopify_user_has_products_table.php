<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('shopify_user_has_products', function (Blueprint $table) {
            $table->id();
            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('shopify_user_has_products');
    }
};
