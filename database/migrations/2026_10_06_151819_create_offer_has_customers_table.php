<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Fri, 02 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('offer_has_customers', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->unsignedInteger('offer_id')->index();
            $table->foreign('offer_id')->references('id')->on('offers')->cascadeOnDelete();
            $table->unsignedInteger('customer_id')->index();
            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
            $table->string('code')->nullable();
            $table->string('voucher')->nullable();
            $table->timestampsTz();
            $table->unique(['offer_id', 'customer_id']);
            $table->unique(['shop_id', 'voucher']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_has_customers');
    }
};
