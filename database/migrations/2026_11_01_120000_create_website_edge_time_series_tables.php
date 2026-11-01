<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 01 Nov 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasTimeSeries;
use App\Stubs\Migrations\HasTimeSeriesRecords;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasTimeSeries;
    use HasTimeSeriesRecords;

    public function up(): void
    {
        Schema::create('website_edge_time_series', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('website_id')->index();
            $table->foreign('website_id')->references('id')->on('websites')->onDelete('cascade');
            $this->getTimeSeriesFields($table);
            $table->unique(['website_id', 'frequency']);
        });

        Schema::create('website_edge_time_series_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('website_edge_time_series_id');
            $table->foreign('website_edge_time_series_id')->references('id')->on('website_edge_time_series')->onDelete('cascade');
            $table->char('frequency', 1);
            $table->string('period');

            $table->unsignedInteger('requests')->default(0);
            $table->unsignedInteger('requests_hit')->default(0);
            $table->unsignedInteger('requests_miss')->default(0);
            $table->unsignedInteger('requests_expired')->default(0);
            $table->unsignedInteger('requests_dynamic')->default(0);
            $table->unsignedInteger('requests_bypass')->default(0);
            $table->unsignedInteger('requests_none')->default(0);
            $table->unsignedInteger('requests_other_cache_status')->default(0);
            $table->unsignedBigInteger('origin_response_ms_total')->default(0);
            $table->unsignedInteger('requests_2xx')->default(0);
            $table->unsignedInteger('requests_3xx')->default(0);
            $table->unsignedInteger('requests_4xx')->default(0);
            $table->unsignedInteger('requests_5xx')->default(0);
            $table->unsignedInteger('requests_google_bot')->default(0);
            $table->unsignedInteger('requests_bing_bot')->default(0);
            $table->unsignedInteger('requests_apple_bot')->default(0);
            $table->unsignedInteger('requests_other_bots')->default(0);
            $table->unsignedInteger('requests_unknown_agent')->default(0);
            $table->unsignedInteger('requests_browsers')->default(0);
            $table->unsignedInteger('requests_far_countries')->default(0);
            $table->unsignedInteger('requests_far_countries_hit')->default(0);

            $this->getTimeSeriesRecordsField($table);
            $table->unique(['website_edge_time_series_id', 'period', 'frequency'], 'website_edge_time_series_records_nk_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_edge_time_series_records');
        Schema::dropIfExists('website_edge_time_series');
    }
};
