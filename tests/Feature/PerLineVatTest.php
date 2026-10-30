<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Catalogue\Product\StoreProduct;
use App\Actions\Catalogue\Product\UpdateProduct;
use App\Actions\Catalogue\Shop\External\Faire\GetFaireOrdersInShop;
use App\Actions\Catalogue\Shop\External\Faire\UpdateFaireOrder;
use App\Actions\Catalogue\Product\UI\IndexProductsInCatalogue;
use App\Actions\Masters\MasterAsset\StoreMasterAsset;
use App\Actions\Masters\MasterAsset\TaxPresetBasketProgress;
use App\Actions\Masters\MasterAsset\UI\IndexMasterProducts;
use App\Actions\Masters\MasterAsset\UI\EditMasterProduct;
use App\Actions\Masters\MasterAsset\UpdateMasterAsset;
use App\Actions\Masters\MasterProductCategory\StoreMasterDepartment;
use App\Actions\Masters\MasterProductCategory\StoreMasterFamily;
use App\Actions\Masters\MasterShop\StoreMasterShop;
use App\Actions\Accounting\Invoice\CalculateInvoiceTotals;
use App\Models\Accounting\Invoice;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Actions\Ordering\Order\GenerateInvoiceFromOrder;
use App\Actions\Ordering\Order\CalculateOrderTotalAmounts;
use App\Actions\Ordering\Order\ResetOrderTaxCategory;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\Order\UpdateOrderShippingEngineAsManual;
use App\Actions\Ordering\Order\UpdateState\SendOrderToWarehouse;
use App\Actions\Ordering\Order\UpdateState\SubmitOrder;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\Ordering\Transaction\StoreTransactionFromShipping;
use App\Actions\Accounting\Invoice\UI\ShowInvoice;
use App\Actions\Billables\ShippingZone\StoreShippingZone;
use App\Actions\Billables\ShippingZoneSchema\StoreShippingZoneSchema;
use App\Actions\Maintenance\Accounting\RepairInvoiceTaxHeader;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Actions\Helpers\TaxCategory\GetTaxCategory;
use App\Models\Helpers\Address;
use App\Models\Helpers\Country;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Shop\ShopEngineEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Models\Catalogue\Product;
use App\Enums\Helpers\TaxCategories\TaxCategoryTypeEnum;
use App\Models\Helpers\TaxCategory;
use App\Models\Helpers\TaxNumber;
use App\Models\Masters\MasterAsset;
use App\Models\Ordering\Order;
use Illuminate\Support\Facades\Http;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\Shipment;
use App\Models\Dispatching\Shipper;
use App\Models\Helpers\Currency;
use App\Actions\Catalogue\Shop\External\Faire\UpdateShippingFaireOrder;
use App\Models\Ordering\Transaction;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;

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
    $product->orgStocks()->attach($sibling->orgStocks()->first()->id, ['quantity' => 1]);

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
    $deliveryNote->deliveryNoteItems()->delete();

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

    /** Food expands to all four discountable categories, and the money map follows. */
    expect($masterAsset->tax_preset)->toBe('food')
        ->and($masterAsset->tax_category)->toHaveCount(4)
        ->and((int)$masterAsset->tax_category[$this->vat20->id])->toBe($this->vat0->id)
        ->and($masterAsset->assets()->first()->tax_category)->toHaveCount(4);

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
        ->and(collect($options)->firstWhere('value', 'food')['description'])->toContain('IVA 10%')
        ->and(collect($options)->firstWhere('value', 'food')['description'])->toContain('LUX: TVA 17% → TVA 3%');

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
    expect($tea->currentHistoricProduct->tax_category)->toHaveCount(4);

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
        expect($masterAsset->refresh()->tax_category)->toHaveCount(4)
            ->and($tea->refresh()->current_historic_asset_id)->toBe($historicBefore);
    } finally {
        $this->shop->updateQuietly(['type' => ShopTypeEnum::B2B]);
    }
});

