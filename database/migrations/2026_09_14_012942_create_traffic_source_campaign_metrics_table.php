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
     * What Google itself reports for a campaign on a given day, one row per campaign per day.
     *
     * These figures used to live in a JSON blob on the campaign row, holding the last 30 days and
     * overwritten by every nightly fetch. That answers "how is this campaign doing right now" and
     * nothing else: no comparison against last month, no trend, no way to add up a channel across
     * campaigns in SQL, and every night the history that had accumulated was thrown away.
     *
     * Money stays in the account's own currency here. Spend is converted to shop, organisation and
     * group currency by traffic_source_costs, which is the one place ROAS reads from; keeping a
     * second converted copy would mean two numbers for one fact and an argument about which is right.
     */
    public function up(): void
    {
        Schema::create('traffic_source_campaign_metrics', function (Blueprint $table) {
            $table->increments('id');

            $table->unsignedInteger('traffic_source_campaign_id');
            $table->foreign('traffic_source_campaign_id')->references('id')->on('traffic_source_campaigns')->cascadeOnDelete();

            $table->date('date');

            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);

            /* Google counts a conversion fractionally when it splits credit across several ads, so
               this is not a whole number even though it counts events. */
            $table->decimal('conversions', 16, 2)->default(0);

            /* Both in the account's currency, named `source_` for the same reason
               traffic_source_costs does: the figure the advertiser was actually billed, before any
               exchange rate this application chose to apply. */
            $table->decimal('source_cost', 16, 2)->default(0);
            $table->decimal('source_conversions_value', 16, 2)->default(0);
            $table->unsignedSmallInteger('source_currency_id')->nullable();
            $table->foreign('source_currency_id')->references('id')->on('currencies');

            $table->timestampsTz();

            /* A day re-fetched is a day corrected, never a day added again: Google keeps attributing
               conversions to a date for weeks after it closes, so the last read of a day wins. */
            $table->unique(['traffic_source_campaign_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('traffic_source_campaign_metrics');
    }
};
