<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

use App\Stubs\Migrations\HasTimeSeries;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasTimeSeries;

    public function up(): void
    {
        Schema::create('collection_time_series', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('collection_id')->index();
            $table->foreign('collection_id')->references('id')->on('collections')->onUpdate('cascade')->onDelete('cascade');
            $this->getTimeSeriesFields($table);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_time_series');
    }
};
