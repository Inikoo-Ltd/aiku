<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Catalogue\Product\StoreProduct;
use App\Actions\Catalogue\Product\UI\EditProduct;
use App\Actions\Catalogue\Product\UI\IndexProductsWithDuplicatedBarcode;
use App\Actions\Catalogue\Product\UpdateProduct;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateProductsWithDuplicatedBarcode;
use App\Actions\Goods\Barcode\StoreBarcode;
use App\Actions\Goods\TradeUnit\StoreTradeUnit;
use App\Actions\Masters\MasterAsset\StoreMasterAsset;
use App\Actions\Masters\MasterAsset\UI\EditMasterProductComposition;
use App\Actions\Masters\MasterAsset\UpdateMasterAsset;
use App\Actions\Masters\MasterProductCategory\StoreMasterDepartment;
use App\Actions\Masters\MasterProductCategory\StoreMasterFamily;
use App\Actions\Masters\MasterShop\StoreMasterShop;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Helpers\Barcode\BarcodeStatusEnum;
use App\Enums\Helpers\Barcode\BarcodeTypeEnum;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Barcode;
use App\Models\Helpers\Language;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\patch;

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
        'code' => 'BC'.substr(uniqid(), -6),
        'name' => 'Barcode Master Shop',
    ]);

    $masterDepartment = StoreMasterDepartment::make()->action($this->masterShop, [
        'code' => 'BCD-'.uniqid(),
        'name' => 'dep',
        'type' => MasterProductCategoryTypeEnum::DEPARTMENT,
    ]);

    $this->masterFamily = StoreMasterFamily::make()->action($masterDepartment, [
        'code' => 'BCF-'.uniqid(),
        'name' => 'fam',
        'type' => MasterProductCategoryTypeEnum::FAMILY,
    ]);

    $this->shop->updateQuietly([
        'master_shop_id' => $this->masterShop->id,
        'language_id'    => Language::where('code', 'en')->first()->id,
    ]);

    $storeBarcode = function (string $number) {
        if (Barcode::where('group_id', $this->group->id)->where('number', $number)->exists()) {
            return;
        }

        StoreBarcode::make()->action($this->group, [
            'number' => $number,
            'status' => BarcodeStatusEnum::AVAILABLE,
            'type'   => BarcodeTypeEnum::EAN,
        ]);
    };

    $this->lampBarcode    = '5056368359705';
    $this->fittingBarcode = '5055796574049';
    $this->bundleBarcode  = '5056368348006';
    $storeBarcode($this->lampBarcode);
    $storeBarcode($this->fittingBarcode);
    $storeBarcode($this->bundleBarcode);

    $this->lamp = StoreTradeUnit::make()->action(group(), array_merge(TradeUnit::factory()->definition(), [
        'code'    => 'GamWL-'.substr(uniqid(), -6),
        'name'    => 'DriftGlow Lamp Grey Luna Shade',
        'barcode' => $this->lampBarcode,
    ]));

    $this->fitting = StoreTradeUnit::make()->action(group(), array_merge(TradeUnit::factory()->definition(), [
        'code'    => 'Salt-'.substr(uniqid(), -6),
        'name'    => 'Salt Lamp Fittings - UK',
        'barcode' => $this->fittingBarcode,
    ]));

    $this->masterAsset = StoreMasterAsset::make()->action($this->masterFamily, [
        'code'        => 'BC-AST',
        'name'        => 'driftglow lamp',
        'is_main'     => true,
        'type'        => MasterAssetTypeEnum::PRODUCT,
        'price'       => 10,
        'stocks'      => [],
        'trade_units' => [
            ['id' => $this->lamp->id, 'quantity' => 1],
            ['id' => $this->fitting->id, 'quantity' => 1],
        ],
    ]);

    [, $seed] = createProduct($this->shop);
    $this->product = StoreProduct::make()->action(
        $seed->family,
        array_merge(Product::factory()->definition(), [
            'code'  => 'BCP'.substr(uniqid(), -8),
            'price' => 10,
        ])
    );
    $this->product->updateQuietly(['master_product_id' => $this->masterAsset->id]);
});

test('the composition view offers every member barcode labelled with its trade unit', function () {
    $blueprint = EditMasterProductComposition::make()->getBlueprint($this->masterAsset);

    $barcodeField = collect($blueprint)->firstWhere('label', __('Barcode'))['fields']['barcode'];

    expect($barcodeField['type'])->toBe('barcode_choice')
        ->and(collect($barcodeField['options']['options'])->pluck('value')->all())
        ->toEqualCanonicalizing([$this->lampBarcode, $this->fittingBarcode])
        ->and(collect($barcodeField['options']['options'])->pluck('name')->all())
        ->toContain('Salt Lamp Fittings - UK');
});

