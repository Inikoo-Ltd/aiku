<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Catalogue\Product\StoreProduct;
use App\Actions\Catalogue\Product\UpdateProduct;
use App\Actions\Catalogue\Product\UI\IndexProductsInCatalogue;
use App\Actions\Masters\MasterAsset\StoreMasterAsset;
use App\Actions\Masters\MasterAsset\TaxPresetBasketProgress;
use App\Actions\Masters\MasterAsset\UI\IndexMasterProducts;
use App\Actions\Masters\MasterAsset\UI\EditMasterProduct;
use App\Actions\Masters\MasterAsset\UpdateMasterAsset;
use App\Actions\Masters\MasterProductCategory\StoreMasterDepartment;
use App\Actions\Masters\MasterProductCategory\StoreMasterFamily;
use App\Actions\Masters\MasterShop\StoreMasterShop;
use App\Actions\Ordering\Order\GenerateInvoiceFromOrder;
use App\Actions\Ordering\Order\CalculateOrderTotalAmounts;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\Order\UpdateState\SendOrderToWarehouse;
use App\Actions\Ordering\Order\UpdateState\SubmitOrder;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Helpers\TaxCategory;
use App\Models\Masters\MasterAsset;
use App\Models\Ordering\Order;

use function Pest\Laravel\actingAs;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    list(
        $this->organisation,
        $this->user,
        $this->shop
    ) = createShop();

    $this->group = $this->organisation->group;

    /** Real selling shops are aiku managed; the basket reprice rails are gated on it. */
    $this->shop->updateQuietly(['is_aiku' => true]);

    $this->customer = createCustomer($this->shop);

    list(, $this->standardProduct) = createProduct($this->shop);

    $this->vat20 = TaxCategory::where('name', 'VAT 20%')->where('status', true)->firstOrFail();
    $this->vat0  = TaxCategory::where('name', 'VAT 0%')->firstOrFail();

    actingAs($this->user);
});

/**
 * A tea whose master says "on a VAT 20% order this is VAT 0%", the way UK loose leaf
 * tea is zero rated as food.
 */
function createZeroRatedProduct(Product $sibling, TaxCategory $orderCategory, TaxCategory $lineCategory): Product
{
    $shop = $sibling->shop;

    $existing = Product::where('shop_id', $shop->id)->where('code', 'TEA')->first();
    if ($existing) {
        return $existing;
    }

    $masterShop = StoreMasterShop::make()->action($shop->group, [
        'type' => ShopTypeEnum::B2B,
        'code' => 'VATM',
        'name' => 'vat master shop',
    ]);

    $masterFamily = StoreMasterFamily::make()->action(
        StoreMasterDepartment::make()->action($masterShop, [
            'code' => 'VATM-DEP',
            'name' => 'dep',
            'type' => MasterProductCategoryTypeEnum::DEPARTMENT,
        ]),
        [
            'code' => 'VATM-FAM',
            'name' => 'fam',
            'type' => MasterProductCategoryTypeEnum::FAMILY,
        ]
    );

    $masterAsset = StoreMasterAsset::make()->action($masterFamily, [
        'code'    => 'VATM-TEA',
        'name'    => 'loose leaf tea',
        'is_main' => true,
        'type'    => MasterAssetTypeEnum::PRODUCT,
        'price'   => 50,
        'stocks'  => [],
    ]);

    $masterAsset->updateQuietly([
        'tax_category' => [$orderCategory->id => $lineCategory->id],
    ]);

    $product = StoreProduct::make()->action(
        $sibling->family,
        array_merge(Product::factory()->definition(), [
            'code'        => 'TEA',
            'price'       => 50,
            'trade_units' => [],
        ])
    );
    $product = UpdateProduct::make()->action($product, ['state' => ProductStateEnum::ACTIVE]);

    $product->asset->updateQuietly(['master_asset_id' => $masterAsset->id]);
    $product->updateQuietly(['master_product_id' => $masterAsset->id]);

    return $product->refresh();
}

