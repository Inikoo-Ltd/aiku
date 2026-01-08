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
        Schema::create('collection_time_series_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('collection_time_series_id')->index();
            $table->foreign('collection_time_series_id')->references('id')->on('collection_time_series')->onUpdate('cascade')->onDelete('cascade');
            $this->getTimeSeriesRecordsSalesField($table);
            $this->getTimeSeriesRecordsOrderingField($table);
            $this->getTimeSeriesRecordsField($table);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_time_series_records');
    }
};
