<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Wed, 26 Nov 2025 14:17:09 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('invoice_category_sales_metrics', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('group_id');
            $table->foreign('group_id')->references('id')->on('groups')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedSmallInteger('organisation_id');
            $table->foreign('organisation_id')->references('id')->on('organisations')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedSmallInteger('invoice_category_id');
            $table->foreign('invoice_category_id')->references('id')->on('invoice_categories')->onUpdate('cascade')->onDelete('cascade');

            $table->date('date')->index();

            $table->unsignedBigInteger('invoices')->default(0);
            $table->unsignedBigInteger('refunds')->default(0);

            $table->decimal('sales_grp_currency', 16)->default(0.00);
            $table->decimal('sales_invoice_category_currency', 16)->default(0.00);
            $table->decimal('revenue_grp_currency', 16)->default(0.00);
            $table->decimal('revenue_invoice_category_currency', 16)->default(0.00);
            $table->decimal('lost_revenue_grp_currency', 16)->default(0.00);
            $table->decimal('lost_revenue_invoice_category_currency', 16)->default(0.00);

            $table->timestampsTz();

            $table->unique(['invoice_category_id', 'date'], 'invoice_category_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_category_sales_metrics');
    }
};