test('a line takes its master asset tax category, the rest take the order one', function () {
    $order = StoreOrder::make()->action($this->customer, []);
    $order->updateQuietly(['tax_category_id' => $this->vat20->id]);
    $order->refresh();

    $tea = createZeroRatedProduct($this->standardProduct, $this->vat20, $this->vat0);

    StoreTransaction::make()->action($order, $this->standardProduct->historicAsset, ['quantity_ordered' => 1]);
    StoreTransaction::make()->action($order, $tea->historicAsset, ['quantity_ordered' => 1]);

    $order->refresh();

    $taxCategoryIds = $order->transactions->pluck('tax_category_id', 'asset_id');

    expect($taxCategoryIds[$this->standardProduct->asset_id])->toBe($this->vat20->id)
        ->and($taxCategoryIds[$tea->asset_id])->toBe($this->vat0->id)
        ->and((float)$order->net_amount)->toBe(150.0)
        ->and((float)$order->tax_amount)->toBe(20.0)
        ->and((float)$order->total_amount)->toBe(170.0);

    return $order;
});

test('the order pdf totals show one row per rate', function (Order $order) {
    $breakdown = $order->taxBreakdown();

    expect($breakdown)->toHaveCount(2)
        ->and(array_sum(array_column($breakdown, 'net_amount')))->toBe(150.0)
        ->and(array_sum(array_column($breakdown, 'tax_amount')))->toBe(20.0);

    return $order;
})->depends('a line takes its master asset tax category, the rest take the order one');

test('the invoice inherits the line rates', function (Order $order) {
    createWarehouse();
    SubmitOrder::make()->action($order);
    $deliveryNote = SendOrderToWarehouse::make()->action($order, []);

    $order->refresh();
    $totals = GenerateInvoiceFromOrder::make()->recalculateTotals($order, $deliveryNote);

    expect($totals['net_amount'])->toBe(150.0)
        ->and($totals['tax_amount'])->toBe(20.0)
        ->and($totals['total_amount'])->toBe(170.0);
})->depends('a line takes its master asset tax category, the rest take the order one');

test('a credit note refunds tax at the line rate, not the order rate', function () {
    $order = StoreOrder::make()->action($this->customer, []);
    $order->updateQuietly(['tax_category_id' => $this->vat20->id]);

    $lines = collect([
        (object)['tax_category_id' => $this->vat20->id, 'net_amount' => -100],
        (object)['tax_category_id' => $this->vat0->id, 'net_amount' => -50],
    ]);

    $breakdown = $order->getTaxBreakdown($lines);

    expect(array_sum(array_column($breakdown, 'tax_amount')))->toBe(-20.0)
        ->and(array_sum(array_column($breakdown, 'net_amount')))->toBe(-150.0);
});

test('an order level discount is split across the rates and still adds up', function () {
    $order = StoreOrder::make()->action($this->customer, []);
    $order->updateQuietly(['tax_category_id' => $this->vat20->id]);

    $lines = collect([
        (object)['tax_category_id' => $this->vat20->id, 'net_amount' => 100],
        (object)['tax_category_id' => $this->vat0->id, 'net_amount' => 50],
    ]);

    $breakdown = $order->getTaxBreakdown($lines, 10.01);

    $net = round(array_sum(array_column($breakdown, 'net_amount')), 2);
    $tax = round(array_sum(array_column($breakdown, 'tax_amount')), 2);

    expect($net)->toBe(139.99)
        ->and($tax)->toBe(18.67);
});

test('staff pick a preset and the tax category map is derived from it', function () {
    $masterAsset = createZeroRatedProduct($this->standardProduct, $this->vat20, $this->vat0)
        ->asset->masterAsset;

    UpdateMasterAsset::make()->action($masterAsset, ['tax_preset' => 'food']);
    $masterAsset->refresh();

    /** Food expands to all three discountable categories, and the money map follows. */
    expect($masterAsset->tax_preset)->toBe('food')
        ->and($masterAsset->tax_category)->toHaveCount(3)
        ->and((int)$masterAsset->tax_category[$this->vat20->id])->toBe($this->vat0->id)
        ->and($masterAsset->assets()->first()->tax_category)->toHaveCount(3);

    UpdateMasterAsset::make()->action($masterAsset, ['tax_preset' => 'standard']);

    expect($masterAsset->refresh()->tax_preset)->toBe('standard')
        ->and($masterAsset->tax_category)->toBe([]);
});

