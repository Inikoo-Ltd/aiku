<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Catalogue\Shop\StoreShop;
use App\Actions\Catalogue\Shop\UpdateShop;
use App\Actions\Dropshipping\Bundle\CalculateBundleItemPriceDetails;
use App\Actions\Dropshipping\Bundle\DeleteBundle;
use App\Actions\Dropshipping\Bundle\StoreBundle;
use App\Actions\Dropshipping\Bundle\StoreOrUpdateBundle;
use App\Actions\Dropshipping\Bundle\UpdateBundle;
use App\Actions\Dropshipping\CustomerSalesChannel\StoreCustomerSalesChannel;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Bundle;
use App\Models\BundleItem;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;

use function Pest\Laravel\actingAs;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    $this->organisation = createOrganisation();
    $this->group        = $this->organisation->group;
    $this->user          = createAdminGuest($this->group)->getUser();

    $shop = Shop::first();
    if (!$shop) {
        $storeData = Shop::factory()->definition();
        data_set($storeData, 'type', ShopTypeEnum::DROPSHIPPING);

        $shop = StoreShop::make()->action(
            $this->organisation,
            $storeData
        );
    }
    $this->shop = UpdateShop::make()->action($shop, ['state' => ShopStateEnum::OPEN]);

    $this->customer = createCustomer($this->shop);

    list($this->tradeUnit, $this->product) = createProduct($this->shop);

    $secondProductData = array_merge(
        Product::factory()->definition(),
        [
            'trade_units' => [
                [
                    'id'       => $this->tradeUnit->id ?? $this->tradeUnit[0]->id,
                    'quantity' => 1,
                ],
            ],
            'price' => 50,
            'rrp'   => 80,
        ]
    );

    $family = $this->shop->productCategories()->where('type', \App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum::FAMILY)->first();

    $this->secondProduct = \App\Actions\Catalogue\Product\StoreProduct::make()->action($family, $secondProductData);
    $this->secondProduct = \App\Actions\Catalogue\Product\UpdateProduct::make()->action($this->secondProduct, ['state' => ProductStateEnum::ACTIVE]);

    $platform = $this->group->platforms()->where('type', PlatformTypeEnum::SHOPIFY)->first();

    $this->customerSalesChannel = StoreCustomerSalesChannel::make()->action(
        $this->customer,
        $platform,
        ['reference' => 'bundle_test_channel']
    );

    actingAs($this->user);
});

test('store bundle with explicit price/rrp/code', function () {
    $bundle = StoreBundle::make()->action(
        $this->customerSalesChannel,
        [
            'name'        => 'Combo Pack',
            'code'        => 'COMBO-1',
            'price'       => 120,
            'rrp'         => 150,
            'description' => 'Two products bundled',
            'products'    => [
                ['product_id' => $this->product->id, 'quantity' => 1],
                ['product_id' => $this->secondProduct->id, 'quantity' => 2],
            ],
        ]
    );

    expect($bundle)->toBeInstanceOf(Bundle::class)
        ->and($bundle->items()->count())->toBe(2)
        ->and($bundle->bundleable)->toBeInstanceOf(Product::class)
        ->and($bundle->bundleable->is_bundle)->toBeTrue()
        ->and($bundle->bundleable->price)->toEqual(120)
        ->and($bundle->bundleable->rrp)->toEqual(150);

    return $bundle;
});

test('store bundle without price/rrp/code/name calculates defaults', function () {
    $bundle = StoreBundle::make()->action(
        $this->customerSalesChannel,
        [
            'products' => [
                ['product_id' => $this->product->id, 'quantity' => 1],
                ['product_id' => $this->secondProduct->id, 'quantity' => 1],
            ],
        ]
    );

    expect($bundle)->toBeInstanceOf(Bundle::class)
        ->and($bundle->bundleable->code)->toStartWith('B-')
        ->and((float) $bundle->bundleable->price)->toBeGreaterThan(0)
        ->and((float) $bundle->bundleable->rrp)->toBeGreaterThan(0);
});

test('update bundle name/description/rrp', function (Bundle $bundle) {
    $bundle = UpdateBundle::make()->action($bundle, [
        'name'        => 'Updated Combo',
        'description' => 'Updated description',
        'rrp'         => 200,
    ]);

    expect($bundle->bundleable->name)->toBe('Updated Combo')
        ->and($bundle->bundleable->description)->toBe('Updated description')
        ->and((float) $bundle->bundleable->rrp)->toEqual(200);

    return $bundle;
})->depends('store bundle with explicit price/rrp/code');

test('update bundle products recalculates items and price', function (Bundle $bundle) {
    $bundle = UpdateBundle::make()->action($bundle, [
        'products' => [
            ['product_id' => $this->product->id, 'quantity' => 3],
        ],
    ]);

    expect($bundle->items()->count())->toBe(1)
        ->and($bundle->items()->first()->quantity)->toBe(3);

    return $bundle;
})->depends('store bundle with explicit price/rrp/code');

