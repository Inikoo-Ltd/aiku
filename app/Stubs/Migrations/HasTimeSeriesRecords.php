<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Stubs\Migrations;

use Illuminate\Database\Schema\Blueprint;

trait HasTimeSeriesRecords
{
    public function getTimeSeriesRecordsField(Blueprint $table): Blueprint
    {
        $table->dateTimeTz('from')->nullable()->index();
        $table->dateTimeTz('to')->nullable()->index();
        $table->timestampsTz();

        return $table;
    }

    public function getTimeSeriesRecordsSalesField(Blueprint $table): Blueprint
    {
        $table->decimal('sales')->default(0);
        $table->decimal('sales_org_currency')->default(0);
        $table->decimal('sales_grp_currency')->default(0);

        return $table;
    }

    public function getTimeSeriesRecordsOrderingField(Blueprint $table): Blueprint
    {
        $table->integer('invoices')->default(0);
        $table->integer('refunds')->default(0);
        $table->integer('orders')->default(0);
        $table->integer('delivery_notes')->default(0);
        $table->integer('customers_invoiced');

        return $table;
    }
}
