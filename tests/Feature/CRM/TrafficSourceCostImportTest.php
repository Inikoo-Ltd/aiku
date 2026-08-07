<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 06 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

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
    $this->metaAds   = createTrafficSource($this->shop, 'meta-ads', 'Meta Ads');

    TrafficSourceCost::where('shop_id', $this->shop->id)->delete();
});

function costCsvFile(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'costs').'.csv';
    file_put_contents($path, "shop,source,campaign,date,amount,currency\n".implode("\n", $rows)."\n");

    return $path;
}

function importCosts(array $rows, array $options = []): int
{
    return Artisan::call('traffic-source:import-costs', array_merge(
        ['file' => costCsvFile($rows)],
        $options
    ));
}

it('imports spend from a csv', function () {
    $currency = $this->shop->currency->code;

    $exit = importCosts([
        "{$this->shop->slug},google-ads,,2026-08-01,153.22,{$currency}",
        "{$this->shop->slug},meta-ads,,2026-08-01,88.40,{$currency}",
    ]);

    expect($exit)->toBe(0);

    $costs = TrafficSourceCost::where('shop_id', $this->shop->id)->get();
    expect($costs)->toHaveCount(2);
    expect((float) $costs->firstWhere('traffic_source_id', $this->googleAds->id)->source_amount)->toBe(153.22);
});

it('matches a campaign by the reference the touch history already stores', function () {
    $campaign = TrafficSourceCampaign::create([
        'traffic_source_id' => $this->googleAds->id,
        'reference'         => 'ref-'.uniqid(),
        'name'              => 'August Push',
        'type'              => 'search',
    ]);

    importCosts([
        "{$this->shop->slug},google-ads,{$campaign->reference},2026-08-01,25.00,{$this->shop->currency->code}",
    ]);

    $cost = TrafficSourceCost::where('shop_id', $this->shop->id)->first();
    expect($cost->traffic_source_campaign_id)->toBe($campaign->id);
});

it('rejects the row and reports failure when the traffic source is unknown', function () {
    $exit = importCosts([
        "{$this->shop->slug},not-a-real-source,,2026-08-01,10.00,{$this->shop->currency->code}",
    ]);

    expect($exit)->toBe(1);
    expect(TrafficSourceCost::where('shop_id', $this->shop->id)->count())->toBe(0);
});

it('rejects a bad row without discarding the good ones around it', function () {
    $currency = $this->shop->currency->code;

    $exit = importCosts([
        "{$this->shop->slug},google-ads,,2026-08-01,10.00,{$currency}",
        "{$this->shop->slug},nonsense,,2026-08-01,10.00,{$currency}",
        "{$this->shop->slug},meta-ads,,2026-08-01,20.00,{$currency}",
    ]);

    expect($exit)->toBe(1);
    expect(TrafficSourceCost::where('shop_id', $this->shop->id)->count())->toBe(2);
});

it('writes nothing in dry run mode', function () {
    $exit = importCosts(
        ["{$this->shop->slug},google-ads,,2026-08-01,10.00,{$this->shop->currency->code}"],
        ['--dry-run' => true]
    );

    expect($exit)->toBe(0);
    expect(TrafficSourceCost::where('shop_id', $this->shop->id)->count())->toBe(0);
});

it('is safe to import the same report twice', function () {
    $row = "{$this->shop->slug},google-ads,,2026-08-01,153.22,{$this->shop->currency->code}";

    importCosts([$row]);
    importCosts([$row]);

    expect(TrafficSourceCost::where('shop_id', $this->shop->id)->count())->toBe(1);
});

it('fails clearly when the header is missing a column', function () {
    $path = tempnam(sys_get_temp_dir(), 'costs').'.csv';
    file_put_contents($path, "shop,source,date,amount\nuk,google-ads,2026-08-01,10\n");

    expect(Artisan::call('traffic-source:import-costs', ['file' => $path]))->toBe(1);
});
