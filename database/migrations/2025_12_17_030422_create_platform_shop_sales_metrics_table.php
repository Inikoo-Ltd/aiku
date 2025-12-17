<?php

/*
 * Author: Steven Wicca <stewicalf@gmail.com>
 * Created: Tue, 17 Dec 2025 11:04:22 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('platform_shop_sales_metrics', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('group_id');
            $table->foreign('group_id')->references('id')->on('groups')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedSmallInteger('organisation_id');
            $table->foreign('organisation_id')->references('id')->on('organisations')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedSmallInteger('platform_id');
            $table->foreign('platform_id')->references('id')->on('platforms')->onUpdate('cascade')->onDelete('cascade');

            $table->date('date')->index();

            $table->unsignedBigInteger('invoices')->default(0);
            $table->unsignedBigInteger('new_channels')->default(0);
            $table->unsignedBigInteger('new_customers')->default(0);
            $table->unsignedBigInteger('new_portfolios')->default(0);
            $table->unsignedBigInteger('new_customer_client')->default(0);

            $table->decimal('sales', 16)->default(0.00);
            $table->decimal('sales_grp_currency', 16)->default(0.00);
            $table->decimal('sales_org_currency', 16)->default(0.00);

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_shop_sales_metrics');
    }
};
