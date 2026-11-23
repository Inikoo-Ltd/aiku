<?php

/*
 * Created: Mon, 23 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026
 */

use App\Enums\CRM\TrafficSource\GoogleAdsCampaignStateEnum;
use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        DB::statement(
            "UPDATE traffic_source_campaigns
                SET state = ?
              WHERE state = ?
                AND in_process_at IS NULL
                AND COALESCE(data->>'primary_status', data->>'status') IS NULL
                AND traffic_source_id IN (SELECT id FROM traffic_sources WHERE type = ?)",
            [
                GoogleAdsCampaignStateEnum::NOT_SYNCED->value,
                GoogleAdsCampaignStateEnum::PUBLISHED_PAUSED->value,
                TrafficSourcesTypeEnum::GOOGLE_ADS->value,
            ]
        );
    }

    public function down(): void
    {
        DB::table('traffic_source_campaigns')
            ->where('state', GoogleAdsCampaignStateEnum::NOT_SYNCED->value)
            ->update(['state' => GoogleAdsCampaignStateEnum::PUBLISHED_PAUSED->value]);
    }
};