test('an imported map that matches a preset is tagged with it, one that does not stays custom', function () {
    $masterAsset = createZeroRatedProduct($this->standardProduct, $this->vat20, $this->vat0)
        ->asset->masterAsset;

    /** The Aurora repair posts raw maps; a food shaped one gets the preset for free. */
    $foodMap = EditMasterProduct::make()->expandTaxPreset('food');
    UpdateMasterAsset::make()->action($masterAsset, ['tax_category' => $foodMap]);

    expect($masterAsset->refresh()->tax_preset)->toBe('food');

    /** A UK only map matches no preset: stored as is, preset null, shown as custom. */
    UpdateMasterAsset::make()->action($masterAsset, ['tax_category' => [$this->vat20->id => $this->vat0->id]]);
    $masterAsset->refresh();

    expect($masterAsset->tax_preset)->toBeNull()
        ->and($masterAsset->tax_category)->toBe([(string)$this->vat20->id => $this->vat0->id])
        ->and(EditMasterProduct::make()->inferTaxPreset($masterAsset->tax_category))->toBe('custom');

    /** Re-posting the display value "custom" must not clear the map. */
    UpdateMasterAsset::make()->action($masterAsset, ['tax_preset' => 'custom']);

    expect($masterAsset->refresh()->tax_category)->toBe([(string)$this->vat20->id => $this->vat0->id]);

    /** Unknown categories in a raw map are dropped, never stored. */
    UpdateMasterAsset::make()->action($masterAsset, ['tax_category' => [999 => $this->vat0->id]]);

    expect($masterAsset->refresh()->tax_category)->toBe([])
        ->and($masterAsset->tax_preset)->toBe('standard');
});

test('the preset cards describe the rates they mean, custom only when it applies', function () {
    $options = EditMasterProduct::make()->getTaxPresetOptions([]);

    expect(collect($options)->pluck('value')->all())->toBe(['standard', 'food', 'dried_flowers'])
        ->and(collect($options)->firstWhere('value', 'food')['description'])->toContain('VAT 0%')
        ->and(collect($options)->firstWhere('value', 'food')['description'])->toContain('IVA 10%');

    /** The Spain-only flowers map is the dried flowers preset, not custom. */
    expect(EditMasterProduct::make()->inferTaxPreset([30 => 52, 51 => 53]))->toBe('dried_flowers');

    /** A UK-only map matches no preset and rides as custom. */
    $custom = EditMasterProduct::make()->getTaxPresetOptions([$this->vat20->id => $this->vat0->id]);

    expect(collect($custom)->pluck('value')->all())->toBe(['standard', 'food', 'dried_flowers', 'custom']);
});

test('changing a product tax treatment repriced baskets and leaves submitted orders alone', function () {
    $tea         = createZeroRatedProduct($this->standardProduct, $this->vat20, $this->vat0);
    $masterAsset = $tea->asset->masterAsset;

    UpdateMasterAsset::make()->action($masterAsset, ['tax_preset' => 'standard']);

    $basket = StoreOrder::make()->action($this->customer, []);
    $basket->updateQuietly(['tax_category_id' => $this->vat20->id]);
    StoreTransaction::make()->action($basket->refresh(), $tea->historicAsset, ['quantity_ordered' => 1]);

    $submitted = StoreOrder::make()->action($this->customer, []);
    $submitted->updateQuietly(['tax_category_id' => $this->vat20->id]);
    StoreTransaction::make()->action($submitted->refresh(), $tea->historicAsset, ['quantity_ordered' => 1]);
    SubmitOrder::make()->action($submitted);

    expect((float)$basket->refresh()->tax_amount)->toBe(10.0)
        ->and((float)$submitted->refresh()->tax_amount)->toBe(10.0);

    UpdateMasterAsset::make()->action($masterAsset, ['tax_preset' => 'food']);

    expect((float)$basket->refresh()->tax_amount)->toBe(0.0)
        ->and((float)$basket->total_amount)->toBe(50.0)
        ->and((float)$submitted->refresh()->tax_amount)->toBe(10.0)
        ->and((float)$submitted->total_amount)->toBe(60.0);
});

test('the master products index can be filtered to the ones that are not standard rated', function () {
    $tea = createZeroRatedProduct($this->standardProduct, $this->vat20, $this->vat0);
    $masterShop = $tea->asset->masterAsset->masterShop;

    $elementGroups = IndexMasterProducts::make()->getElementGroups($masterShop);
    expect($elementGroups)->toHaveKey('tax');

    $filter = function (string $element) use ($masterShop, $elementGroups) {
        $query = MasterAsset::query()->where('master_shop_id', $masterShop->id);
        ($elementGroups['tax']['engine'])($query, [$element]);

        return $query->pluck('code')->all();
    };

    expect($filter('overridden'))->toContain('VATM-TEA')
        ->and($filter('standard'))->not->toContain('VATM-TEA');
});

