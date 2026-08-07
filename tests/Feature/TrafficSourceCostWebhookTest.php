<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Catalogue\Shop\StoreShop;
use App\Actions\CRM\TrafficSource\ReceiveTrafficSourceCostWebhook;
use App\Models\Catalogue\Shop;
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

    TrafficSourceCost::where('shop_id', $this->shop->id)->delete();

    $this->token = $this->shop->createToken('Test Agency', [ReceiveTrafficSourceCostWebhook::ABILITY])->plainTextToken;
});

function postCosts(string $token, array $payload)
{
    return test()->withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson(route('webhooks.traffic_source_costs'), $payload);
}

function costPayload(array $overrides = []): array
{
    return array_merge([
        'source'   => 'google-ads',
        'currency' => test()->shop->currency->code,
        'costs'    => [
            ['date' => '2026-08-06', 'campaign' => '21723300927', 'campaign_name' => 'Brand', 'amount' => 153.22],
        ],
    ], $overrides);
}

it('stores spend posted with a valid token', function () {
    $response = postCosts($this->token, costPayload());

    $response->assertOk()->assertJsonPath('stored', 1);

    $cost = TrafficSourceCost::where('shop_id', $this->shop->id)->first();
    expect((float) $cost->source_amount)->toBe(153.22)
        ->and($cost->traffic_source_id)->toBe($this->googleAds->id);

    $campaign = TrafficSourceCampaign::find($cost->traffic_source_campaign_id);
    expect($campaign->reference)->toBe('21723300927')
        ->and($campaign->name)->toBe('Brand');
});

it('rejects a request with no token or a made up one', function () {
    postCosts('', costPayload())->assertForbidden();
    postCosts('999|nonsense', costPayload())->assertForbidden();

    expect(TrafficSourceCost::where('shop_id', $this->shop->id)->count())->toBe(0);
});

it('rejects a token belonging to another shop', function () {
    $otherShop  = StoreShop::run($this->organisation, Shop::factory()->definition());
    $otherToken = $otherShop->createToken('Other Agency', [ReceiveTrafficSourceCostWebhook::ABILITY])->plainTextToken;

    postCosts($otherToken, costPayload(['shop' => $this->shop->slug]))->assertForbidden();

    expect(TrafficSourceCost::where('shop_id', $this->shop->id)->count())->toBe(0);
});

it('rejects a shop token minted for something other than costs', function () {
    $wrongAbility = $this->shop->createToken('Reporting', ['reports'])->plainTextToken;

    postCosts($wrongAbility, costPayload())->assertForbidden();
});

it('does not double count when the same day is posted twice', function () {
    postCosts($this->token, costPayload())->assertOk();
    postCosts($this->token, costPayload())->assertOk();

    $costs = TrafficSourceCost::where('shop_id', $this->shop->id)->get();
    expect($costs)->toHaveCount(1)
        ->and((float) $costs->first()->source_amount)->toBe(153.22);
});

it('updates the day when a corrected figure is posted', function () {
    postCosts($this->token, costPayload())->assertOk();
    postCosts($this->token, costPayload([
        'costs' => [['date' => '2026-08-06', 'campaign' => '21723300927', 'amount' => 160.00]],
    ]))->assertOk();

    $costs = TrafficSourceCost::where('shop_id', $this->shop->id)->get();
    expect($costs)->toHaveCount(1)
        ->and((float) $costs->first()->source_amount)->toBe(160.00);
});

it('rejects a malformed payload without writing anything', function () {
    postCosts($this->token, ['source' => 'google-ads'])->assertStatus(422);
    postCosts($this->token, costPayload(['source' => 'not-a-source']))->assertStatus(422);
    postCosts($this->token, costPayload([
        'costs' => [['date' => '06/08/2026', 'amount' => 'lots']],
    ]))->assertStatus(422);

    expect(TrafficSourceCost::where('shop_id', $this->shop->id)->count())->toBe(0);
});

it('rejects an unknown currency', function () {
    postCosts($this->token, costPayload(['currency' => 'ZZZ']))->assertStatus(422);

    expect(TrafficSourceCost::where('shop_id', $this->shop->id)->count())->toBe(0);
});
