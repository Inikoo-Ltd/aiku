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
        Schema::create('offer_campaign_time_series', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('offer_campaign_id');
            $table->foreign('offer_campaign_id')->references('id')->on('offer_campaigns')->onUpdate('cascade')->onDelete('cascade');
            $this->getTimeSeriesFields($table);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_campaign_time_series');
    }
};
