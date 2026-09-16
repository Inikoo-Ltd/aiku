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
     * Impression share columns hold Google's daily ratio between 0 and 1. Google reports anything
     * under 10% as 0.0999 and anything over 90% as 0.9001, and returns nothing for campaign types
     * other than Search and Shopping, which is why they are nullable. A period figure is not the
     * average of the days: it is weighted by eligible impressions, derived as impressions divided
     * by search_impression_share, wherever it is read.
     */
    private const array IMPRESSION_SHARE_COLUMNS = [
        'search_impression_share',
        'search_rank_lost_impression_share',
        'search_budget_lost_impression_share',
        'search_top_impression_share',
        'search_rank_lost_top_impression_share',
        'search_budget_lost_top_impression_share',
        'search_absolute_top_impression_share',
        'search_rank_lost_absolute_top_impression_share',
        'search_budget_lost_absolute_top_impression_share',
    ];

    public function up(): void
    {
        Schema::table('traffic_source_campaign_metrics', function (Blueprint $table) {
            $table->decimal('all_conversions', 16, 2)->default(0);
            $table->decimal('source_all_conversions_value', 16, 2)->default(0);

            foreach (self::IMPRESSION_SHARE_COLUMNS as $column) {
                $table->decimal($column, 6, 4)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('traffic_source_campaign_metrics', function (Blueprint $table) {
            $table->dropColumn(array_merge(
                ['all_conversions', 'source_all_conversions_value'],
                self::IMPRESSION_SHARE_COLUMNS
            ));
        });
    }
};