/** HELP-2967: order re-rated to 0% mid-life but lines kept the old category; the invoice must not resurrect the old tax. */
test('invoicing re-syncs stale line tax categories to the order', function () {
    $order = StoreOrder::make()->action($this->customer, []);
    $order->updateQuietly(['tax_category_id' => $this->vat20->id]);
    $order->refresh();

    StoreTransaction::make()->action($order, $this->standardProduct->historicAsset, ['quantity_ordered' => 1]);

    createWarehouse();
    SubmitOrder::make()->action($order->refresh());
    SendOrderToWarehouse::make()->action($order, []);

    $order->refresh();
    $order->updateQuietly(['tax_category_id' => $this->vat0->id]);
    expect($order->transactions()->value('tax_category_id'))->toBe($this->vat20->id);

    $invoice = GenerateInvoiceFromOrder::make()->handle($order->refresh());

    expect((float)$invoice->tax_amount)->toBe(0.0)
        ->and($invoice->invoiceTransactions()->where('model_type', 'Product')->value('tax_category_id'))->toBe($this->vat0->id);
});

/** HELP-3192: Faire zero rated the tea, invoicing re-charged it at the order's 20%. */
test('the tax faire charged survives invoicing and later recalculations', function () {
    $order = StoreOrder::make()->action($this->customer, []);
    $order->updateQuietly(['tax_category_id' => $this->vat20->id]);
    StoreTransaction::make()->action($order->refresh(), $this->standardProduct->historicAsset, ['quantity_ordered' => 1]);

    createWarehouse();
    SubmitOrder::make()->action($order->refresh());
    SendOrderToWarehouse::make()->action($order, []);

    $order->refresh()->update(['data->marketplace_tax_amount' => 7.5]);
    CalculateOrderTotalAmounts::run($order->refresh());
    expect((float)$order->refresh()->tax_amount)->toBe(7.5);

    $invoice = GenerateInvoiceFromOrder::make()->handle($order->refresh());
    CalculateInvoiceTotals::make()->action($invoice->refresh());

    expect((float)$invoice->refresh()->tax_amount)->toBe(7.5)
        ->and((float)$invoice->total_amount)->toBe((float)$invoice->net_amount + 7.5)
        ->and((float)$order->refresh()->tax_amount)->toBe(7.5);


    $invoice->delete();
});

/** HELP-3805: Faire re-ordered two backordered lines as a new order with the same item ids, and the import died after storing an empty order. */
test('a faire line moved to a re-order leaves the original order unless it already reached the warehouse', function () {
    $faireItemId = 'oi_help3805_'.uniqid();

    $originalOrder = StoreOrder::make()->action($this->customer, []);
    $staleLine     = StoreTransaction::make()->action($originalOrder->refresh(), $this->standardProduct->historicAsset, ['quantity_ordered' => 1, 'marketplace_id' => $faireItemId]);

    $action = GetFaireOrdersInShop::make();

    $staleLine->updateQuietly(['quantity_dispatched' => 1]);
    expect($action->releaseFaireItemMovedFromAnotherOrder($this->shop, $faireItemId)?->id)->toBe($originalOrder->id)
        ->and(Transaction::find($staleLine->id))->not->toBeNull();

    $staleLine->updateQuietly(['quantity_dispatched' => 0]);
    expect($action->releaseFaireItemMovedFromAnotherOrder($this->shop, $faireItemId))->toBeNull()
        ->and(Transaction::find($staleLine->id))->toBeNull();

    $reOrder = StoreOrder::make()->action($this->customer, []);
    $movedLine = StoreTransaction::make()->action($reOrder->refresh(), $this->standardProduct->historicAsset, ['quantity_ordered' => 1, 'marketplace_id' => $faireItemId]);

    expect($movedLine->order_id)->toBe($reOrder->id);
});

/** HELP-4149: refreshing Faire data on a cancelled order put Faire's lines back onto it. */
test('a cancelled faire order is never refreshed from faire', function () {
    $order = StoreOrder::make()->action($this->customer, []);
    $order->updateQuietly(['state' => OrderStateEnum::CANCELLED, 'external_id' => 'help4149_'.uniqid()]);

    Http::fake();

    UpdateFaireOrder::make()->handle($order->refresh());

    Http::assertNothingSent();
    expect($order->transactions()->count())->toBe(0)
        ->and($order->refresh()->state)->toBe(OrderStateEnum::CANCELLED);
});

