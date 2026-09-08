<?php

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Maintenance\Masters\RepairMasterAssetRrpAnomalies;
use App\Actions\Masters\MasterAsset\StoreMasterAsset;
use App\Actions\Masters\MasterProductCategory\StoreMasterDepartment;
use App\Actions\Masters\MasterProductCategory\StoreMasterFamily;
use App\Actions\Masters\MasterShop\StoreMasterShop;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Helpers\Currency;

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

test('detects and fixes double multiplied master rrps and cascades to products', function () {
    $masterShop = StoreMasterShop::make()->action($this->group, [
        'type' => ShopTypeEnum::B2B,
        'code' => 'RRPFIX',
        'name' => 'RRP Fix Master Shop',
    ]);

    $masterDepartment = StoreMasterDepartment::make()->action($masterShop, [
        'code' => 'RRPFIX-DEP',
        'name' => 'dep',
        'type' => MasterProductCategoryTypeEnum::DEPARTMENT,
    ]);

    $masterFamily = StoreMasterFamily::make()->action($masterDepartment, [
        'code' => 'RRPFIX-FAM',
        'name' => 'fam',
        'type' => MasterProductCategoryTypeEnum::FAMILY,
    ]);

    $masterAsset = StoreMasterAsset::make()->action($masterFamily, [
        'code'    => 'RRPFIX-ASSET',
        'name'    => 'rrp fix asset',
        'is_main' => true,
        'type'    => \App\Enums\Masters\MasterAsset\MasterAssetTypeEnum::PRODUCT,
        'price'   => 300,
        'stocks'  => [],
    ]);

    $eur = Currency::where('code', 'EUR')->firstOrFail();

    [, $product] = createProduct($this->shop);
    $this->shop->updateQuietly(['master_shop_id' => $masterShop->id, 'currency_id' => $eur->id]);
    $product->updateQuietly([
        'master_product_id' => $masterAsset->id,
        'currency_id'       => $eur->id,
        'units'             => 480,
        'rrp'               => 221184000,
    ]);

    $masterAsset->updateQuietly([
        'units'       => 480,
        'master_rrps' => [
            'GBP' => ['value' => 720, 'independent' => false],
            'EUR' => ['value' => 460800, 'independent' => false],
        ],
    ]);
    $masterAsset->refresh();

    $exchangeRates = ['GBP' => 1.0, 'EUR' => 1.2];

    $findings = RepairMasterAssetRrpAnomalies::make()->handle($masterAsset, $exchangeRates, 0.5, false);

    $masterAsset->refresh();
    $product->refresh();

    expect($findings)->toHaveCount(2)
        ->and($findings[0]['scope'])->toBe('master')
        ->and($findings[0]['currency'])->toBe('EUR')
        ->and($findings[0]['suggested'])->toBe(960.0)
        ->and($findings[1]['scope'])->toBe("product:{$this->shop->code}")
        ->and($findings[1]['suggested'])->toBe(960.0)
        ->and((float) data_get($masterAsset->master_rrps, 'EUR.value'))->toBe(460800.0)
        ->and((float) $product->rrp)->toBe(221184000.0);

    RepairMasterAssetRrpAnomalies::make()->handle($masterAsset, $exchangeRates, 0.5, true);

    $masterAsset->refresh();
    $product->refresh();

    expect((float) data_get($masterAsset->master_rrps, 'EUR.value'))->toBe(960.0)
        ->and((float) $masterAsset->rrp)->toBe(960.0)
        ->and((float) data_get($masterAsset->master_rrps, 'GBP.value'))->toBe(720.0)
        ->and((float) $product->rrp)->toBe(960.0);

    $sane = RepairMasterAssetRrpAnomalies::make()->handle($masterAsset->fresh(), $exchangeRates, 0.5, false);
    expect($sane)->toBeEmpty();
});

