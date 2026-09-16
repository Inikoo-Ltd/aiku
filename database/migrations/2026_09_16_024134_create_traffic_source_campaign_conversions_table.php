<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Google's conversion total for a day, split by the conversion action that recorded it, one row per
     * campaign, day, category and action name. This is what tells a purchase apart from a sign-up: the
     * campaign metrics row only carries the total. Google omits actions with nothing recorded, so a
     * day with no rows here is a day with no conversions.
     */
    public function up(): void
    {
        Schema::create('traffic_source_campaign_conversions', function (Blueprint $table) {
            $table->increments('id');

            $table->unsignedInteger('traffic_source_campaign_id');
            $table->foreign('traffic_source_campaign_id')->references('id')->on('traffic_source_campaigns')->cascadeOnDelete();

            $table->date('date');
            $table->string('category')->index();
            $table->string('action_name');

            $table->decimal('conversions', 16, 2)->default(0);
            $table->decimal('source_conversions_value', 16, 2)->default(0);
            $table->decimal('all_conversions', 16, 2)->default(0);
            $table->decimal('source_all_conversions_value', 16, 2)->default(0);
            $table->unsignedSmallInteger('source_currency_id')->nullable();
            $table->foreign('source_currency_id')->references('id')->on('currencies');

            $table->timestampsTz();

            $table->unique(['traffic_source_campaign_id', 'date', 'category', 'action_name'], 'tsc_conversions_day_action_unique');
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('traffic_source_campaign_conversions');
    }
};
