<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Catalogue\Product\Json\GetIrisProductTrends;
use App\Actions\Catalogue\Product\UpdateProduct;
use App\Actions\Catalogue\Shop\BreakShopPricesCache;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    loadDB();
    Cache::flush();
    $this->shop    = createShop()[2];
    $this->product = createProduct($this->shop)[1];

    $this->product->update([
        'has_live_webpage'   => true,
        'available_quantity' => 10,
        'price'              => 100,
        'is_for_sale'        => true,
    ]);

    $seriesId = DB::table('asset_time_series')->insertGetId([
        'asset_id'  => $this->product->asset_id,
        'shop_id'   => $this->shop->id,
        'frequency' => TimeSeriesFrequencyEnum::DAILY->value,
    ]);

    $this->recordSales = function (int $daysAgo, float $sales) use ($seriesId) {
        DB::table('asset_time_series_records')->insert([
            'asset_time_series_id' => $seriesId,
            'frequency'            => TimeSeriesFrequencyEnum::DAILY->singleLetter(),
            'sales_external'       => $sales,
            'sales_internal'       => 0,
            'period'               => now()->subDays($daysAgo)->format('Y-m-d'),
            'from'                 => now()->subDays($daysAgo)->startOfDay(),
            'to'                   => now()->subDays($daysAgo)->endOfDay(),
        ]);
    };

    $this->trendSales = function (string $period) {
        $products = GetIrisProductTrends::make()->handle($this->shop, ['period' => $period]);

        expect($products)->toHaveCount(1);

        return (float) $products->first()->trend_sales;
    };
});

test('the requested window only counts the sales that fall in it', function () {
    $before = ($this->trendSales)('3d');

    ($this->recordSales)(1, 30);
    ($this->recordSales)(40, 500);
    BreakShopPricesCache::run($this->shop->id);

    expect(($this->trendSales)('3d'))->toBe($before + 30);
});

test('a wider window also counts the older sales', function () {
    $before = ($this->trendSales)('1q');

    ($this->recordSales)(1, 30);
    ($this->recordSales)(40, 500);
    BreakShopPricesCache::run($this->shop->id);

    expect(($this->trendSales)('1q'))->toBe($before + 530);
});

test('the ranking is served from cache until the shop prices change', function () {
    $before = ($this->trendSales)('3d');

    ($this->recordSales)(1, 30);

    expect(($this->trendSales)('3d'))->toBe($before);

    BreakShopPricesCache::run($this->shop->id);

    expect(($this->trendSales)('3d'))->toBe($before + 30);
});

test('stock is read live rather than from the cached ranking', function () {
    ($this->trendSales)('3d');

    $this->product->update(['available_quantity' => 0]);

    $products = GetIrisProductTrends::make()->handle($this->shop, ['period' => '3d']);

    expect($products)->toHaveCount(0);
});

test('editing a product price breaks the cache of its shop', function () {
    $generation = BreakShopPricesCache::make()->getGeneration($this->shop->id);

    UpdateProduct::make()->action($this->product, ['price' => 123]);

    expect(BreakShopPricesCache::make()->getGeneration($this->shop->id))->toBeGreaterThan($generation);
});
