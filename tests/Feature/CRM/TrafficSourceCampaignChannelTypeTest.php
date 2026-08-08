<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\CRM\TrafficSource\ReceiveTrafficSourceCostWebhook;
use App\Models\CRM\TrafficSourceCampaign;
use App\Models\CRM\TrafficSourceCost;
use Illuminate\Support\Facades\Artisan;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    Artisan::call('migrate');

    list(
        $this->organisation,
        $this->user,
        $this->shop
    ) = createShop();

    $this->googleAds = createTrafficSource($this->shop, 'google-ads', 'Google Ads');

    $this->token = $this->shop->createToken('ads-script', [ReceiveTrafficSourceCostWebhook::ABILITY])->plainTextToken;

    TrafficSourceCost::where('shop_id', $this->shop->id)->delete();
});

function postCosts(array $costs, string $token): \Illuminate\Testing\TestResponse
{
    return test()->withHeaders(['Authorization' => 'Bearer '.$token])
        ->postJson(route('webhooks.traffic_source_costs'), [
            'source'   => 'google-ads',
            'currency' => test()->shop->currency->code,
            'costs'    => $costs,
        ]);
}

it('labels a campaign with the channel type the ad platform reports', function () {
    $reference = 'ref-'.uniqid();

    postCosts([[
        'date'          => '2026-08-07',
        'campaign'      => $reference,
        'campaign_name' => 'Brand Video Push',
        'channel_type'  => 'VIDEO',
        'amount'        => 42.50,
    ]], $this->token)->assertOk();

    expect(TrafficSourceCampaign::where('reference', $reference)->first()->channel_type)->toBe('VIDEO');
});

it('fills in the channel type of a campaign that was created by a click', function () {
    $campaign = TrafficSourceCampaign::create([
        'traffic_source_id' => $this->googleAds->id,
        'reference'         => 'ref-'.uniqid(),
        'name'              => 'Known from a gclid only',
        'type'              => 'google-ads',
    ]);

    expect($campaign->channel_type)->toBeNull();

    postCosts([[
        'date'         => '2026-08-07',
        'campaign'     => $campaign->reference,
        'channel_type' => 'PERFORMANCE_MAX',
        'amount'       => 10.00,
    ]], $this->token)->assertOk();

    expect($campaign->refresh()->channel_type)->toBe('PERFORMANCE_MAX');
});

it('keeps the spend on the same channel the touch history knows, whatever the label says', function () {
    $reference = 'ref-'.uniqid();

    postCosts([[
        'date'         => '2026-08-07',
        'campaign'     => $reference,
        'channel_type' => 'VIDEO',
        'amount'       => 99.00,
    ]], $this->token)->assertOk();

    $campaign = TrafficSourceCampaign::where('reference', $reference)->first();

    expect($campaign->traffic_source_id)->toBe($this->googleAds->id)
        ->and($campaign->type)->toBe('google-ads')
        ->and((float) TrafficSourceCost::where('traffic_source_campaign_id', $campaign->id)->first()->source_amount)->toBe(99.00);
});

it('still accepts a script that has not been updated to send a channel type', function () {
    $reference = 'ref-'.uniqid();

    postCosts([[
        'date'     => '2026-08-07',
        'campaign' => $reference,
        'amount'   => 5.00,
    ]], $this->token)->assertOk();

    expect(TrafficSourceCampaign::where('reference', $reference)->first()->channel_type)->toBeNull();
});