test('respects not_follow_master_prices and independent flags', function () {
    $masterShop  = \App\Models\Masters\MasterShop::where('code', 'RRPFIX')->firstOrFail();
    $masterAsset = \App\Models\Masters\MasterAsset::where('code', 'RRPFIX-ASSET')->firstOrFail();

    $product = $masterAsset->products()->firstOrFail();
    $product->updateQuietly(['rrp' => 460800, 'not_follow_master_prices' => true]);

    $masterAsset->updateQuietly([
        'master_rrps' => [
            'GBP' => ['value' => 720, 'independent' => false],
            'EUR' => ['value' => 460800, 'independent' => true],
        ],
    ]);
    $masterAsset->refresh();

    $findings = RepairMasterAssetRrpAnomalies::make()->handle($masterAsset, ['GBP' => 1.0, 'EUR' => 1.2], 0.5, true);

    $masterAsset->refresh();
    $product->refresh();

    expect((float) data_get($masterAsset->master_rrps, 'EUR.value'))->toBe(460800.0)
        ->and((float) $product->rrp)->toBe(460800.0)
        ->and(collect($findings)->every(fn ($finding) => $finding['independent']))->toBeTrue();
})->depends('detects and fixes double multiplied master rrps and cascades to products');

test('skips discontinued products', function () {
    $masterAsset = \App\Models\Masters\MasterAsset::where('code', 'RRPFIX-ASSET')->firstOrFail();

    $product = $masterAsset->products()->firstOrFail();
    $product->updateQuietly([
        'rrp'                      => 460800,
        'not_follow_master_prices' => false,
        'state'                    => \App\Enums\Catalogue\Product\ProductStateEnum::DISCONTINUED,
    ]);

    $masterAsset->updateQuietly([
        'master_rrps' => [
            'GBP' => ['value' => 720, 'independent' => false],
            'EUR' => ['value' => 960, 'independent' => false],
        ],
    ]);
    $masterAsset->refresh();

    $findings = RepairMasterAssetRrpAnomalies::make()->handle($masterAsset, ['GBP' => 1.0, 'EUR' => 1.2], 0.5, true);

    expect($findings)->toBeEmpty()
        ->and((float) $product->fresh()->rrp)->toBe(460800.0);
})->depends('respects not_follow_master_prices and independent flags');

test('cascade hydrator sets product rrp to master total, not multiplied by units', function () {
    $masterAsset = \App\Models\Masters\MasterAsset::where('code', 'RRPFIX-ASSET')->firstOrFail();

    $product = $masterAsset->products()->firstOrFail();
    $product->updateQuietly([
        'not_follow_master_prices' => false,
        'state'                    => \App\Enums\Catalogue\Product\ProductStateEnum::ACTIVE,
    ]);

    $masterAsset->updateQuietly([
        'master_rrps' => [
            'GBP' => ['value' => 720, 'independent' => false],
            'EUR' => ['value' => 960, 'independent' => false],
        ],
    ]);
    $masterAsset->refresh();

    \App\Actions\Masters\MasterAsset\Hydrators\MasterAssetHydrateMasterPricesRRPtoChild::run($masterAsset);

    expect((float) $product->fresh()->rrp)->toBe(960.0);
})->depends('skips discontinued products');

test('divides rrp by units in every currency when both majors carry the same corrupt ratio', function () {
    $masterFamily = \App\Models\Masters\MasterProductCategory::where('code', 'RRPFIX-FAM')->firstOrFail();

    $masterAsset = StoreMasterAsset::make()->action($masterFamily, [
        'code'    => 'RRPFIX-UNITS',
        'name'    => 'rrp times units asset',
        'is_main' => true,
        'type'    => \App\Enums\Masters\MasterAsset\MasterAssetTypeEnum::PRODUCT,
        'price'   => 100,
        'stocks'  => [],
    ]);

    $masterAsset->updateQuietly([
        'units'         => 36,
        'master_prices' => [
            'GBP' => ['value' => 100, 'independent' => false],
            'EUR' => ['value' => 120, 'independent' => false],
            'USD' => ['value' => 130, 'independent' => false],
        ],
        'master_rrps' => [
            'GBP' => ['value' => 10080, 'independent' => false],
            'EUR' => ['value' => 12096, 'independent' => false],
            'USD' => ['value' => 13104, 'independent' => false],
        ],
    ]);
    $masterAsset->refresh();

    RepairMasterAssetRrpAnomalies::make()->handle($masterAsset, ['GBP' => 1.0, 'EUR' => 1.2, 'USD' => 1.3], 0.5, true);

    $rrps = $masterAsset->fresh()->master_rrps;

    expect((float) data_get($rrps, 'GBP.value'))->toBe(280.0)
        ->and((float) data_get($rrps, 'EUR.value'))->toBe(336.0)
        ->and((float) data_get($rrps, 'USD.value'))->toBe(364.0);

    expect(RepairMasterAssetRrpAnomalies::make()->handle($masterAsset->fresh(), ['GBP' => 1.0, 'EUR' => 1.2, 'USD' => 1.3], 0.5, false))->toBeEmpty();
})->depends('skips discontinued products');

