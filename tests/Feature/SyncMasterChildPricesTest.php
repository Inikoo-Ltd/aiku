<?php

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Masters\MasterAsset\StoreMasterAsset;
use App\Actions\Masters\MasterProductCategory\StoreMasterDepartment;
use App\Actions\Masters\MasterProductCategory\StoreMasterFamily;
use App\Actions\Masters\MasterShop\StoreMasterShop;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use Illuminate\Support\Facades\Artisan;
use App\Actions\Goods\TradeUnit\StoreTradeUnit;
use App\Models\Goods\TradeUnit;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    $this->group      = createGroup();
    $this->adminGuest = createAdminGuest($this->group);
    list(
        $this->organisation,
        $this->user,
        $this->shop
    ) = createShop();
    actingAs($this->adminGuest->getUser());

    $this->masterShop = StoreMasterShop::make()->action($this->group, [
        'type' => ShopTypeEnum::B2B,
        'code' => 'SMS'.substr(uniqid(), -6),
        'name' => 'Sync Child Prices Master Shop',
    ]);

    $masterDepartment = StoreMasterDepartment::make()->action($this->masterShop, [
        'code' => 'SD-'.uniqid(),
        'name' => 'dep',
        'type' => MasterProductCategoryTypeEnum::DEPARTMENT,
    ]);

    $this->masterFamily = StoreMasterFamily::make()->action($masterDepartment, [
        'code' => 'SF-'.uniqid(),
        'name' => 'fam',
        'type' => MasterProductCategoryTypeEnum::FAMILY,
    ]);

    $this->shop->updateQuietly(['master_shop_id' => $this->masterShop->id]);
    $this->currencyCode = $this->shop->currency->code;
    $this->tradeUnitId  = StoreTradeUnit::make()->action(group(), TradeUnit::factory()->definition())->id;
});

/** Master asset carrying a per-currency price/rrp, with one trade unit at the given quantity. */
function syncTestMasterAsset($masterFamily, string $code, string $currencyCode, float $units, int $tradeUnitId)
{
    $masterAsset = StoreMasterAsset::make()->action($masterFamily, [
        'code'    => $code,
        'name'    => $code,
        'is_main' => true,
        'type'    => MasterAssetTypeEnum::PRODUCT,
        'price'   => 10,
        'stocks'  => [],
    ]);

    $masterAsset->updateQuietly([
        'units'         => $units,
        'master_prices' => [$currencyCode => ['value' => '50', 'independent' => false]],
        'master_rrps'   => [$currencyCode => ['value' => '120', 'independent' => false]],
    ]);

    DB::table('model_has_trade_units')->insert([
        'model_type'    => 'MasterAsset',
        'model_id'      => $masterAsset->id,
        'trade_unit_id' => $tradeUnitId,
        'quantity'      => $units,
        'created_at'    => now(),
        'updated_at'    => now(),
    ]);

    return $masterAsset;
}

/**
 * createProduct() returns the shop's first existing product on repeat calls, so each case gets
 * its own product stored directly against that family instead.
 */
function syncTestProduct($shop, $masterAsset, float $units, int $tradeUnitId, float $tradeUnitQuantity)
{
    [, $seed] = createProduct($shop);

    $product = \App\Actions\Catalogue\Product\StoreProduct::make()->action(
        $seed->family,
        array_merge(\App\Models\Catalogue\Product::factory()->definition(), [
            'code'        => 'SP'.substr(uniqid(), -8),
            'price'       => 999,
            'trade_units' => [['id' => $tradeUnitId, 'quantity' => $tradeUnitQuantity]],
        ])
    );

    $product->updateQuietly([
        'master_product_id' => $masterAsset->id,
        'units'             => $units,
        'price'             => 999,
        'rrp'               => 888,
        'is_for_sale'       => true,
    ]);

    DB::table('model_has_trade_units')->where('model_type', 'Product')->where('model_id', $product->id)->delete();
    DB::table('model_has_trade_units')->insert([
        'model_type'    => 'Product',
        'model_id'      => $product->id,
        'trade_unit_id' => $tradeUnitId,
        'quantity'      => $tradeUnitQuantity,
        'created_at'    => now(),
        'updated_at'    => now(),
    ]);

    return $product;
}

