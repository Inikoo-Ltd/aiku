<?php

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Maintenance\Masters\RepairMasterProductUnitsIntegrity;
use App\Actions\Masters\MasterAsset\StoreMasterAsset;
use App\Actions\Masters\MasterProductCategory\StoreMasterDepartment;
use App\Actions\Masters\MasterProductCategory\StoreMasterFamily;
use App\Actions\Masters\MasterShop\StoreMasterShop;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;

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

    $masterShop = StoreMasterShop::make()->action($this->group, [
        'type' => ShopTypeEnum::B2B,
        'code' => 'UNITFIX'.uniqid(),
        'name' => 'Units Fix Master Shop',
    ]);

    $masterDepartment = StoreMasterDepartment::make()->action($masterShop, [
        'code' => 'UNITFIX-DEP'.uniqid(),
        'name' => 'dep',
        'type' => MasterProductCategoryTypeEnum::DEPARTMENT,
    ]);

    $masterFamily = StoreMasterFamily::make()->action($masterDepartment, [
        'code' => 'UNITFIX-FAM'.uniqid(),
        'name' => 'fam',
        'type' => MasterProductCategoryTypeEnum::FAMILY,
    ]);

    $this->masterAsset = StoreMasterAsset::make()->action($masterFamily, [
        'code'    => 'UNITFIX-ASSET'.uniqid(),
        'name'    => 'units fix asset',
        'is_main' => true,
        'type'    => \App\Enums\Masters\MasterAsset\MasterAssetTypeEnum::PRODUCT,
        'price'   => 10,
        'stocks'  => [],
    ]);

    [, $this->product] = createProduct($this->shop);
    $this->tradeUnit   = $this->product->tradeUnits()->first();
    $this->currencyCode = $this->shop->currency->code;

    $this->masterAsset->tradeUnits()->sync([$this->tradeUnit->id => ['quantity' => 8]]);
    $this->product->tradeUnits()->sync([$this->tradeUnit->id => ['quantity' => 8]]);

    $this->shop->updateQuietly(['master_shop_id' => $masterShop->id]);
    $this->product->updateQuietly([
        'master_product_id'             => $this->masterAsset->id,
        'is_for_sale'                   => true,
        'not_follow_master_trade_units' => false,
        'price'                         => 100,
    ]);

    $this->masterAsset->updateQuietly([
        'master_prices' => [$this->currencyCode => ['value' => 100, 'independent' => false]],
    ]);

    $this->repair = RepairMasterProductUnitsIntegrity::make();
});

test('product stale vs self-consistent master is fixed only with price proof', function () {
    $this->masterAsset->updateQuietly(['units' => 8]);
    $this->product->updateQuietly(['units' => 80]);

    $findings = $this->repair->handle($this->masterAsset->refresh());
    expect($findings)->toHaveCount(1)
        ->and($findings[0]['bucket'])->toBe('product_stale_pivot')
        ->and($findings[0]['suggested'])->toBe(8.0)
        ->and($findings[0]['fixed'])->toBeFalse()
        ->and((float) $this->product->refresh()->units)->toBe(80.0);

    $this->repair->handle($this->masterAsset->refresh(), fix: true);
    expect((float) $this->product->refresh()->units)->toBe(8.0)
        ->and($this->repair->handle($this->masterAsset->refresh()))->toBeEmpty()
        ->and($this->product->refresh()->units_review)->toBeNull();
});

test('product stale is not fixed when price diverges or is missing', function () {
    $this->masterAsset->updateQuietly(['units' => 8]);
    $this->product->updateQuietly(['units' => 80, 'price' => 150]);

    $findings = $this->repair->handle($this->masterAsset->refresh(), fix: true);
    expect($findings[0]['bucket'])->toBe('product_stale_pivot_price_divergent')
        ->and($findings[0]['suggested'])->toBeNull()
        ->and((float) $this->product->refresh()->units)->toBe(80.0)
        ->and($this->product->units_review)->toBe('price_divergent');

    $this->masterAsset->updateQuietly(['master_prices' => []]);
    $this->product->updateQuietly(['price' => 100]);

    $findings = $this->repair->handle($this->masterAsset->refresh(), fix: true);
    expect($findings[0]['bucket'])->toBe('product_stale_pivot_price_unverifiable')
        ->and((float) $this->product->refresh()->units)->toBe(80.0);
});

test('master stale vs product consensus matching pivot is fixed only with price proof', function () {
    $this->masterAsset->updateQuietly(['units' => 0.071]);
    $this->product->updateQuietly(['units' => 8]);

    $findings = $this->repair->handle($this->masterAsset->refresh());
    expect($findings)->toHaveCount(1)
        ->and($findings[0]['bucket'])->toBe('master_stale_consensus')
        ->and($findings[0]['suggested'])->toBe(8.0);

    $this->repair->handle($this->masterAsset->refresh(), fix: true);
    expect((float) $this->masterAsset->refresh()->units)->toBe(8.0);
});

test('master stale consensus is not fixed when price diverges or is missing', function () {
    $this->masterAsset->updateQuietly(['units' => 0.071]);
    $this->product->updateQuietly(['units' => 8, 'price' => 150]);

    $findings = $this->repair->handle($this->masterAsset->refresh(), fix: true);
    expect($findings[0]['bucket'])->toBe('master_stale_consensus_price_divergent')
        ->and((float) $this->masterAsset->refresh()->units)->toBe(0.071)
        ->and($this->masterAsset->units_review)->toBe('price_divergent');

    $this->masterAsset->updateQuietly(['master_prices' => []]);
    $this->product->updateQuietly(['price' => 100]);

    $findings = $this->repair->handle($this->masterAsset->refresh(), fix: true);
    expect($findings[0]['bucket'])->toBe('master_stale_consensus_price_unverifiable')
        ->and((float) $this->masterAsset->refresh()->units)->toBe(0.071);
});

test('consensus conflicting with pivot is never fixed', function () {
    $this->masterAsset->updateQuietly(['units' => 1]);
    $this->product->updateQuietly(['units' => 80]);

    $findings = $this->repair->handle($this->masterAsset->refresh(), fix: true);
    expect($findings)->toHaveCount(1)
        ->and($findings[0]['bucket'])->toBe('consensus_conflicts_pivot')
        ->and($findings[0]['suggested'])->toBeNull()
        ->and((float) $this->masterAsset->refresh()->units)->toBe(1.0)
        ->and((float) $this->product->refresh()->units)->toBe(80.0);
});

test('different trade unit composition is never fixed', function () {
    $this->masterAsset->updateQuietly(['units' => 8]);
    $this->product->updateQuietly(['units' => 4]);
    $this->product->tradeUnits()->sync([$this->tradeUnit->id => ['quantity' => 4]]);

    $findings = $this->repair->handle($this->masterAsset->refresh(), fix: true);
    expect($findings)->toHaveCount(1)
        ->and($findings[0]['bucket'])->toBe('diff_trade_units')
        ->and((float) $this->product->refresh()->units)->toBe(4.0)
        ->and($this->product->units_review)->toBe('diff_trade_units');
});

test('products with not_follow_master_trade_units are ignored', function () {
    $this->masterAsset->updateQuietly(['units' => 8]);
    $this->product->updateQuietly(['units' => 80, 'not_follow_master_trade_units' => true]);

    expect($this->repair->handle($this->masterAsset->refresh(), fix: true))->toBeEmpty()
        ->and((float) $this->product->refresh()->units)->toBe(80.0);
});