/** HELP-3906, HELP-3989, HELP-4038: Faire states only the VAT on the shipping of its own label, so whoever edits the order types the shipping and it must carry that VAT; an order we ship ourselves takes it before its tracking goes to Faire. */
test('manual shipping on a faire label order must match the shipping vat faire states and on our own shipping is allowed before the tracking', function () {
    $order = StoreOrder::make()->action($this->customer, []);
    StoreTransaction::make()->action($order->refresh(), $this->standardProduct->historicAsset, ['quantity_ordered' => 1]);
    createWarehouse();
    SubmitOrder::make()->action($order->refresh());
    SendOrderToWarehouse::make()->action($order, []);
    $order->refresh()->update(['is_shipping_by_external' => true, 'tax_category_id' => $this->vat20->id]);

    $this->shop->updateQuietly(['type' => ShopTypeEnum::EXTERNAL, 'engine' => ShopEngineEnum::FAIRE]);

    $faireTaxes = [
        ['taxable_item_type' => 'ORDER_ITEM', 'tax_type' => 'VAT', 'value' => ['amount_minor' => 200, 'currency' => $this->shop->currency->code]],
        ['taxable_item_type' => 'SHIPPING', 'tax_type' => 'VAT', 'value' => ['amount_minor' => 101, 'currency' => $this->shop->currency->code]],
    ];
    Http::fake(['*faire.com/*' => function () use (&$faireTaxes) {
        return Http::response(['payout_costs' => ['taxes' => $faireTaxes]]);
    }]);

    try {
        patch(route('grp.models.order.set_shipping_engine_manual', $order->id), ['shipping_amount' => 50.30])
            ->assertSessionHasErrors('message');
        expect((float)$order->refresh()->shipping_amount)->toBe(0.0);

        $faireTaxes[] = ['taxable_item_type' => 'SHIPPING', 'tax_type' => 'RECARGO', 'value' => ['amount_minor' => 26, 'currency' => $this->shop->currency->code]];
        patch(route('grp.models.order.set_shipping_engine_manual', $order->id), ['shipping_amount' => 50.30])
            ->assertSessionHasNoErrors();
        expect((float)$order->refresh()->shipping_amount)->toBe(50.30);
        array_pop($faireTaxes);

        patch(route('grp.models.order.set_shipping_engine_manual', $order->id), ['shipping_amount' => 5.03])
            ->assertSessionHasNoErrors();
        expect((float)$order->refresh()->shipping_amount)->toBe(5.03);

        $order->update(['is_shipping_by_external' => false]);
        patch(route('grp.models.order.set_shipping_engine_manual', $order->id), ['shipping_amount' => 4.95])
            ->assertSessionHasNoErrors();
        expect((float)$order->refresh()->shipping_amount)->toBe(4.95);

        $order->refresh()->deliveryNotes()->first()->shipments()->create([
            'group_id'        => $order->group_id,
            'organisation_id' => $order->organisation_id,
            'tracking'        => 'HELP3906',
            'data'            => [],
        ]);

        patch(route('grp.models.order.set_shipping_engine_manual', $order->id), ['shipping_amount' => 9.99])
            ->assertSessionHasErrors('message');
        get(route('grp.models.order.set_shipping_engine_auto', $order->id))
            ->assertSessionHasErrors('message');
        expect((float)$order->refresh()->shipping_amount)->toBe(4.95);
    } finally {
        $this->shop->updateQuietly(['type' => ShopTypeEnum::B2B, 'engine' => ShopEngineEnum::AIKU]);
    }
});

/** HELP-3906: the shipping typed on the order is what Faire is asked to reimburse, unless the shipment has its own cost. */
test('the tracking sent to faire carries the order shipping on its first tracking, never on a faire label', function () {
    $nextId   = 0;
    $shipment = function (string $tracking, ?float $cost = null, string $shipper = 'gls') use (&$nextId) {
        return (new Shipment(['id' => ++$nextId, 'tracking' => $tracking, 'cost' => $cost]))
            ->setRelation('shipper', new Shipper(['code' => $shipper, 'trade_as' => 'GLS']));
    };
    $order = (new Order(['shipping_amount' => 40.91]))->setRelation('currency', new Currency(['code' => 'EUR']));
    $sent  = fn (array $shipments) => array_column(array_column(
        UpdateShippingFaireOrder::make()->getFaireShipments((new DeliveryNote())->setRelation('shipments', collect($shipments)), $order),
        'maker_cost'
    ), 'amount_minor');

    expect($sent([$shipment('A1 , A2'), $shipment('B1')]))->toBe([4091, 0, 0])
        ->and($sent([$shipment('A1', 40.91)]))->toBe([4091])
        ->and($sent([$shipment('A1', null, 'dhl_express-faire')]))->toBe([0])
        ->and($sent([$shipment('')]))->toBe([]);
});

