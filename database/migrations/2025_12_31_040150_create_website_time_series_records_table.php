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
        Schema::create('website_time_series_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('website_time_series_id')->index();
            $table->foreign('website_time_series_id')->references('id')->on('website_time_series')->onUpdate('cascade')->onDelete('cascade');

            $table->unsignedInteger('visitors')->default(0);
            $table->unsignedInteger('sessions')->default(0);
            $table->unsignedInteger('page_views')->default(0);
            $table->unsignedInteger('avg_session_duration')->default(0);
            $table->decimal('bounce_rate', 5, 2)->default(0);
            $table->decimal('pages_per_session', 5, 2)->default(0);
            $table->unsignedInteger('new_visitors')->default(0);
            $table->unsignedInteger('returning_visitors')->default(0);
            $table->unsignedInteger('visitors_desktop')->default(0);
            $table->unsignedInteger('visitors_mobile')->default(0);
            $table->unsignedInteger('visitors_tablet')->default(0);

            $this->getTimeSeriesRecordsField($table);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_time_series_records');
    }
};
