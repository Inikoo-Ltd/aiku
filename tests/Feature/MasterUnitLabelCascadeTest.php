<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Catalogue\Product\StoreProduct;
use App\Actions\Goods\TradeUnit\StoreTradeUnit;
use App\Actions\Helpers\Translations\Translate;
use App\Actions\Masters\MasterAsset\StoreMasterAsset;
use App\Actions\Masters\MasterAsset\UpdateMasterAsset;
use App\Actions\Masters\MasterProductCategory\StoreMasterDepartment;
use App\Actions\Masters\MasterProductCategory\StoreMasterFamily;
use App\Actions\Masters\MasterShop\StoreMasterShop;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Language;

use function Pest\Laravel\actingAs;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    $this->group      = createGroup();
    $this->adminGuest = createAdminGuest($this->group);
    list($this->organisation, $this->user, $this->shop) = createShop();
    actingAs($this->adminGuest->getUser());

    $this->masterShop = StoreMasterShop::make()->action($this->group, [
        'type' => ShopTypeEnum::B2B,
        'code' => 'UL'.substr(uniqid(), -6),
        'name' => 'Unit Label Master Shop',
    ]);

    $masterDepartment = StoreMasterDepartment::make()->action($this->masterShop, [
        'code' => 'ULD-'.uniqid(),
        'name' => 'dep',
        'type' => MasterProductCategoryTypeEnum::DEPARTMENT,
    ]);

    $this->masterFamily = StoreMasterFamily::make()->action($masterDepartment, [
        'code' => 'ULF-'.uniqid(),
        'name' => 'fam',
        'type' => MasterProductCategoryTypeEnum::FAMILY,
    ]);

    $this->shop->updateQuietly([
        'master_shop_id' => $this->masterShop->id,
        'language_id'   => Language::where('code', 'en')->first()->id,
    ]);

    $this->bottle = StoreTradeUnit::make()->action(group(), TradeUnit::factory()->definition())->id;
    $this->plug   = StoreTradeUnit::make()->action(group(), TradeUnit::factory()->definition())->id;

    $this->masterAsset = StoreMasterAsset::make()->action($this->masterFamily, [
        'code'        => 'UL-AST',
        'name'        => 'unit label asset',
        'is_main'     => true,
        'type'        => MasterAssetTypeEnum::PRODUCT,
        'price'       => 10,
        'stocks'      => [],
        'trade_units' => [
            ['id' => $this->bottle, 'quantity' => 6],
            ['id' => $this->plug, 'quantity' => 6],
        ],
    ]);

    [, $seed] = createProduct($this->shop);
    $this->product = StoreProduct::make()->action(
        $seed->family,
        array_merge(Product::factory()->definition(), [
            'code'  => 'ULP'.substr(uniqid(), -8),
            'price' => 10,
            'unit'  => 'piece',
        ])
    );
    $this->product->updateQuietly(['master_product_id' => $this->masterAsset->id]);
});

test('the unit label typed on a master reaches its products', function () {
    UpdateMasterAsset::make()->action($this->masterAsset, ['unit' => 'bottle']);

    expect($this->masterAsset->refresh()->unit)->toBe('bottle')
        ->and($this->product->refresh()->unit)->toBe('bottle');
});

test('a unit label saved with the composition survives it', function () {
    UpdateMasterAsset::make()->action($this->masterAsset, [
        'unit'        => 'bottle',
        'trade_units' => [
            ['id' => $this->bottle, 'quantity' => 6],
            ['id' => $this->plug, 'quantity' => 6],
        ],
    ]);

    expect($this->masterAsset->refresh()->unit)->toBe('bottle')
        ->and((float) $this->masterAsset->units)->toBe(6.0);
});

test('a composition saved on its own still names the unit', function () {
    UpdateMasterAsset::make()->action($this->masterAsset, [
        'trade_units' => [
            ['id' => $this->bottle, 'quantity' => 4],
            ['id' => $this->plug, 'quantity' => 4],
        ],
    ]);

    expect($this->masterAsset->refresh()->unit)->toBe('bundle')
        ->and((float) $this->masterAsset->units)->toBe(4.0);
});

test('the unit label is translated for shops in another language that follow the master', function () {
    Translate::mock()->shouldReceive('handle')->andReturn('bouteille');

    $french = Language::where('code', 'fr')->first();
    $this->shop->updateQuietly([
        'language_id' => $french->id,
        'settings'    => array_merge($this->shop->settings ?? [], [
            'catalog' => ['product_follow_master' => true],
        ]),
    ]);

    UpdateMasterAsset::make()->action($this->masterAsset, ['unit' => 'bottle']);

    expect($this->product->refresh()->unit)->toBe('bouteille');
});

test('a shop that does not follow the master keeps its own unit label', function () {
    $french = Language::where('code', 'fr')->first();
    $this->shop->updateQuietly([
        'language_id' => $french->id,
        'settings'    => array_merge($this->shop->settings ?? [], [
            'catalog' => ['product_follow_master' => false],
        ]),
    ]);

    UpdateMasterAsset::make()->action($this->masterAsset, ['unit' => 'bottle']);

    expect($this->product->refresh()->unit)->toBe('piece');
});