/** HELP-3906: the shipping Faire reimburses when we ship an order ourselves is read from Faire, also after invoicing. */
test('the shipping faire reimburses is copied onto an order with none and never rewrites one already there', function () {
    $order = StoreOrder::make()->action($this->customer, []);
    StoreTransaction::make()->action($order->refresh(), $this->standardProduct->historicAsset, ['quantity_ordered' => 1]);

    $faireShipment = fn (int $amountMinor) => ['shipments' => [['maker_cost' => ['amount_minor' => $amountMinor]]]];
    $action        = UpdateFaireOrder::make();

    $action->syncFaireShipping($order->refresh(), $faireShipment(0));
    expect((float)$order->refresh()->shipping_amount)->toBe(0.0);

    $order->updateQuietly(['state' => OrderStateEnum::FINALISED]);
    $invoice = GenerateInvoiceFromOrder::make()->handle($order->refresh());
    $order->refresh();
    $orderNet   = (float)$order->net_amount;
    $orderTax   = (float)$order->tax_amount;
    $invoiceNet = (float)$invoice->refresh()->net_amount;
    $invoiceTax = (float)$invoice->tax_amount;

    $invoice->invoiceTransactions()->create([
        ...$invoice->invoiceTransactions()->first()->only(['group_id', 'organisation_id', 'shop_id', 'customer_id', 'order_id', 'tax_category_id', 'date']),
        'model_type'   => 'ShippingZone',
        'quantity'     => 1,
        'gross_amount' => 0,
        'net_amount'   => 0,
    ]);

    $action->syncFaireShipping($order, $faireShipment(1234));

    expect((float)$order->refresh()->shipping_amount)->toBe(12.34)
        ->and(round((float)$order->net_amount - $orderNet, 2))->toBe(12.34)
        ->and((float)$order->tax_amount)->toBe($orderTax)
        ->and(round((float)$order->total_amount, 2))->toBe(round((float)$order->net_amount + $orderTax, 2))
        ->and(round((float)$invoice->refresh()->net_amount - $invoiceNet, 2))->toBe(12.34)
        ->and((float)$invoice->tax_amount)->toBe($invoiceTax)
        ->and((float)$invoice->invoiceTransactions()->where('model_type', 'ShippingZone')->sum('net_amount'))->toBe(12.34)
        ->and((float)$invoice->invoiceTransactions()->where('model_type', 'ShippingZone')->sum('grp_net_amount'))->toBeGreaterThan(0.0);

    $action->syncFaireShipping($order, $faireShipment(2000));
    expect((float)$order->refresh()->shipping_amount)->toBe(12.34)
        ->and($invoice->invoiceTransactions()->where('model_type', 'ShippingZone')->count())->toBe(1);

    $invoice->delete();
});

test('manual shipping on a non-faire order does not touch faire', function () {
    $order = StoreOrder::make()->action($this->customer, []);
    StoreTransaction::make()->action($order->refresh(), $this->standardProduct->historicAsset, ['quantity_ordered' => 1]);

    UpdateFaireOrder::shouldNotRun();

    UpdateOrderShippingEngineAsManual::make()->handle($order->refresh(), ['shipping_amount' => 4.95]);

    expect((float)$order->refresh()->shipping_amount)->toBe(4.95);
});

