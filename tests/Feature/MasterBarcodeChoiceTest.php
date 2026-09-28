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
use App\Actions\Goods\Barcode\AssignNextBarcodeToTradeUnit;
use App\Actions\Goods\Barcode\Json\GetNextFreeBarcode;
use App\Actions\Goods\Barcode\StoreBarcode;
use App\Actions\Maintenance\Goods\RepairBarcodesStatus;
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
use App\Models\Goods\ModelHasBarcode;
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

    $this->poolBarcode = '20'.str_pad((string)random_int(0, 99999999999), 11, '0', STR_PAD_LEFT);
    $storeBarcode($this->poolBarcode);

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

    Product::where('shop_id', $this->shop->id)
        ->whereIn('barcode', [$this->lampBarcode, $this->fittingBarcode, $this->bundleBarcode])
        ->update(['barcode' => null]);
});

test('the composition view of a bundle takes its barcode from the pool, not from a member', function () {
    $blueprint = EditMasterProductComposition::make()->getBlueprint($this->masterAsset);

    $barcodeField = collect($blueprint)->firstWhere('label', __('Barcode'))['fields']['barcode'];

    expect($barcodeField['type'])->toBe('barcode_choice')
        ->and($barcodeField['options']['options'])->toBe([])
        ->and($barcodeField['options']['nextFreeRoute']['name'])->toBe('grp.json.barcodes.next_free');
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

test('a master selling a fraction of one trade unit gets its own pool barcode that the hydrator keeps', function () {
    $this->lamp->updateQuietly(['is_divisible' => true]);

    UpdateMasterAsset::make()->action($this->masterAsset, [
        'trade_units' => [
            ['id' => $this->lamp->id, 'quantity' => 0.01],
        ],
    ]);
    \App\Actions\Catalogue\Product\SyncProductTradeUnits::run($this->product, [
        ['id' => $this->lamp->id, 'quantity' => 0.01],
    ]);

    $barcodeSection = collect(EditMasterProductComposition::make()->getBlueprint($this->masterAsset->refresh()))->firstWhere('label', __('Barcode'));

    expect($barcodeSection['fields']['barcode']['options']['nextFreeRoute']['name'])->toBe('grp.json.barcodes.next_free');

    UpdateMasterAsset::make()->action($this->masterAsset, ['barcode' => $this->poolBarcode]);
    \App\Actions\Catalogue\Product\Hydrators\ProductHydrateBarcodeFromTradeUnit::run($this->product->refresh());

    expect($this->product->refresh()->barcode)->toBe($this->poolBarcode);
});

test('the barcode chosen on the master reaches its products and is marked hand set', function () {
    UpdateMasterAsset::make()->action($this->masterAsset, ['barcode' => $this->poolBarcode]);

    expect($this->masterAsset->refresh()->barcode)->toBe($this->poolBarcode)
        ->and($this->masterAsset->independent_barcode)->toBeTrue()
        ->and($this->product->refresh()->barcode)->toBe($this->poolBarcode);
});

test('a pool barcode saved on a master is booked to it and stays used when removed', function () {
    UpdateMasterAsset::make()->action($this->masterAsset, ['barcode' => $this->poolBarcode]);

    $barcode = Barcode::where('group_id', $this->group->id)->where('number', $this->poolBarcode)->first();

    expect($barcode->status)->toBe(BarcodeStatusEnum::USED)
        ->and(ModelHasBarcode::where('barcode_id', $barcode->id)->where('model_type', 'MasterAsset')->where('model_id', $this->masterAsset->id)->value('status'))->toBeTrue();

    UpdateMasterAsset::make()->action($this->masterAsset->refresh(), ['barcode' => null]);

    expect($barcode->refresh()->status)->toBe(BarcodeStatusEnum::USED)
        ->and(ModelHasBarcode::where('barcode_id', $barcode->id)->where('status', true)->exists())->toBeFalse()
        ->and(Barcode::where('id', $barcode->id)->free()->exists())->toBeFalse();
});

test('a member trade unit barcode is refused as the bundle GTIN', function () {
    UpdateMasterAsset::make()->action($this->masterAsset, ['barcode' => $this->fittingBarcode]);
})->throws(Illuminate\Validation\ValidationException::class);

test('the next free barcode skips a number a trade unit carries even when marked available', function () {
    Barcode::where('group_id', $this->group->id)->where('number', $this->lampBarcode)->update(['status' => BarcodeStatusEnum::AVAILABLE]);

    $next = GetNextFreeBarcode::make()->handle($this->group);

    expect($next)->not->toBeNull()
        ->and($next->number)->not->toBe($this->lampBarcode)
        ->and(Barcode::where('group_id', $this->group->id)->where('number', $this->lampBarcode)->free()->exists())->toBeFalse()
        ->and(Barcode::where('group_id', $this->group->id)->where('number', $this->poolBarcode)->free()->exists())->toBeTrue();
});

test('a trade unit without a barcode is given the next free one from the pool', function () {
    $tradeUnit = StoreTradeUnit::make()->action(group(), array_merge(TradeUnit::factory()->definition(), [
        'code' => 'Pool-'.substr(uniqid(), -6),
        'name' => 'Needs a barcode',
    ]));

    $expected = GetNextFreeBarcode::make()->handle($this->group);

    $barcode = AssignNextBarcodeToTradeUnit::make()->action($tradeUnit);

    expect($barcode->number)->toBe($expected->number)
        ->and($barcode->refresh()->status)->toBe(BarcodeStatusEnum::USED)
        ->and($tradeUnit->refresh()->barcode)->toBe($barcode->number)
        ->and($tradeUnit->barcode_id)->toBe($barcode->id);

    expect(fn () => AssignNextBarcodeToTradeUnit::make()->action($tradeUnit))
        ->toThrow(Illuminate\Validation\ValidationException::class);
});

test('the status repair marks a carried barcode used and leaves a free one available', function () {
    Barcode::where('group_id', $this->group->id)->where('number', $this->lampBarcode)->update(['status' => BarcodeStatusEnum::AVAILABLE]);

    RepairBarcodesStatus::make()->handle($this->group, true);

    expect(Barcode::where('group_id', $this->group->id)->where('number', $this->lampBarcode)->value('status'))->toBe(BarcodeStatusEnum::USED)
        ->and(Barcode::where('group_id', $this->group->id)->where('number', $this->poolBarcode)->value('status'))->toBe(BarcodeStatusEnum::AVAILABLE);
});

test('a bundle GTIN we do not own is refused', function () {
    UpdateMasterAsset::make()->action($this->masterAsset, ['barcode' => '9999999999999']);
})->throws(Illuminate\Validation\ValidationException::class);

test('a product that has chosen its own barcode ignores the master', function () {
    $this->product->updateQuietly([
        'barcode'             => $this->bundleBarcode,
        'independent_barcode' => true,
    ]);

    UpdateMasterAsset::make()->action($this->masterAsset, ['barcode' => $this->poolBarcode]);

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

test('a trade unit without a barcode is still listed on the product edit screen so staff see why it cannot be picked', function () {
    $cap = StoreTradeUnit::make()->action(group(), array_merge(TradeUnit::factory()->definition(), [
        'code'    => 'Cap-'.substr(uniqid(), -6),
        'name'    => 'Bottle cap',
        'barcode' => $this->bundleBarcode,
    ]));
    $cap->updateQuietly(['barcode' => null]);

    \App\Actions\Catalogue\Product\SyncProductTradeUnits::run($this->product, [
        ['id' => $this->lamp->id, 'quantity' => 1],
        ['id' => $cap->id, 'quantity' => 1],
    ]);

    $barcodeField = collect(EditProduct::make()->getBlueprint($this->product->refresh()))
        ->firstWhere('label', __('Properties'))['fields']['barcode'];

    expect(collect($barcodeField['options']['withoutBarcode'])->pluck('code')->all())->toBe([$cap->code]);
});

test('a master barcode already on another listing in one of its shops is refused', function () {
    $other = StoreProduct::make()->action(
        $this->product->family,
        array_merge(Product::factory()->definition(), [
            'code'  => 'BCP'.substr(uniqid(), -8),
            'price' => 10,
        ])
    );
    $other->updateQuietly(['barcode' => $this->lampBarcode]);

    expect(fn () => UpdateMasterAsset::make()->action($this->masterAsset, ['barcode' => $this->lampBarcode]))
        ->toThrow(Illuminate\Validation\ValidationException::class)
        ->and($this->product->refresh()->barcode)->not->toBe($this->lampBarcode);
});
