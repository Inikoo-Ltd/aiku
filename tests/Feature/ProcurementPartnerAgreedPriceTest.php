<?php

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Accounting\Invoice\ISDocInvoice;
use App\Actions\Inventory\Warehouse\StoreWarehouse;
use App\Actions\Masters\MasterAsset\GetPartnerAgreedUnitPrice;
use App\Actions\Ordering\Order\CalculateOrderDiscounts;
use App\Actions\Ordering\Order\GenerateInvoiceFromOrder;
use App\Actions\Ordering\Order\Hydrators\OrderHydrateDiscretionaryOffersData;
use App\Actions\Ordering\Order\RecalculateTotalsOrdersInBasket;
use App\Actions\Ordering\Order\Watcher\FixMiscalculatedTransactionAmounts;
use App\Actions\Ordering\Transaction\UpdateTransaction;
use App\Actions\Procurement\OrgPartner\GetPartnerBuyingPrices;
use App\Actions\Procurement\OrgPartner\GetPartnerOrderCapacity;
use App\Actions\Procurement\OrgPartner\GetPartnerSellingProduct;
use App\Actions\Procurement\OrgPartner\PartnerSkoPrice;
use App\Actions\Procurement\PartnerShoppingListItem\SuggestPartnerShoppingList;
use App\Actions\Procurement\OrgPartner\StoreOrgPartner;
use App\Actions\Production\PartnerShippingList\CherryPickPartnerShoppingListItems;
use App\Actions\Production\PartnerShippingList\SendPartnerOrderToWarehouse;
use App\Actions\Catalogue\Shop\StoreShop;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Currency;
use App\Models\Inventory\Warehouse;
use App\Models\Ordering\Transaction;
use App\Models\Procurement\OrgPartner;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

require_once __DIR__.'/../Support/ProcurementHelpers.php';

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    $this->organisation = createOrganisation();
    $this->adminGuest   = createAdminGuest($this->organisation->group);

    $orgPartner = OrgPartner::first();
    if (!$orgPartner) {
        $orgPartner = StoreOrgPartner::make()->action($this->organisation, createOrganisation());
    }
    $this->orgPartner = $orgPartner;

    actingAs($this->adminGuest->getUser());
});

/**
 * Picks a fresh shopping list line of the product for the partner and returns its order line.
 */
function pickAgreedPriceLine(object $test, float $quantity = 10): Transaction
{
    $item = submittedPartnerShoppingListItem($test->orgPartner, $test->buyerOrgStock, ['quantity' => $quantity]);
    CherryPickPartnerShoppingListItems::make()->action($test->seller->refresh(), [['id' => $item->id]]);

    return $item->refresh()->transaction->refresh();
}

