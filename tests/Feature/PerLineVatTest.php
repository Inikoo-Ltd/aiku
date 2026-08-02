<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Catalogue\Product\StoreProduct;
use App\Actions\Catalogue\Product\UpdateProduct;
use App\Actions\Masters\MasterAsset\StoreMasterAsset;
use App\Actions\Masters\MasterAsset\UI\EditMasterProduct;
use App\Actions\Masters\MasterAsset\UpdateMasterAsset;
use App\Actions\Masters\MasterProductCategory\StoreMasterDepartment;
use App\Actions\Masters\MasterProductCategory\StoreMasterFamily;
use App\Actions\Masters\MasterShop\StoreMasterShop;
use App\Actions\Ordering\Order\GenerateInvoiceFromOrder;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\Order\UpdateState\SendOrderToWarehouse;
use App\Actions\Ordering\Order\UpdateState\SubmitOrder;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Helpers\Country;
use App\Models\Helpers\TaxCategory;
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

    $this->group    = $this->organisation->group;
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

test('the master product edit form round trips the tax treatment', function () {
    $masterAsset = createZeroRatedProduct($this->standardProduct, $this->vat20, $this->vat0)
        ->asset->masterAsset;

    $gb = Country::where('code', 'GB')->firstOrFail();

    UpdateMasterAsset::make()->action($masterAsset, ['tax_category' => '']);

    expect($masterAsset->refresh()->tax_category)->toBe([]);

    UpdateMasterAsset::make()->action($masterAsset, ['tax_category' => (string)$gb->id]);

    expect($masterAsset->refresh()->tax_category)->toBe([(string)$this->vat20->id => $this->vat0->id])
        ->and($masterAsset->assets()->first()->tax_category)->toBe([(string)$this->vat20->id => $this->vat0->id])
        ->and(EditMasterProduct::make()->collapseToReducedRateCountries($masterAsset->tax_category))->toBe([$gb->id]);
});

test('the tax treatment options name the food case', function () {
    $options = EditMasterProduct::make()->getReducedRateOptions();
    $labels  = array_column($options, 'label');

    expect($labels[0])->toBe('Standard rate')
        ->and(collect($labels)->filter(fn ($l) => str_contains($l, 'food')))->toHaveCount(1);
});

test('changing a product tax treatment repriced baskets and leaves submitted orders alone', function () {
    $tea         = createZeroRatedProduct($this->standardProduct, $this->vat20, $this->vat0);
    $masterAsset = $tea->asset->masterAsset;
    $gb          = Country::where('code', 'GB')->firstOrFail();

    UpdateMasterAsset::make()->action($masterAsset, ['tax_category' => '']);

    $basket = StoreOrder::make()->action($this->customer, []);
    $basket->updateQuietly(['tax_category_id' => $this->vat20->id]);
    StoreTransaction::make()->action($basket->refresh(), $tea->historicAsset, ['quantity_ordered' => 1]);

    $submitted = StoreOrder::make()->action($this->customer, []);
    $submitted->updateQuietly(['tax_category_id' => $this->vat20->id]);
    StoreTransaction::make()->action($submitted->refresh(), $tea->historicAsset, ['quantity_ordered' => 1]);
    SubmitOrder::make()->action($submitted);

    expect((float)$basket->refresh()->tax_amount)->toBe(10.0)
        ->and((float)$submitted->refresh()->tax_amount)->toBe(10.0);

    UpdateMasterAsset::make()->action($masterAsset, ['tax_category' => (string)$gb->id]);

    expect((float)$basket->refresh()->tax_amount)->toBe(0.0)
        ->and((float)$basket->total_amount)->toBe(50.0)
        ->and((float)$submitted->refresh()->tax_amount)->toBe(10.0)
        ->and((float)$submitted->total_amount)->toBe(60.0);
});

test('a reduced rate country expands to every one of its live categories, matching special with special', function () {
    $es = Country::where('code', 'ES')->firstOrFail();
    $gb = Country::where('code', 'GB')->firstOrFail();

    $map = EditMasterProduct::make()->expandReducedRateCountries([$es->id, $gb->id]);

    foreach ($map as $orderTaxCategoryId => $lineTaxCategoryId) {
        $order = TaxCategory::find($orderTaxCategoryId);
        $line  = TaxCategory::find($lineTaxCategoryId);

        expect($order->country_id)->toBe($line->country_id)
            ->and($line->rate)->toBeLessThan($order->rate)
            ->and($line->type->value)->toBe($order->type->value == 'special' ? 'reduced_special' : 'reduced');
    }

    /** Spain charges IVA and IVA+RE, the UK only VAT, so Spain contributes two entries. */
    expect($map)->toHaveCount(3);
});
