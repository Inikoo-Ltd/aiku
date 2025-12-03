<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Fri, 28 Nov 2025 16:23:20 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('platform_sales_metrics', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('platform_id');
            $table->foreign('platform_id')->references('id')->on('platforms')->onUpdate('cascade')->onDelete('cascade');

            $table->date('date')->index();

            $table->unsignedBigInteger('invoices')->default(0);
            $table->unsignedBigInteger('new_channels')->default(0);
            $table->unsignedBigInteger('new_customers')->default(0);
            $table->unsignedBigInteger('new_portfolios')->default(0);
            $table->unsignedBigInteger('new_customer_client')->default(0);

            $table->decimal('sales_grp_currency', 16)->default(0.00);

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_sales_metrics');
    }
};