describe('partner agreed prices on hub orders', function () {
    afterEach(function () {
        DB::rollBack();
    });

    beforeEach(function () {
        $transactionsManager = new \Illuminate\Foundation\Testing\DatabaseTransactionsManager([null]);
        app()->instance('db.transactions', $transactionsManager);
        DB::connection()->setTransactionManager($transactionsManager);
        DB::beginTransaction();

        $seller = $this->orgPartner->partner;
        $seller->update([
            'is_manufacturing_hub' => true,
            'settings'             => array_replace_recursive($seller->settings ?? [], ['procurement' => ['partner_discount' => 0.45]]),
        ]);
        $this->seller = $seller->refresh();

        $sellerShop = $seller->shops()->first();
        if (!$sellerShop) {
            $sellerShop = StoreShop::run($seller, Shop::factory()->definition());
        }

        [, $sellerProduct]   = createProduct($sellerShop);
        $this->buyerOrgStock = createOrgStocks($this->orgPartner->organisation, [$sellerProduct->orgStocks()->first()->stock])[0];
        $this->product       = GetPartnerSellingProduct::run($this->orgPartner->refresh(), $this->buyerOrgStock->stock_id);
        $this->listPrice     = (float) $this->product->historicAsset->price;

        expect($this->listPrice)->toBeGreaterThan(0.0);
    });

    test('without an agreed price a hub partner line takes the hub partner discount as before', function () {
        $transaction = pickAgreedPriceLine($this);
        $gross       = round((float) $transaction->quantity_ordered * $this->listPrice, 2);

        expect($transaction->agreed_unit_price)->toBeNull()
            ->and((float) $transaction->discretionary_offer)->toBe(0.45)
            ->and((float) $transaction->gross_amount)->toEqualWithDelta($gross, 0.01)
            ->and((float) $transaction->net_amount)->toEqualWithDelta($gross * 0.55, 0.01)
            ->and($transaction->order->refresh()->discretionary_offers_data)->toHaveKey((string) $transaction->id);
    });

    test('an agreed price below the product price is charged as it is, with no discount', function () {
        $agreedPrice = round($this->listPrice * 0.6, 2);
        agreePartnerPrice($this->orgPartner, $this->product, $agreedPrice);

        $transaction = pickAgreedPriceLine($this);
        $gross       = round((float) $transaction->quantity_ordered * $agreedPrice, 2);

        expect((float) $transaction->agreed_unit_price)->toBe($agreedPrice)
            ->and((float) $transaction->gross_amount)->toEqualWithDelta($gross, 0.01)
            ->and((float) $transaction->net_amount)->toBe((float) $transaction->gross_amount)
            ->and((float) $transaction->discretionary_offer)->toBe(0.0)
            ->and($transaction->current_discount_factor)->toEqual(1)
            ->and($transaction->offers_data)->toBeEmpty()
            ->and($transaction->order->refresh()->discretionary_offers_data ?? [])->not->toHaveKey((string) $transaction->id);
    });

    test('an agreed price above the product price is charged in full', function () {
        $agreedPrice = round($this->listPrice * 1.5 + 1, 2);
        agreePartnerPrice($this->orgPartner, $this->product, $agreedPrice);

        $transaction = pickAgreedPriceLine($this);
        $units       = (float) $transaction->quantity_ordered;

        expect((float) $transaction->agreed_unit_price)->toBe($agreedPrice)
            ->and((float) $transaction->gross_amount)->toEqualWithDelta(round($units * $agreedPrice, 2), 0.01)
            ->and((float) $transaction->gross_amount)->toBeGreaterThan(round($units * $this->listPrice, 2))
            ->and((float) $transaction->net_amount)->toBe((float) $transaction->gross_amount);
    });

    test('an agreed price in another currency is converted to the shop currency', function () {
        $shopCurrency    = $this->product->shop->currency;
        $partnerCurrency = Currency::where('code', $shopCurrency->code === 'EUR' ? 'USD' : 'EUR')->firstOrFail();
        $exchangeKey     = "current-currency-exchange:$partnerCurrency->code-$shopCurrency->code";
        Cache::put($exchangeKey, 0.85, now()->addMinutes(5));

        agreePartnerPrice($this->orgPartner, $this->product, 10, $partnerCurrency);

        $agreedPrice = GetPartnerAgreedUnitPrice::run($this->product->refresh(), $this->orgPartner);
        $transaction = pickAgreedPriceLine($this);

        Cache::forget($exchangeKey);

        expect($agreedPrice)->toBe(8.5)
            ->and((float) $transaction->agreed_unit_price)->toBe(8.5)
            ->and((float) $transaction->net_amount)->toEqualWithDelta(round((float) $transaction->quantity_ordered * 8.5, 2), 0.01);
    });

    test('a quantity change keeps the agreed price, a new pick at the same price joins the line and one at another price gets its own', function () {
        $agreedPrice = round($this->listPrice * 0.6, 2);
        agreePartnerPrice($this->orgPartner, $this->product, $agreedPrice);

        $transaction = pickAgreedPriceLine($this);
        $units       = (float) $transaction->quantity_ordered + 2;

        UpdateTransaction::make()->action($transaction, ['quantity_ordered' => $units]);
        $transaction->refresh();

        expect((float) $transaction->gross_amount)->toEqualWithDelta(round($units * $agreedPrice, 2), 0.01)
            ->and((float) $transaction->net_amount)->toBe((float) $transaction->gross_amount);

        $samePrice = pickAgreedPriceLine($this);

        expect($samePrice->id)->toBe($transaction->id)
            ->and((float) $samePrice->net_amount)->toEqualWithDelta(round((float) $samePrice->quantity_ordered * $agreedPrice, 2), 0.01);

        $newPrice = round($this->listPrice * 0.7, 2);
        agreePartnerPrice($this->orgPartner, $this->product, $newPrice);
        $otherPrice = pickAgreedPriceLine($this);

        expect($otherPrice->id)->not->toBe($transaction->id)
            ->and($otherPrice->order_id)->toBe($transaction->order_id)
            ->and((float) $otherPrice->agreed_unit_price)->toBe($newPrice)
            ->and((float) $otherPrice->net_amount)->toEqualWithDelta(round((float) $otherPrice->quantity_ordered * $newPrice, 2), 0.01);
    });

    test('a discount pass and a basket reprice leave an agreed price line alone', function () {
        $agreedPrice = round($this->listPrice * 0.6, 2);
        agreePartnerPrice($this->orgPartner, $this->product, $agreedPrice);

        $transaction = pickAgreedPriceLine($this);
        $gross       = (float) $transaction->gross_amount;
        $order       = $transaction->order;

        $transaction->update(['discretionary_offer' => 0.3, 'discretionary_offer_label' => 'by hand']);
        OrderHydrateDiscretionaryOffersData::run($order);
        CalculateOrderDiscounts::run($order->refresh());
        $transaction->refresh();

        expect((float) $transaction->gross_amount)->toBe($gross)
            ->and((float) $transaction->net_amount)->toBe($gross)
            ->and($transaction->current_discount_factor)->toEqual(1)
            ->and($transaction->offers_data)->toBeEmpty()
            ->and(DB::table('transaction_has_offer_allowances')->where('transaction_id', $transaction->id)->exists())->toBeFalse();

        expect($order->refresh()->state)->toBe(OrderStateEnum::CREATING);
        RecalculateTotalsOrdersInBasket::make()->handle($order->id);
        $transaction->refresh();

        expect((float) $transaction->gross_amount)->toBe($gross)
            ->and((float) $transaction->net_amount)->toBe($gross);
    });

    test('invoicing a short pick charges the agreed price for the units picked', function () {
        if (!$this->seller->warehouses()->exists()) {
            StoreWarehouse::make()->action($this->seller, Warehouse::factory()->definition());
        }

        $agreedPrice = round($this->listPrice * 1.5 + 1, 2);
        agreePartnerPrice($this->orgPartner, $this->product, $agreedPrice);

        $transaction = pickAgreedPriceLine($this, 20);
        $order       = $transaction->order;
        $units       = (float) $transaction->quantity_ordered;

        SendPartnerOrderToWarehouse::make()->action($order);
        $transaction->refresh();

        expect((float) $transaction->net_amount)->toEqualWithDelta(round($units * $agreedPrice, 2), 0.01);

        $deliveryNote     = $order->deliveryNotes()->first();
        $deliveryNoteItem = $deliveryNote->deliveryNoteItems()->first();
        $deliveryNoteItem->update(['quantity_picked' => (float) $deliveryNoteItem->quantity_required / 2]);

        $generator = GenerateInvoiceFromOrder::make();
        $lines     = $generator->loadTransactions($order->refresh(), $deliveryNote);
        $line      = $generator->recalculateTransactionTotals($lines->firstWhere('id', $transaction->id), $deliveryNote);
        $header    = $generator->recalculateTotals($order, $deliveryNote, $lines);

        expect($line['quantity'])->toEqualWithDelta($units / 2, 0.000001)
            ->and($line['gross_amount'])->toEqualWithDelta($agreedPrice * $units / 2, 0.01)
            ->and($line['net_amount'])->toBe($line['gross_amount'])
            ->and($header['gross_amount'])->toEqualWithDelta($agreedPrice * $units / 2, 0.01)
            ->and($header['goods_amount'])->toEqualWithDelta($agreedPrice * $units / 2, 0.01);

        $invoice            = GenerateInvoiceFromOrder::make()->action($order->refresh());
        $invoiceTransaction = $invoice->invoiceTransactions()->where('transaction_id', $transaction->id)->firstOrFail();

        expect((float) $invoiceTransaction->gross_amount)->toEqualWithDelta(round($agreedPrice * $units / 2, 2), 0.01)
            ->and((float) $invoiceTransaction->net_amount)->toBe((float) $invoiceTransaction->gross_amount);

        $invoice->update(['uuid' => Str::uuid()]);
        expect(ISDocInvoice::run($invoice->refresh()))->toMatch('/<UnitPrice>'.preg_quote(number_format($agreedPrice, 2, '.', ''), '/').'0*<\/UnitPrice>/');
    });

    test('the miscalculation watcher neither flags nor reprices an agreed price line', function () {
        $agreedPrice = round($this->listPrice * 0.6, 2);
        agreePartnerPrice($this->orgPartner, $this->product, $agreedPrice);

        $transaction = pickAgreedPriceLine($this);
        $gross       = (float) $transaction->gross_amount;
        $order       = $transaction->order;

        Sentry::spy();
        FixMiscalculatedTransactionAmounts::run($order->refresh(), true);
        Sentry::shouldNotHaveReceived('withScope');

        expect((float) $transaction->refresh()->gross_amount)->toBe($gross)
            ->and((float) $transaction->net_amount)->toBe($gross);

        DB::table('transactions')->where('id', $transaction->id)->update(['net_amount' => $gross - 1]);
        FixMiscalculatedTransactionAmounts::run($order->refresh(), true);
        Sentry::shouldHaveReceived('withScope')->once();

        expect((float) $transaction->refresh()->gross_amount)->toBe($gross)
            ->and((float) $transaction->net_amount)->toBe($gross);
    });
});

