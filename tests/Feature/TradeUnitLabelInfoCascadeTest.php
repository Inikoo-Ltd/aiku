<?php

/*
 * Author Louis Perez
 * Created on 11-09-2026-11h-52m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Catalogue\Product\StoreProduct;
use App\Actions\Catalogue\Product\SyncProductTradeUnits;
use App\Actions\Goods\TradeUnit\StoreTradeUnit;
use App\Actions\Goods\TradeUnit\UpdateTradeUnit;
use App\Enums\Goods\TradeUnit\TradeUnitLabelPresenceEnum;
use App\Actions\Masters\MasterAsset\StoreMasterAsset;
use App\Actions\Masters\MasterAsset\StoreMasterProductFromTradeUnits;
use App\Actions\Masters\MasterAsset\UpdateMasterAsset;
use App\Actions\Masters\MasterProductCategory\StoreMasterDepartment;
use App\Actions\Masters\MasterProductCategory\StoreMasterFamily;
use App\Actions\Masters\MasterShop\StoreMasterShop;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Goods\TradeUnit;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    $this->group      = createGroup();
    $this->adminGuest = createAdminGuest($this->group);
    list($this->organisation, $this->user, $this->shop) = createShop();
    actingAs($this->adminGuest->getUser());

    $masterShop = StoreMasterShop::make()->action($this->group, [
        'type' => ShopTypeEnum::B2B,
        'code' => 'LI'.substr(uniqid(), -6),
        'name' => 'Label Info Master Shop',
    ]);

    $masterDepartment = StoreMasterDepartment::make()->action($masterShop, [
        'code' => 'LID-'.uniqid(),
        'name' => 'dep',
        'type' => MasterProductCategoryTypeEnum::DEPARTMENT,
    ]);

    $masterFamily = $this->masterFamily = StoreMasterFamily::make()->action($masterDepartment, [
        'code' => 'LIF-'.uniqid(),
        'name' => 'fam',
        'type' => MasterProductCategoryTypeEnum::FAMILY,
    ]);

    $this->bottle = StoreTradeUnit::make()->action(group(), TradeUnit::factory()->definition());
    $this->plug   = StoreTradeUnit::make()->action(group(), TradeUnit::factory()->definition());

    $this->masterAsset = StoreMasterAsset::make()->action($masterFamily, [
        'code'        => 'LI-AST'.substr(uniqid(), -6),
        'name'        => 'label info asset',
        'is_main'     => true,
        'type'        => MasterAssetTypeEnum::PRODUCT,
        'price'       => 10,
        'stocks'      => [],
        'trade_units' => [
            ['id' => $this->bottle->id, 'quantity' => 1],
            ['id' => $this->plug->id, 'quantity' => 1],
        ],
    ]);

    [, $seed] = createProduct($this->shop);
    $this->product = StoreProduct::make()->action(
        $seed->family,
        array_merge(Product::factory()->definition(), [
            'code'  => 'LIP'.substr(uniqid(), -8),
            'price' => 10,
            'unit'  => 'piece',
        ])
    );
    $this->product->updateQuietly(['master_product_id' => $this->masterAsset->id]);
    $this->product->tradeUnits()->sync([
        $this->bottle->id => ['quantity' => 1],
        $this->plug->id   => ['quantity' => 1],
    ]);
});

test('label presence flags are saved into the trade unit label info', function () {
    UpdateTradeUnit::make()->action($this->bottle, [
        'ce_marking' => true,
        'ip_rating'  => false,
    ]);

    expect($this->bottle->refresh()->label_info)->toMatchArray([
        'ce_marking' => true,
        'ip_rating'  => false,
    ]);
});

test('a master asset shows a flag when any of its trade units has it', function () {
    UpdateTradeUnit::make()->action($this->bottle, ['ce_marking' => true]);

    expect($this->masterAsset->refresh()->label_info)->toMatchArray([
        'ce_marking'   => true,
        'ukca_marking' => false,
    ]);

    UpdateTradeUnit::make()->action($this->plug, ['ce_marking' => true]);
    UpdateTradeUnit::make()->action($this->bottle, ['ce_marking' => false]);

    expect($this->masterAsset->refresh()->label_info['ce_marking'])->toBeTrue();

    UpdateTradeUnit::make()->action($this->plug, ['ce_marking' => false]);

    expect($this->masterAsset->refresh()->label_info['ce_marking'])->toBeFalse();
});

test('a product following its master copies the master label info', function () {
    $this->masterAsset->updateQuietly(['label_info' => ['weee_symbol' => true]]);

    UpdateTradeUnit::make()->action($this->bottle, ['ce_marking' => true]);

    expect($this->product->refresh()->label_info)->toMatchArray($this->masterAsset->refresh()->label_info)
        ->and($this->product->label_info['ce_marking'])->toBeTrue();
});

test('a product not following its master takes label info from its own trade units', function () {
    $this->product->updateQuietly(['not_follow_master_trade_units' => true]);
    $this->product->tradeUnits()->sync([$this->plug->id => ['quantity' => 1]]);

    UpdateTradeUnit::make()->action($this->bottle, ['ukca_marking' => true]);

    expect($this->masterAsset->refresh()->label_info['ukca_marking'])->toBeTrue()
        ->and(data_get($this->product->refresh()->label_info, 'ukca_marking', false))->toBeFalse();

    UpdateTradeUnit::make()->action($this->plug, ['ip_rating' => true]);

    expect($this->product->refresh()->label_info['ip_rating'])->toBeTrue();
});

test('label presence is exposed with show only when the stored value is true', function () {
    expect(TradeUnitLabelPresenceEnum::presenceFromLabelInfo([
        'ce_marking'   => true,
        'ukca_marking' => 'true',
        'weee_symbol'  => false,
    ]))->toBe([
        'ce_marking'                    => ['show' => true],
        'ukca_marking'                  => ['show' => false],
        'weee_symbol'                   => ['show' => false],
        'ip_rating'                     => ['show' => false],
        'sorting_recycling_information' => ['show' => false],
    ])
        ->and(TradeUnitLabelPresenceEnum::presenceFromLabelInfo(null)['ce_marking'])->toBe(['show' => false]);
});

test('trade unit, product and master product showcases include label presence', function () {
    UpdateTradeUnit::make()->action($this->bottle, ['weee_symbol' => true]);

    get(route('grp.org.shops.show.catalogue.products.all_products.show', [
        $this->organisation->slug,
        $this->product->shop->slug,
        $this->product->refresh()->slug,
    ]))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('showcase.label_info.weee_symbol.show', true)
        ->where('showcase.label_info.ce_marking.show', false)
        ->etc());

    get(route('grp.trade_units.units.show', [$this->bottle->refresh()->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('showcase.label_info.weee_symbol.show', true)
            ->where('showcase.label_info.ce_marking.show', false)
            ->etc());

    get(route('grp.masters.master_shops.show.master_products.show', [
        $this->masterAsset->masterShop->slug,
        $this->masterAsset->refresh()->slug,
    ]))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('showcase.label_info.weee_symbol.show', true)
        ->where('showcase.label_info.ce_marking.show', false)
        ->etc());
});

test('markets and languages are saved into the trade unit label info and shown on its showcase', function () {
    UpdateTradeUnit::make()->action($this->bottle, [
        'markets'   => [
            ['label' => 'UK', 'key' => 'uk', 'value' => false],
            ['label' => 'EU', 'key' => 'eu', 'value' => true],
            ['label' => 'ES', 'key' => 'es', 'value' => 'true'],
        ],
        'languages' => ['en', 'fr'],
    ]);

    expect($this->bottle->refresh()->label_info)->toMatchArray([
        'markets'   => ['eu', 'es'],
        'languages' => ['en', 'fr'],
    ]);

    get(route('grp.trade_units.units.show', [$this->bottle->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('showcase.label_info.markets.show', true)
            ->where('showcase.label_info.markets.value', [['value' => 'eu', 'label' => 'EU'], ['value' => 'es', 'label' => 'ES']])
            ->where('showcase.label_info.languages.show', true)
            ->where('showcase.label_info.languages.value', fn ($languages) => collect($languages)->pluck('code')->sort()->values()->all() === ['en', 'fr'])
            ->etc());
});

test('an unknown market is rejected', function () {
    UpdateTradeUnit::make()->action($this->bottle, ['markets' => ['us']]);
})->throws(Illuminate\Validation\ValidationException::class);

test('a master product created from trade units starts with their label presence', function () {
    UpdateTradeUnit::make()->action($this->plug, ['ip_rating' => true]);

    $masterAsset = StoreMasterProductFromTradeUnits::make()->action($this->masterFamily, [
        'code'              => 'LIN'.substr(uniqid(), -6),
        'name'              => 'label info new master',
        'unit'              => 'piece',
        'master_prices'     => [],
        'master_rrps'       => [],
        'is_minion_variant' => false,
        'is_for_sale'       => true,
        'trade_units'       => [
            ['id' => $this->plug->id, 'quantity' => 1],
        ],
    ]);

    expect($masterAsset->refresh()->label_info)->toMatchArray([
        'ip_rating'  => true,
        'ce_marking' => false,
    ]);
});

test('a master keeps only the markets every trade unit shares and unions their languages', function () {
    UpdateTradeUnit::make()->action($this->bottle, ['markets' => ['uk', 'eu'], 'languages' => ['fr', 'en']]);
    UpdateTradeUnit::make()->action($this->plug, ['markets' => ['eu', 'es', 'uk'], 'languages' => ['en', 'es']]);

    expect($this->masterAsset->refresh()->label_info)->toMatchArray([
        'markets'   => ['uk', 'eu'],
        'languages' => ['en', 'es', 'fr'],
    ])
        ->and($this->product->refresh()->label_info)->toMatchArray([
            'markets'   => ['uk', 'eu'],
            'languages' => ['en', 'es', 'fr'],
        ]);

    UpdateTradeUnit::make()->action($this->plug, ['markets' => ['es']]);

    expect($this->masterAsset->refresh()->label_info['markets'])->toBe([])
        ->and($this->product->refresh()->label_info['markets'])->toBe([]);
});

test('changing a master composition recalculates its label info and its following products', function () {
    UpdateTradeUnit::make()->action($this->bottle, ['ce_marking' => true, 'markets' => ['uk']]);

    expect($this->masterAsset->refresh()->label_info['ce_marking'])->toBeTrue();

    UpdateMasterAsset::make()->action($this->masterAsset, [
        'trade_units' => [
            ['id' => $this->plug->id, 'quantity' => 1],
        ],
    ]);

    expect($this->masterAsset->refresh()->label_info)->toMatchArray([
        'ce_marking' => false,
        'markets'    => [],
    ])
        ->and($this->product->refresh()->label_info['ce_marking'])->toBeFalse();
});

test('changing an independent product composition recalculates its label info', function () {
    $this->product->updateQuietly(['not_follow_master_trade_units' => true]);
    UpdateTradeUnit::make()->action($this->bottle, ['weee_symbol' => true, 'languages' => ['de']]);

    SyncProductTradeUnits::run($this->product->refresh(), [
        ['id' => $this->plug->id, 'quantity' => 1],
    ]);

    expect($this->product->refresh()->label_info)->toMatchArray([
        'weee_symbol' => false,
        'languages'   => [],
    ]);

    SyncProductTradeUnits::run($this->product->refresh(), [
        ['id' => $this->bottle->id, 'quantity' => 1],
    ]);

    expect($this->product->refresh()->label_info)->toMatchArray([
        'weee_symbol' => true,
        'languages'   => ['de'],
    ]);
});