test('update bundle payload items updates quantities and price', function (Bundle $bundle) {
    $bundleItem = $bundle->items()->first();

    $bundle = UpdateBundle::make()->action($bundle, [
        'payloadItems' => [
            ['bundle_item_id' => $bundleItem->id, 'quantity' => 5],
        ],
    ]);

    $bundleItem->refresh();
    expect($bundleItem->quantity)->toBe(5);
})->depends('update bundle products recalculates items and price');

test('store or update bundle dispatches to store when no id given', function () {
    $bundle = StoreOrUpdateBundle::make()->action(
        $this->customerSalesChannel,
        [
            'name'     => 'Fresh Bundle',
            'products' => [
                ['product_id' => $this->product->id, 'quantity' => 1],
            ],
        ]
    );

    expect($bundle)->toBeInstanceOf(Bundle::class)
        ->and($bundle->bundleable->name)->toBe('Fresh Bundle');

    return $bundle;
});

test('store or update bundle dispatches to update when id given', function (Bundle $bundle) {
    $updated = StoreOrUpdateBundle::make()->action(
        $this->customerSalesChannel,
        [
            'id'   => $bundle->id,
            'name' => 'Renamed Bundle',
        ]
    );

    expect($updated->id)->toBe($bundle->id)
        ->and($updated->bundleable->name)->toBe('Renamed Bundle');
})->depends('store or update bundle dispatches to store when no id given');

test('calculate bundle item price details', function () {
    $result = CalculateBundleItemPriceDetails::make()->handle(
        $this->customerSalesChannel,
        [
            'products' => [
                ['product_id' => $this->product->id, 'quantity' => 1],
                ['product_id' => $this->secondProduct->id, 'quantity' => 2],
            ],
        ]
    );

    expect($result)->toBeArray()
        ->and($result['products'])->toHaveCount(2)
        ->and($result['total_price'])->toBeFloat()
        ->and($result['total_rrp'])->toBeFloat()
        ->and($result)->toHaveKeys(['products', 'total_price', 'total_bundle_price', 'total_rrp', 'profit', 'profit_percentage']);
});

test('update bundle on manual platform sets platform flags', function () {
    $manualPlatform = $this->group->platforms()->where('type', PlatformTypeEnum::MANUAL)->first();

    $manualChannel = StoreCustomerSalesChannel::make()->action(
        $this->customer,
        $manualPlatform,
        []
    );

    $bundle = StoreBundle::make()->action(
        $manualChannel,
        [
            'name'     => 'Manual Bundle',
            'products' => [
                ['product_id' => $this->product->id, 'quantity' => 1],
            ],
        ]
    );

    UpdateBundle::make()->action($bundle, ['name' => 'Manual Bundle Updated']);

    $bundle->refresh();
    expect($bundle->platform_status)->toBeTrue()
        ->and($bundle->has_valid_platform_product_id)->toBeTrue()
        ->and($bundle->exist_in_platform)->toBeTrue();
});

test('update bundle products updates existing item quantity', function () {
    $channel = StoreCustomerSalesChannel::make()->action(
        $this->customer,
        $this->group->platforms()->where('type', PlatformTypeEnum::SHOPIFY)->first(),
        ['reference' => 'qty_update_channel']
    );

    $bundle = StoreBundle::make()->action(
        $channel,
        [
            'name'     => 'Qty Update Bundle',
            'products' => [
                ['product_id' => $this->product->id, 'quantity' => 1],
                ['product_id' => $this->secondProduct->id, 'quantity' => 1],
            ],
        ]
    );

    $bundleItemBefore = BundleItem::where('bundle_id', $bundle->id)
        ->where('item_id', $this->product->id)
        ->first();

    expect($bundleItemBefore->quantity)->toBe(1);

    UpdateBundle::make()->action($bundle, [
        'products' => [
            ['product_id' => $this->product->id, 'quantity' => 5],
        ],
    ]);

    $bundleItemBefore->refresh();
    expect($bundleItemBefore->quantity)->toBe(5);
});

test('bundle action rules return expected keys', function () {
    expect(StoreBundle::make()->rules())
        ->toHaveKeys(['products', 'products.*.product_id', 'products.*.quantity']);

    expect(UpdateBundle::make()->rules())
        ->toHaveKeys(['payloadItems', 'products']);

    expect(StoreOrUpdateBundle::make()->rules())
        ->toBeArray();

    $calcRules = CalculateBundleItemPriceDetails::make()->rules();
    expect($calcRules)->toHaveKeys(['products.*.product_id', 'products.*.quantity']);
});

test('delete bundle discontinues product and clears flags', function (Bundle $bundle) {
    DeleteBundle::make()->handle($bundle);

    $bundle->refresh();
    expect($bundle->status)->toBeFalse()
        ->and($bundle->platform_status)->toBeFalse()
        ->and($bundle->exist_in_platform)->toBeFalse()
        ->and($bundle->bundleable->state)->toBe(ProductStateEnum::DISCONTINUED);
})->depends('store or update bundle dispatches to store when no id given');
