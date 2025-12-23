<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

use App\Stubs\Migrations\HasTimeSeriesRecords;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasTimeSeriesRecords;

    public function up(): void
    {
        Schema::create('product_category_time_series_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('product_category_time_series_id')->index();
            $table->foreign('product_category_time_series_id')->references('id')->on('product_category_time_series')->onUpdate('cascade')->onDelete('cascade');
            $table->decimal('sales')->default(0);
            $table->decimal('sales_org_currency')->default(0);
            $table->decimal('sales_grp_currency')->default(0);
            $table->integer('invoices')->default(0);
            $table->integer('refunds')->default(0);
            $table->integer('orders')->default(0);
            $table->integer('delivery_notes')->default(0);
            $table->integer('customers_invoiced');
            $this->getTimeSeriesRecordsField($table);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_category_time_series_records');
    }
};
