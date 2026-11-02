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
use App\Actions\Dropshipping\Portfolio\DeletePortfolio;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\Bundle;
use App\Models\Dropshipping\BundleItem;
use App\Models\Dropshipping\Portfolio;

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

    [, $this->product] = createProduct($this->shop);
    $this->tradeUnit = $this->product->tradeUnits()->firstOrFail();

    $secondProductData = array_merge(
        Product::factory()->definition(),
        [
            'trade_units' => [
                [
                    'id'       => $this->tradeUnit->id,
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
        ->toHaveKeys(['products', 'products.*.product_id', 'products.*.quantity'])
        ->and(UpdateBundle::make()->rules())
        ->toHaveKeys(['payloadItems', 'products'])
        ->and(StoreOrUpdateBundle::make()->rules())
        ->toBeArray();

    $calcRules = CalculateBundleItemPriceDetails::make()->rules();
    expect($calcRules)->toHaveKeys(['products.*.product_id', 'products.*.quantity']);
});

test('delete bundle discontinues product and clears flags', function (Bundle $bundle) {
    expect($bundle->bundleable->portfolios()->where('status', true)->exists())->toBeTrue();

    DeleteBundle::make()->handle($bundle);

    $bundle->refresh();
    expect($bundle->status)->toBeFalse()
        ->and($bundle->platform_status)->toBeFalse()
        ->and($bundle->exist_in_platform)->toBeFalse()
        ->and($bundle->bundleable->state)->toBe(ProductStateEnum::DISCONTINUED)
        ->and($bundle->bundleable->portfolios()->where('status', true)->exists())->toBeFalse();
})->depends('store or update bundle dispatches to store when no id given');

test('deleting a bundle portfolio discontinues the bundle instead of leaving it without a portfolio', function () {
    $channel = StoreCustomerSalesChannel::make()->action(
        $this->customer,
        $this->group->platforms()->where('type', PlatformTypeEnum::MANUAL)->first(),
        ['reference' => 'delete_bundle_portfolio_channel']
    );

    $bundle = StoreBundle::make()->action($channel, [
        'products' => [
            ['product_id' => $this->product->id, 'quantity' => 1],
        ],
    ]);

    $portfolio = $bundle->bundleable->portfolios()->where('bundle_id', $bundle->id)->firstOrFail();

    DeletePortfolio::make()->action($portfolio);

    $bundle->refresh();
    expect(Portfolio::find($portfolio->id))->toBeNull()
        ->and($bundle->status)->toBeFalse()
        ->and($bundle->bundleable->state)->toBe(ProductStateEnum::DISCONTINUED);
});

test('two components made of the same trade unit both count towards the bundle instead of one replacing the other', function () {
    $channel = StoreCustomerSalesChannel::make()->action(
        $this->customer,
        $this->group->platforms()->where('type', PlatformTypeEnum::SHOPIFY)->first(),
        ['reference' => 'shared_trade_unit_channel']
    );

    $sharedTradeUnitId = $this->tradeUnit->id;

    $bundle = StoreBundle::make()->action($channel, [
        'name'     => 'Shared Trade Unit Bundle',
        'products' => [
            ['product_id' => $this->product->id, 'quantity' => 1],
            ['product_id' => $this->secondProduct->id, 'quantity' => 2],
        ],
    ]);

    $bundleTradeUnits = $bundle->bundleable->tradeUnits;

    expect($bundleTradeUnits)->toHaveCount(1)
        ->and($bundleTradeUnits->first()->id)->toBe($sharedTradeUnitId)
        ->and((float) $bundleTradeUnits->first()->pivot->quantity)->toEqual(3.0);
});

test('a component added after the bundle was created reaches the bundle product and its portfolio sku', function () {
    $channel = StoreCustomerSalesChannel::make()->action(
        $this->customer,
        $this->group->platforms()->where('type', PlatformTypeEnum::SHOPIFY)->first(),
        ['reference' => 'late_component_channel']
    );

    $stock = \App\Actions\Goods\Stock\StoreStock::make()->action($this->group, \App\Models\Goods\Stock::factory()->definition());
    $otherTradeUnit = $stock->tradeUnits()->firstOrFail();

    $family = $this->shop->productCategories()
        ->where('type', \App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum::FAMILY)
        ->first();

    $thirdProduct = \App\Actions\Catalogue\Product\StoreProduct::make()->action($family, array_merge(
        Product::factory()->definition(),
        [
            'trade_units' => [['id' => $otherTradeUnit->id, 'quantity' => 1]],
            'price'       => 10,
            'rrp'         => 20,
        ]
    ));
    $thirdProduct = \App\Actions\Catalogue\Product\UpdateProduct::make()->action($thirdProduct, ['state' => ProductStateEnum::ACTIVE]);

    $bundle = StoreBundle::make()->action($channel, [
        'name'     => 'Grows Later Bundle',
        'products' => [
            ['product_id' => $this->product->id, 'quantity' => 1],
        ],
    ]);

    $portfolio = \App\Models\Dropshipping\Portfolio::where('bundle_id', $bundle->id)->firstOrFail();
    $skuOnCreation = $portfolio->sku;

    UpdateBundle::make()->action($bundle, [
        'products' => [
            ['product_id' => $this->product->id, 'quantity' => 1],
            ['product_id' => $thirdProduct->id, 'quantity' => 1],
        ],
    ]);

    $bundleTradeUnitIds = $bundle->bundleable->refresh()->tradeUnits->pluck('id');

    expect($bundle->items()->count())->toBe(2)
        ->and($bundleTradeUnitIds)->toContain($otherTradeUnit->id)
        ->and($bundleTradeUnitIds)->toHaveCount(2)
        ->and($portfolio->refresh()->sku)->not->toBe($skuOnCreation)
        ->and($portfolio->sku)->toBe($stock->slug);
});

test('a bundle holding several of a component made of several trade units multiplies the two quantities', function () {
    $channel = StoreCustomerSalesChannel::make()->action(
        $this->customer,
        $this->group->platforms()->where('type', PlatformTypeEnum::SHOPIFY)->first(),
        ['reference' => 'multi_trade_unit_channel']
    );

    $tradeUnitId = $this->tradeUnit->id;

    $family = $this->shop->productCategories()
        ->where('type', \App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum::FAMILY)
        ->first();

    $threePack = \App\Actions\Catalogue\Product\StoreProduct::make()->action($family, array_merge(
        Product::factory()->definition(),
        [
            'trade_units' => [['id' => $tradeUnitId, 'quantity' => 3]],
            'price'       => 10,
            'rrp'         => 20,
        ]
    ));

    $bundle = StoreBundle::make()->action($channel, [
        'name'     => 'Three Pack Bundle',
        'products' => [
            ['product_id' => $threePack->id, 'quantity' => 2],
        ],
    ]);

    expect((float) $bundle->bundleable->tradeUnits->first()->pivot->quantity)->toEqual(6.0);
});

test('a bundle of several of one stock gets its own sku instead of the sku of the single', function () {
    $channel = StoreCustomerSalesChannel::make()->action(
        $this->customer,
        $this->group->platforms()->where('type', PlatformTypeEnum::TIKTOK)->first(),
        ['reference' => 'multi_pack_sku_channel']
    );

    $stock     = \App\Actions\Goods\Stock\StoreStock::make()->action($this->group, \App\Models\Goods\Stock::factory()->definition());
    $tradeUnit = $stock->tradeUnits()->firstOrFail();

    $family = $this->shop->productCategories()
        ->where('type', \App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum::FAMILY)
        ->first();

    $single = \App\Actions\Catalogue\Product\StoreProduct::make()->action($family, array_merge(
        Product::factory()->definition(),
        [
            'trade_units' => [['id' => $tradeUnit->id, 'quantity' => 1]],
            'price'       => 10,
            'rrp'         => 20,
        ]
    ));
    $single = \App\Actions\Catalogue\Product\UpdateProduct::make()->action($single, ['state' => ProductStateEnum::ACTIVE]);

    $twoPack = StoreBundle::make()->action($channel, [
        'name'     => 'Two Pack Bundle',
        'products' => [['product_id' => $single->id, 'quantity' => 2]],
    ]);

    $mixed = StoreBundle::make()->action($channel, [
        'name'     => 'One Each Bundle',
        'products' => [['product_id' => $single->id, 'quantity' => 1]],
    ]);

    $twoPackSku = \App\Models\Dropshipping\Portfolio::where('bundle_id', $twoPack->id)->firstOrFail()->sku;

    expect($twoPackSku)->toBe($stock->slug.'-x2')
        ->and(\App\Models\Dropshipping\Portfolio::where('bundle_id', $mixed->id)->firstOrFail()->sku)->toBe($stock->slug)
        ->and(\App\Actions\Dropshipping\Portfolio\StorePortfolio::make()->findProductBySKU($twoPackSku, $this->shop)?->id)->toBe($twoPack->bundleable->id);
});

test('a customer bundle stays out of the public shop data feed', function () {
    $bundle = StoreBundle::make()->action(
        $this->customerSalesChannel,
        [
            'products' => [
                ['product_id' => $this->product->id, 'quantity' => 1],
            ],
        ]
    );
    $bundle->bundleable->update(['state' => ProductStateEnum::ACTIVE]);

    $feedProductIds = (new \App\Exports\Marketing\ProductsInShopExport($this->shop))->query()->pluck('id');

    expect($feedProductIds)->not->toContain($bundle->bundleable_id);
});

test('editing a bundle keeps exactly the pictures chosen, so a replaced component no longer shows its old picture', function () {
    $bundle = StoreBundle::make()->action($this->customerSalesChannel, [
        'name'     => 'Picture Swap Bundle',
        'products' => [
            ['product_id' => $this->product->id, 'quantity' => 1],
        ],
    ]);

    $storeImage = function (string $name) {
        $path = tempnam(sys_get_temp_dir(), 'bundle').'.png';
        $image = imagecreatetruecolor(2, 2);
        imagepng($image, $path);

        return \App\Actions\Helpers\Media\StoreMediaFromFile::run($this->product, [
            'path'         => $path,
            'originalName' => $name,
            'extension'    => 'png',
            'checksum'     => md5($name.md5_file($path)),
        ], 'products', 'image');
    };

    $oldCandle = $storeImage('old-candle.png');
    $newCandle = $storeImage('new-candle.png');

    UpdateBundle::make()->action($bundle, ['images' => [['id' => $oldCandle->id, 'is_main' => true]]]);
    UpdateBundle::make()->action($bundle, ['images' => [['id' => $oldCandle->id, 'is_main' => true]]]);

    $bundleProduct = $bundle->bundleable->refresh();
    expect($bundleProduct->images()->pluck('media.id')->all())->toBe([$oldCandle->id])
        ->and($bundleProduct->image_id)->toBe($oldCandle->id);

    UpdateBundle::make()->action($bundle, ['images' => [['id' => $newCandle->id, 'is_main' => true]]]);

    $bundleProduct->refresh();
    expect($bundleProduct->images()->pluck('media.id')->all())->toBe([$newCandle->id])
        ->and($bundleProduct->image_id)->toBe($newCandle->id);
});

test('a component swapped for another one moves the bundle portfolio sku to the new component set', function () {
    $channel = StoreCustomerSalesChannel::make()->action(
        $this->customer,
        $this->group->platforms()->where('type', PlatformTypeEnum::SHOPIFY)->first(),
        ['reference' => 'swapped_component_channel']
    );

    $family = $this->shop->productCategories()
        ->where('type', \App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum::FAMILY)
        ->first();

    $makeComponent = function () use ($family): array {
        $stock = \App\Actions\Goods\Stock\StoreStock::make()->action($this->group, \App\Models\Goods\Stock::factory()->definition());
        $product = \App\Actions\Catalogue\Product\StoreProduct::make()->action($family, array_merge(
            Product::factory()->definition(),
            [
                'trade_units' => [['id' => $stock->tradeUnits()->firstOrFail()->id, 'quantity' => 1]],
                'price'       => 10,
                'rrp'         => 20,
            ]
        ));

        return [$stock, \App\Actions\Catalogue\Product\UpdateProduct::make()->action($product, ['state' => ProductStateEnum::ACTIVE])];
    };

    [$oldStock, $oldComponent] = $makeComponent();
    [$newStock, $newComponent] = $makeComponent();

    $bundle = StoreBundle::make()->action($channel, [
        'name'     => 'Swapped Component Bundle',
        'products' => [
            ['product_id' => $this->product->id, 'quantity' => 1],
            ['product_id' => $oldComponent->id, 'quantity' => 1],
        ],
    ]);

    $portfolio = \App\Models\Dropshipping\Portfolio::where('bundle_id', $bundle->id)->firstOrFail();

    expect($portfolio->sku)->toContain($oldStock->slug);

    UpdateBundle::make()->action($bundle, [
        'rrp'      => 41.45,
        'products' => [
            ['product_id' => $this->product->id, 'quantity' => 1],
            ['product_id' => $newComponent->id, 'quantity' => 1],
        ],
    ]);

    expect($bundle->items()->pluck('item_id')->all())->toEqualCanonicalizing([$this->product->id, $newComponent->id])
        ->and($portfolio->refresh()->sku)->toContain($newStock->slug)
        ->and($portfolio->sku)->not->toContain($oldStock->slug);
});

test('a bundle left holding one piece of a pack is given the whole pack, without touching its price or its sku', function () {
    $channel = StoreCustomerSalesChannel::make()->action(
        $this->customer,
        $this->group->platforms()->where('type', PlatformTypeEnum::SHOPIFY)->first(),
        ['reference' => 'repair_fewer_trade_units_channel']
    );

    $family = $this->shop->productCategories()
        ->where('type', \App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum::FAMILY)
        ->first();

    $twelvePack = \App\Actions\Catalogue\Product\StoreProduct::make()->action($family, array_merge(
        Product::factory()->definition(),
        [
            'trade_units' => [['id' => $this->tradeUnit->id, 'quantity' => 12]],
            'price'       => 10,
            'rrp'         => 20,
        ]
    ));

    $bundle = StoreBundle::make()->action($channel, [
        'name'     => 'Bundle Created Before The Pack Was Multiplied',
        'products' => [
            ['product_id' => $twelvePack->id, 'quantity' => 1],
        ],
    ]);

    $bundleProduct = $bundle->bundleable;
    $portfolio     = \App\Models\Dropshipping\Portfolio::where('item_type', 'Product')->where('item_id', $bundleProduct->id)->firstOrFail();
    $priceBefore   = $bundleProduct->price;
    $skuBefore     = $portfolio->sku;

    $holdOnTheBundle = fn (float $quantity) => $bundleProduct->tradeUnits()->updateExistingPivot($this->tradeUnit->id, ['quantity' => $quantity]);
    $heldByTheBundle = fn () => (float) $bundleProduct->tradeUnits()->firstOrFail()->pivot->quantity;
    $repair          = \App\Actions\Maintenance\Dropshipping\RepairBundlesHoldingFewerTradeUnits::make();

    $holdOnTheBundle(1);

    $dryRun = $repair->handle($bundle, true);
    expect($dryRun['outcome'])->toBe('repaired')
        ->and($dryRun['changes'][0]['from'])->toEqual(1.0)
        ->and($dryRun['changes'][0]['to'])->toEqual(12.0)
        ->and($heldByTheBundle())->toEqual(1.0);

    expect($repair->handle($bundle)['outcome'])->toBe('repaired')
        ->and($heldByTheBundle())->toEqual(12.0)
        ->and($bundleProduct->refresh()->price)->toEqual($priceBefore)
        ->and($portfolio->refresh()->sku)->toBe($skuBefore)
        ->and($repair->handle($bundle)['outcome'])->toBe('correct')
        ->and($repair->bundlesQuery($this->shop, $bundleProduct->code)->pluck('id')->all())->toBe([$bundle->id]);

    $holdOnTheBundle(24);

    expect($repair->handle($bundle)['outcome'])->toBe('needs_review')
        ->and($heldByTheBundle())->toEqual(24.0);
});

test('a bundle holding a whole pack where its component is one piece of it can be given back the fraction its items say', function () {
    $channel = StoreCustomerSalesChannel::make()->action(
        $this->customer,
        $this->group->platforms()->where('type', PlatformTypeEnum::SHOPIFY)->first(),
        ['reference' => 'rederive_fraction_channel']
    );

    $family = $this->shop->productCategories()
        ->where('type', \App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum::FAMILY)
        ->first();

    $this->tradeUnit->update(['is_divisible' => false]);

    $onePieceOfTen = \App\Actions\Catalogue\Product\StoreProduct::make()->action($family, array_merge(
        Product::factory()->definition(),
        [
            'trade_units' => [['id' => $this->tradeUnit->id, 'quantity' => 0.1]],
            'price'       => 10,
            'rrp'         => 20,
        ]
    ));

    $bundle = StoreBundle::make()->action($channel, [
        'name'     => 'Bundle Of One Piece',
        'products' => [
            ['product_id' => $onePieceOfTen->id, 'quantity' => 1],
        ],
    ]);

    $bundleProduct   = $bundle->bundleable;
    $heldByTheBundle = fn () => (float) $bundleProduct->tradeUnits()->firstOrFail()->pivot->quantity;
    $repair          = \App\Actions\Maintenance\Dropshipping\RepairBundlesHoldingFewerTradeUnits::make();

    $bundleProduct->tradeUnits()->updateExistingPivot($this->tradeUnit->id, ['quantity' => 1]);

    expect($repair->handle($bundle)['outcome'])->toBe('needs_review')
        ->and($heldByTheBundle())->toEqual(1.0);

    $rederived = $repair->handle($bundle, false, true);

    expect($rederived['outcome'])->toBe('repaired')
        ->and($rederived['changes'][0]['to'])->toEqual(0.1)
        ->and($heldByTheBundle())->toEqual(0.1);
});

test('changing what a component is made of reaches the bundles that hold it', function () {
    $channel = StoreCustomerSalesChannel::make()->action(
        $this->customer,
        $this->group->platforms()->where('type', PlatformTypeEnum::SHOPIFY)->first(),
        ['reference' => 'component_change_channel']
    );

    $family = $this->shop->productCategories()
        ->where('type', \App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum::FAMILY)
        ->first();

    $stock     = \App\Actions\Goods\Stock\StoreStock::make()->action($this->group, \App\Models\Goods\Stock::factory()->definition());
    $tradeUnit = $stock->tradeUnits()->firstOrFail();

    $component = \App\Actions\Catalogue\Product\StoreProduct::make()->action($family, array_merge(
        Product::factory()->definition(),
        [
            'trade_units' => [['id' => $tradeUnit->id, 'quantity' => 3]],
            'price'       => 10,
            'rrp'         => 20,
        ]
    ));

    $bundle = StoreBundle::make()->action($channel, [
        'name'     => 'Bundle Of A Component That Changes',
        'products' => [
            ['product_id' => $component->id, 'quantity' => 2],
        ],
    ]);

    $bundleProduct   = $bundle->bundleable;
    $portfolio       = \App\Models\Dropshipping\Portfolio::where('bundle_id', $bundle->id)->firstOrFail();
    $priceBefore     = $bundleProduct->price;
    $heldByTheBundle = fn () => (float) $bundleProduct->tradeUnits()->firstOrFail()->pivot->quantity;

    expect($heldByTheBundle())->toEqual(6.0);

    \App\Actions\Catalogue\Product\UpdateProduct::make()->action($component, [
        'trade_units' => [['id' => $tradeUnit->id, 'quantity' => 5]],
    ]);

    expect($heldByTheBundle())->toEqual(10.0)
        ->and($bundleProduct->refresh()->price)->toEqual($priceBefore)
        ->and($portfolio->refresh()->sku)->toBe($stock->slug.'-x10');

    $portfolio->update(['platform_product_id' => 'listing-of-the-customer']);

    \App\Actions\Catalogue\Product\UpdateProduct::make()->action($component, [
        'trade_units' => [['id' => $tradeUnit->id, 'quantity' => 4]],
    ]);

    expect($heldByTheBundle())->toEqual(8.0)
        ->and($portfolio->refresh()->sku)->toBe($stock->slug.'-x10');
});

test('a bundle line being refunded lists its components with what one piece of each was charged', function () {
    $bundle = StoreBundle::make()->action($this->customerSalesChannel, [
        'name'     => 'Bundle To Refund',
        'products' => [
            ['product_id' => $this->product->id, 'quantity' => 1],
            ['product_id' => $this->secondProduct->id, 'quantity' => 2],
        ],
    ]);

    $bundleProduct = $bundle->bundleable;
    $componentsValue = $this->product->price + 2 * $this->secondProduct->price;

    $invoiceTransaction = (new \App\Models\Accounting\InvoiceTransaction())->forceFill([
        'model_type' => 'Product',
        'model_id'   => $bundleProduct->id,
        'quantity'   => 2,
        'net_amount' => 180,
    ])->setRelation('model', $bundleProduct);

    $bundleItems = collect((new \App\Http\Resources\Accounting\RefundInProcessTransactionsResource($invoiceTransaction))->bundleItems())->keyBy('code');

    expect($bundleItems)->toHaveCount(2)
        ->and($bundleItems[$this->product->code]['quantity'])->toEqual(2)
        ->and($bundleItems[$this->secondProduct->code]['quantity'])->toEqual(4)
        ->and($bundleItems[$this->secondProduct->code]['net_amount'])->toEqual(round(90 * $this->secondProduct->price / $componentsValue, 2))
        ->and($bundleItems[$this->product->code]['net_amount'] + 2 * $bundleItems[$this->secondProduct->code]['net_amount'])->toEqualWithDelta(90, 0.02);

    $plainLine = (new \App\Models\Accounting\InvoiceTransaction())->forceFill([
        'model_type' => 'Product',
        'quantity'   => 1,
        'net_amount' => 50,
    ])->setRelation('model', $this->secondProduct);

    expect((new \App\Http\Resources\Accounting\RefundInProcessTransactionsResource($plainLine))->bundleItems())->toBe([]);
});

test('a bundle listed in the customer store keeps its products, but its title can still change', function () {
    $bundle = StoreBundle::make()->action($this->customerSalesChannel, [
        'name'     => 'Listed Bundle',
        'products' => [
            ['product_id' => $this->product->id, 'quantity' => 1],
            ['product_id' => $this->secondProduct->id, 'quantity' => 2],
        ],
    ]);

    expect(UpdateBundle::make()->componentsAreLocked($bundle))->toBeFalse();

    $portfolio = Portfolio::where('bundle_id', $bundle->id)->firstOrFail();
    $portfolio->update(['platform_product_id' => 'gid://shopify/Product/1']);
    $skuBefore = $portfolio->sku;

    $sameProducts = [
        ['product_id' => $this->secondProduct->id, 'quantity' => 2],
        ['product_id' => $this->product->id, 'quantity' => 1],
    ];

    UpdateBundle::make()->action($bundle, ['name' => 'Listed Bundle Renamed', 'products' => $sameProducts]);

    expect($bundle->bundleable->refresh()->name)->toBe('Listed Bundle Renamed')
        ->and(UpdateBundle::make()->componentsAreLocked($bundle))->toBeTrue();

    expect(fn () => UpdateBundle::make()->action($bundle, ['products' => [['product_id' => $this->product->id, 'quantity' => 1]]]))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    $bundleItem = $bundle->items()->where('item_id', $this->secondProduct->id)->firstOrFail();
    expect(fn () => UpdateBundle::make()->action($bundle, ['payloadItems' => [['bundle_item_id' => $bundleItem->id, 'quantity' => 3]]]))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    expect($bundle->items()->count())->toBe(2)
        ->and((float) $bundleItem->refresh()->quantity)->toEqual(2.0)
        ->and($portfolio->refresh()->sku)->toBe($skuBefore);
});

test('a bundle that has been ordered keeps its products even when it is not listed anywhere, but one only in a basket does not', function () {
    $bundle = StoreBundle::make()->action($this->customerSalesChannel, [
        'name'     => 'Ordered Bundle',
        'products' => [
            ['product_id' => $this->product->id, 'quantity' => 1],
        ],
    ]);

    $order = \App\Actions\Ordering\Order\StoreOrder::make()->action($this->customer, [
        'reference'        => 'bundle-lock-'.$bundle->id,
        'date'             => date('Y-m-d'),
        'customer_id'      => $this->customer->id,
        'delivery_address' => new \App\Models\Helpers\Address(\App\Models\Helpers\Address::factory()->definition()),
        'billing_address'  => new \App\Models\Helpers\Address(\App\Models\Helpers\Address::factory()->definition()),
    ]);
    $transaction = \App\Actions\Ordering\Transaction\StoreTransaction::make()->action($order, $bundle->bundleable->historicAsset, ['quantity_ordered' => 1]);

    $transaction->update(['state' => \App\Enums\Ordering\Transaction\TransactionStateEnum::CREATING]);
    expect(UpdateBundle::make()->componentsAreLocked($bundle))->toBeFalse();

    $transaction->update(['state' => \App\Enums\Ordering\Transaction\TransactionStateEnum::SUBMITTED]);
    expect(UpdateBundle::make()->componentsAreLocked($bundle))->toBeTrue();

    expect(fn () => UpdateBundle::make()->action($bundle, ['products' => [['product_id' => $this->secondProduct->id, 'quantity' => 1]]]))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('duplicating a bundle makes an unlisted copy with the same products and pictures that can be changed', function () {
    $bundle = StoreBundle::make()->action($this->customerSalesChannel, [
        'name'        => 'Bundle To Duplicate',
        'description' => 'What is inside',
        'rrp'         => 99,
        'products'    => [
            ['product_id' => $this->product->id, 'quantity' => 1],
            ['product_id' => $this->secondProduct->id, 'quantity' => 2],
        ],
    ]);
    $path = tempnam(sys_get_temp_dir(), 'bundle').'.png';
    imagepng(imagecreatetruecolor(2, 2), $path);
    $media = \App\Actions\Helpers\Media\StoreMediaFromFile::run($this->product, [
        'path'         => $path,
        'originalName' => 'duplicate-bundle.png',
        'extension'    => 'png',
        'checksum'     => md5('duplicate-bundle'.md5_file($path)),
    ], 'products', 'image');
    UpdateBundle::make()->action($bundle, ['images' => [['id' => $media->id, 'is_main' => true]]]);
    Portfolio::where('bundle_id', $bundle->id)->update(['platform_product_id' => 'gid://shopify/Product/2']);

    $copy = \App\Actions\Dropshipping\Bundle\DuplicateBundle::make()->action($bundle);

    $quantities = fn (Bundle $bundle) => $bundle->items()->pluck('quantity', 'item_id')->map(fn ($quantity) => (float) $quantity)->sortKeys()->all();

    expect($copy->id)->not->toBe($bundle->id)
        ->and($copy->bundleable_id)->not->toBe($bundle->bundleable_id)
        ->and($copy->bundleable->name)->toBe('Bundle To Duplicate')
        ->and((float) $copy->bundleable->rrp)->toEqual(99.0)
        ->and($copy->bundleable->image_id)->toBe($media->id)
        ->and($quantities($copy))->toBe($quantities($bundle))
        ->and(Portfolio::where('bundle_id', $copy->id)->firstOrFail()->platform_product_id)->toBeNull()
        ->and(UpdateBundle::make()->componentsAreLocked($copy))->toBeFalse();

    UpdateBundle::make()->action($copy, ['products' => [['product_id' => $this->product->id, 'quantity' => 3]]]);

    expect($quantities($copy))->toBe([$this->product->id => 3.0])
        ->and($quantities($bundle))->toBe([$this->product->id => 1.0, $this->secondProduct->id => 2.0]);
});

test('the bundle page data says whether its products are locked', function () {
    $bundle = StoreBundle::make()->action($this->customerSalesChannel, [
        'name'     => 'Resource Bundle',
        'products' => [
            ['product_id' => $this->product->id, 'quantity' => 1],
        ],
    ]);

    expect(\App\Http\Resources\CRM\BundleResource::make($bundle)->resolve())
        ->toHaveKey('components_locked', false);

    Portfolio::where('bundle_id', $bundle->id)->firstOrFail()->update(['platform_product_id' => 'gid://shopify/Product/2']);

    expect(\App\Http\Resources\CRM\BundleResource::make($bundle)->resolve())
        ->toHaveKey('components_locked', true);
});