/** HELP-3670: deleting the invoice of a finalised order makes its shipping editable again. */
test('manual shipping is locked on an invoiced finalised order and unlocked once the invoice is deleted', function () {
    $order = StoreOrder::make()->action($this->customer, []);
    StoreTransaction::make()->action($order->refresh(), $this->standardProduct->historicAsset, ['quantity_ordered' => 1]);
    UpdateOrderShippingEngineAsManual::make()->handle($order->refresh(), ['shipping_amount' => 4.49]);

    $order->refresh()->updateQuietly(['state' => OrderStateEnum::FINALISED]);
    $invoice = GenerateInvoiceFromOrder::make()->handle($order->refresh());

    patch(route('grp.models.order.set_shipping_engine_manual', $order->id), ['shipping_amount' => 0])
        ->assertSessionHasErrors('message');
    expect((float)$order->refresh()->shipping_amount)->toBe(4.49);

    $invoice->delete();

    patch(route('grp.models.order.set_shipping_engine_manual', $order->id), ['shipping_amount' => 0])
        ->assertSessionHasNoErrors();
    expect((float)$order->refresh()->shipping_amount)->toBe(0.0);
});

/** HELP-2967: a validity flip re-rates open orders, but never touches an invoiced one. */
test('a tax number validity change re-rates open orders and skips invoiced ones', function () {
    $openOrder = StoreOrder::make()->action($this->customer, []);
    StoreTransaction::make()->action($openOrder, $this->standardProduct->historicAsset, ['quantity_ordered' => 1]);

    $invoicedOrder = StoreOrder::make()->action($this->customer, []);
    StoreTransaction::make()->action($invoicedOrder, $this->standardProduct->historicAsset, ['quantity_ordered' => 1]);
    createWarehouse();
    SubmitOrder::make()->action($invoicedOrder->refresh());
    SendOrderToWarehouse::make()->action($invoicedOrder, []);
    GenerateInvoiceFromOrder::make()->handle($invoicedOrder->refresh());

    $resetOrderIds = [];
    ResetOrderTaxCategory::partialMock()
        ->shouldReceive('handle')
        ->andReturnUsing(function (Order $order) use (&$resetOrderIds) {
            $resetOrderIds[] = $order->id;

            return $order;
        });

    $taxNumber = $this->customer->taxNumber()->create(['number' => '46388143', 'country_code' => 'DK', 'valid' => false]);
    $taxNumber->update(['valid' => true]);

    expect($resetOrderIds)->toContain($openOrder->id)
        ->not->toContain($invoicedOrder->id);
});

/**
 * HELP-3081: the order's shipping_amount column lagged the shipping line at dispatch; the header
 * was computed from the column, the invoice line stored from the transaction, and the VAT figure
 * on the invoice no longer added up to its own rows. Header and lines must come from the same rows.
 */
test('the invoice header is derived from the same lines it stores, not from the order columns', function () {
    $order = StoreOrder::make()->action($this->customer, []);
    $order->updateQuietly(['tax_category_id' => $this->vat20->id]);
    StoreTransaction::make()->action($order->refresh(), $this->standardProduct->historicAsset, ['quantity_ordered' => 1]);

    createWarehouse();
    SubmitOrder::make()->action($order->refresh());
    SendOrderToWarehouse::make()->action($order, [])->deliveryNoteItems()->delete();

    $schema       = StoreShippingZoneSchema::make()->action($order->shop, ['name' => 'flat']);
    $shippingZone = StoreShippingZone::make()->action($schema, [
        'code'        => 'FLAT',
        'name'        => 'flat',
        'status'      => true,
        'price'       => ['type' => 'Step Order Items Net Amount', 'steps' => [['from' => 0, 'to' => 'INF', 'price' => 20]]],
        'territories' => [['country_code' => 'GB']],
        'position'    => 1,
        'is_failover' => false,
    ]);
    StoreTransactionFromShipping::make()->action($order->refresh(), $shippingZone, [
        'date'             => Carbon::now(),
        'quantity_ordered' => 1,
        'gross_amount'     => 20,
        'net_amount'       => 20,
    ], strict: false);

    $order->refresh()->updateQuietly(['shipping_amount' => 35]);

    $invoice = GenerateInvoiceFromOrder::make()->handle($order->refresh());

    $fromLines = RepairInvoiceTaxHeader::make()->expectedTotals($invoice);

    expect((float)$invoice->net_amount)->toBe(120.0)
        ->and((float)$invoice->tax_amount)->toBe(24.0)
        ->and((float)$invoice->total_amount)->toBe(144.0)
        ->and($fromLines)->toBe(['net' => 120.0, 'tax' => 24.0, 'total' => 144.0])
        ->and((float)$order->refresh()->tax_amount)->toBe(24.0)
        ->and((float)$order->total_amount)->toBe(144.0);

    return $invoice;
});

