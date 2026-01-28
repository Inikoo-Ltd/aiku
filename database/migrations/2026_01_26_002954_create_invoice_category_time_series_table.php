<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

use App\Stubs\Migrations\HasTimeSeries;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasTimeSeries;

    public function up(): void
    {
        Schema::create('invoice_category_time_series', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('invoice_category_id');
            $table->foreign('invoice_category_id')->references('id')->on('invoice_categories')->onUpdate('cascade')->onDelete('cascade');
            $this->getTimeSeriesFields($table);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_category_time_series');
    }
};
