<?php

/*
 * Created: Mon, 23 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026
 */

use App\Actions\CRM\TrafficSourceCampaign\GoogleAds\RepairGoogleAdsGhostCampaigns;

test('a ghost id is owned by the campaign nearly all its clicks resolve to', function () {
    expect(RepairGoogleAdsGhostCampaigns::owningCampaignRef(['22530180876' => 95, '24318795792' => 5], '22540182691'))
        ->toBe('22530180876');
});

test('a ghost id split between campaigns is left undecided', function () {
    expect(RepairGoogleAdsGhostCampaigns::owningCampaignRef(['22530180876' => 60, '24318795792' => 40], '22540182691'))
        ->toBeNull();
});

test('a ghost id with too few resolved clicks is left undecided', function () {
    expect(RepairGoogleAdsGhostCampaigns::owningCampaignRef(['22530180876' => RepairGoogleAdsGhostCampaigns::MIN_RESOLVED_CLICKS - 1], '22540182691'))
        ->toBeNull();
});

test('an id whose clicks resolve to itself is a real campaign, not a ghost', function () {
    expect(RepairGoogleAdsGhostCampaigns::owningCampaignRef(['22540182691' => 50], '22540182691'))
        ->toBeNull();
});