/** An issued invoice with a wrong header shows the header, not rows that do not add up to it. */
test('tax rows that do not add up to the stored header give way to the header', function ($invoice) {
    $invoice->updateQuietly(['tax_amount' => 27, 'total_amount' => 147]);
    $invoice->refresh();

    expect($invoice->taxBreakdown())->toBe([]);

    $rows = ShowInvoice::make()->getInvoiceTaxRows($invoice);
    expect($rows)->toHaveCount(1)
        ->and((float)$rows[0]['price_total'])->toBe(27.0);

    return $invoice;
})->depends('the invoice header is derived from the same lines it stores, not from the order columns');

test('the repair rewrites the header from the lines and mirrors it onto the order', function ($invoice) {
    $invoice->order->updateQuietly(['tax_amount' => 27, 'total_amount' => 147]);

    $this->artisan('repair:invoice_tax_header', ['--shop' => $invoice->shop->slug])
        ->expectsOutputToContain('1 invoices with a header that does not add up to its lines.')
        ->assertSuccessful();
    expect((float)$invoice->refresh()->tax_amount)->toBe(27.0);

    Storage::fake('local');
    $this->artisan('repair:invoice_tax_header', ['--shop' => $invoice->shop->slug, '--from' => '2020-01-01', '--fix' => true])
        ->expectsOutputToContain('1 invoices repaired.')
        ->assertSuccessful();

    $recordFile = collect(Storage::disk('local')->files('repairs'))->sole();
    expect(Storage::disk('local')->get($recordFile))
        ->toContain($invoice->reference.','.$invoice->order->reference.',120.00,27.00,147.00,120.00,27.00,147.00,120.00,24.00,144.00,120.00,24.00,144.00');

    expect((float)$invoice->refresh()->tax_amount)->toBe(24.0)
        ->and((float)$invoice->total_amount)->toBe(144.0)
        ->and((float)$invoice->order->refresh()->tax_amount)->toBe(24.0)
        ->and((float)$invoice->order->total_amount)->toBe(144.0)
        ->and($invoice->taxBreakdown())->toHaveCount(1);
})->depends('tax rows that do not add up to the stored header give way to the header');

/**
 * HELP-2768: a customer billed abroad and delivered in the UK was invoiced without VAT.
 * HELP-3146: goods we ship out of the UK are a zero-rated direct export (VATA 1994 s30(6)), even when billed in the UK.
 */
test('a uk delivery is standard rated whatever the billing country, and a delivery outside the uk is not', function (string $billing, string $delivery, float $rate) {
    $address = fn (string $code) => new Address(array_merge(Address::factory()->definition(), [
        'country_id'   => Country::where('code', $code)->firstOrFail()->id,
        'country_code' => $code,
    ]));

    $taxCategory = GetTaxCategory::run(Country::where('code', 'GB')->firstOrFail(), null, $address($billing), $address($delivery));

    expect((float)$taxCategory->rate)->toBe($rate);
})->with([
    'billed in Ukraine, delivered in the UK' => ['UA', 'GB', 0.2],
    'billed in the UK, delivered in France'  => ['GB', 'FR', 0.0],
    'billed in the UK, delivered in Switzerland' => ['GB', 'CH', 0.0],
    'billed in the UK, delivered in the Isle of Man' => ['GB', 'IM', 0.2],
    'both in the UK'                          => ['GB', 'GB', 0.2],
    'billed in Ukraine, delivered in France'  => ['UA', 'FR', 0.0],
]);

/**
 * INI-095: the intra-Community exemption needs a VAT number from a member state other than the one
 * the goods leave (VAT Directive art. 138(1)(b)); a number from the seller's own country gets its VAT.
 */