describe('partner agreed prices on the partner shopping screens', function () {
    afterEach(function () {
        DB::rollBack();
    });

    beforeEach(function () {
        $transactionsManager = new \Illuminate\Foundation\Testing\DatabaseTransactionsManager([null]);
        app()->instance('db.transactions', $transactionsManager);
        DB::connection()->setTransactionManager($transactionsManager);
        DB::beginTransaction();

        $seller = $this->orgPartner->partner;
        $seller->update([
            'is_manufacturing_hub' => true,
            'settings'             => array_replace_recursive($seller->settings ?? [], ['procurement' => ['partner_discount' => 0.45]]),
        ]);
        $this->seller = $seller->refresh();

        $sellerShop = $seller->shops()->first() ?? StoreShop::run($seller, Shop::factory()->definition());

        [, $sellerProduct]   = createProduct($sellerShop);
        $this->buyerOrgStock = createOrgStocks($this->orgPartner->organisation, [$sellerProduct->orgStocks()->first()->stock])[0];
        $this->orgPartner    = $this->orgPartner->refresh();
        $this->product       = GetPartnerSellingProduct::run($this->orgPartner, $this->buyerOrgStock->stock_id);
        $this->listPrice     = (float) $this->product->price;

        $pivot                    = DB::table('product_has_org_stocks')->where('product_id', $this->product->id)->first();
        $this->sellerOrgStockId   = (int) $pivot->org_stock_id;
        $this->skosPerProductUnit = (float) $pivot->quantity > 0 ? (float) $pivot->quantity : 1.0;
        $this->exchange           = $this->orgPartner->exchangeToOrgCurrency();
        $this->partnerCurrency    = $this->orgPartner->organisation->currency;
    });

    function buyerPricePerSko(object $test): ?float
    {
        $price = DB::selectOne('select '.PartnerSkoPrice::buyerPricePerSkoSql((string) $test->sellerOrgStockId, $test->orgPartner).' as price')->price;

        return $price === null ? null : (float) $price;
    }

    test('the buyer price per SKO is the product price less the hub discount without an agreed price', function () {
        expect(buyerPricePerSko($this))->toEqualWithDelta($this->listPrice * 0.55 / $this->skosPerProductUnit, 0.0001);
    });

    test('the buyer price per SKO is the agreed price, brought back from the partner currency', function () {
        $agreedPrice = round($this->listPrice * 0.7 * $this->exchange, 2);
        agreePartnerPrice($this->orgPartner, $this->product, $agreedPrice, $this->partnerCurrency);

        expect(buyerPricePerSko($this) * $this->exchange * $this->skosPerProductUnit)->toEqualWithDelta($agreedPrice, 0.0001);
    });

    test('buying prices per product flag the agreed ones', function () {
        $agreedPrice = round($this->listPrice * 1.2 * $this->exchange, 2);
        agreePartnerPrice($this->orgPartner, $this->product, $agreedPrice, $this->partnerCurrency);

        $prices = GetPartnerBuyingPrices::run($this->orgPartner, collect([$this->product->refresh()]));

        expect($prices[$this->product->id]['agreed'])->toBeTrue()
            ->and($prices[$this->product->id]['price'] * $this->exchange)->toEqualWithDelta($agreedPrice, 0.0001);
    });

    test('the open list value keeps list prices for the capacity check and shows agreed prices to the partner', function () {
        $agreedPrice = round($this->listPrice * 0.7 * $this->exchange, 2);
        agreePartnerPrice($this->orgPartner, $this->product, $agreedPrice, $this->partnerCurrency);

        $before = GetPartnerOrderCapacity::make()->openListValue($this->orgPartner);
        $item   = submittedPartnerShoppingListItem($this->orgPartner, $this->buyerOrgStock, ['quantity' => 10]);
        $after  = GetPartnerOrderCapacity::make()->openListValue($this->orgPartner);
        $skos   = 10.0;

        expect((float) $item->quantity)->toBeGreaterThanOrEqual($skos)
            ->and($after['value'] - $before['value'])->toEqualWithDelta($skos * $this->listPrice / $this->skosPerProductUnit, 0.01)
            ->and(($after['buyer_value'] - $before['buyer_value']) * $this->exchange)->toEqualWithDelta($skos * $agreedPrice / $this->skosPerProductUnit, 0.01);
    });

    test('a suggested line is priced at the agreed price through the factored product price', function () {
        $agreedPrice = round($this->listPrice * 0.8 * $this->exchange, 2);
        agreePartnerPrice($this->orgPartner, $this->product, $agreedPrice, $this->partnerCurrency);

        $factoredPrice = (float) DB::table('products')
            ->where('products.id', $this->product->id)
            ->value(DB::raw(PartnerSkoPrice::factoredProductPriceSql('products', $this->orgPartner)));

        expect($factoredPrice * SuggestPartnerShoppingList::make()->exchange($this->orgPartner))->toEqualWithDelta($agreedPrice, 0.0001);
    });
});