test('syncs child price and rrp only when units and trade units both match the master', function () {
    $tradeUnitId = $this->tradeUnitId;

    $matching   = syncTestMasterAsset($this->masterFamily, 'SYNC-OK', $this->currencyCode, 4, $tradeUnitId);
    $wrongUnits = syncTestMasterAsset($this->masterFamily, 'SYNC-UNITS', $this->currencyCode, 4, $tradeUnitId);
    $wrongTus   = syncTestMasterAsset($this->masterFamily, 'SYNC-TU', $this->currencyCode, 4, $tradeUnitId);

    $okProduct         = syncTestProduct($this->shop, $matching, 4, $tradeUnitId, 4);
    $wrongUnitsProduct = syncTestProduct($this->shop, $wrongUnits, 1, $tradeUnitId, 4);
    $wrongTusProduct   = syncTestProduct($this->shop, $wrongTus, 4, $tradeUnitId, 99);

    Artisan::call('repair:master_child_prices', [
        'master_shop' => $this->masterShop->slug,
        '--currency'  => $this->currencyCode,
    ]);

    // Report only: nothing written.
    expect((float) $okProduct->refresh()->price)->toBe(999.0);

    Artisan::call('repair:master_child_prices', [
        'master_shop' => $this->masterShop->slug,
        '--currency'  => $this->currencyCode,
        '--fix'       => true,
    ]);

    expect((float) $okProduct->refresh()->price)->toBe(50.0)
        ->and((float) $okProduct->rrp)->toBe(120.0)
        // units disagree -> the two sides describe different baskets, left for units reconciliation
        ->and((float) $wrongUnitsProduct->refresh()->price)->toBe(999.0)
        // trade-unit quantity disagrees -> same reason
        ->and((float) $wrongTusProduct->refresh()->price)->toBe(999.0);
});

test('never touches declared rebels even when units and trade units match', function () {
    $tradeUnitId = $this->tradeUnitId;

    $masterAsset = syncTestMasterAsset($this->masterFamily, 'SYNC-REBEL', $this->currencyCode, 4, $tradeUnitId);
    $rebel       = syncTestProduct($this->shop, $masterAsset, 4, $tradeUnitId, 4);
    $rebel->updateQuietly(['not_follow_master_prices' => true]);

    Artisan::call('repair:master_child_prices', [
        'master_shop' => $this->masterShop->slug,
        '--currency'  => $this->currencyCode,
        '--fix'       => true,
    ]);

    expect((float) $rebel->refresh()->price)->toBe(999.0);
});

test('skips products that are not for sale', function () {
    $tradeUnitId = $this->tradeUnitId;

    $masterAsset = syncTestMasterAsset($this->masterFamily, 'SYNC-NFS', $this->currencyCode, 4, $tradeUnitId);
    $notForSale  = syncTestProduct($this->shop, $masterAsset, 4, $tradeUnitId, 4);
    $notForSale->updateQuietly(['is_for_sale' => false]);

    Artisan::call('repair:master_child_prices', [
        'master_shop' => $this->masterShop->slug,
        '--currency'  => $this->currencyCode,
        '--fix'       => true,
    ]);

    expect((float) $notForSale->refresh()->price)->toBe(999.0);
});

test('shop option limits the sync to that shop', function () {
    $masterAsset = syncTestMasterAsset($this->masterFamily, 'SYNC-SHOP', $this->currencyCode, 4, $this->tradeUnitId);
    $product     = syncTestProduct($this->shop, $masterAsset, 4, $this->tradeUnitId, 4);

    Artisan::call('repair:master_child_prices', [
        'master_shop' => $this->masterShop->slug,
        '--currency'  => $this->currencyCode,
        '--shop'      => 'NOSUCHSHOP',
        '--fix'       => true,
    ]);
    expect((float) $product->refresh()->price)->toBe(999.0);

    Artisan::call('repair:master_child_prices', [
        'master_shop' => $this->masterShop->slug,
        '--currency'  => $this->currencyCode,
        '--shop'      => $this->shop->code,
        '--fix'       => true,
    ]);
    expect((float) $product->refresh()->price)->toBe(50.0);
});

test('never syncs when either side has no trade units at all', function () {
    $masterAsset = syncTestMasterAsset($this->masterFamily, 'SYNC-NOTU', $this->currencyCode, 4, $this->tradeUnitId);
    $product     = syncTestProduct($this->shop, $masterAsset, 4, $this->tradeUnitId, 4);

    // Both sides stripped: units still agree, but there is no composition to prove they are the
    // same basket, so it must stay out of the cohort.
    DB::table('model_has_trade_units')->where('model_type', 'Product')->where('model_id', $product->id)->delete();
    DB::table('model_has_trade_units')->where('model_type', 'MasterAsset')->where('model_id', $masterAsset->id)->delete();

    Artisan::call('repair:master_child_prices', [
        'master_shop' => $this->masterShop->slug,
        '--currency'  => $this->currencyCode,
        '--fix'       => true,
    ]);

    expect((float) $product->refresh()->price)->toBe(999.0);
});

test('finalise only skips the price sync entirely', function () {
    $masterAsset = syncTestMasterAsset($this->masterFamily, 'SYNC-FIN', $this->currencyCode, 4, $this->tradeUnitId);
    $product     = syncTestProduct($this->shop, $masterAsset, 4, $this->tradeUnitId, 4);

    Artisan::call('repair:master_child_prices', [
        'master_shop'     => $this->masterShop->slug,
        '--currency'      => $this->currencyCode,
        '--finalise-only' => true,
    ]);

    // Recovers an interrupted run's closing sequence without touching a single price.
    expect((float) $product->refresh()->price)->toBe(999.0)
        ->and(Artisan::output())->toContain('Finalising');
});