test('a valid eu vat number is zero rated only when another member state issued it', function (string $taxNumberCountry, string $delivery, ?bool $isSellerVatCharged) {
    $country = fn (string $code) => Country::where('code', $code)->firstOrFail();
    $address = fn (string $code) => new Address(array_merge(Address::factory()->definition(), [
        'country_id'   => $country($code)->id,
        'country_code' => $code,
    ]));
    $taxNumber = new TaxNumber([
        'country_id'   => $country($taxNumberCountry)->id,
        'country_code' => $taxNumberCountry,
        'number'       => '123456789',
        'valid'        => true,
    ]);

    $taxCategory = GetTaxCategory::run($country('SK'), $taxNumber, $address('AT'), $address($delivery));

    $slovakStandard = TaxCategory::where('type', TaxCategoryTypeEnum::STANDARD)->where('country_id', $country('SK')->id)->where('status', true)->firstOrFail();

    expect((float)$slovakStandard->rate)->toBeGreaterThan(0.0)
        ->and($taxCategory->id)->toBe(match ($isSellerVatCharged) {
            true  => $slovakStandard->id,
            false => 2,
            null  => 1,
        });
})->with([
    'german number, slovak seller, delivered in Austria' => ['DE', 'AT', false],
    'slovak number, slovak seller, delivered in Austria' => ['SK', 'AT', true],
    'slovak number, slovak seller, exported to Norway'   => ['SK', 'NO', null],
]);

/** A pastpay invoice rendered its pdf before its lines existed, caching an empty relation, and the
 *  header derived from that cache was zero (HELP-3106). Totals always read the lines afresh. */
test('the invoice totals read the lines afresh, not a relation cached before they were stored', function ($invoice) {
    $invoice = Invoice::find($invoice->id);
    $invoice->setRelation('invoiceTransactions', new \Illuminate\Database\Eloquent\Collection());

    CalculateInvoiceTotals::make()->action($invoice);

    expect((float)$invoice->refresh()->net_amount)->toBe(120.0)
        ->and((float)$invoice->total_amount)->toBe(144.0);
})->depends('the invoice header is derived from the same lines it stores, not from the order columns');

test('a line whose historic asset is deleted falls back to the master map, and one whose asset is deleted to the order category', function () {
    $order = StoreOrder::make()->action($this->customer, []);
    $order->updateQuietly(['tax_category_id' => $this->vat20->id]);

    $tea = createZeroRatedProduct($this->standardProduct, $this->vat20, $this->vat0);
    StoreTransaction::make()->action($order->refresh(), $tea->historicAsset, ['quantity_ordered' => 1]);
    $line        = $order->transactions()->where('asset_id', $tea->asset_id)->first();
    $originalMap = DB::table('historic_assets')->where('id', $line->historic_asset_id)->value('tax_category');

    $line->historicAsset->updateQuietly(['tax_category' => [$this->vat20->id => $this->vat20->id]]);
    CalculateOrderTotalAmounts::make()->applyLineTaxCategories($order->refresh());
    expect($line->refresh()->tax_category_id)->toBe($this->vat20->id);

    DB::table('historic_assets')->where('id', $line->historic_asset_id)->update(['deleted_at' => now()]);
    CalculateOrderTotalAmounts::make()->applyLineTaxCategories($order);
    expect($line->refresh()->tax_category_id)->toBe($this->vat0->id);

    DB::table('assets')->where('id', $tea->asset_id)->update(['deleted_at' => now()]);
    CalculateOrderTotalAmounts::make()->applyLineTaxCategories($order);
    expect($line->refresh()->tax_category_id)->toBe($this->vat20->id);

    DB::table('assets')->where('id', $tea->asset_id)->update(['deleted_at' => null]);
    DB::table('historic_assets')->where('id', $line->historic_asset_id)->update(['deleted_at' => null, 'tax_category' => $originalMap]);
});