test('the catalogue index can be filtered the same way, and keeps products with no master', function () {
    $tea = createZeroRatedProduct($this->standardProduct, $this->vat20, $this->vat0);

    $elementGroups = IndexProductsInCatalogue::make()->getElementGroups($this->shop);
    expect($elementGroups)->toHaveKey('tax');

    $shopId = $this->shop->id;
    $filter = function (string $element) use ($elementGroups, $shopId) {
        $query = Product::query()->where('shop_id', $shopId);
        ($elementGroups['tax']['engine'])($query, [$element]);

        return $query->pluck('code')->all();
    };

    expect($filter('overridden'))->toContain($tea->code)
        ->and($filter('standard'))->not->toContain($tea->code)
        /** The stock product has no master, and NOT IN would silently drop it. */
        ->and($filter('standard'))->toContain($this->standardProduct->code);
});

test('a preset change freezes lines already sold and moves baskets, like a price change', function () {
    $tea         = createZeroRatedProduct($this->standardProduct, $this->vat20, $this->vat0);
    $masterAsset = $tea->asset->masterAsset;

    UpdateMasterAsset::make()->action($masterAsset, ['tax_preset' => 'food']);
    $tea->refresh();

    /** The preset change minted a historic carrying the treatment. */
    expect($tea->currentHistoricProduct->tax_category)->toHaveCount(3);

    $submitted = StoreOrder::make()->action($this->customer, []);
    $submitted->updateQuietly(['tax_category_id' => $this->vat20->id]);
    StoreTransaction::make()->action($submitted->refresh(), $tea->currentHistoricProduct, ['quantity_ordered' => 1]);
    SubmitOrder::make()->action($submitted);

    $basket = StoreOrder::make()->action($this->customer, []);
    $basket->updateQuietly(['tax_category_id' => $this->vat20->id]);
    StoreTransaction::make()->action($basket->refresh(), $tea->currentHistoricProduct, ['quantity_ordered' => 1]);

    expect((float)$submitted->refresh()->tax_amount)->toBe(0.0)
        ->and((float)$basket->refresh()->tax_amount)->toBe(0.0);

    $frozenHistoricId = $submitted->transactions->firstWhere('asset_id', $tea->asset_id)->historic_asset_id;

    UpdateMasterAsset::make()->action($masterAsset, ['tax_preset' => 'standard']);
    $tea->refresh();

    /** The basket moved to the new historic and is taxed at the standard rate again. */
    expect($basket->refresh()->transactions->firstWhere('asset_id', $tea->asset_id)->historic_asset_id)
        ->toBe($tea->current_historic_asset_id)
        ->and((float)$basket->tax_amount)->toBe(10.0);

    /** The sweep counted its baskets and reported itself finished, that is the progress bar. */
    $progress = TaxPresetBasketProgress::get($masterAsset);
    expect($progress['state'])->toBe('finished')
        ->and($progress['baskets_total'])->toBeGreaterThanOrEqual(1)
        ->and($progress['baskets_done'])->toBe($progress['baskets_total']);

    /**
     * The submitted order keeps the historic it was sold under - and even a forced
     * recalculation must not re-rate it, that is the whole point of freezing.
     */
    CalculateOrderTotalAmounts::run($submitted, false, false);

    expect($submitted->refresh()->transactions->firstWhere('asset_id', $tea->asset_id)->historic_asset_id)
        ->toBe($frozenHistoricId)
        ->and((float)$submitted->tax_amount)->toBe(0.0);
});

test('a preset change leaves external shop products alone, faire is taxed from its payload', function () {
    $tea         = createZeroRatedProduct($this->standardProduct, $this->vat20, $this->vat0);
    $masterAsset = $tea->asset->masterAsset;

    $this->shop->updateQuietly(['type' => ShopTypeEnum::EXTERNAL]);

    try {
        $historicBefore = $tea->refresh()->current_historic_asset_id;

        UpdateMasterAsset::make()->action($masterAsset, ['tax_preset' => 'food']);

        /** The map cascades (masters stay authoritative) but no historic is minted. */
        expect($masterAsset->refresh()->tax_category)->toHaveCount(3)
            ->and($tea->refresh()->current_historic_asset_id)->toBe($historicBefore);
    } finally {
        $this->shop->updateQuietly(['type' => ShopTypeEnum::B2B]);
    }
});