test('a master built from one trade unit is offered no choice', function () {
    UpdateMasterAsset::make()->action($this->masterAsset, [
        'trade_units' => [
            ['id' => $this->lamp->id, 'quantity' => 1],
        ],
    ]);

    $blueprint = EditMasterProductComposition::make()->getBlueprint($this->masterAsset->refresh());

    expect(collect($blueprint)->firstWhere('label', __('Barcode')))->toBeNull();
});

test('the barcode chosen on the master reaches its products and is marked hand set', function () {
    UpdateMasterAsset::make()->action($this->masterAsset, ['barcode' => $this->lampBarcode]);

    expect($this->masterAsset->refresh()->barcode)->toBe($this->lampBarcode)
        ->and($this->masterAsset->independent_barcode)->toBeTrue()
        ->and($this->product->refresh()->barcode)->toBe($this->lampBarcode);
});

test('a bundle GTIN we do not own is refused', function () {
    UpdateMasterAsset::make()->action($this->masterAsset, ['barcode' => '9999999999999']);
})->throws(Illuminate\Validation\ValidationException::class);

test('a product that has chosen its own barcode ignores the master', function () {
    $this->product->updateQuietly([
        'barcode'             => $this->bundleBarcode,
        'independent_barcode' => true,
    ]);

    UpdateMasterAsset::make()->action($this->masterAsset, ['barcode' => $this->lampBarcode]);

    expect($this->product->refresh()->barcode)->toBe($this->bundleBarcode);
});

test('the hydrator never touches a hand set barcode', function () {
    $this->product->updateQuietly([
        'barcode'             => $this->bundleBarcode,
        'independent_barcode' => true,
    ]);

    \App\Actions\Catalogue\Product\Hydrators\ProductHydrateBarcodeFromTradeUnit::run($this->product->refresh());

    expect($this->product->refresh()->barcode)->toBe($this->bundleBarcode);
});

test('the product edit screen labels each barcode with its trade unit', function () {
    \App\Actions\Catalogue\Product\SyncProductTradeUnits::run($this->product, [
        ['id' => $this->lamp->id, 'quantity' => 1],
        ['id' => $this->fitting->id, 'quantity' => 1],
    ]);

    $blueprint = EditProduct::make()->getBlueprint($this->product->refresh());

    $barcodeField = collect($blueprint)->firstWhere('label', __('Properties'))['fields']['barcode'];

    expect($barcodeField['type'])->toBe('barcode_choice')
        ->and(collect($barcodeField['options']['options'])->pluck('code')->all())
        ->toContain($this->fitting->code);
});

test('two listings in one shop sharing a barcode are counted and listed', function () {
    $second = StoreProduct::make()->action(
        $this->product->family,
        array_merge(Product::factory()->definition(), [
            'code'  => 'BCP'.substr(uniqid(), -8),
            'price' => 10,
        ])
    );

    $this->product->updateQuietly(['barcode' => $this->lampBarcode]);
    $second->updateQuietly(['barcode' => $this->lampBarcode]);

    ShopHydrateProductsWithDuplicatedBarcode::run($this->shop);

    $listed = IndexProductsWithDuplicatedBarcode::make()->handle($this->shop)->pluck('id')->all();

    expect($listed)->toContain($this->product->id, $second->id)
        ->and($this->shop->refresh()->stats->number_products_with_duplicated_barcode)->toBe(count($listed));
});

test('a listing sharing a barcode can be edited and names the other listing', function () {
    $second = StoreProduct::make()->action(
        $this->product->family,
        array_merge(Product::factory()->definition(), [
            'code'  => 'BCP'.substr(uniqid(), -8),
            'price' => 10,
        ])
    );

    $this->product->updateQuietly(['barcode' => $this->lampBarcode]);
    $second->updateQuietly(['barcode' => $this->lampBarcode]);

    $barcodeField = collect(EditProduct::make()->getBlueprint($this->product->refresh()))
        ->firstWhere('label', __('Properties'))['fields']['barcode'];

    expect($barcodeField['readonly'])->toBeFalse()
        ->and($barcodeField['information'])->toContain($second->code);
});

test('a barcode already on another listing in the shop is refused', function () {
    $second = StoreProduct::make()->action(
        $this->product->family,
        array_merge(Product::factory()->definition(), [
            'code'  => 'BCP'.substr(uniqid(), -8),
            'price' => 10,
        ])
    );

    $this->product->updateQuietly(['barcode' => $this->lampBarcode]);

    UpdateProduct::make()->action($second, ['barcode' => $this->lampBarcode]);
})->throws(Illuminate\Validation\ValidationException::class);

test('a person choosing no barcode keeps the hydrator away from that listing', function () {
    $this->product->updateQuietly(['barcode' => $this->lampBarcode]);

    patch(route('grp.models.product.update', $this->product->id), ['barcode' => null])
        ->assertRedirect();

    expect($this->product->refresh()->barcode)->toBeNull()
        ->and($this->product->independent_barcode)->toBeTrue();
});