test('leaves a legitimate multipack whose rrp is quoted per single item untouched', function () {
    $masterFamily = \App\Models\Masters\MasterProductCategory::where('code', 'RRPFIX-FAM')->firstOrFail();

    $masterAsset = StoreMasterAsset::make()->action($masterFamily, [
        'code'    => 'RRPFIX-MULTIPACK',
        'name'    => 'legit multipack',
        'is_main' => true,
        'type'    => \App\Enums\Masters\MasterAsset\MasterAssetTypeEnum::PRODUCT,
        'price'   => 10,
        'stocks'  => [],
    ]);

    $masterAsset->updateQuietly([
        'units'         => 12,
        'master_prices' => [
            'GBP' => ['value' => 10, 'independent' => false],
            'EUR' => ['value' => 12, 'independent' => false],
        ],
        'master_rrps' => [
            'GBP' => ['value' => 125, 'independent' => false],
            'EUR' => ['value' => 150, 'independent' => false],
        ],
    ]);
    $masterAsset->refresh();

    RepairMasterAssetRrpAnomalies::make()->handle($masterAsset, ['GBP' => 1.0, 'EUR' => 1.2], 0.5, true);

    $rrps = $masterAsset->fresh()->master_rrps;

    expect((float) data_get($rrps, 'GBP.value'))->toBe(125.0)
        ->and((float) data_get($rrps, 'EUR.value'))->toBe(150.0);
})->depends('skips discontinued products');

test('leaves a healthy low units asset whose markup survives a division untouched', function () {
    $masterFamily = \App\Models\Masters\MasterProductCategory::where('code', 'RRPFIX-FAM')->firstOrFail();

    $masterAsset = StoreMasterAsset::make()->action($masterFamily, [
        'code'    => 'RRPFIX-HEALTHY',
        'name'    => 'healthy low units',
        'is_main' => true,
        'type'    => \App\Enums\Masters\MasterAsset\MasterAssetTypeEnum::PRODUCT,
        'price'   => 100,
        'stocks'  => [],
    ]);

    $masterAsset->updateQuietly([
        'units'         => 2,
        'master_prices' => [
            'GBP' => ['value' => 100, 'independent' => false],
            'EUR' => ['value' => 120, 'independent' => false],
        ],
        'master_rrps' => [
            'GBP' => ['value' => 300, 'independent' => false],
            'EUR' => ['value' => 360, 'independent' => false],
        ],
    ]);
    $masterAsset->refresh();

    RepairMasterAssetRrpAnomalies::make()->handle($masterAsset, ['GBP' => 1.0, 'EUR' => 1.2], 0.5, true);

    $rrps = $masterAsset->fresh()->master_rrps;

    expect((float) data_get($rrps, 'GBP.value'))->toBe(300.0)
        ->and((float) data_get($rrps, 'EUR.value'))->toBe(360.0);
})->depends('skips discontinued products');

test('skips master assets with status false', function () {
    $masterAsset = \App\Models\Masters\MasterAsset::where('code', 'RRPFIX-ASSET')->firstOrFail();
    $masterAsset->updateQuietly([
        'status'      => false,
        'master_rrps' => [
            'GBP' => ['value' => 720, 'independent' => false],
            'EUR' => ['value' => 460800, 'independent' => false],
        ],
    ]);
    $masterAsset->refresh();

    $findings = RepairMasterAssetRrpAnomalies::make()->handle($masterAsset, ['GBP' => 1.0, 'EUR' => 1.2], 0.5, true);

    expect($findings)->toBeEmpty()
        ->and((float) data_get($masterAsset->fresh()->master_rrps, 'EUR.value'))->toBe(460800.0);
})->depends('skips discontinued products');
