<?php

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Masters\MasterAsset\StoreMasterAsset;
use App\Actions\Masters\MasterProductCategory\StoreMasterDepartment;
use App\Actions\Masters\MasterProductCategory\StoreMasterFamily;
use App\Actions\Masters\MasterShop\StoreMasterShop;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use Illuminate\Support\Facades\Artisan;

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
});

test('hydrates master prices from major shop product and derives minors from official exchange', function () {
    $masterShop = StoreMasterShop::make()->action($this->group, [
        'type' => ShopTypeEnum::B2B,
        'code' => 'HYDFIX',
        'name' => 'Hydrate Prices Master Shop',
    ]);

    $masterDepartment = StoreMasterDepartment::make()->action($masterShop, [
        'code' => 'HYDFIX-DEP',
        'name' => 'dep',
        'type' => MasterProductCategoryTypeEnum::DEPARTMENT,
    ]);

    $masterFamily = StoreMasterFamily::make()->action($masterDepartment, [
        'code' => 'HYDFIX-FAM',
        'name' => 'fam',
        'type' => MasterProductCategoryTypeEnum::FAMILY,
    ]);

    $masterAsset = StoreMasterAsset::make()->action($masterFamily, [
        'code'    => 'HYDFIX-ASSET',
        'name'    => 'hydrate prices asset',
        'is_main' => true,
        'type'    => \App\Enums\Masters\MasterAsset\MasterAssetTypeEnum::PRODUCT,
        'price'   => 10,
        'stocks'  => [],
    ]);

    [, $product] = createProduct($this->shop);
    $majorCode   = $this->shop->currency->code;

    $this->shop->updateQuietly(['master_shop_id' => $masterShop->id]);
    $product->updateQuietly([
        'master_product_id' => $masterAsset->id,
        'price'             => 100,
        'rrp'               => 200,
    ]);

    $masterShop->update(['price_exchanges' => [
        $majorCode => ['is_major' => true],
        'USD'      => ['is_major' => false, 'major' => $majorCode, 'exchange' => 2],
    ]]);

    Artisan::call('repair:master_asset_hydrate_prices', ['master_shop' => $masterShop->slug, '--dry-run' => true]);
    expect($masterAsset->refresh()->master_prices)->toBe([]);

    Artisan::call('repair:master_asset_hydrate_prices', ['master_shop' => $masterShop->slug]);
    $masterAsset->refresh();

    expect(data_get($masterAsset->master_prices, "$majorCode.value"))->toBe('100')
        ->and(data_get($masterAsset->master_prices, 'USD.value'))->toBe('200')
        ->and(data_get($masterAsset->master_prices, 'USD.independent'))->toBeFalse()
        ->and(data_get($masterAsset->master_rrps, "$majorCode.value"))->toBe('200')
        ->and(data_get($masterAsset->master_rrps, 'USD.value'))->toBe('400')
        ->and((float) $masterAsset->price)->toBe(100.0)
        ->and((float) $masterAsset->rrp)->toBe(200.0);

    $masterAsset->updateQuietly([
        'master_prices' => array_merge($masterAsset->master_prices, [
            'USD' => ['value' => 555, 'independent' => true],
            'XXX' => ['value' => 42, 'independent' => false],
        ]),
    ]);

    Artisan::call('repair:master_asset_hydrate_prices', ['master_shop' => $masterShop->slug]);
    $masterAsset->refresh();

    expect(data_get($masterAsset->master_prices, 'USD.value'))->toBe(555)
        ->and(data_get($masterAsset->master_prices, 'USD.independent'))->toBeTrue()
        ->and(data_get($masterAsset->master_prices, 'XXX.value'))->toBe(42);
});
