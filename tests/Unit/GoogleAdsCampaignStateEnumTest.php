<?php

/*
 * Created: Mon, 23 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026
 */

use App\Enums\CRM\TrafficSource\GoogleAdsCampaignStateEnum;
use App\Models\CRM\TrafficSourceCampaign;

test('a campaign Google never reported on is not synced, not paused', function (?string $status) {
    expect(GoogleAdsCampaignStateEnum::fromGoogleStatus($status))->toBe(GoogleAdsCampaignStateEnum::NOT_SYNCED);
})->with([null, '']);

test('Google statuses map to serving or paused', function (string $status, GoogleAdsCampaignStateEnum $state) {
    expect(GoogleAdsCampaignStateEnum::fromGoogleStatus($status))->toBe($state);
})->with([
    ['ELIGIBLE', GoogleAdsCampaignStateEnum::PUBLISHED_SERVING],
    ['ENABLED', GoogleAdsCampaignStateEnum::PUBLISHED_SERVING],
    ['LIMITED', GoogleAdsCampaignStateEnum::PUBLISHED_SERVING],
    ['PAUSED', GoogleAdsCampaignStateEnum::PUBLISHED_PAUSED],
    ['NOT_ELIGIBLE', GoogleAdsCampaignStateEnum::PUBLISHED_PAUSED],
    ['ENDED', GoogleAdsCampaignStateEnum::PUBLISHED_PAUSED],
]);

test('google ads campaign state labels and icons cover every case', function () {
    $values = array_column(GoogleAdsCampaignStateEnum::cases(), 'value');

    expect(array_keys(GoogleAdsCampaignStateEnum::labels()))->toEqualCanonicalizing($values)
        ->and(array_keys(GoogleAdsCampaignStateEnum::stateIcon()))->toEqualCanonicalizing($values);
});

test('the timeline leaves out the not synced state', function () {
    expect(GoogleAdsCampaignStateEnum::timelineCases())->not->toContain(GoogleAdsCampaignStateEnum::NOT_SYNCED);
});

test('a campaign created from a click or a cost row starts not synced', function () {
    expect((new TrafficSourceCampaign())->state)->toBe(GoogleAdsCampaignStateEnum::NOT_SYNCED);
});
