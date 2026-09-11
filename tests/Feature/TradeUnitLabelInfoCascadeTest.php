<?php

/*
 * Author Louis Perez
 * Created on 11-09-2026-11h-52m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Catalogue\Product\StoreProduct;
use App\Actions\Goods\TradeUnit\StoreTradeUnit;
use App\Actions\Goods\TradeUnit\UpdateTradeUnit;
use App\Actions\Masters\MasterAsset\StoreMasterAsset;
use App\Actions\Masters\MasterProductCategory\StoreMasterDepartment;
use App\Actions\Masters\MasterProductCategory\StoreMasterFamily;
use App\Actions\Masters\MasterShop\StoreMasterShop;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Goods\TradeUnit;

use function Pest\Laravel\actingAs;

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

    $masterFamily = StoreMasterFamily::make()->action($masterDepartment, [
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