/** HELP-4266: a Faire product went from a pack of 4 to singles after the order came in; a Faire refresh restated the line as 4 singles while its submitted snapshot still read 1 for 14.72, and the invoice billed 58.88. */
test('a faire line restated in new units is invoiced at what faire billed', function () {
    $product       = $this->standardProduct;
    $originalPrice = $product->price;
    $originalUnits = $product->units;
    $faireItemId   = 'oi_help4266_'.uniqid();

    $order = StoreOrder::make()->action($this->customer, []);
    $order->updateQuietly(['external_id' => 'help4266_'.uniqid()]);
    StoreTransaction::make()->action(
        order: $order->refresh(),
        historicAsset: $product->historicAsset,
        modelData: ['quantity_ordered' => 1, 'gross_amount' => 14.72, 'net_amount' => 14.72, 'marketplace_id' => $faireItemId],
        strict: false
    );
    createWarehouse();
    SubmitOrder::make()->action($order->refresh());

    $product->updateQuietly(['units' => 1]);
    Http::fake(['*faire.com/*' => Http::response([
        'state'        => 'PROCESSING',
        'items'        => [['id' => $faireItemId, 'quantity' => 4, 'price' => ['amount_minor' => 368, 'currency' => $this->shop->currency->code]]],
        'payout_costs' => [],
    ])]);

    UpdateFaireOrder::make()->handle($order->refresh());
    $invoice = GenerateInvoiceFromOrder::make()->handle($order->refresh());

    expect((float)$invoice->invoiceTransactions()->where('model_type', 'Product')->value('quantity'))->toBe(4.0)
        ->and((float)$invoice->invoiceTransactions()->where('model_type', 'Product')->value('net_amount'))->toBe(14.72);

    $invoice->delete();
    UpdateProduct::run($product->refresh(), ['units' => $originalUnits, 'price' => $originalPrice]);
});

/** INI-131: after a damaged or missing claim Faire states only the VAT on what was delivered, and a refresh cut the invoice's VAT on goods it still bills in full. Faire takes the damaged items off before its discount, and an item the invoice never billed adds nothing. */
test('a damaged or missing claim on faire keeps the vat the invoice charged', function () {
    $product       = $this->standardProduct;
    $originalPrice = $product->price;
    $originalUnits = $product->units;
    $product->updateQuietly(['units' => 1]);

    $deliveredItemId = 'oi_ini131d_'.uniqid();
    $damagedItemId   = 'oi_ini131m_'.uniqid();

    $order = StoreOrder::make()->action($this->customer, []);
    $order->updateQuietly(['tax_category_id' => $this->vat20->id, 'external_id' => 'ini131_'.uniqid()]);
    foreach ([$deliveredItemId, $damagedItemId] as $faireItemId) {
        StoreTransaction::make()->action(
            order: $order->refresh(),
            historicAsset: $product->historicAsset,
            modelData: ['quantity_ordered' => 1, 'gross_amount' => 10, 'net_amount' => 10, 'marketplace_id' => $faireItemId],
            strict: false
        );
    }
    createWarehouse();
    SubmitOrder::make()->action($order->refresh());
    $invoice = GenerateInvoiceFromOrder::make()->handle($order->refresh());

    $currency     = $this->shop->currency->code;
    $faireItem    = fn (string $id, string $state) => ['id' => $id, 'quantity' => 1, 'state' => $state, 'price' => ['amount_minor' => 1000, 'currency' => $currency]];
    $faireOrder   = fn (int $discountMinor) => [
        'state'        => 'DELIVERED',
        'items'        => [$faireItem($deliveredItemId, 'DELIVERED'), $faireItem($damagedItemId, 'DAMAGED_OR_MISSING')],
        'payout_costs' => [
            'taxes'                     => [['taxable_item_type' => 'ORDER_ITEM', 'tax_type' => 'VAT', 'value' => ['amount_minor' => 200, 'currency' => $currency]]],
            'total_brand_discounts'     => ['amount_minor' => $discountMinor, 'currency' => $currency],
            'damaged_and_missing_items' => ['amount_minor' => 1000, 'currency' => $currency],
        ],
    ];

    $neverBilledItem = $faireOrder(500);
    $neverBilledItem['items'][] = $faireItem('oi_ini131x_'.uniqid(), 'DAMAGED_OR_MISSING');

    expect(UpdateFaireOrder::make()->getFaireDamagedTaxAmount($order, $faireOrder(500)))->toBe(2.0)
        ->and(UpdateFaireOrder::make()->getFaireDamagedTaxAmount($order, $neverBilledItem))->toBe(2.0);

    Http::fake(['*faire.com/*' => Http::response($faireOrder(0))]);
    UpdateFaireOrder::make()->handle($order->refresh());

    expect((float)$invoice->refresh()->tax_amount)->toBe(4.0)
        ->and((float)$invoice->net_amount)->toBe(20.0)
        ->and((float)$order->refresh()->tax_amount)->toBe(4.0);

    $invoice->delete();
    UpdateProduct::run($product->refresh(), ['units' => $originalUnits, 'price' => $originalPrice]);
});
