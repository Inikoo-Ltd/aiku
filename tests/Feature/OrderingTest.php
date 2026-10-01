<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 26 Nov 2024 21:36:24 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Models\Inventory\OrgStock;
use App\Actions\Accounting\Invoice\RefundClaimToBalance;
use App\Actions\Accounting\Invoice\PayInvoice;
use App\Actions\Accounting\OrderPaymentApiPoint\StoreOrderPaymentLink;
use App\Actions\Accounting\Invoice\StoreRefund;
use App\Actions\Accounting\Invoice\StoreInvoice;
use App\Actions\CRM\Customer\UpdateCustomer;
use App\Actions\Comms\Email\SendInvoicePaidEmailToCustomer;
use App\Actions\Comms\Outbox\ProcessInvoicePaidNotification;
use App\Enums\Ordering\Order\OrderToBePaidByEnum;
use Lorisleiva\Actions\Decorators\JobDecorator;
use App\Actions\Accounting\Invoice\UpdateInvoice;
use App\Actions\Accounting\InvoiceTransaction\DeleteInProcessInvoiceTransaction;
use App\Actions\Accounting\InvoiceTransaction\StoreInvoiceTransaction;
use App\Actions\Accounting\InvoiceTransaction\UpdateInvoiceTransaction;
use App\Actions\Accounting\OrgPaymentServiceProvider\StoreOrgPaymentServiceProviderAccount;
use App\Actions\Billables\Charge\StoreCharge;
use App\Actions\Billables\ShippingZone\HydrateShippingZones;
use App\Actions\Billables\ShippingZone\DeleteShippingZone;
use App\Actions\Billables\ShippingZone\StoreShippingZone;
use App\Actions\Billables\ShippingZone\UpdateShippingZone;
use App\Actions\Billables\ShippingZoneSchema\DeleteShippingZoneSchema;
use App\Actions\Billables\ShippingZoneSchema\HydrateShippingZoneSchemas;
use App\Actions\Billables\ShippingZoneSchema\StoreShippingZoneSchema;
use App\Actions\Billables\ShippingZoneSchema\UpdateShippingZoneSchema;
use App\Actions\Catalogue\Collection\StoreCollection;
use App\Actions\Catalogue\Product\Json\GetIrisBasketTransactionsInCollection;
use App\Actions\Catalogue\Product\Json\GetOrderProducts;
use App\Actions\Catalogue\Product\Json\GetOrderProductsForModification;
use App\Actions\Catalogue\Product\SyncProductExclusiveCustomers;
use App\Actions\Catalogue\ShippingCountry\DeleteShippingCountry;
use App\Actions\Catalogue\ShippingCountry\StoreShippingCountry;
use App\Actions\Catalogue\ShippingCountry\UpdateShippingCountry;
use App\Actions\Catalogue\Shop\Seeders\SeedShopPermissions;
use App\Actions\Catalogue\Shop\StoreShop;
use App\Actions\CRM\Customer\StoreCustomer;
use App\Actions\Dispatching\DeliveryNote\StoreDeliveryNote;
use App\Actions\Dispatching\Shipment\StoreShipment;
use App\Actions\Dispatching\Shipper\StoreShipper;
use App\Actions\Dropshipping\CustomerClient\StoreCustomerClient;
use App\Actions\Dropshipping\CustomerClient\UpdateCustomerClient;
use App\Actions\Dropshipping\CustomerSalesChannel\StoreCustomerSalesChannel;
use App\Actions\Helpers\Intervals\ProcessResetIntervalsGroups;
use App\Actions\Helpers\Intervals\ProcessResetIntervalsOrganisations;
use App\Actions\Helpers\Intervals\ProcessResetIntervalsShops;
use App\Actions\Helpers\Intervals\ResetDailyIntervals;
use App\Actions\Ordering\Adjustment\StoreAdjustment;
use App\Actions\Ordering\Adjustment\UpdateAdjustment;
use App\Actions\Billables\Charge\DeleteCharge;
use App\Actions\Billables\Charge\UpdateCharge;
use App\Actions\Ordering\Order\CalculateOrderHangingCharges;
use App\Enums\Ordering\Order\OrderChargesEngineEnum;
use App\Actions\Ordering\Order\CalculateOrderShipping;
use App\Actions\Ordering\Order\CalculateOrderTotalAmounts;
use App\Actions\Ordering\Order\HydrateOrders;
use App\Actions\Ordering\Order\ImportTransactionInOrder;
use App\Actions\Ordering\Order\Hydrators\OrderHydrateShipments;
use App\Actions\Ordering\Order\PayOrder;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Retina\Dropshipping\Orders\PayRetinaOrderWithBalance;
use App\Actions\Retina\Ecom\Basket\RetinaEcomUpdateTransaction;
use App\Actions\Retina\Ecom\Basket\UI\IndexBasketTransactions;
use App\Actions\Catalogue\Product\StoreProduct;
use App\Actions\Catalogue\Product\StoreProductWebpage;
use App\Actions\Ordering\Order\UpdateOrder;
use App\Actions\Ordering\Order\ResetOrderTaxCategory;
use App\Actions\Ordering\Order\UpdateOrderBillingAddress;
use App\Actions\Ordering\Order\UpdateOrderDeliveryAddress;
use App\Actions\Ordering\Order\UpdateOrderGiftMessage;
use App\Actions\Ordering\Order\PdfOrderGiftMessage;
use App\Actions\Ordering\Order\UpdateOrderIsShippingTBC;
use App\Actions\Ordering\Order\UpdateOrderShippingTBCAmount;
use App\Actions\Billables\Service\StoreService;
use App\Actions\Ordering\Order\UpdateState\DispatchOrder;
use App\Actions\Ordering\Order\UpdateState\FinaliseOrder;
use App\Actions\Ordering\Order\UpdateState\SendOrderToWarehouse;
use App\Actions\Ordering\Order\UpdateState\SendUnpaidOrderToWarehouse;
use App\Actions\Ordering\Order\UpdateState\SubmitOrder;
use App\Actions\Ordering\Order\UpdateState\UpdateOrderStateToHandling;
use App\Actions\Ordering\Purge\HydratePurges;
use App\Actions\Ordering\Purge\StorePurge;
use App\Actions\Ordering\Purge\UpdatePurge;
use App\Actions\Ordering\PurgedOrder\UpdatePurgedOrder;
use App\Actions\Ordering\Transaction\DeleteTransaction;
use App\Actions\Ordering\Transaction\UpdateTransactionChargeAmount;
use App\Actions\Ordering\Order\GenerateInvoiceFromOrder;
use App\Actions\Iris\Basket\StoreEcomBasketTransaction;
use App\Actions\Retina\Dropshipping\Orders\ImportRetinaOrderTransaction;
use App\Actions\Retina\Ordering\StoreRetinaTransaction;
use App\Actions\Retina\Ordering\UpdateRetinaTransaction;
use App\Actions\Maintenance\Ordering\RemoveDiscontinuedProductsFromBaskets;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\Ordering\Transaction\SyncBasketLinesWithProductStock;
use Illuminate\Support\Str;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Actions\Accounting\Payment\StorePayment;
use App\Actions\Accounting\CreditTransaction\StoreCreditTransaction;
use App\Actions\CRM\Customer\PayOrderWithCustomerBalance;
use App\Actions\Ordering\Transaction\StoreTransactionFromAdjustment;
use App\Actions\Ordering\Transaction\StoreTransactionFromCharge;
use App\Actions\Ordering\Transaction\StoreTransactionFromShipping;
use App\Actions\Ordering\Transaction\UpdateTransaction;
use App\Actions\Ordering\UpcomingTransaction\DeleteUpcomingTransaction;
use App\Actions\Ordering\UpcomingTransaction\StoreUpcomingTransaction;
use App\Actions\Ordering\UpcomingTransaction\UpdateUpcomingTransaction;
use App\Actions\SysAdmin\GetSectionRoute;
use App\Actions\UI\Grp\Layout\GetShopNavigation;
use App\Enums\Accounting\CreditTransaction\CreditTransactionTypeEnum;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\Accounting\Invoice\InvoicePayStatusEnum;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Accounting\PaymentServiceProvider\PaymentServiceProviderEnum;
use App\Enums\Accounting\PaymentServiceProvider\PaymentServiceProviderTypeEnum;
use App\Enums\Analytics\AikuSection\AikuSectionEnum;
use App\Enums\Catalogue\Charge\ChargeStateEnum;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Catalogue\Charge\ChargeTriggerEnum;
use App\Enums\Catalogue\Charge\ChargeTypeEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Enums\Ordering\Adjustment\AdjustmentTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Enums\Ordering\Purge\PurgeTypeEnum;
use App\Enums\Ordering\Transaction\TransactionStateEnum;
use App\Enums\Ordering\Transaction\UpcomingTransactionStateEnum;
use App\Enums\Ordering\Transaction\UpcomingTransactionTypeEnum;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Http\Resources\Fulfilment\RetinaEcomBasketTransactionsResources;
use App\Http\Resources\Ordering\TransactionsResource;
use App\Models\Ordering\UpcomingTransaction;
use App\Models\Accounting\CreditTransaction;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceTransaction;
use App\Models\Accounting\PaymentServiceProvider;
use App\Models\Analytics\AikuScopedSection;
use App\Models\Billables\Charge;
use App\Models\Catalogue\Asset;
use App\Models\Catalogue\Product;
use Illuminate\Validation\ValidationException;
use App\Enums\Billables\Service\ServiceStateEnum;
use App\Models\Billables\ShippingZone;
use App\Models\Billables\ShippingZoneSchema;
use App\Models\Catalogue\HistoricAsset;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\Shipment;
use App\Models\Dispatching\Shipper;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\Platform;
use App\Models\Helpers\Address;
use App\Models\Procurement\OrgPartner;
use App\Models\Helpers\Country;
use App\Actions\Ordering\Order\WriteOffOrderShortfall;
use App\Enums\Ordering\Order\OrderPayDetailedStatusEnum;
use App\Enums\Ordering\Order\OrderPayStatusEnum;
use App\Enums\UI\Ordering\OrdersBacklogTabsEnum;
use App\Models\Ordering\Adjustment;
use App\Enums\Helpers\Import\UploadRecordStatusEnum;
use App\Imports\Ordering\TransactionImport;
use App\Models\Helpers\Upload;
use App\Models\Helpers\TaxCategory;
use App\Actions\Ordering\Order\UI\IndexOrderChannels;
use App\Actions\Dropshipping\Platform\GetPlatformTimeSeriesStats;
use App\Actions\Dropshipping\Platform\ProcessPlatformTimeSeriesRecords;
use App\Actions\SysAdmin\Group\Seeders\SeedSalesChannels;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\Ordering\SalesChannel\SalesChannelTypeEnum;
use App\Models\Dropshipping\PlatformSalesChannelTimeSeriesRecord;
use App\Models\Ordering\Order;
use App\Models\Ordering\Purge;
use App\Models\Ordering\PurgedOrder;
use App\Actions\Ordering\SalesChannel\StoreSalesChannel;
use App\Models\Ordering\SalesChannel;
use App\Models\Ordering\ShippingCountry;
use App\Models\Ordering\Transaction;
use App\Actions\Catalogue\Shop\CalculateShopOrderAlertSizes;
use App\Actions\Ordering\Order\SendNewOrderAlert;
use App\Actions\SysAdmin\User\GetUserOrderAlerts;
use App\Enums\Ordering\Order\OrderAlertTypeEnum;
use App\Events\BroadcastNewOrderAlert;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\Event;
use App\Models\SysAdmin\Permission;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;
use App\Actions\Retina\Dropshipping\Orders\UpdateRetinaOrderGiftMessagePdf;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

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

    list(
        $this->tradeUnit,
        $this->product
    ) = createProduct($this->shop);

    $this->customer = createCustomer($this->shop);

    $this->warehouse = createWarehouse();

    Config::set(
        'inertia.testing.page_paths',
        [resource_path('js/Pages/Grp')]
    );
    actingAs($this->user);
});

afterEach(function () {
    $this->shop->update(['shipping_zone_schema_id' => null]);
    $this->organisation->update(['settings' => Arr::except($this->organisation->settings, 'fulfilment_gate')]);
    $this->product->orgStocks()->update(['quantity_available' => 0]);
});

test('store shipping country action', function () {
    $countryId = Country::where('iso3', 'FRA')->first()->id;
    // Ensure migration exists (in case snapshot DB was loaded)
    $shippingCountry = StoreShippingCountry::make()->action($this->shop, [
        'country_id' => $countryId
    ]);
    $this->shop->refresh();
    expect($shippingCountry)->toBeInstanceOf(ShippingCountry::class)
        ->and($this->shop->stats->number_shipping_countries)->toBe(1);
});


test('update shipping country action', function () {
    $shippingCountry = StoreShippingCountry::make()->action($this->shop, [
        'country_id' => 4,
    ]);
    expect($shippingCountry)->toBeInstanceOf(ShippingCountry::class);

    $updated = UpdateShippingCountry::make()->action($shippingCountry, [
        'territories' => ['A', 'B']
    ]);

    expect($updated->fresh()->territories)->toBe(['A', 'B']);
});

test('delete shipping country action dispatches hydrator and removes model', function () {
    $shippingCountry = StoreShippingCountry::make()->action($this->shop, [
        'country_id' => 6,
    ]);
    expect(ShippingCountry::query()->count())->toBeGreaterThanOrEqual(1);

    DeleteShippingCountry::make()->action($shippingCountry);

    expect(ShippingCountry::query()->whereKey($shippingCountry->id)->exists())->toBeFalse();
});


test('create order', function () {
    $billingAddress  = new Address(Address::factory()->definition());
    $deliveryAddress = new Address(Address::factory()->definition());

    $modelData = Order::factory()->definition();
    data_set($modelData, 'billing_address', $billingAddress);
    data_set($modelData, 'delivery_address', $deliveryAddress);


    $order = StoreOrder::make()->action($this->customer, $modelData);

    $adminGuest = createAdminGuest($this->group);
    actingAs($adminGuest->getUser());
    $this->customer->refresh();

    expect($order)->toBeInstanceOf(Order::class)
        ->and($order->state)->toBe(OrderStateEnum::CREATING)
        ->and($order->customer)->toBeInstanceOf(Customer::class)
        ->and($this->group->orderingStats->number_orders)->toBe(1)
        ->and($this->group->orderingStats->number_orders_state_creating)->toBe(1)
        ->and($this->group->orderingStats->number_orders_handing_type_shipping)->toBe(1)
        ->and($this->organisation->orderingStats->number_orders)->toBe(1)
        ->and($this->organisation->orderingStats->number_orders_state_creating)->toBe(1)
        ->and($this->organisation->orderingStats->number_orders_handing_type_shipping)->toBe(1)
        ->and($this->shop->orderingStats->number_orders)->toBe(1)
        ->and($this->shop->orderingStats->number_orders_state_creating)->toBe(1)
        ->and($this->shop->orderingStats->number_orders_handing_type_shipping)->toBe(1)
        ->and($this->customer->stats->number_orders)->toBe(1)
        ->and($this->customer->stats->number_orders_state_creating)->toBe(1)
        ->and($this->customer->stats->number_orders_handing_type_shipping)->toBe(1)
        ->and($order->stats->number_item_transactions_at_submission)->toBe(0);

    return $order;
});

test('UI Edit Order', function () {
    $billingAddress  = new Address(Address::factory()->definition());
    $deliveryAddress = new Address(Address::factory()->definition());

    $modelData = Order::factory()->definition();
    data_set($modelData, 'billing_address', $billingAddress);
    data_set($modelData, 'delivery_address', $deliveryAddress);

    $order = StoreOrder::make()->action($this->customer, $modelData);

    $adminGuest = createAdminGuest($this->group);
    actingAs($adminGuest->getUser());

    $response = get(
        route(
            'grp.org.shops.show.ordering.orders.edit',
            [
                'organisation' => $this->organisation->slug,
                'shop'         => $this->shop->slug,
                'order'        => $order->slug,
            ]
        )
    );

    $response->assertOk();
    $response->assertInertia(function (AssertableInertia $page) use ($order) {
        $page
            ->component('EditModel')
            ->has('breadcrumbs')
            ->has('title')
            ->has('pageHead', function (AssertableInertia $head) use ($order) {
                $head->where('title', $order->slug)
                    ->has('actions', 1)
                    ->where('actions.0.style', 'exitEdit')
                    ->etc();
            })
            ->has('formData', function (AssertableInertia $form) use ($order) {
                $form->has('blueprint')
                    ->where('args.updateRoute.name', 'grp.models.order.update')
                    ->where('args.updateRoute.parameters.order', $order->id)
                    ->etc();
            });
    });
});

test('get order products', function (Order $order) {
    // Create a transaction if needed (may not be necessary if the order already has products)
    $order->transactions->first()
        ?: StoreTransaction::make()->action(
            $order,
            $this->product->historicAsset,
            Transaction::factory()->definition()
        );

    $order->refresh();


    // Test the GetOrderProducts action
    $result = GetOrderProducts::make()->handle($order);


    expect($result)->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($result->count())->toBeGreaterThanOrEqual(1);

    // Test that the product data is correctly retrieved
    $products = $result->items();
    expect($products)->toBeArray()
        ->and(count($products))->toBeGreaterThanOrEqual(1);

    // Verify the first product data
    $firstProduct = $products[0];
    expect($firstProduct->id)->toBe(1)->and($firstProduct->transaction_id)->toBe(1);

    // Test the JSON response
    if (method_exists(GetOrderProducts::class, 'jsonResponse')) {
        $jsonResponse = GetOrderProducts::make()->jsonResponse($result);
        expect($jsonResponse)->toBeInstanceOf(\Illuminate\Http\Resources\Json\AnonymousResourceCollection::class);
    }

    return $order;
})->depends('create order');


test('order products picker offers not for sale products to partners only', function (Order $order) {
    $this->product->update(['is_for_sale' => false]);

    $offered = fn () => collect(GetOrderProducts::make()->handle($order)->items())->pluck('id')
        ->merge(collect(GetOrderProductsForModification::make()->handle($order)->items())->pluck('id'));

    expect($offered())->not->toContain($this->product->id);

    $orgPartner = OrgPartner::create([
        'group_id'        => $order->group_id,
        'organisation_id' => $order->organisation_id,
        'partner_id'      => $order->organisation_id,
        'customer_id'     => $order->customer_id,
    ]);

    expect($order->isPartnerOrder())->toBeTrue()
        ->and($offered())->toContain($this->product->id);

    $outsideCustomer = StoreCustomer::make()->action($this->shop, Customer::factory()->definition());
    SyncProductExclusiveCustomers::make()->action($this->product, ['customer_ids' => [$outsideCustomer->id]]);
    expect($offered())->not->toContain($this->product->id);

    SyncProductExclusiveCustomers::make()->action($this->product, ['customer_ids' => [$order->customer_id]]);
    expect($offered())->toContain($this->product->id);

    SyncProductExclusiveCustomers::make()->action($this->product, ['customer_ids' => []]);
    $orgPartner->delete();
    $this->product->update(['is_for_sale' => true]);

    return $order;
})->depends('create order');

test('delete previous transaction', function (Order $order) {
    $transaction = $order->transactions()->first();
    UpdateTransaction::make()->action(
        $transaction,
        ['quantity_ordered' => 0]
    );
    $order->refresh();
    expect($order->transactions()->count())->toBe(0)
        ->and($order->stats->number_item_transactions)->toBe(0)
        ->and($order->stats->number_item_transactions_at_submission)->toBe(0);

    return $order;
})->depends('get order products');


test('import transactions in order from spreadsheet', function (Order $order) {
    $path = tempnam(sys_get_temp_dir(), 'order-transactions').'.csv';
    file_put_contents($path, "code,quantity\n".$this->product->code.",7\n");
    $file = new \Illuminate\Http\UploadedFile($path, 'transactions.csv', 'text/csv', null, true);

    $upload = ImportTransactionInOrder::make()->action($order, ['file' => $file]);
    $order->refresh();

    $transaction = $order->transactions()->where('historic_asset_id', $this->product->historicAsset->id)->first();

    expect($upload->number_success)->toBe(1)
        ->and($upload->number_fails)->toBe(0)
        ->and($transaction->quantity_ordered)->toEqual(7)
        ->and($transaction->data['bulk_import']['id'])->toBe($upload->id);

    DeleteTransaction::make()->action($transaction);
    $order->refresh();
    expect($order->transactions()->count())->toBe(0);

    return $order;
})->depends('delete previous transaction');

test('create transaction', function ($order) {
    $transactionData = Transaction::factory()->definition();
    $historicAsset   = $this->product->historicAsset;
    expect($historicAsset)->toBeInstanceOf(HistoricAsset::class);
    $transaction = StoreTransaction::make()->action($order, $historicAsset, $transactionData);

    $order->refresh();


    expect($transaction)->toBeInstanceOf(Transaction::class)
        ->and($transaction->order->stats->number_item_transactions_at_submission)->toBe(1)
        ->and($order->stats->number_item_transactions)->toBe(1);

    return $transaction;
})->depends('delete previous transaction');

test('store transaction for existing product adds to quantity instead of duplicating', function (Transaction $transaction) {
    $order         = $transaction->order;
    $historicAsset = $this->product->historicAsset;
    $quantityBefore = (float) $transaction->quantity_ordered;

    $transactionData                     = Transaction::factory()->definition();
    $transactionData['quantity_ordered'] = 3;

    $dedupedTransaction = StoreTransaction::make()->action($order, $historicAsset, $transactionData);
    $order->refresh();

    expect($dedupedTransaction->id)->toBe($transaction->id)
        ->and((float) $dedupedTransaction->quantity_ordered)->toEqual($quantityBefore + 3)
        ->and($order->transactions()->where('model_type', 'Product')->count())->toBe(1);
})->depends('create transaction');

test('proforma price breakdown shows gross, discount and net only when requested', function (Transaction $transaction) {
    $transaction->update(['gross_amount' => 100, 'net_amount' => 80]);
    $order = $transaction->order->refresh();

    $renderProforma = fn (bool $priceBreakdown) => view('invoices.templates.pdf.proforma-invoice', [
        'shop'                 => $order->shop,
        'order'                => $order,
        'transactions'         => $order->transactions()->where('model_type', 'Product')->get(),
        'totalItemsNet'        => $order->total_amount,
        'totalShipping'        => 0,
        'totalNet'             => '0.00',
        'amountToDeduct'       => 0,
        'pro_mode'             => false,
        'country_of_origin'    => false,
        'rrp'                  => false,
        'parts'                => false,
        'commodity_codes'      => false,
        'weight'               => false,
        'barcode'              => false,
        'hide_payment_status'  => false,
        'cpnp'                 => false,
        'group_by_tariff_code' => false,
        'price_breakdown'      => $priceBreakdown,
    ])->render();

    $symbol = $order->currency->symbol;

    expect($renderProforma(true))->toContain(__('Gross'))
        ->toContain($symbol.'100.00')
        ->toContain('-'.$symbol.'20.00')
        ->toContain($symbol.'80.00')
        ->and($renderProforma(false))->not->toContain(__('Gross'));
})->depends('create transaction');

test('create transaction from adjustment', function (Order $order) {
    $adjustment = StoreAdjustment::make()->action(
        $order->shop,
        [
            'type'       => AdjustmentTypeEnum::CREDIT,
            'net_amount' => 10,
        ],
        strict: false
    );
    expect($adjustment)->toBeInstanceOf(Adjustment::class);
    $transaction = StoreTransactionFromAdjustment::make()->action($order, $adjustment, [
        'date'             => Carbon::now(),
        'quantity_ordered' => 1,
    ]);

    $order->refresh();

    expect($transaction)->toBeInstanceOf(Transaction::class)
        ->and($transaction->order->stats->number_item_transactions_at_submission)->toBe(1)
        ->and($order->stats->number_item_transactions)->toBe(1)
        ->and($order->shop->stats->number_adjustments)->toBe(1)
        ->and($order->shop->stats->number_adjustments_type_credit)->toBe(1)
        ->and($order->organisation->catalogueStats->number_adjustments)->toBe(1)
        ->and($order->group->catalogueStats->number_adjustments)->toBe(1);

    return $transaction;
})->depends('create order');

test('update adjustment', function () {
    $adjustment = StoreAdjustment::make()->action(
        $this->shop,
        [
            'type'       => AdjustmentTypeEnum::CREDIT,
            'net_amount' => 10,
        ],
        strict: false
    );
    expect($adjustment)->toBeInstanceOf(Adjustment::class);
    $updatedAdjustment = UpdateAdjustment::make()->action($adjustment, [
        'net_amount' => 20,
    ], strict: false);

    $updatedAdjustment->refresh();

    expect($updatedAdjustment)->toBeInstanceOf(Adjustment::class)
        ->and(intval($updatedAdjustment->net_amount))->toBe(20)
        ->and($updatedAdjustment->shop->stats->number_adjustments)->toBe(2);

    return $updatedAdjustment;
});

test('create transaction from charge', function (Order $order) {
    $charge = StoreCharge::make()->action($order->shop, [
        'code'        => 'charge-1',
        'name'        => 'charge 1',
        'description' => 'charge 1 description',
        'state'       => ChargeStateEnum::ACTIVE,
        'trigger'     => ChargeTriggerEnum::ORDER,
        'type'        => ChargeTypeEnum::TRACKING,
    ]);

    expect($charge)->toBeInstanceOf(Charge::class);
    $transaction = StoreTransactionFromCharge::make()->action($order, $charge, [
        'date'             => Carbon::now(),
        'quantity_ordered' => 1,
    ]);

    $order->refresh();

    expect($transaction)->toBeInstanceOf(Transaction::class)
        ->and($transaction->order->stats->number_item_transactions_at_submission)->toBe(1)
        ->and($order->stats->number_item_transactions)->toBe(1);

    return $transaction;
})->depends('create order');

test('small order charge configured through the UI applies to an order', function (Order $order) {
    $charge = StoreCharge::make()->action($order->shop, [
        'code'        => 'SOC',
        'name'        => 'SOC',
        'description' => 'small order charge',
        'type'        => ChargeTypeEnum::HANGING,
    ]);

    expect($charge->state)->toBe(ChargeStateEnum::IN_PROCESS)
        ->and($charge->settings)->not->toHaveKey('rules');

    UpdateCharge::make()->action($charge, [
        'state'     => ChargeStateEnum::ACTIVE,
        'min_order' => 2550,
        'amount'    => 255,
    ], strict: false);

    $charge->refresh();
    expect($charge->settings['rules'])->toBe('<;2550');
    $chargeTransactions = fn () => $order->transactions()
        ->where('model_type', 'Charge')
        ->where('model_id', $charge->id);

    $order->goods_amount = 1000;
    CalculateOrderHangingCharges::run($order);

    expect($chargeTransactions()->count())->toBe(1)
        ->and((int) $chargeTransactions()->first()->net_amount)->toBe(255);

    UpdateTransactionChargeAmount::make()->handle($chargeTransactions()->first(), ['amount' => 0]);
    $order->goods_amount = 1000;
    CalculateOrderHangingCharges::run($order);

    expect((float) $chargeTransactions()->first()->net_amount)->toBe(0.0)
        ->and((int) $chargeTransactions()->first()->gross_amount)->toBe(255);

    $order->goods_amount = 3000;
    CalculateOrderHangingCharges::run($order);

    expect($chargeTransactions()->count())->toBe(0);
})->depends('create order');

test('removing the small order charge keeps it off the order', function (Order $order) {
    $charge = $order->shop->charges()
        ->where('type', ChargeTypeEnum::HANGING)
        ->where('state', ChargeStateEnum::ACTIVE)
        ->firstOrFail();

    $order->update(['charges_engine' => OrderChargesEngineEnum::AUTO]);
    $order->goods_amount = 1000;
    CalculateOrderHangingCharges::run($order);

    $chargeTransactions = fn () => $order->transactions()
        ->where('model_type', 'Charge')
        ->where('model_id', $charge->id);

    expect($chargeTransactions()->count())->toBe(1);

    DeleteTransaction::make()->action($chargeTransactions()->first());

    $order->refresh();
    expect($order->charges_engine)->toBe(OrderChargesEngineEnum::MANUAL)
        ->and($chargeTransactions()->count())->toBe(0);

    $order->goods_amount = 1000;
    CalculateOrderHangingCharges::run($order);

    expect($chargeTransactions()->count())->toBe(0);
})->depends('create order');

test('delete an unused charge', function (Order $order) {
    $charge = StoreCharge::make()->action($order->shop, [
        'code'        => 'del-me',
        'name'        => 'delete me',
        'description' => 'never used',
        'type'        => ChargeTypeEnum::OTHER,
    ]);
    $assetId = $charge->asset_id;

    DeleteCharge::make()->action($charge);

    expect(Charge::find($charge->id))->toBeNull()
        ->and(Asset::find($assetId))->toBeNull();
})->depends('create order');

test('deleting a charge already used on an order is refused', function (Order $order) {
    $charge = StoreCharge::make()->action($order->shop, [
        'code'        => 'keep-me',
        'name'        => 'keep me',
        'description' => 'used on an order',
        'type'        => ChargeTypeEnum::OTHER,
    ]);

    StoreTransactionFromCharge::make()->action($order, $charge, [
        'date'             => Carbon::now(),
        'quantity_ordered' => 1,
    ]);

    expect(fn () => DeleteCharge::make()->action($charge))->toThrow(ValidationException::class)
        ->and(Charge::find($charge->id))->not->toBeNull();
})->depends('create order');

test('create transaction from shipping', function (Order $order) {
    $shippingZoneSchema = StoreShippingZoneSchema::make()->action($order->shop, [
        'name' => 'schema 1',
    ]);
    $shipping           = StoreShippingZone::make()->action($shippingZoneSchema, [
        'code'        => 'SHIP-1',
        'name'        => 'shipping 1',
        'status'      => true,
        'price'       => [
            'type'  => "Step Order Items Net Amount",
            "steps" => [
                [
                    "to"    => 175,
                    "from"  => 0,
                    "price" => 20
                ],
                [
                    "to"    => 450,
                    "from"  => 175,
                    "price" => 40
                ],
                [
                    "to"    => 975,
                    "from"  => 450,
                    "price" => 60
                ],
                [
                    "to"    => "INF",
                    "from"  => 975,
                    "price" => 0
                ]
            ]
        ],
        'territories' => [
            [
                "country_code" => "FR"
            ],
            [
                "country_code" => "BE"
            ],
            [
                "country_code" => "LU"
            ]
        ],
        'position'    => 1,
        'is_failover' => false,
    ]);
    expect($shipping)->toBeInstanceOf(ShippingZone::class);
    $transaction = StoreTransactionFromShipping::make()->action($order, $shipping, [
        'date'             => Carbon::now(),
        'quantity_ordered' => 1,
    ]);

    $order->refresh();

    expect($transaction)->toBeInstanceOf(Transaction::class)
        ->and($transaction->order->stats->number_item_transactions_at_submission)->toBe(1)
        ->and($order->stats->number_item_transactions)->toBe(1);

    return $transaction;
})->depends('create order');

test('dropshipping shop strips per-shipper pricing on update', function () {
    $shippingZone = ShippingZone::where('code', 'SHIP-1')->firstOrFail();
    $originalShopType = $shippingZone->shop->type;
    $shippingZone->shop->update(['type' => ShopTypeEnum::DROPSHIPPING]);
    $shippingZone->refresh();

    $shipper = StoreShipper::make()->action($shippingZone->organisation, [
        'code'     => 'DS-SHIP',
        'name'     => 'DS Shipper',
        'trade_as' => 'DS Shipper',
    ]);

    try {
        $updated = UpdateShippingZone::make()->action($shippingZone, [
            'shippers_price' => [
                ['shipper_id' => $shipper->id, 'type' => 'TBC'],
            ],
        ]);
    } finally {
        $shippingZone->shop->update(['type' => $originalShopType]);
    }

    expect($updated->shippers_price)->toBe([]);
})->depends('create transaction from shipping');

test('update transaction', function ($transaction) {
    $transaction = UpdateTransaction::make()->action(
        $transaction,
        [
            'quantity_ordered' => $transaction->quantity_ordered + 1,
        ]
    );

    expect($transaction)->toBeInstanceOf(Transaction::class);
})->depends('create transaction');


test('update order', function ($order) {
    $order = UpdateOrder::make()->action($order, Order::factory()->definition());

    $this->assertModelExists($order);

    $order = UpdateOrder::make()->action($order, ['is_re' => true]);
    expect($order->is_re)->toBeTrue()
        ->and($order->tax_category_id)->not->toBeNull();
})->depends('create order');

test('update order state to submitted', function (Order $order) {
    $order = SubmitOrder::make()->action($order);

    /** The backlog splits submitted orders into paid and unpaid and CS work from those two
     * counters alone, so the halves must always add back up to the whole */
    $handlingStats = $order->shop->orderHandlingStats->refresh();
    expect($handlingStats->number_orders_state_submitted_paid + $handlingStats->number_orders_state_submitted_not_paid)
        ->toBe($handlingStats->number_orders_state_submitted);

    expect($order->pay_status)->toEqual(OrderPayStatusEnum::UNPAID)
        ->and($order->state)->toEqual(OrderStateEnum::SUBMITTED)
        ->and($order->shop->orderingStats->number_orders_state_submitted)->toBe(1)
        ->and($order->organisation->orderingStats->number_orders_state_submitted)->toBe(1)
        ->and($order->group->orderingStats->number_orders_state_submitted)->toBe(1)
        ->and($order->stats->number_item_transactions)->toBe(1);

    return $order;
})->depends('create order');

test('no pay status can hide a submitted order from the backlog', function (Order $order) {
    /** Staff only ever see submitted orders through these two buckets, so between them they must
     * account for every submitted order whatever its pay status is (HELP-3116). A status invented
     * later must land in the chase queue by default, never in neither. */
    $statuses = collect(OrderPayStatusEnum::cases())->pluck('value')->push('a_status_invented_later');

    foreach ($statuses as $status) {
        DB::table('orders')->where('id', $order->id)->update(['pay_status' => $status]);

        $settled    = Order::where('id', $order->id)->paySettled()->count();
        $notSettled = Order::where('id', $order->id)->payNotSettled()->count();

        expect($settled + $notSettled)->toBe(1, "pay status [$status] is in ".($settled + $notSettled).' buckets, must be exactly 1');
    }

    DB::table('orders')->where('id', $order->id)->update(['pay_status' => OrderPayStatusEnum::UNPAID->value]);
})->depends('update order state to submitted');

test('every order state has a backlog queue or is deliberately excluded from one', function () {
    /** The backlog's tabs are a hand written list while the states are an enum, so a state added
     * later would have no tab and its orders would be as invisible as a null pay status was
     * (HELP-3116). Give a new state a tab, or say out loud that it needs no queue. */
    $accountedFor = collect(OrdersBacklogTabsEnum::statesShown())
        ->merge(OrdersBacklogTabsEnum::statesNeedingNoQueue())
        ->pluck('value');

    $homeless = collect(OrderStateEnum::cases())
        ->reject(fn (OrderStateEnum $state) => $accountedFor->contains($state->value))
        ->pluck('value');

    expect($homeless)->toBeEmpty('order states shown by no backlog tab: '.$homeless->implode(', '));
});

test('customer cannot update basket transaction on submitted order', function (Order $order) {
    $webUser = new \App\Models\CRM\WebUser();
    $webUser->setRelation('customer', $order->customer);
    $transaction = $order->transactions()->first();

    $request = Mockery::mock(\Lorisleiva\Actions\ActionRequest::class);
    $request->shouldReceive('user')->andReturn($webUser);

    expect(fn () => \App\Actions\Iris\Basket\UpdateEcomBasketTransaction::make()->asController($transaction, $request))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class, 'Order can not be modified after submission');
})->depends('update order state to submitted');

test('update order state to in warehouse', function (Order $order) {
    $deliveryNote = SendOrderToWarehouse::make()->action($order, []);
    $order->refresh();
    expect($deliveryNote)->toBeInstanceOf(DeliveryNote::class)
        ->and($order->state)->toEqual(OrderStateEnum::IN_WAREHOUSE);

    return $order;
})->depends('update order state to submitted');

test('delivery note recipient follows the order recipient, not the customer', function (Order $order) {
    $order = UpdateOrder::make()->action($order, [
        'contact_name' => 'Jana Novak',
        'company_name' => 'Novak Retail s.r.o.',
    ]);

    expect($order->company_name)->toBe('Novak Retail s.r.o.')
        ->and(SendOrderToWarehouse::make()->getCompanyName($order))->toBe('Novak Retail s.r.o.')
        ->and(SendOrderToWarehouse::make()->getContactName($order))->toBe('Jana Novak');

    $deliveryNote = $order->deliveryNotes()->first();
    expect($deliveryNote->company_name)->toBe('Novak Retail s.r.o.')
        ->and($deliveryNote->contact_name)->toBe('Jana Novak');

    $order = UpdateOrder::make()->action($order, ['company_name' => null]);
    expect(SendOrderToWarehouse::make()->getCompanyName($order))->toBe($order->customer->company_name)
        ->and($order->deliveryNotes()->first()->company_name)->toBe($order->customer->company_name);

    return $order;
})->depends('update order state to in warehouse');

test('staff can change the billing address of an order already in the warehouse', function (Order $order) {
    $newAddress                   = Address::factory()->definition();
    $newAddress['address_line_1'] = 'Billing street 42';

    $order = UpdateOrderBillingAddress::make()->action($order, ['address' => $newAddress]);

    expect($order->billingAddress->address_line_1)->toBe('Billing street 42')
        ->and($order->billing_country_id)->toBe($order->billingAddress->country_id);
})->depends('update order state to in warehouse');

test('update order private warehouse note propagates to delivery note', function (Order $order) {
    $order = UpdateOrder::make()->action($order, ['private_warehouse_note' => 'fragile, double box']);
    /** @var DeliveryNote $deliveryNote */
    $deliveryNote = $order->deliveryNotes()->first();
    expect($order->private_warehouse_note)->toBe('fragile, double box')
        ->and($deliveryNote->private_warehouse_note)->toBe('fragile, double box');

    return $order;
})->depends('update order state to in warehouse');

test('update order state to Handling', function (Order $order) {
    $order = UpdateOrderStateToHandling::make()->action($order);
    $order->refresh();
    expect($order)->toBeInstanceOf(Order::class)
        ->and($order->state)->toEqual(OrderStateEnum::HANDLING);

    return $order;
})->depends('update order state to in warehouse');


test('update order state to Finalised ', function (Order $order) {
    $shipper = StoreShipper::make()->action($order->organisation, [
        'code'     => 'hello',
        'name'     => 'hello',
        'trade_as' => 'hello',
    ]);

    /** @var DeliveryNote $deliveryNote */
    $deliveryNote = $order->deliveryNotes()->where('type', DeliveryNoteTypeEnum::ORDER)->first();
    StoreShipment::make()->action($deliveryNote, $shipper, [
        'reference'          => 'abc',
        'tracking'           => 'abc',
        'combined_label_url' => 'https://www.google.com',
    ]);

    $order = FinaliseOrder::make()->action($order);
    $order->refresh();
    expect($order)->toBeInstanceOf(Order::class)
        ->and($order->state)->toEqual(OrderStateEnum::FINALISED);

    return $order;
})->depends('update order state to Handling');

test('finalising an already invoiced order does not create a second invoice', function (Order $order) {
    expect(fn () => FinaliseOrder::make()->action($order))->toThrow(ValidationException::class)
        ->and($order->invoices()->where('type', InvoiceTypeEnum::INVOICE)->count())->toBe(1);
})->depends('update order state to Finalised ');

test('tbc shipping amount is refused once the order is finalised', function (Order $order) {
    $shippingAmount = $order->shipping_amount;
    expect(fn () => UpdateOrderShippingTBCAmount::make()->action($order, ['shipping_tbc_amount' => 115]))->toThrow(ValidationException::class)
        ->and($order->fresh()->shipping_amount)->toEqual($shippingAmount)
        ->and($order->fresh()->shipping_tbc_amount)->not->toEqual(115);
})->depends('update order state to Finalised ');

test('create customer client', function () {
    $shop     = StoreShop::make()->action($this->organisation, Shop::factory()->definition());
    $customer = StoreCustomer::make()->action($shop, Customer::factory()->definition());
    $platform = Platform::where('type', PlatformTypeEnum::MANUAL)->first();

    $customerSalesChannel = StoreCustomerSalesChannel::make()->action($customer, $platform, [
        'reference' => 'test_manual_reference'
    ]);

    StoreCustomerSalesChannel::make()->action($customer, $platform, []);
    $customerClient = StoreCustomerClient::make()->action(
        $customerSalesChannel,
        array_merge(
            CustomerClient::factory()->definition(),
        )
    );
    $this->assertModelExists($customerClient);
    expect($customerClient->shop->code)->toBe($shop->code)
        ->and($customerClient->customer->reference)->toBe($customer->reference);

    return $customerClient;
});

test('update customer client', function ($customerClient) {
    $customerClient = UpdateCustomerClient::make()->action($customerClient, ['reference' => '001']);
    expect($customerClient->reference)->toBe('001');
})->depends('create customer client');

test('create invoice from customer', function () {
    $invoiceData = Invoice::factory()->definition();
    data_set($invoiceData, 'billing_address', new Address(Address::factory()->definition()));
    $invoice = StoreInvoice::make()->action($this->customer, $invoiceData);
    expect($invoice)->toBeInstanceOf(Invoice::class)
        ->and($invoice->customer)->toBeInstanceOf(Customer::class)
        ->and($invoice->customer->stats->number_invoices)->toBe(2);

    return $invoice;
})->depends();

test('update invoice from customer', function ($invoice) {
    $invoice = UpdateInvoice::make()->action($invoice, [
        'reference' => '00001a'

    ]);
    expect($invoice->reference)->toBe('00001a');
})->depends('create invoice from customer');

test('create invoice from order', function (Order $order) {
    $transaction = $order->transactions->first();
    $invoiceData = Invoice::factory()->definition();
    data_set($invoiceData, 'billing_address', new Address(Address::factory()->definition()));
    data_set($invoiceData, 'reference', '00002');
    $invoice            = StoreInvoice::make()->action($order, $invoiceData);
    $invoiceTransaction = StoreInvoiceTransaction::make()->action($invoice, $transaction, [
        'date'            => now(),
        'tax_category_id' => $transaction->tax_category_id,
        'quantity'        => 10,
        'gross_amount'    => 1000,
        'net_amount'      => 1000,
    ]);
    $customer           = $invoice->customer;
    $this->shop->refresh();
    expect($invoice)->toBeInstanceOf(Invoice::class)
        ->and($customer)->toBeInstanceOf(Customer::class)
        ->and($invoice->customer->id)->toBe($order->customer_id)
        ->and($invoice->reference)->toBe('00002')
        ->and($customer->stats->number_invoices)->toBe(3)
        ->and($this->shop->orderingStats->number_invoices)->toBe(3)
        ->and($invoiceTransaction)->toBeInstanceOf(InvoiceTransaction::class);

    expect(fn () => UpdateOrder::make()->action($order, ['is_re' => false]))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    return $invoice;
})->depends('create order', 'update invoice from customer');

test('update invoice transaction', function (Invoice $invoice) {
    $transaction        = $invoice->invoiceTransactions->first();
    $updatedTransaction = UpdateInvoiceTransaction::make()->action($transaction, [
        'quantity' => 100
    ]);
    expect($updatedTransaction)->toBeInstanceOf(InvoiceTransaction::class)
        ->and(intval($updatedTransaction->quantity))->toBe(100);

    return $updatedTransaction;
})->depends('create invoice from order');

test('delete invoice transaction', function (InvoiceTransaction $invoiceTransaction) {
    $invoice = $invoiceTransaction->invoice;
    DeleteInProcessInvoiceTransaction::make()->action($invoiceTransaction);
    $invoice->refresh();
    expect($invoice)->toBeInstanceOf(Invoice::class)
        ->and($invoice->stats->number_invoice_transactions)->toBe(0)
        ->and((float) $invoice->net_amount)->toBe(0.0)
        ->and((float) $invoice->tax_amount)->toBe(0.0)
        ->and((float) $invoice->total_amount)->toBe(0.0);

    return $invoice;
})->depends('update invoice transaction');

test('create old order', function () {
    $billingAddress  = new Address(Address::factory()->definition());
    $deliveryAddress = new Address(Address::factory()->definition());

    $modelData = Order::factory()->definition();
    data_set($modelData, 'billing_address', $billingAddress);
    data_set($modelData, 'delivery_address', $deliveryAddress);

    $order = StoreOrder::make()->action(parent: $this->customer, modelData: $modelData);


    $transactionData = Transaction::factory()->definition();
    $historicAsset   = $this->product->historicAsset;
    expect($historicAsset)->toBeInstanceOf(HistoricAsset::class);
    $transaction = StoreTransaction::make()->action($order, $historicAsset, $transactionData);

    $order->refresh();

    expect($order)->toBeInstanceOf(Order::class)
        ->and($order->state)->toBe(OrderStateEnum::CREATING)
        ->and($order->stats->number_item_transactions)->toBe(1)
        ->and($order->stats->number_item_transactions_at_submission)->toBe(1)
        ->and($transaction)->toBeInstanceOf(Transaction::class);

    $this->customer->refresh();
    $shop = $order->shop;
    $shop->refresh();
    $order->update([
        'updated_at' => Date::now()->subDays(40)->toDateString()
    ]);

    return $order;
});

test('create purge', function (Order $order) {
    $shop  = $order->shop;
    $purge = StorePurge::make()->action($shop, [
        'type'          => PurgeTypeEnum::MANUAL,
        'scheduled_at'  => now(),
        'inactive_days' => 30,
    ]);

    expect($purge)->toBeInstanceOf(Purge::class)
        ->and($purge->type)->toBe(PurgeTypeEnum::MANUAL)
        ->and($purge->stats->estimated_number_orders)->toBe(1);

    return $purge;
})->depends('create old order');

test('update purge', function (Purge $purge) {
    $newSchedule = Date::now()->addDays(5);
    $purge       = UpdatePurge::make()->action($purge, [
        'scheduled_at' => $newSchedule
    ]);

    expect($purge)->toBeInstanceOf(Purge::class)
        ->and(Carbon::parse($purge->scheduled_at)->toDateString())->toBe($newSchedule->toDateString());

    return $purge;
})->depends('create purge');

test('update purge order', function (Purge $purge) {
    $purgedOrder        = $purge->purgedOrders->first();
    $updatedPurgedOrder = UpdatePurgedOrder::make()->action($purgedOrder, [
        'error_message' => 'error test'
    ]);

    expect($updatedPurgedOrder)->toBeInstanceOf(PurgedOrder::class)
        ->and($updatedPurgedOrder->error_message)->toBe('error test');

    return $updatedPurgedOrder;
})->depends('create purge');

test('delete transaction', function (Order $order) {
    $transaction = $order->transactions->first();

    DeleteTransaction::make()->action($transaction);
    $order->refresh();

    expect($order->transactions()->count())->toBe(0);

    return $order;
})->depends('create old order');

test('UI create asset shipping', function () {
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.shops.show.billables.shipping.create', [$this->organisation->slug, $this->shop]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CreateModel')
            ->where('title', 'New schema')
            ->has('breadcrumbs', 4)
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', 'New schema')
                    ->etc()
            )
            ->has('formData');
    });
});

test('UI show asset shipping', function () {
    $shippingZoneSchema = ShippingZoneSchema::first();
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.shops.show.billables.shipping.current.show', [$this->organisation->slug, $this->shop, $shippingZoneSchema]));
    $response->assertInertia(function (AssertableInertia $page) use ($shippingZoneSchema) {
        $page
            ->component('Org/Catalogue/ShippingZoneSchema')
            ->where('title', 'Shipping Zone Schema')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $shippingZoneSchema->name)
                    ->etc()
            )
            ->has('navigation')
            ->has('tabs');
    });
});

test('UI edit asset shipping', function () {
    $shippingZoneSchema = ShippingZoneSchema::first();
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.shops.show.billables.shipping.current.edit', [$this->organisation->slug, $this->shop, $shippingZoneSchema]));
    $response->assertInertia(function (AssertableInertia $page) use ($shippingZoneSchema) {
        $page
            ->component('EditModel')
            ->where('title', 'Shipping Zone Schema')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $shippingZoneSchema->name)
                    ->etc()
            )
            ->has('navigation')
            ->has('formData');
    });
});

test('UI show ordering backlog', function () {
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.shops.show.ordering.backlog', [$this->organisation->slug, $this->shop]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Ordering/OrdersBacklog')
            ->where('title', 'Orders backlog')
            ->has('breadcrumbs', 4)
            ->where('tabs.current', 'submitted_unpaid')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', 'Orders backlog')
                    ->etc()
            );
    });
});

test('UI show order navigation follows the bucket it was opened from', function () {
    $this->withoutExceptionHandling();

    $makeOrder = function (string $date, int $netAmount) {
        $modelData = Order::factory()->definition();
        data_set($modelData, 'billing_address', new Address(Address::factory()->definition()));
        data_set($modelData, 'delivery_address', new Address(Address::factory()->definition()));

        $order = StoreOrder::make()->action($this->customer, $modelData);
        $order->update(['state' => OrderStateEnum::IN_WAREHOUSE, 'date' => $date, 'net_amount' => $netAmount, 'submitted_at' => null]);

        return $order->refresh();
    };

    $newest = $makeOrder('2026-07-20 10:00:00', 300);
    $middle = $makeOrder('2026-07-19 10:00:00', 200);
    $oldest = $makeOrder('2026-07-18 10:00:00', 100);

    $routeParameters = [$this->organisation->slug, $this->shop->slug, $middle->slug];

    $response = get(route('grp.org.shops.show.ordering.orders.show', $routeParameters).'?bucket=in_warehouse&bucket_scope=shop');
    $response->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('navigation.previous.label', $newest->reference)
            ->where('navigation.next.label', $oldest->reference)
            ->etc()
    );

    $ascendingByAmount = get(route('grp.org.shops.show.ordering.orders.show', $routeParameters).'?bucket=in_warehouse&bucket_scope=shop&bucket_sort=net_amount');
    $ascendingByAmount->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('navigation.previous.label', $oldest->reference)
            ->where('navigation.next.label', $newest->reference)
            ->etc()
    );

    $byJoinedColumn = get(route('grp.org.shops.show.ordering.orders.show', $routeParameters).'?bucket=in_warehouse&bucket_scope=shop&bucket_sort=-customer_name');
    $byJoinedColumn->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('navigation.previous.label', $oldest->reference)
            ->where('navigation.next.label', $newest->reference)
            ->etc()
    );

    $byNullColumn = get(route('grp.org.shops.show.ordering.orders.show', $routeParameters).'?bucket=in_warehouse&bucket_scope=shop&bucket_sort=submitted_at');
    $byNullColumn->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('navigation.previous.label', $newest->reference)
            ->where('navigation.next.label', $oldest->reference)
            ->etc()
    );

    $withoutBucket = get(route('grp.org.shops.show.ordering.orders.show', $routeParameters));
    $withoutBucket->assertInertia(
        fn (AssertableInertia $page) => $page->has('navigation')->etc()
    );

});

test('UI show ordering backlog waiting crm items', function () {
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.shops.show.ordering.backlog.waiting_items', [$this->organisation->slug, $this->shop]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Ordering/WaitingCrmItems')
            ->where('title', 'Waiting Items (CRM)')
            ->has('breadcrumbs', 5)
            ->has('waiting_crm_items')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', 'Waiting Items')
                    ->etc()
            );
    });
});

test('UI index ordering purges', function () {
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.shops.show.ordering.purges.index', [$this->organisation->slug, $this->shop]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Ordering/Purges')
            ->where('title', 'Purges')
            ->has('breadcrumbs', 3)
            ->has('data')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', 'Purges')
                    ->etc()
            );
    });
});

test('UI index ordering invoices by payment status', function () {
    $this->withoutExceptionHandling();

    setPermissionsTeamId($this->group->id);
    SeedShopPermissions::run($this->shop);
    $this->user->givePermissionTo(
        Permission::where('name', "orders.{$this->shop->id}.view")->firstOrFail()
    );
    Cache::tags('auth-user:'.$this->user->id)->flush();
    actingAs($this->user->fresh());

    $orderingNavigation = GetShopNavigation::run($this->shop, $this->user->fresh());
    expect(collect(data_get($orderingNavigation, 'ordering.topMenu.subSections'))->pluck('route.name')->all())
        ->toContain('grp.org.shops.show.ordering.invoices.index');

    StoreInvoice::make()->action($this->customer, Invoice::factory()->definition());
    $paidInvoice = StoreInvoice::make()->action($this->customer, Invoice::factory()->definition());
    $paidInvoice->updateQuietly([
        'pay_status' => InvoicePayStatusEnum::PAID,
    ]);

    $invoiceQuery = Invoice::query()
        ->where('shop_id', $this->shop->id)
        ->where('type', InvoiceTypeEnum::INVOICE)
        ->whereNot('in_process', true);

    $allInvoicesCount    = (clone $invoiceQuery)->count();
    $paidInvoicesCount   = (clone $invoiceQuery)->where('pay_status', InvoicePayStatusEnum::PAID)->count();
    $unpaidInvoicesCount = (clone $invoiceQuery)->where('pay_status', InvoicePayStatusEnum::UNPAID)->count();

    $routeParameters = [
        'organisation' => $this->organisation->slug,
        'shop'         => $this->shop->slug,
    ];

    get(route('grp.org.shops.show.ordering.invoices.index', $routeParameters))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Org/Ordering/Invoices')
            ->where('title', 'Invoices')
            ->where('tabs.current', 'all')
            ->has('tabs.navigation', 3)
            ->where('all.meta.total', $allInvoicesCount)
            ->where('pageHead.subNavigation.1.route.name', 'grp.org.shops.show.ordering.invoices.index')
            ->has('breadcrumbs', 3));

    get(route('grp.org.shops.show.ordering.invoices.index', [
        ...$routeParameters,
        'tab' => 'paid',
    ]))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('tabs.current', 'paid')
        ->where('paid.meta.total', $paidInvoicesCount));

    get(route('grp.org.shops.show.ordering.invoices.index', [
        ...$routeParameters,
        'tab' => 'unpaid',
    ]))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('tabs.current', 'unpaid')
        ->where('unpaid.meta.total', $unpaidInvoicesCount));
});

test('UI create ordering purge', function () {
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.shops.show.ordering.purges.create', [$this->organisation->slug, $this->shop]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CreateModel')
            ->where('title', 'New purge')
            ->has('breadcrumbs', 4)
            ->has('formData', fn ($page) => $page
                ->where('route', [
                    'name'       => 'grp.models.purge.store',
                    'parameters' => [
                        'shop' => $this->shop->id,
                    ]
                ])
                ->etc())
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', 'New purge')
                    ->etc()
            );
    });
});

test('UI edit ordering purge', function () {
    $purge = Purge::first();
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.shops.show.ordering.purges.edit', [$this->organisation->slug, $this->shop, $purge]));
    $response->assertInertia(function (AssertableInertia $page) use ($purge) {
        $page
            ->component('EditModel')
            ->where('title', 'Purge')
            ->has('breadcrumbs', 3)
            ->has('formData', fn ($page) => $page
                ->where('args', [
                    'updateRoute' => [
                        'name'       => 'grp.models.purge.update',
                        'parameters' => $purge->id

                    ],
                ])
                ->etc())
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $purge->scheduled_at->toISOString())
                    ->etc()
            );
    });
});

test('UI get section route index', function () {
    $sectionScope = GetSectionRoute::make()->handle('grp.org.shops.show.ordering.orders.index', [
        'organisation' => $this->organisation->slug,
        'shop'         => $this->shop->slug
    ]);
    expect($sectionScope)->toBeInstanceOf(AikuScopedSection::class)
        ->and($sectionScope->organisation_id)->toBe($this->organisation->id)
        ->and($sectionScope->code)->toBe(AikuSectionEnum::SHOP_ORDERING->value)
        ->and($sectionScope->model_slug)->toBe($this->shop->slug);
});

test('test reset intervals', function () {
    $this->artisan('intervals:reset-day')->assertExitCode(0);
    $this->artisan('intervals:reset-week')->assertExitCode(0);
    $this->artisan('intervals:reset-month')->assertExitCode(0);
    $this->artisan('intervals:reset-quarter')->assertExitCode(0);
    $this->artisan('intervals:reset-year')->assertExitCode(0);
});

test('purge hydrators', function () {
    $purge = Purge::first();
    HydratePurges::run($purge);
    $this->artisan('hydrate:purges')->assertExitCode(0);
});

test('order hydrators', function () {
    $order = Order::first();
    HydrateOrders::run($order);
    $this->artisan('hydrate:orders ')->assertExitCode(0);
});

test('Ordering hydrators', function () {
    $this->artisan('hydrate', [
        '--sections' => 'ordering',
    ])->assertExitCode(0);
});

test('Pay order creates payment and attaches to order', function () {
    $billingAddress  = new Address(Address::factory()->definition());
    $deliveryAddress = new Address(Address::factory()->definition());

    $orderData = Order::factory()->definition();
    data_set($orderData, 'billing_address', $billingAddress);
    data_set($orderData, 'delivery_address', $deliveryAddress);

    $order = StoreOrder::make()->action($this->customer, $orderData);

    $paymentAccount = StoreOrgPaymentServiceProviderAccount::make()->action(
        $this->organisation,
        PaymentServiceProvider::where('type', PaymentServiceProviderTypeEnum::CASH->value)->first(),
        [
            'code' => 'ACC'.mt_rand(1000, 9999),
            'name' => 'Cash Account',
        ]
    );

    $amount    = 50.25;
    $reference = 'PAY-'.uniqid();

    $payment = PayOrder::make()->action($order, $paymentAccount, [
        'amount'    => $amount,
        'reference' => $reference,
        'status'    => PaymentStatusEnum::SUCCESS,
        'state'     => PaymentStateEnum::COMPLETED,
    ]);

    $order->refresh();

    expect($payment->amount)->toBe((string)$amount)
        ->and($payment->reference)->toBe($reference)
        ->and($order->payments()->where('payments.id', $payment->id)->exists())->toBeTrue();
});

test('Pay order with accounts payment account creates credit transaction', function () {
    $billingAddress  = new Address(Address::factory()->definition());
    $deliveryAddress = new Address(Address::factory()->definition());

    $orderData = Order::factory()->definition();
    data_set($orderData, 'billing_address', $billingAddress);
    data_set($orderData, 'delivery_address', $deliveryAddress);

    $order = StoreOrder::make()->action($this->customer, $orderData);

    $paymentAccount = StoreOrgPaymentServiceProviderAccount::make()->action(
        $this->organisation,
        PaymentServiceProvider::where('type', PaymentServiceProviderTypeEnum::CASH->value)->first(),
        [
            'code' => 'ACC'.mt_rand(1000, 9999),
            'name' => 'Accounts Account',
        ]
    );

    // Ensure this account behaves as an accounts ledger so PayOrder creates a credit transaction
    $paymentAccount->is_accounts = true;
    $paymentAccount->save();

    $amount  = 75.00;
    $payment = PayOrder::make()->action($order, $paymentAccount, [
        'amount' => $amount,
        'status' => PaymentStatusEnum::SUCCESS,
        'state'  => PaymentStateEnum::COMPLETED,
    ]);

    $payment->refresh();

    expect($payment->creditTransaction)->not->toBeNull()
        ->and((float)$payment->creditTransaction->amount)->toBe(-$amount)
        ->and($payment->creditTransaction->payment_id)->toBe($payment->id);
});

test('Pay order attaches payment to invoice when invoice exists', function () {
    $billingAddress  = new Address(Address::factory()->definition());
    $deliveryAddress = new Address(Address::factory()->definition());

    $orderData = Order::factory()->definition();
    data_set($orderData, 'billing_address', $billingAddress);
    data_set($orderData, 'delivery_address', $deliveryAddress);

    $order = StoreOrder::make()->action($this->customer, $orderData);

    $invoice = StoreInvoice::make()->action($order, [
        'type'         => InvoiceTypeEnum::INVOICE,
        'currency_id'  => $this->shop->currency_id,
        'net_amount'   => 0,
        'total_amount' => 0,
        'gross_amount' => 0,
        'tax_amount'   => 0,
    ]);

    $paymentAccount = StoreOrgPaymentServiceProviderAccount::make()->action(
        $this->organisation,
        PaymentServiceProvider::where('type', PaymentServiceProviderTypeEnum::CASH->value)->first(),
        [
            'code' => 'ACC'.mt_rand(1000, 9999),
            'name' => 'Cash Account 2',
        ]
    );

    $payment = PayOrder::make()->action($order, $paymentAccount, [
        'amount' => 10.00,
        'status' => PaymentStatusEnum::SUCCESS,
        'state'  => PaymentStateEnum::COMPLETED,
    ]);

    $payment->refresh();
    $invoice->refresh();

    expect($payment->invoices()->where('invoices.id', $invoice->id)->exists())->toBeTrue();
});

describe('COD invoice paid email', function () {
    function payCodOrder(string $paymentAccountType): array
    {
        $billingAddress  = new Address(Address::factory()->definition());
        $deliveryAddress = new Address(Address::factory()->definition());

        $orderData = Order::factory()->definition();
        data_set($orderData, 'billing_address', $billingAddress);
        data_set($orderData, 'delivery_address', $deliveryAddress);
        data_set($orderData, 'to_be_paid_by', OrderToBePaidByEnum::CASH_ON_DELIVERY);

        $order = StoreOrder::make()->action(test()->customer, $orderData);

        $invoice = StoreInvoice::make()->action($order, [
            'type'                 => InvoiceTypeEnum::INVOICE,
            'currency_id'          => test()->shop->currency_id,
            'net_amount'           => 10,
            'total_amount'         => 10,
            'gross_amount'         => 10,
            'tax_amount'           => 0,
        ]);

        $invoice->update(['is_cash_on_delivery' => true]);

        $paymentAccount = StoreOrgPaymentServiceProviderAccount::make()->action(
            test()->organisation,
            PaymentServiceProvider::where('type', $paymentAccountType)->first(),
            [
                'code' => 'ACC'.mt_rand(1000, 9999),
                'name' => 'Account '.mt_rand(1000, 9999),
            ]
        );

        Queue::fake();

        PayOrder::make()->action($order, $paymentAccount, [
            'amount' => 10.00,
            'status' => PaymentStatusEnum::SUCCESS,
            'state'  => PaymentStateEnum::COMPLETED,
        ]);

        return [$order, $invoice->refresh()];
    }

    test('paying a COD order notifies the customer', function () {
        payCodOrder(PaymentServiceProviderTypeEnum::CASH->value);

        Queue::assertPushed(JobDecorator::class, fn ($job) => $job->displayName() === ProcessInvoicePaidNotification::class);
    });

    test('a COD order settled through a non cash account still notifies the customer', function () {
        [, $invoice] = payCodOrder(PaymentServiceProviderTypeEnum::BANK->value);

        expect($invoice->pay_status)->toBe(InvoicePayStatusEnum::PAID);

        Queue::assertPushed(JobDecorator::class, fn ($job) => $job->displayName() === ProcessInvoicePaidNotification::class);
    });

    test('the invoice email goes out only once the invoice reads as paid', function () {
        [, $invoice] = payCodOrder(PaymentServiceProviderTypeEnum::CASH->value);

        Queue::fake();
        ProcessInvoicePaidNotification::make()->handle($invoice->id);
        Queue::assertPushed(JobDecorator::class, fn ($job) => $job->displayName() === SendInvoicePaidEmailToCustomer::class);

        $unpaidInvoice = StoreInvoice::make()->action($this->customer, array_merge(
            Invoice::factory()->definition(),
            ['total_amount' => 10, 'net_amount' => 10, 'gross_amount' => 10, 'tax_amount' => 0]
        ));

        Queue::fake();
        ProcessInvoicePaidNotification::make()->handle($unpaidInvoice->id);
        Queue::assertNotPushed(JobDecorator::class, fn ($job) => $job->displayName() === SendInvoicePaidEmailToCustomer::class);
    });

    test('a paid invoice that is not cash on delivery is left alone', function () {
        [, $invoice] = payCodOrder(PaymentServiceProviderTypeEnum::CASH->value);
        $invoice->update(['is_cash_on_delivery' => false]);

        Queue::fake();
        ProcessInvoicePaidNotification::make()->handle($invoice->id);
        Queue::assertNotPushed(JobDecorator::class, fn ($job) => $job->displayName() === SendInvoicePaidEmailToCustomer::class);
    });
});

test('invoice from overpaid order credits excess to customer balance', function () {
    $billingAddress  = new Address(Address::factory()->definition());
    $deliveryAddress = new Address(Address::factory()->definition());

    $orderData = Order::factory()->definition();
    data_set($orderData, 'billing_address', $billingAddress);
    data_set($orderData, 'delivery_address', $deliveryAddress);

    $order = StoreOrder::make()->action($this->customer, $orderData);

    $paymentAccount = StoreOrgPaymentServiceProviderAccount::make()->action(
        $this->organisation,
        PaymentServiceProvider::where('type', PaymentServiceProviderTypeEnum::CASH->value)->first(),
        [
            'code' => 'ACC'.mt_rand(1000, 9999),
            'name' => 'Cash Account Excess',
        ]
    );

    PayOrder::make()->action($order, $paymentAccount, [
        'amount' => 100.00,
        'status' => PaymentStatusEnum::SUCCESS,
        'state'  => PaymentStateEnum::COMPLETED,
    ]);

    $excessCreditsBefore = CreditTransaction::where('customer_id', $this->customer->id)
        ->where('type', CreditTransactionTypeEnum::FROM_EXCESS)->count();

    $invoice = GenerateInvoiceFromOrder::make()->action($order->refresh());

    $excessCreditsAfter = CreditTransaction::where('customer_id', $this->customer->id)
        ->where('type', CreditTransactionTypeEnum::FROM_EXCESS)->count();

    expect($invoice)->toBeInstanceOf(Invoice::class)
        ->and($excessCreditsAfter)->toBe($excessCreditsBefore + 1)
        ->and($invoice->delivery_country_id)->toBe($order->deliveryAddress->country_id)
        ->and($invoice->deliveryAddress->postal_code)->toBe($order->deliveryAddress->postal_code);

    $refund = StoreRefund::make()->action($invoice, []);

    expect($refund->delivery_address_id)->toBe($invoice->delivery_address_id)
        ->and($refund->delivery_country_id)->toBe($invoice->delivery_country_id);
});

test('invoice from overpaid order paid by bank transfer credits excess to customer balance', function () {
    $billingAddress  = new Address(Address::factory()->definition());
    $deliveryAddress = new Address(Address::factory()->definition());

    $orderData = Order::factory()->definition();
    data_set($orderData, 'billing_address', $billingAddress);
    data_set($orderData, 'delivery_address', $deliveryAddress);

    $order = StoreOrder::make()->action($this->customer, $orderData);

    $paymentAccount = StoreOrgPaymentServiceProviderAccount::make()->action(
        $this->organisation,
        PaymentServiceProvider::where('type', PaymentServiceProviderTypeEnum::BANK->value)->first(),
        [
            'code' => 'ACC'.mt_rand(1000, 9999),
            'name' => 'Bank Account Excess',
        ]
    );

    expect($paymentAccount->type->isManuallySettled())->toBeTrue();

    PayOrder::make()->action($order, $paymentAccount, [
        'amount' => 100.00,
        'status' => PaymentStatusEnum::SUCCESS,
        'state'  => PaymentStateEnum::COMPLETED,
    ]);

    $excessCreditsBefore = CreditTransaction::where('customer_id', $this->customer->id)
        ->where('type', CreditTransactionTypeEnum::FROM_EXCESS)->count();

    $invoice = GenerateInvoiceFromOrder::make()->action($order->refresh());

    $excessCreditsAfter = CreditTransaction::where('customer_id', $this->customer->id)
        ->where('type', CreditTransactionTypeEnum::FROM_EXCESS)->count();

    expect($invoice)->toBeInstanceOf(Invoice::class)
        ->and($excessCreditsAfter)->toBe($excessCreditsBefore + 1)
        ->and($order->refresh()->payments()->count())->toBe(2);
});

test('invoice from overpaid order with payment settled at invoicing does not credit excess', function () {
    $billingAddress  = new Address(Address::factory()->definition());
    $deliveryAddress = new Address(Address::factory()->definition());

    $orderData = Order::factory()->definition();
    data_set($orderData, 'billing_address', $billingAddress);
    data_set($orderData, 'delivery_address', $deliveryAddress);

    $order = StoreOrder::make()->action($this->customer, $orderData);

    $paymentAccount = StoreOrgPaymentServiceProviderAccount::make()->action(
        $this->organisation,
        PaymentServiceProvider::where('code', PaymentServiceProviderEnum::PASTPAY->value)->first(),
        [
            'code' => 'ACC'.mt_rand(1000, 9999),
            'name' => 'Pastpay Account Excess',
        ]
    );

    expect($paymentAccount->type->isManuallySettled())->toBeTrue();

    PayOrder::make()->action($order, $paymentAccount, [
        'amount' => 100.00,
        'status' => PaymentStatusEnum::SUCCESS,
        'state'  => PaymentStateEnum::COMPLETED,
    ]);

    $excessCreditsBefore = CreditTransaction::where('customer_id', $this->customer->id)
        ->where('type', CreditTransactionTypeEnum::FROM_EXCESS)->count();

    $invoice = GenerateInvoiceFromOrder::make()->action($order->refresh());

    $excessCreditsAfter = CreditTransaction::where('customer_id', $this->customer->id)
        ->where('type', CreditTransactionTypeEnum::FROM_EXCESS)->count();

    expect($invoice)->toBeInstanceOf(Invoice::class)
        ->and($excessCreditsAfter)->toBe($excessCreditsBefore)
        ->and($order->refresh()->payments()->count())->toBe(1);
});

test('create shipping zone schema', function () {
    $shippingZoneSchema = StoreShippingZoneSchema::make()->action($this->shop, ShippingZoneSchema::factory()->definition());
    expect($shippingZoneSchema)->toBeInstanceOf(ShippingZoneSchema::class);

    return $shippingZoneSchema;
});

test('update shipping zone schema', function ($shippingZoneSchema) {
    $shippingZoneSchema = UpdateShippingZoneSchema::make()->action($shippingZoneSchema, ShippingZoneSchema::factory()->definition());
    $this->assertModelExists($shippingZoneSchema);
})->depends('create shipping zone schema');

test('delete shipping zone schema action removes model', function () {
    $shippingZoneSchema = StoreShippingZoneSchema::make()->action($this->shop, ShippingZoneSchema::factory()->definition());

    DeleteShippingZoneSchema::make()->action($shippingZoneSchema);

    expect(ShippingZoneSchema::query()->whereKey($shippingZoneSchema->id)->exists())->toBeFalse();
});

test('delete shipping zone schema command removes model', function () {
    $shippingZoneSchema = StoreShippingZoneSchema::make()->action($this->shop, ShippingZoneSchema::factory()->definition());

    $this->artisan('delete:shipping_zone_schema '.$shippingZoneSchema->slug)
        ->expectsOutput('Shipping zone schema '.$shippingZoneSchema->name.' deleted')
        ->assertSuccessful();

    expect(ShippingZoneSchema::query()->whereKey($shippingZoneSchema->id)->exists())->toBeFalse();
});

test('delete shipping zone schema command reports a missing schema', function () {
    $this->artisan('delete:shipping_zone_schema missing-shipping-zone-schema')
        ->expectsOutput('Shipping zone schema not found')
        ->assertFailed();
});

test('create shipping zone', function ($shippingZoneSchema) {
    $shippingZone = StoreShippingZone::make()->action($shippingZoneSchema, ShippingZone::factory()->definition());
    $this->assertModelExists($shippingZoneSchema);

    return $shippingZone;
})->depends('create shipping zone schema');

test('update shipping zone', function ($shippingZone) {
    $shippingZone = UpdateShippingZone::make()->action($shippingZone, ShippingZone::factory()->definition());
    $this->assertModelExists($shippingZone);
})->depends('create shipping zone');

test('delete shipping zone action removes zone and dependants', function () {
    $shippingZoneSchema = StoreShippingZoneSchema::make()->action($this->shop, ShippingZoneSchema::factory()->definition());
    $shippingZone       = StoreShippingZone::make()->action($shippingZoneSchema, ShippingZone::factory()->definition());

    DeleteShippingZone::make()->action($shippingZone);

    expect(ShippingZone::query()->whereKey($shippingZone->id)->exists())->toBeFalse()
        ->and($shippingZone->stats()->exists())->toBeFalse()
        ->and($shippingZone->asset?->trashed())->toBeTrue();
});

test('delete shipping zone command removes model', function () {
    $shippingZoneSchema = StoreShippingZoneSchema::make()->action($this->shop, ShippingZoneSchema::factory()->definition());
    $shippingZone       = StoreShippingZone::make()->action($shippingZoneSchema, ShippingZone::factory()->definition());

    $this->artisan('delete:shipping_zone '.$shippingZone->slug)
        ->expectsOutput('Shipping zone '.$shippingZone->name.' deleted')
        ->assertSuccessful();

    expect(ShippingZone::query()->whereKey($shippingZone->id)->exists())->toBeFalse();
});

test('delete shipping zone command reports a missing zone', function () {
    $this->artisan('delete:shipping_zone missing-shipping-zone')
        ->expectsOutput('Shipping zone not found')
        ->assertFailed();
});


test('shipping zone schemas hydrators', function () {
    $shippingZoneSchema = ShippingZoneSchema::first();
    HydrateShippingZoneSchemas::run($shippingZoneSchema);
    $this->artisan('hydrate:shipping_zone_schemas')->assertExitCode(0);
});

test('shipping zone hydrators', function () {
    $shippingZone = ShippingZone::first();
    HydrateShippingZones::run($shippingZone);
    $this->artisan('hydrate:shipping_zones')->assertExitCode(0);
});

test('order is_shipping_tbc becomes true when shipping zone price type is TBC', function () {
    // Create an order
    $order = StoreOrder::make()->action($this->customer, Order::factory()->definition());

    // Create a shipping zone schema and a zone with nested orders.price.type
    $schema = StoreShippingZoneSchema::make()->action($this->shop, ShippingZoneSchema::factory()->definition());

    $zone = StoreShippingZone::make()->action(
        $schema,
        array_merge(
            ShippingZone::factory()->definition(),
            [
                'price' => [
                    'type' => 'TBC',
                ],
            ]
        )
    );

    // Attach zone to order
    $order->update(['shipping_zone_id' => $zone->id]);

    // Run action
    UpdateOrderIsShippingTBC::run($order);

    expect($order->fresh()->is_shipping_tbc)->toBeTrue();
});

test('order is_shipping_tbc becomes false when shipping zone price type is not TBC', function () {
    $order = StoreOrder::make()->action($this->customer, Order::factory()->definition());

    $schema = StoreShippingZoneSchema::make()->action($this->shop, ShippingZoneSchema::factory()->definition());
    $zone   = StoreShippingZone::make()->action(
        $schema,
        array_merge(
            ShippingZone::factory()->definition(),
            [
                'price' => [
                    'orders' => [
                        'price' => [
                            'type' => 'FIXED',
                        ],
                    ],
                ],
            ]
        )
    );

    $order->update(['shipping_zone_id' => $zone->id, 'is_shipping_tbc' => true]);

    UpdateOrderIsShippingTBC::run($order);

    expect($order->fresh()->is_shipping_tbc)->toBeFalse();
});

test('command updates is_shipping_tbc to true for TBC zone', function () {
    $order = StoreOrder::make()->action($this->customer, Order::factory()->definition());

    $schema = StoreShippingZoneSchema::make()->action($this->shop, [
        'name' => 'Schema A',
    ]);
    $zone   = StoreShippingZone::make()->action($schema, [
        'code'        => 'TBC-ZONE',
        'name'        => 'TBC Zone',
        'status'      => true,
        'price'       => ['type' => 'TBC'],
        'territories' => [],
        'position'    => 1,
    ]);

    $order->update([
        'shipping_zone_id' => $zone->id,
        'shipping_engine'  => \App\Enums\Ordering\Order\OrderShippingEngineEnum::AUTO,
        'is_shipping_tbc'  => false,
    ]);

    \Artisan::call('order:is-shipping-tbc', ['--slug' => $order->slug]);

    expect($order->fresh()->is_shipping_tbc)->toBeTrue();
});

test('command updates is_shipping_tbc to false for non-TBC zone', function () {
    $order = StoreOrder::make()->action($this->customer, Order::factory()->definition());

    $schema = StoreShippingZoneSchema::make()->action($this->shop, [
        'name' => 'Schema B',
    ]);
    $zone   = StoreShippingZone::make()->action($schema, [
        'code'        => 'FLAT-ZONE',
        'name'        => 'Flat Zone',
        'status'      => true,
        'price'       => ['type' => 'Flat'],
        'territories' => [],
        'position'    => 1,
    ]);

    $order->update([
        'shipping_zone_id' => $zone->id,
        'shipping_engine'  => \App\Enums\Ordering\Order\OrderShippingEngineEnum::AUTO,
        'is_shipping_tbc'  => true,
    ]);

    \Artisan::call('order:is-shipping-tbc', ['--slug' => $order->slug]);

    expect($order->fresh()->is_shipping_tbc)->toBeFalse();
});

test('order is_shipping_tbc false when no shipping zone present', function () {
    $order = StoreOrder::make()->action($this->customer, Order::factory()->definition());

    // Ensure no shipping zone is linked
    $order->update(['shipping_zone_id' => null, 'is_shipping_tbc' => true]);

    UpdateOrderIsShippingTBC::run($order);

    expect($order->fresh()->is_shipping_tbc)->toBeFalse();
});

it('hydrates order tracking numbers from multiple delivery notes and shipments', function () {
    $order = StoreOrder::make()->action($this->customer, Order::factory()->definition());

    // Create the first delivery note
    $dn1 = StoreDeliveryNote::make()->action($order, [
        'reference'        => 'DN1',
        'state'            => DeliveryNoteStateEnum::UNASSIGNED,
        'email'            => 'test@email.com',
        'phone'            => '+62081353890000',
        'date'             => date('Y-m-d'),
        'delivery_address' => new Address(Address::factory()->definition()),
        'warehouse_id'     => $this->warehouse->id
    ]);

    // Create a second delivery note
    $dn2 = StoreDeliveryNote::make()->action($order, [
        'reference'        => 'DN2',
        'state'            => DeliveryNoteStateEnum::UNASSIGNED,
        'email'            => 'test@email.com',
        'phone'            => '+62081353890000',
        'date'             => date('Y-m-d'),
        'delivery_address' => new Address(Address::factory()->definition()),
        'warehouse_id'     => $this->warehouse->id
    ]);

    /** @var Shipper $shipper */
    $shipper = Shipper::factory()->create([
        'organisation_id' => $this->organisation->id,
        'group_id'        => $this->organisation->group_id,
        'code'            => 'SHIPPER1',
    ]);

    // Create shipments for DN1
    $shipment1 = Shipment::factory()->create([
        'tracking'        => 'TRACK1',
        'group_id'        => $this->organisation->group_id,
        'organisation_id' => $this->organisation->id,
        'shipper_id'      => $shipper->id,
    ]);
    $dn1->shipments()->attach($shipment1);

    $shipment2 = Shipment::factory()->create([
        'tracking'        => 'TRACK2',
        'group_id'        => $this->organisation->group_id,
        'organisation_id' => $this->organisation->id,
        'shipper_id'      => $shipper->id,
    ]);
    $dn1->shipments()->attach($shipment2);

    // Create a shipment for DN2
    $shipment3 = Shipment::factory()->create([
        'tracking'        => 'TRACK3',
        'group_id'        => $this->organisation->group_id,
        'organisation_id' => $this->organisation->id,
        'shipper_id'      => $shipper->id,
    ]);
    $dn2->shipments()->attach($shipment3);

    // Create a shipment with 'na' tracking for DN2 (should be ignored)
    $shipmentNA = Shipment::factory()->create([
        'tracking'        => 'na',
        'group_id'        => $this->organisation->group_id,
        'organisation_id' => $this->organisation->id,
    ]);
    $dn2->shipments()->attach($shipmentNA);

    // Run hydrator
    OrderHydrateShipments::run($order->id);

    $order->refresh();

    expect($order->tracking_number)->toBe('TRACK1, TRACK2, TRACK3')
        ->and($order->shipping_data)->tobeArray()->toHaveCount(3);
});

it('nullifies order tracking number when no shipments exist', function () {
    $order = StoreOrder::make()->action($this->customer, Order::factory()->definition());
    $order->update(['tracking_number' => 'OLD_TRACKING']);
    $order->deliveryNotes->each->delete();

    OrderHydrateShipments::run($order->id);

    $order->refresh();

    expect($order->tracking_number)->toBeNull();
});

test('get iris basket transactions in collection action', function () {
    $collection = StoreCollection::make()->action($this->shop, [
        'code' => 'TEST_COLLECTION',
        'name' => 'Test Collection',
    ]);

    $this->product->containedByCollections()->attach($collection, ['model_type' => 'Product', 'type' => 'Product']);


    // Case 2: With basket, but no transactions for this product
    $basket = StoreOrder::make()->action($this->customer, Order::factory()->definition());
    $this->customer->update(['current_order_in_basket_id' => $basket->id]);

    $result = GetIrisBasketTransactionsInCollection::run($this->customer->fresh(), $collection);
    expect($result)->toBeArray()
        ->and($result)->toHaveKey($this->product->id)
        ->and($result[$this->product->id]['quantity_ordered'])->toBe(0);

    // Case 3: With basket and transactions
    $transactionData                     = Transaction::factory()->definition();
    $transactionData['quantity_ordered'] = 5;
    $transactionData['order_id']         = $basket->id;
    StoreTransaction::make()->action($basket, $this->product->currentHistoricProduct, $transactionData);

    $result = GetIrisBasketTransactionsInCollection::run($this->customer->fresh(), $collection);
    expect($result)->toBeArray()
        ->and($result)->toHaveKey($this->product->id)
        ->and((float)$result[$this->product->id]['quantity_ordered'])->toBe(5.0);
});

test('reset daily intervals action dispatches expected jobs', function () {
    Queue::fake();

    ResetDailyIntervals::make()->handle();

    ProcessResetIntervalsGroups::assertPushed(1);
    ProcessResetIntervalsOrganisations::assertPushed(1);
    ProcessResetIntervalsShops::assertPushed(1);
});

test('store upcoming transaction', function () {
    $upcomingTransaction = StoreUpcomingTransaction::make()->action($this->customer, [
        'product_id'    => $this->product->id,
        'quantity'      => 3,
        'type'          => UpcomingTransactionTypeEnum::GIFT->value,
        'public_notes'  => 'send it over',
        'private_notes' => 'warehouse only',
    ]);

    $upcomingTransaction->refresh();

    expect($upcomingTransaction)->toBeInstanceOf(UpcomingTransaction::class)
        ->and($upcomingTransaction->customer_id)->toBe($this->customer->id)
        ->and($upcomingTransaction->group_id)->toBe($this->customer->group_id)
        ->and($upcomingTransaction->organisation_id)->toBe($this->customer->organisation_id)
        ->and($upcomingTransaction->shop_id)->toBe($this->customer->shop_id)
        ->and($upcomingTransaction->product_id)->toBe($this->product->id)
        ->and((float)$upcomingTransaction->quantity)->toBe(3.0)
        ->and($upcomingTransaction->type)->toBe(UpcomingTransactionTypeEnum::GIFT)
        ->and($upcomingTransaction->state)->toBe(UpcomingTransactionStateEnum::READY);

    return $upcomingTransaction;
});

test('update upcoming transaction', function () {
    $upcomingTransaction = StoreUpcomingTransaction::make()->action($this->customer, [
        'product_id' => $this->product->id,
        'quantity'   => 1,
        'type'       => UpcomingTransactionTypeEnum::GIFT->value,
    ]);

    $updated = UpdateUpcomingTransaction::make()->action($upcomingTransaction, [
        'quantity' => 5,
        'type'     => UpcomingTransactionTypeEnum::FOLLOW_ON->value,
    ]);

    expect((float)$updated->quantity)->toBe(5.0)
        ->and($updated->type)->toBe(UpcomingTransactionTypeEnum::FOLLOW_ON);
});

test('delete upcoming transaction', function () {
    $upcomingTransaction = StoreUpcomingTransaction::make()->action($this->customer, [
        'product_id' => $this->product->id,
        'quantity'   => 1,
        'type'       => UpcomingTransactionTypeEnum::GIFT->value,
    ]);

    DeleteUpcomingTransaction::make()->action($upcomingTransaction);

    expect(UpcomingTransaction::find($upcomingTransaction->id))->toBeNull();
});

test('store upcoming transaction via controller', function () {
    $response = postJson(route('grp.models.customer.upcoming_transactions.store', [$this->customer->id]), [
        'product_id' => $this->product->id,
        'quantity'   => 2,
        'type'       => UpcomingTransactionTypeEnum::FOLLOW_ON->value,
    ]);

    $response->assertSuccessful();
    expect(UpcomingTransaction::where('customer_id', $this->customer->id)
        ->where('product_id', $this->product->id)
        ->where('type', UpcomingTransactionTypeEnum::FOLLOW_ON)
        ->exists())->toBeTrue();
});

test('update upcoming transaction via controller', function () {
    $upcomingTransaction = StoreUpcomingTransaction::make()->action($this->customer, [
        'product_id' => $this->product->id,
        'quantity'   => 1,
        'type'       => UpcomingTransactionTypeEnum::GIFT->value,
    ]);

    $response = patchJson(route('grp.models.upcoming_transaction.update', [$upcomingTransaction->id]), [
        'quantity' => 9,
    ]);

    $response->assertOk();
    expect((float)$upcomingTransaction->refresh()->quantity)->toBe(9.0);
});

test('delete upcoming transaction via controller', function () {
    $upcomingTransaction = StoreUpcomingTransaction::make()->action($this->customer, [
        'product_id' => $this->product->id,
        'quantity'   => 1,
        'type'       => UpcomingTransactionTypeEnum::GIFT->value,
    ]);

    deleteJson(route('grp.models.upcoming_transaction.delete', [$upcomingTransaction->id]))->assertOk();

    expect(UpcomingTransaction::find($upcomingTransaction->id))->toBeNull();
});

test('index upcoming transactions json only returns ready', function () {
    UpcomingTransaction::where('customer_id', $this->customer->id)->delete();

    $ready = StoreUpcomingTransaction::make()->action($this->customer, [
        'product_id' => $this->product->id,
        'quantity'   => 2,
        'type'       => UpcomingTransactionTypeEnum::GIFT->value,
    ]);

    StoreUpcomingTransaction::make()->action($this->customer, [
        'product_id' => $this->product->id,
        'quantity'   => 2,
        'type'       => UpcomingTransactionTypeEnum::GIFT->value,
        'state'      => UpcomingTransactionStateEnum::APPLIED->value,
    ]);

    $response = getJson(route('grp.org.shops.show.crm.customers.show.upcoming_transactions.index', [
        $this->organisation->slug,
        $this->shop->slug,
        $this->customer->slug,
    ]));

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $ready->id)
        ->assertJsonPath('data.0.product_code', $this->product->code)
        ->assertJsonPath('data.0.update.name', 'grp.models.upcoming_transaction.update')
        ->assertJsonPath('data.0.delete.name', 'grp.models.upcoming_transaction.delete');
});

test('UI index upcoming transactions', function () {
    UpcomingTransaction::where('customer_id', $this->customer->id)->delete();

    StoreUpcomingTransaction::make()->action($this->customer, [
        'product_id' => $this->product->id,
        'quantity'   => 1,
        'type'       => UpcomingTransactionTypeEnum::GIFT->value,
    ]);

    $response = get(route('grp.org.shops.show.crm.customers.show.upcoming_transactions.index', [
        $this->organisation->slug,
        $this->shop->slug,
        $this->customer->slug,
    ]));

    $response->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Org/Ordering/UpcomingTransactions')
            ->has('data.data', 1));
});

test('submit order applies ready upcoming transactions as follow-on bonus', function () {
    UpcomingTransaction::where('customer_id', $this->customer->id)->delete();
    $this->product->update(['status' => ProductStatusEnum::FOR_SALE]);

    $upcomingTransaction = StoreUpcomingTransaction::make()->action($this->customer, [
        'product_id' => $this->product->id,
        'quantity'   => 4,
        'type'       => UpcomingTransactionTypeEnum::FOLLOW_ON->value,
    ]);

    $order = StoreOrder::make()->action($this->customer, Order::factory()->definition());

    SubmitOrder::make()->action($order);

    $order->refresh();
    $upcomingTransaction->refresh();

    $transaction = $order->transactions()->where('is_follow_on', true)->first();

    expect($transaction)->not->toBeNull()
        ->and((float)$transaction->quantity_bonus)->toBe(4.0)
        ->and((float)$transaction->quantity_ordered)->toBe(0.0)
        ->and($upcomingTransaction->state)->toBe(UpcomingTransactionStateEnum::APPLIED)
        ->and($upcomingTransaction->order_id)->toBe($order->id)
        ->and($upcomingTransaction->transaction_id)->toBe($transaction->id)
        ->and($upcomingTransaction->order->id)->toBe($order->id)
        ->and($upcomingTransaction->transaction->id)->toBe($transaction->id)
        ->and($upcomingTransaction->product->id)->toBe($this->product->id);
});

test('submit order skips upcoming transactions for out of stock products', function () {
    UpcomingTransaction::where('customer_id', $this->customer->id)->delete();
    $this->product->update(['status' => ProductStatusEnum::OUT_OF_STOCK]);

    $upcomingTransaction = StoreUpcomingTransaction::make()->action($this->customer, [
        'product_id' => $this->product->id,
        'quantity'   => 4,
        'type'       => UpcomingTransactionTypeEnum::FOLLOW_ON->value,
    ]);

    $order = StoreOrder::make()->action($this->customer, Order::factory()->definition());

    SubmitOrder::make()->action($order);

    $order->refresh();
    $upcomingTransaction->refresh();

    expect($order->transactions()->where('is_follow_on', true)->count())->toBe(0)
        ->and($upcomingTransaction->state)->toBe(UpcomingTransactionStateEnum::READY);
});

test('submit order skips upcoming transaction when product has no current historic asset', function () {
    UpcomingTransaction::where('customer_id', $this->customer->id)->delete();
    $originalHistoricAssetId = $this->product->current_historic_asset_id;
    $this->product->update(['status' => ProductStatusEnum::FOR_SALE, 'current_historic_asset_id' => null]);

    $upcomingTransaction = StoreUpcomingTransaction::make()->action($this->customer, [
        'product_id' => $this->product->id,
        'quantity'   => 3,
        'type'       => UpcomingTransactionTypeEnum::FOLLOW_ON->value,
    ]);

    $order = StoreOrder::make()->action($this->customer, Order::factory()->definition());

    SubmitOrder::make()->action($order);

    $order->refresh();

    expect($order->state)->toBe(OrderStateEnum::SUBMITTED)
        ->and($order->transactions()->where('is_follow_on', true)->count())->toBe(0)
        ->and($upcomingTransaction->refresh()->state)->toBe(UpcomingTransactionStateEnum::READY);

    $upcomingTransaction->delete();
    $this->product->update(['current_historic_asset_id' => $originalHistoricAssetId]);
});

test('transactions resource adds bonus to ordered quantity for follow-on', function () {
    $order = StoreOrder::make()->action($this->customer, Order::factory()->definition());

    $transaction = StoreTransaction::make()->action(
        $order,
        $this->product->currentHistoricProduct,
        [
            'quantity_ordered' => 0,
            'quantity_bonus'   => 6,
            'is_follow_on'     => true,
        ]
    );

    $array = (new TransactionsResource($transaction->refresh()))->toArray(request());

    expect((float)$array['quantity_ordered'])->toBe(6.0)
        ->and((float)$array['quantity_bonus'])->toBe(6.0)
        ->and($array['is_follow_on'])->toBeTrue();
});

test('transaction import marks rows with missing code or quantity as failed', function () {
    $order = StoreOrder::make()->action($this->customer, Order::factory()->definition());

    $upload = Upload::create([
        'group_id'          => $order->group_id,
        'organisation_id'   => $order->organisation_id,
        'model'             => 'Transaction',
        'parent_type'       => $order->getMorphClass(),
        'parent_id'         => $order->id,
        'original_filename' => 'test.xlsx',
        'filename'          => 'test.xlsx',
        'filesize'          => 0,
        'number_rows'       => 0,
        'number_success'    => 0,
        'number_fails'      => 0,
    ]);

    $import = new TransactionImport($order, $upload);

    $makeUploadRecord = fn () => $upload->records()->create([
        'values' => [],
        'status' => UploadRecordStatusEnum::PROCESSING,
    ]);

    $missingCodeRecord = $makeUploadRecord();
    $import->storeModel(collect(['quantity' => 3]), $missingCodeRecord);
    expect($missingCodeRecord->refresh()->status)->toBe(UploadRecordStatusEnum::FAILED->value)
        ->and($missingCodeRecord->errors)->toContain('Missing product code.');

    $missingQuantityRecord = $makeUploadRecord();
    $import->storeModel(collect(['code' => $this->product->code]), $missingQuantityRecord);
    expect($missingQuantityRecord->refresh()->status)->toBe(UploadRecordStatusEnum::FAILED->value)
        ->and($missingQuantityRecord->errors[0])->toContain('invalid quantity');
});

test('transaction import prefers the active product when a discontinued one shares the code', function () {
    $order = StoreOrder::make()->action($this->customer, Order::factory()->definition());

    $staleProduct = $this->product->replicate(['slug']);
    $staleProduct->slug  = $this->product->slug.'-old';
    $staleProduct->state = ProductStateEnum::DISCONTINUED;
    $staleProduct->saveQuietly();

    $upload = Upload::create([
        'group_id'          => $order->group_id,
        'organisation_id'   => $order->organisation_id,
        'model'             => 'Transaction',
        'parent_type'       => $order->getMorphClass(),
        'parent_id'         => $order->id,
        'original_filename' => 'test.xlsx',
        'filename'          => 'test.xlsx',
        'filesize'          => 0,
        'number_rows'       => 0,
        'number_success'    => 0,
        'number_fails'      => 0,
    ]);

    $import = new TransactionImport($order, $upload);
    $record = $upload->records()->create([
        'values' => [],
        'status' => UploadRecordStatusEnum::PROCESSING,
    ]);

    $import->storeModel(collect(['code' => $this->product->code, 'quantity' => 2]), $record);

    expect($record->refresh()->status)->toBe(UploadRecordStatusEnum::COMPLETE->value)
        ->and($order->transactions()->where('model_type', 'Product')->pluck('model_id')->all())->toBe([$this->product->id]);
});

test('recalculating basket totals skips orders that are no longer baskets', function () {
    $billingAddress  = new Address(Address::factory()->definition());
    $deliveryAddress = new Address(Address::factory()->definition());

    $modelData = Order::factory()->definition();
    data_set($modelData, 'billing_address', $billingAddress);
    data_set($modelData, 'delivery_address', $deliveryAddress);

    $order = StoreOrder::make()->action($this->customer, $modelData);
    $order->updateQuietly(['state' => OrderStateEnum::IN_WAREHOUSE, 'net_amount' => 111.11, 'total_amount' => 111.11]);

    // A bulk run selects baskets when it queues the jobs, but drains for hours. An order submitted
    // in the meantime must not be repriced from current prices after the fact.
    \App\Actions\Ordering\Order\RecalculateTotalsOrdersInBasket::make()->handle($order->id);

    expect((float) $order->refresh()->net_amount)->toBe(111.11)
        ->and((float) $order->total_amount)->toBe(111.11)
        ->and($order->state)->toBe(OrderStateEnum::IN_WAREHOUSE);
});

test('bulk basket recalculation skips orders submitted while the job was waiting', function () {
    $billingAddress  = new Address(Address::factory()->definition());
    $deliveryAddress = new Address(Address::factory()->definition());

    $modelData = Order::factory()->definition();
    data_set($modelData, 'billing_address', $billingAddress);
    data_set($modelData, 'delivery_address', $deliveryAddress);

    $order = StoreOrder::make()->action($this->customer, $modelData);
    $order->updateQuietly(['state' => OrderStateEnum::IN_WAREHOUSE, 'net_amount' => 222.22, 'total_amount' => 222.22]);

    // Offer activation lists baskets then queues one job each with a delay. An order submitted
    // inside that window must be left alone by both halves of the recalculation.
    \App\Actions\Ordering\Order\CalculateOrderTotalAmounts::make()
        ->handle($order, true, true, false, true, true);
    \App\Actions\Ordering\Order\CalculateOrderDiscounts::make()->handle($order, true);

    expect((float) $order->refresh()->net_amount)->toBe(222.22)
        ->and((float) $order->total_amount)->toBe(222.22);

    // Without the flag the same call still works on a submitted order, as other callers rely on.
    \App\Actions\Ordering\Order\CalculateOrderTotalAmounts::make()->handle($order);

    expect((float) $order->refresh()->total_amount)->not->toBe(222.22);
});

test('invoice totals from a part picked order keep net plus tax equal to the total', function () {
    $billingAddress  = new Address(Address::factory()->definition());
    $deliveryAddress = new Address(Address::factory()->definition());

    $modelData = Order::factory()->definition();
    data_set($modelData, 'billing_address', $billingAddress);
    data_set($modelData, 'delivery_address', $deliveryAddress);

    $order = StoreOrder::make()->action($this->customer, $modelData);
    $order->update(['tax_category_id' => TaxCategory::where('rate', 0.2)->firstOrFail()->id]);

    $historicAsset = $this->product->historicAsset;
    $historicAsset->update(['price' => 100]);

    $transaction = StoreTransaction::make()->action($order, $historicAsset, array_merge(
        Transaction::factory()->definition(),
        ['quantity_ordered' => 3]
    ));
    $transaction->update(['gross_amount' => 300, 'net_amount' => 300, 'quantity_bonus' => 0]);

    SubmitOrder::make()->action($order);
    $deliveryNote = SendOrderToWarehouse::make()->action($order, []);

    // A partial pick makes the net land on 266.622, where net, tax and total each round differently.
    DB::table('delivery_note_items')->insert([
        'group_id'          => $order->group_id,
        'organisation_id'   => $order->organisation_id,
        'shop_id'           => $order->shop_id,
        'delivery_note_id'  => $deliveryNote->id,
        'transaction_id'    => $transaction->id,
        'state'             => 'picked',
        'quantity_required' => 100000,
        'quantity_picked'   => 88874,
        'data'              => '{}',
    ]);

    $order->refresh();
    $order->transactions()->whereNot('model_type', 'Product')->delete();
    $order->update(['amount_off' => 0]);

    $totals = GenerateInvoiceFromOrder::make()->recalculateTotals($order, $deliveryNote);

    expect($totals['net_amount'])->toBe(266.62)
        ->and($totals['tax_amount'])->toBe(53.32)
        ->and($totals['total_amount'])->toBe(319.94)
        ->and($totals['net_amount'] + $totals['tax_amount'])->toBe($totals['total_amount']);
});

test('a ten per cent line keeps the penny it was submitted at', function () {
    $billingAddress  = new Address(Address::factory()->definition());
    $deliveryAddress = new Address(Address::factory()->definition());

    $modelData = Order::factory()->definition();
    data_set($modelData, 'billing_address', $billingAddress);
    data_set($modelData, 'delivery_address', $deliveryAddress);

    $order = StoreOrder::make()->action($this->customer, $modelData);

    $historicAsset = $this->product->historicAsset;
    $historicAsset->update(['price' => 61.95]);

    $transaction = StoreTransaction::make()->action($order, $historicAsset, array_merge(
        Transaction::factory()->definition(),
        ['quantity_ordered' => 1]
    ));

    // 61.95 off ten per cent: the discount is 6.195, which must round up to 6.20 like the
    // basket did, not down to 6.19 because 1 - 0.9 is a hair under a tenth in binary.
    $transaction->update([
        'gross_amount'            => 61.95,
        'net_amount'              => 55.75,
        'quantity_bonus'          => 0,
        'current_discount_factor' => 0.9,
    ]);

    SubmitOrder::make()->action($order);
    $deliveryNote = SendOrderToWarehouse::make()->action($order, []);

    $totals = GenerateInvoiceFromOrder::make()->recalculateTransactionTotals($transaction->refresh(), $deliveryNote);

    expect($totals['net_amount'])->toBe(55.75);
});

test('a part picked line bills the fraction of the price it was sold at', function () {
    $billingAddress  = new Address(Address::factory()->definition());
    $deliveryAddress = new Address(Address::factory()->definition());

    $modelData = Order::factory()->definition();
    data_set($modelData, 'billing_address', $billingAddress);
    data_set($modelData, 'delivery_address', $deliveryAddress);

    $order = StoreOrder::make()->action($this->customer, $modelData);

    $historicAsset = $this->product->historicAsset;
    $historicAsset->update(['price' => 26.95]);

    $transaction = StoreTransaction::make()->action($order, $historicAsset, array_merge(
        Transaction::factory()->definition(),
        ['quantity_ordered' => 4]
    ));
    $transaction->update([
        'gross_amount'            => 107.80,
        'net_amount'              => 102.41,
        'quantity_bonus'          => 0,
        'current_discount_factor' => 0.95,
    ]);

    SubmitOrder::make()->action($order);
    $deliveryNote = SendOrderToWarehouse::make()->action($order, []);

    DB::table('delivery_note_items')->insert([
        'group_id'          => $order->group_id,
        'organisation_id'   => $order->organisation_id,
        'shop_id'           => $order->shop_id,
        'delivery_note_id'  => $deliveryNote->id,
        'transaction_id'    => $transaction->id,
        'state'             => 'picked',
        'quantity_required' => 4,
        'quantity_picked'   => 2,
        'data'              => '{}',
    ]);

    $totals = GenerateInvoiceFromOrder::make()->recalculateTransactionTotals($transaction->refresh(), $deliveryNote);

    // half of 102.41, not half of the gross discounted again
    expect($totals['net_amount'])->toBe(51.21);
});

test('a line discounted after it was submitted is priced on its new factor', function () {
    $billingAddress  = new Address(Address::factory()->definition());
    $deliveryAddress = new Address(Address::factory()->definition());

    $modelData = Order::factory()->definition();
    data_set($modelData, 'billing_address', $billingAddress);
    data_set($modelData, 'delivery_address', $deliveryAddress);

    $order = StoreOrder::make()->action($this->customer, $modelData);

    $historicAsset = $this->product->historicAsset;
    $historicAsset->update(['price' => 61.95]);

    $transaction = StoreTransaction::make()->action($order, $historicAsset, array_merge(
        Transaction::factory()->definition(),
        ['quantity_ordered' => 1]
    ));
    $transaction->update(['gross_amount' => 61.95, 'net_amount' => 61.95, 'quantity_bonus' => 0]);

    SubmitOrder::make()->action($order);
    $deliveryNote = SendOrderToWarehouse::make()->action($order, []);

    // sold at full price, then given ten per cent off by hand afterwards
    $transaction->update(['current_discount_factor' => 0.9, 'net_amount' => 55.75]);

    $totals = GenerateInvoiceFromOrder::make()->recalculateTransactionTotals($transaction->refresh(), $deliveryNote);

    expect($totals['net_amount'])->toBe(55.75);
});

test('shipping zone with territories wins over a catch all zone placed above it', function () {
    $shippingZoneSchema = StoreShippingZoneSchema::make()->action($this->shop, [
        'name' => 'catch all on top schema',
    ]);

    $franceZone = StoreShippingZone::make()->action($shippingZoneSchema, [
        'code'        => 'ZONE-FR',
        'name'        => 'France',
        'status'      => true,
        'price'       => [
            'type'  => 'Step Order Items Net Amount',
            'steps' => [
                ['from' => 0, 'to' => 'INF', 'price' => 12.5],
            ],
        ],
        'territories' => [['country_code' => 'FR']],
        'position'    => 1,
        'is_failover' => false,
    ]);

    $restOfTheWorld = StoreShippingZone::make()->action($shippingZoneSchema, [
        'code'        => 'ZONE-ROW',
        'name'        => 'Rest of the world',
        'status'      => true,
        'price'       => ['type' => 'TBC'],
        'territories' => [],
        'position'    => 2,
        'is_failover' => false,
    ]);

    $this->shop->update(['shipping_zone_schema_id' => $shippingZoneSchema->id]);

    $modelData = Order::factory()->definition();
    data_set($modelData, 'billing_address', new Address(Address::factory()->definition()));
    data_set($modelData, 'delivery_address', new Address(Address::factory()->definition()));

    $order = StoreOrder::make()->action($this->customer, $modelData);
    $order->deliveryAddress->update(['country_code' => 'FR', 'postal_code' => '75001']);
    StoreTransaction::make()->action($order, $this->product->historicAsset, Transaction::factory()->definition());

    $order = CalculateOrderShipping::make()->handle($order->refresh());

    expect($order->shipping_zone_id)->toBe($franceZone->id)
        ->and($order->shipping_zone_id)->not->toBe($restOfTheWorld->id)
        ->and($order->is_shipping_tbc)->toBeFalse()
        ->and((float)$order->shipping_amount)->toBe(12.5);

    return $order;
});

test('a step priced TBC leaves the shipping to be confirmed instead of free', function (Order $order) {
    $this->shop->update(['shipping_zone_schema_id' => $order->shipping_zone_schema_id]);

    UpdateShippingZone::make()->action(ShippingZone::find($order->shipping_zone_id), [
        'price' => [
            'type'  => 'Step Order Items Net Amount',
            'steps' => [
                ['from' => 0, 'to' => 'INF', 'price' => 'TBC'],
            ],
        ],
    ]);

    $order = CalculateOrderShipping::make()->handle($order->refresh());

    $shippingTransaction = $order->transactions()->where('model_type', 'ShippingZone')->first();

    expect($order->is_shipping_tbc)->toBeTrue()
        ->and((float)$shippingTransaction->net_amount)->toBe(0.0);
})->depends('shipping zone with territories wins over a catch all zone placed above it');

test('repricing a basket picks up a shipping price that changed since it was created', function () {
    $shippingZoneSchema = StoreShippingZoneSchema::make()->action($this->shop, [
        'name' => 'stale basket schema',
    ]);

    $shippingZone = StoreShippingZone::make()->action($shippingZoneSchema, [
        'code'        => 'ZONE-STALE',
        'name'        => 'France',
        'status'      => true,
        'price'       => [
            'type'  => 'Step Order Items Net Amount',
            'steps' => [
                ['from' => 0, 'to' => 'INF', 'price' => 5],
            ],
        ],
        'territories' => [['country_code' => 'FR']],
        'position'    => 1,
        'is_failover' => false,
    ]);

    $this->shop->update(['shipping_zone_schema_id' => $shippingZoneSchema->id]);

    $modelData = Order::factory()->definition();
    data_set($modelData, 'billing_address', new Address(Address::factory()->definition()));
    data_set($modelData, 'delivery_address', new Address(Address::factory()->definition()));

    $order = StoreOrder::make()->action($this->customer, $modelData);
    $order->deliveryAddress->update(['country_code' => 'FR', 'postal_code' => '75001']);
    StoreTransaction::make()->action($order, $this->product->historicAsset, Transaction::factory()->definition());

    CalculateOrderShipping::make()->handle($order->refresh());

    expect((float)$order->transactions()->where('model_type', 'ShippingZone')->first()->net_amount)->toBe(5.0);

    UpdateShippingZone::make()->action($shippingZone, [
        'price' => [
            'type'  => 'Step Order Items Net Amount',
            'steps' => [
                ['from' => 0, 'to' => 'INF', 'price' => 25],
            ],
        ],
    ]);

    CalculateOrderTotalAmounts::run($order->refresh(), forceRecalculate: true, onlyIfInBasket: true);

    expect((float)$order->refresh()->transactions()->where('model_type', 'ShippingZone')->first()->net_amount)->toBe(25.0);

    return $order;
});

test('repricing skips an order that is no longer a basket', function (Order $order) {
    $this->shop->update(['shipping_zone_schema_id' => $order->shipping_zone_schema_id]);

    SubmitOrder::make()->action($order);

    UpdateShippingZone::make()->action(ShippingZone::find($order->shipping_zone_id), [
        'price' => [
            'type'  => 'Step Order Items Net Amount',
            'steps' => [
                ['from' => 0, 'to' => 'INF', 'price' => 99],
            ],
        ],
    ]);

    CalculateOrderTotalAmounts::run($order->refresh(), forceRecalculate: true, onlyIfInBasket: true);

    expect((float)$order->refresh()->transactions()->where('model_type', 'ShippingZone')->first()->net_amount)->toBe(25.0);
})->depends('repricing a basket picks up a shipping price that changed since it was created');

test('write off settles an order short by less than the tolerance', function () {
    $billingAddress  = new Address(Address::factory()->definition());
    $deliveryAddress = new Address(Address::factory()->definition());

    $orderData = Order::factory()->definition();
    data_set($orderData, 'billing_address', $billingAddress);
    data_set($orderData, 'delivery_address', $deliveryAddress);

    $order = StoreOrder::make()->action($this->customer, $orderData);

    StoreTransaction::make()->action($order, $this->product->historicAsset, [
        'quantity_ordered' => 1,
    ]);

    $order->refresh();
    $total = (float)$order->total_amount;

    $paymentAccount = StoreOrgPaymentServiceProviderAccount::make()->action(
        $this->organisation,
        PaymentServiceProvider::where('type', PaymentServiceProviderTypeEnum::CASH->value)->first(),
        [
            'code' => 'WO'.mt_rand(1000, 9999),
            'name' => 'Cash Account',
        ]
    );

    expect(WriteOffOrderShortfall::make()->action($order)['success'])->toBeFalse();

    PayOrder::make()->action($order, $paymentAccount, [
        'amount'    => round($total - 0.04, 2),
        'reference' => 'PAY-'.uniqid(),
        'status'    => PaymentStatusEnum::SUCCESS,
        'state'     => PaymentStateEnum::COMPLETED,
    ]);
    $order->refresh();

    expect($order->pay_status)->toBe(OrderPayStatusEnum::UNPAID);

    $result = WriteOffOrderShortfall::make()->action($order);
    $order->refresh();

    $adjustmentTransaction = $order->transactions()->where('model_type', 'Adjustment')->first();

    expect($result['success'])->toBeTrue()
        ->and((float)$order->total_amount)->toBe((float)$order->payment_amount)
        ->and($order->pay_status)->toBe(OrderPayStatusEnum::PAID)
        ->and($adjustmentTransaction)->not->toBeNull()
        ->and((float)$adjustmentTransaction->net_amount)->toBeLessThan(0)
        ->and(WriteOffOrderShortfall::make()->action($order)['success'])->toBeFalse();
});

test('write off settles an overpaid order with a positive adjustment', function () {
    $billingAddress  = new Address(Address::factory()->definition());
    $deliveryAddress = new Address(Address::factory()->definition());

    $orderData = Order::factory()->definition();
    data_set($orderData, 'billing_address', $billingAddress);
    data_set($orderData, 'delivery_address', $deliveryAddress);

    $order = StoreOrder::make()->action($this->customer, $orderData);

    StoreTransaction::make()->action($order, $this->product->historicAsset, [
        'quantity_ordered' => 1,
    ]);

    $order->refresh();

    $paymentAccount = StoreOrgPaymentServiceProviderAccount::make()->action(
        $this->organisation,
        PaymentServiceProvider::where('type', PaymentServiceProviderTypeEnum::CASH->value)->first(),
        [
            'code' => 'WO'.mt_rand(1000, 9999),
            'name' => 'Cash Account',
        ]
    );

    PayOrder::make()->action($order, $paymentAccount, [
        'amount'    => round($order->total_amount + 0.03, 2),
        'reference' => 'PAY-'.uniqid(),
        'status'    => PaymentStatusEnum::SUCCESS,
        'state'     => PaymentStateEnum::COMPLETED,
    ]);
    $order->refresh();

    $result = WriteOffOrderShortfall::make()->action($order);
    $order->refresh();

    $adjustmentTransaction = $order->transactions()->where('model_type', 'Adjustment')->first();

    expect($result['success'])->toBeTrue()
        ->and((float)$order->total_amount)->toBe((float)$order->payment_amount)
        ->and($order->pay_status)->toBe(OrderPayStatusEnum::PAID)
        ->and((float)$adjustmentTransaction->net_amount)->toBeGreaterThan(0);
});

test('paid then refunded order reads refunded not unpaid', function () {
    $billingAddress  = new Address(Address::factory()->definition());
    $deliveryAddress = new Address(Address::factory()->definition());

    $orderData = Order::factory()->definition();
    data_set($orderData, 'billing_address', $billingAddress);
    data_set($orderData, 'delivery_address', $deliveryAddress);

    $order = StoreOrder::make()->action($this->customer, $orderData);

    StoreTransaction::make()->action($order, $this->product->historicAsset, [
        'quantity_ordered' => 1,
    ]);

    $order->refresh();
    $total = (float)$order->total_amount;

    $paymentAccount = StoreOrgPaymentServiceProviderAccount::make()->action(
        $this->organisation,
        PaymentServiceProvider::where('type', PaymentServiceProviderTypeEnum::CASH->value)->first(),
        [
            'code' => 'RF'.mt_rand(1000, 9999),
            'name' => 'Cash Account',
        ]
    );

    PayOrder::make()->action($order, $paymentAccount, [
        'amount'    => $total,
        'reference' => 'PAY-'.uniqid(),
        'status'    => PaymentStatusEnum::SUCCESS,
        'state'     => PaymentStateEnum::COMPLETED,
    ]);
    $order->refresh();

    expect($order->pay_status)->toBe(OrderPayStatusEnum::PAID)
        ->and($order->pay_detailed_status)->toBe(OrderPayDetailedStatusEnum::PAID);

    PayOrder::make()->action($order, $paymentAccount, [
        'amount'    => -$total,
        'reference' => 'REFUND-'.uniqid(),
        'status'    => PaymentStatusEnum::SUCCESS,
        'state'     => PaymentStateEnum::COMPLETED,
    ]);
    $order->refresh();

    expect($order->pay_status)->toBe(OrderPayStatusEnum::UNPAID)
        ->and($order->pay_detailed_status)->toBe(OrderPayDetailedStatusEnum::REFUNDED)
        ->and((float)$order->payment_amount)->toBe(0.0);
});

test('service only order sent to warehouse skips delivery note', function () {
    $service = StoreService::make()->action($this->shop, [
        'code'  => 'SRV1',
        'name'  => 'Service one',
        'price' => 10,
        'unit'  => 'each',
        'state' => ServiceStateEnum::ACTIVE,
    ]);

    $billingAddress  = new Address(Address::factory()->definition());
    $deliveryAddress = new Address(Address::factory()->definition());
    $modelData        = Order::factory()->definition();
    data_set($modelData, 'billing_address', $billingAddress);
    data_set($modelData, 'delivery_address', $deliveryAddress);
    $order = StoreOrder::make()->action($this->customer, $modelData);

    StoreTransaction::make()->action($order, $service->historicAsset, [
        'quantity_ordered' => 1,
    ]);

    $order = SubmitOrder::make()->action($order);

    $result = SendOrderToWarehouse::make()->action($order, [
        'warehouse_id' => $this->warehouse->id,
    ]);

    $order->refresh();

    expect($result)->toBeNull()
        ->and($order->deliveryNotes()->count())->toBe(0)
        ->and($order->state)->toBe(OrderStateEnum::DISPATCHED)
        ->and($order->invoices()->count())->toBe(1)
        ->and($order->transactions()->where('model_type', 'Service')->first()->state)->toBe(TransactionStateEnum::DISPATCHED->value);
});

test('mixed product and service order dispatch', function () {
    $service = StoreService::make()->action($this->shop, [
        'code'  => 'SRV2',
        'name'  => 'Service two',
        'price' => 10,
        'unit'  => 'each',
        'state' => ServiceStateEnum::ACTIVE,
    ]);

    $billingAddress  = new Address(Address::factory()->definition());
    $deliveryAddress = new Address(Address::factory()->definition());
    $modelData        = Order::factory()->definition();
    data_set($modelData, 'billing_address', $billingAddress);
    data_set($modelData, 'delivery_address', $deliveryAddress);
    $order = StoreOrder::make()->action($this->customer, $modelData);

    $productTransaction = StoreTransaction::make()->action($order, $this->product->historicAsset, [
        'quantity_ordered' => 1,
    ]);
    $serviceTransaction = StoreTransaction::make()->action($order, $service->historicAsset, [
        'quantity_ordered' => 1,
    ]);

    $order = SubmitOrder::make()->action($order);

    $deliveryNote = SendOrderToWarehouse::make()->action($order, [
        'warehouse_id' => $this->warehouse->id,
    ]);
    $order->refresh();

    expect($deliveryNote)->toBeInstanceOf(DeliveryNote::class)
        ->and($deliveryNote->deliveryNoteItems()->where('transaction_id', $serviceTransaction->id)->count())->toBe(0);

    DispatchOrder::make()->action($order, $deliveryNote);
    $order->refresh();

    expect($order->transactions()->find($serviceTransaction->id)->state)->toBe(TransactionStateEnum::DISPATCHED->value)
        ->and((float)$order->transactions()->find($serviceTransaction->id)->quantity_dispatched)->toBe(1.0);
});

test('service transaction defaults net amount to historic asset price times quantity', function () {
    $service = StoreService::make()->action($this->shop, [
        'code'  => 'SRV3',
        'name'  => 'Service three',
        'price' => 15,
        'unit'  => 'each',
        'state' => ServiceStateEnum::ACTIVE,
    ]);

    $billingAddress  = new Address(Address::factory()->definition());
    $deliveryAddress = new Address(Address::factory()->definition());
    $modelData        = Order::factory()->definition();
    data_set($modelData, 'billing_address', $billingAddress);
    data_set($modelData, 'delivery_address', $deliveryAddress);
    $order = StoreOrder::make()->action($this->customer, $modelData);

    $serviceTransaction = StoreTransaction::make()->action($order, $service->historicAsset, [
        'quantity_ordered' => 2,
    ]);

    expect((float)$serviceTransaction->net_amount)->toBe((float)$service->historicAsset->price * 2)
        ->and((float)$serviceTransaction->gross_amount)->toBe((float)$service->historicAsset->price * 2);
});

describe('order margin data', function () {
    test('margin fields math and no-cost blanking', function () {
        $trait = new class () {
            use \App\Actions\Traits\WithMarginData;

            public function fields(...$args): ?array
            {
                return $this->marginFields(...$args);
            }
        };

        expect($trait->fields('Service', 10, 10, null, null))->toBeNull()
            ->and($trait->fields('Product', 100, 100, 40, null))->toBe([
                'margin_pct'          => 60.0,
                'profit_amount'       => 60.0,
                'margin_is_estimated' => false,
                'margin_no_cost'      => false,
            ])
            ->and($trait->fields('Product', 100, 100, null, 30))->toBe([
                'margin_pct'          => 70.0,
                'profit_amount'       => 70.0,
                'margin_is_estimated' => true,
                'margin_no_cost'      => false,
            ])
            ->and($trait->fields('Product', 1000, 1000, 120, 400, 30, 100))->toBe([
                'margin_pct'          => 60.0,
                'profit_amount'       => 600.0,
                'margin_is_estimated' => true,
                'margin_no_cost'      => false,
            ])
            ->and($trait->fields('Product', 100, 100, null, null))->toBe([
                'margin_pct'          => null,
                'profit_amount'       => null,
                'margin_is_estimated' => false,
                'margin_no_cost'      => true,
            ]);
    });

    test('order margin summary runs and estimates from sku value', function () {
        $billingAddress  = new Address(Address::factory()->definition());
        $deliveryAddress = new Address(Address::factory()->definition());

        $modelData = Order::factory()->definition();
        data_set($modelData, 'billing_address', $billingAddress);
        data_set($modelData, 'delivery_address', $deliveryAddress);

        $order = StoreOrder::make()->action($this->customer, $modelData);
        StoreTransaction::make()->action($order, $this->product->historicAsset, Transaction::factory()->definition());

        $adminGuest = createAdminGuest($this->group);
        actingAs($adminGuest->getUser());

        $action = new class () {
            use \App\Actions\Traits\WithMarginData;

            public function canSeeMargins(\App\Models\Catalogue\Shop $shop): bool
            {
                return true;
            }
        };

        $summary = $action->getMarginSummary($order->refresh());

        if ($summary !== null) {
            expect($summary)->toHaveKeys(['profit_amount', 'margin_pct', 'is_estimated', 'lines_without_cost', 'currency_code']);
        } else {
            expect($summary)->toBeNull();
        }
    });

    test('margin summary excludes uncosted lines instead of treating them as free', function () {
        $trait = new class () {
            use \App\Actions\Traits\WithMarginData;

            public function aggregate(iterable $lines, float $breakEvenPct = 0.0): ?array
            {
                return $this->aggregateMarginLines($lines, 'GBP', $breakEvenPct);
            }
        };

        $exact      = (object) ['net' => 100, 'org_net' => 100, 'gross' => 100, 'org_exchange' => 1, 'actual_cost' => 40, 'estimated_cost' => null, 'picked' => null, 'ordered' => null];
        $estimated  = (object) ['net' => 100, 'org_net' => 100, 'gross' => 100, 'org_exchange' => 1, 'actual_cost' => null, 'estimated_cost' => 50, 'picked' => null, 'ordered' => null];
        $uncosted   = (object) ['net' => 100, 'org_net' => 100, 'gross' => 100, 'org_exchange' => 1, 'actual_cost' => null, 'estimated_cost' => null, 'picked' => null, 'ordered' => null];
        $partial    = (object) ['net' => 1000, 'org_net' => 1000, 'gross' => 1000, 'org_exchange' => 1, 'actual_cost' => 120, 'estimated_cost' => 400, 'picked' => 30, 'ordered' => 100];
        $discounted = (object) ['net' => 180, 'org_net' => 180, 'gross' => 200, 'org_exchange' => 1, 'actual_cost' => 90, 'estimated_cost' => null, 'picked' => null, 'ordered' => null];

        $exactPlusUncosted = $trait->aggregate([$exact, $uncosted]);

        expect($exactPlusUncosted['profit_amount'])->toBe(60.0)
            ->and($exactPlusUncosted['margin_pct'])->toBe(60.0)
            ->and($exactPlusUncosted['before_discounts'])->toBeNull()
            ->and($exactPlusUncosted['is_estimated'])->toBeFalse()
            ->and($exactPlusUncosted['lines_without_cost'])->toBe(1)
            ->and($trait->aggregate([$exact, $estimated])['margin_pct'])->toBe(55.0)
            ->and($trait->aggregate([$exact, $estimated])['is_estimated'])->toBeTrue()
            ->and($trait->aggregate([$partial])['margin_pct'])->toBe(60.0)
            ->and($trait->aggregate([$uncosted]))->toBeNull();

        $withDiscount = $trait->aggregate([$discounted]);

        expect($withDiscount['margin_pct'])->toBe(50.0)
            ->and($withDiscount['before_discounts'])->toBe(['margin_pct' => 55.0, 'profit_amount' => 110.0])
            ->and($withDiscount['is_below_break_even'])->toBeFalse();

        $belowBreakEven = $trait->aggregate([$exact], breakEvenPct: 70.0);

        expect($belowBreakEven['is_below_break_even'])->toBeTrue()
            ->and($belowBreakEven['break_even_pct'])->toBe(70.0)
            ->and($belowBreakEven['margin_status'])->toBe('danger')
            ->and($trait->aggregate([$exact], breakEvenPct: 55.0)['margin_status'])->toBe('warning')
            ->and($trait->aggregate([$exact], breakEvenPct: 40.0)['margin_status'])->toBe('ok');

        $gift = (object) ['net' => 0, 'org_net' => 0, 'gross' => 0, 'org_exchange' => 1, 'actual_cost' => 20, 'estimated_cost' => null, 'picked' => null, 'ordered' => null];

        expect($trait->aggregate([$exact, $gift])['margin_pct'])->toBe(40.0)
            ->and($trait->aggregate([$exact, $gift])['profit_amount'])->toBe(40.0);
    });
});

test('submitting an order keeps the warehouse note typed in the basket', function () {
    $billingAddress  = new Address(Address::factory()->definition());
    $deliveryAddress = new Address(Address::factory()->definition());
    $modelData       = Order::factory()->definition();
    data_set($modelData, 'billing_address', $billingAddress);
    data_set($modelData, 'delivery_address', $deliveryAddress);
    $order = StoreOrder::make()->action($this->customer, $modelData);

    UpdateOrder::make()->action($order, ['private_warehouse_note' => 'fragile, double box']);
    $this->customer->update(['warehouse_internal_notes' => null]);

    $order = SubmitOrder::make()->action($order->refresh());

    expect($order->private_warehouse_note)->toBe('fragile, double box');
});

test('submitting an order merges the customer profile warehouse note', function () {
    $billingAddress  = new Address(Address::factory()->definition());
    $deliveryAddress = new Address(Address::factory()->definition());
    $modelData       = Order::factory()->definition();
    data_set($modelData, 'billing_address', $billingAddress);
    data_set($modelData, 'delivery_address', $deliveryAddress);
    $order = StoreOrder::make()->action($this->customer, $modelData);

    UpdateOrder::make()->action($order, ['private_warehouse_note' => 'fragile']);
    $this->customer->update(['warehouse_internal_notes' => 'always call before delivery']);

    $order = SubmitOrder::make()->action($order->refresh());

    expect($order->private_warehouse_note)->toBe('fragile — always call before delivery');
});

describe('order state element group', function () {
    test('every state label has a count so no chip renders NaN', function () {
        $elements = array_merge_recursive(
            OrderStateEnum::labels(),
            OrderStateEnum::count($this->shop)
        );

        foreach (OrderStateEnum::cases() as $case) {
            expect($elements[$case->value])->toBeArray()->toHaveCount(2)
                ->and($elements[$case->value][1])->toBeInt();
        }
        expect($elements['picked'][1])->toBe(0)
            ->and($elements['packing'][1])->toBe(0);
    });
});

test('UI show order flags unpaid API orders as auto-held', function () {
    $this->withoutExceptionHandling();

    $modelData = Order::factory()->definition();
    data_set($modelData, 'billing_address', new Address(Address::factory()->definition()));
    data_set($modelData, 'delivery_address', new Address(Address::factory()->definition()));

    $order      = StoreOrder::make()->action($this->customer, $modelData);
    SeedSalesChannels::run($this->organisation->group);
    $apiChannel = SalesChannel::where('type', SalesChannelTypeEnum::API)->first();
    $order->update([
        'sales_channel_id' => $apiChannel->id,
        'state'            => OrderStateEnum::SUBMITTED,
        'pay_status'       => OrderPayStatusEnum::UNPAID,
        'submitted_at'     => now(),
    ]);

    $response = get(route('grp.org.shops.show.ordering.orders.show', [$this->organisation->slug, $this->shop->slug, $order->slug]));
    $response->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('pageHead.api_order.label', 'API order')
            ->where('pageHead.api_order.held_unpaid', true)
            ->etc()
    );

    $order->update(['pay_status' => OrderPayStatusEnum::PAID]);
    $paid = get(route('grp.org.shops.show.ordering.orders.show', [$this->organisation->slug, $this->shop->slug, $order->slug]));
    $paid->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('pageHead.api_order.held_unpaid', false)
            ->etc()
    );
});

test('UI order channels report groups by platform and sales channel', function () {
    $this->withoutExceptionHandling();

    $modelData = Order::factory()->definition();
    data_set($modelData, 'billing_address', new Address(Address::factory()->definition()));
    data_set($modelData, 'delivery_address', new Address(Address::factory()->definition()));
    $order = StoreOrder::make()->action($this->customer, $modelData);

    SeedSalesChannels::run($this->organisation->group);
    $apiChannel = SalesChannel::where('type', SalesChannelTypeEnum::API)->first();
    $order->update([
        'sales_channel_id' => $apiChannel->id,
        'state'            => OrderStateEnum::SUBMITTED,
        'pay_status'       => OrderPayStatusEnum::UNPAID,
        'submitted_at'     => now(),
    ]);

    $response = get(route('grp.org.shops.show.ordering.channels.index', [$this->organisation->slug, $this->shop->slug]));
    $response->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('Org/Ordering/OrderChannels')
            ->has('data.rows')
            ->where('data.currency_code', $this->shop->currency->code)
            ->where('pageHead.title', 'Order Channels')
            ->etc()
    );

    $rows = collect(IndexOrderChannels::make()->handle($this->shop)['rows']);
    expect($rows)->not->toBeEmpty();

    $apiRow = $rows->first(fn ($row) => $row['sales_channel_type'] === 'api');
    expect($apiRow)->not->toBeNull()
        ->and($apiRow['number_held_unpaid'])->toBeGreaterThanOrEqual(1);
});

test('UI shop dashboard buries brands as a link and brands page renders', function () {
    $this->withoutExceptionHandling();

    if ($this->shop->type->value === 'dropshipping') {
        $dashboard = get(route('grp.org.shops.show.dashboard.show', [$this->organisation->slug, $this->shop->slug]));
        $dashboard->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('dashboard.super_blocks.0.brands_link.route.name', 'grp.org.shops.show.dashboard.brands')
                ->missing('dashboard.super_blocks.0.blocks.0.tables.brands')
                ->etc()
        );
    }

    $brandsPage = get(route('grp.org.shops.show.dashboard.brands', [$this->organisation->slug, $this->shop->slug]));
    $brandsPage->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('Org/Catalogue/Shop')
            ->has('dashboard.super_blocks.0.blocks.0.tables.brands')
            ->etc()
    );
});

test('manual platform time series splits sales by channel', function () {
    SeedSalesChannels::run($this->organisation->group);
    $platform   = Platform::where('type', PlatformTypeEnum::MANUAL)->first();
    $webChannel = SalesChannel::where('type', SalesChannelTypeEnum::WEBSITE)->first();
    $apiChannel = SalesChannel::where('type', SalesChannelTypeEnum::API)->first();

    StoreInvoice::make()->action($this->customer, array_merge(Invoice::factory()->definition(), [
        'platform_id'      => $platform->id,
        'sales_channel_id' => $webChannel->id,
    ]));
    StoreInvoice::make()->action($this->customer, array_merge(Invoice::factory()->definition(), [
        'platform_id'      => $platform->id,
        'sales_channel_id' => $apiChannel->id,
        'gross_amount'     => 20,
        'net_amount'       => 20,
        'total_amount'     => 20,
    ]));
    StoreInvoice::make()->action($this->customer, array_merge(Invoice::factory()->definition(), [
        'platform_id'  => $platform->id,
        'gross_amount' => 5,
        'net_amount'   => 5,
        'total_amount' => 5,
    ]));

    ProcessPlatformTimeSeriesRecords::run($platform->id, $this->shop->id, TimeSeriesFrequencyEnum::DAILY, now()->toDateString(), now()->toDateString());

    $records = PlatformSalesChannelTimeSeriesRecord::where('shop_id', $this->shop->id)->where('frequency', 'D')->get();
    expect($records)->toHaveCount(2)
        ->and((float) $records->firstWhere('sales_channel_id', $webChannel->id)->sales_external)->toBe(15.0)
        ->and((int) $records->firstWhere('sales_channel_id', $webChannel->id)->invoices)->toBe(2)
        ->and((float) $records->firstWhere('sales_channel_id', $apiChannel->id)->sales_external)->toBe(20.0)
        ->and((int) $records->firstWhere('sales_channel_id', $apiChannel->id)->invoices)->toBe(1)
        ->and((float) $records->sum('sales_external'))->toBe(35.0);

    $stats    = collect(GetPlatformTimeSeriesStats::run($this->shop));
    $children = $stats->filter(fn ($row) => !empty($row['is_sales_channel']))->values();
    expect($stats->firstWhere('slug', $platform->slug))->not->toBeNull()
        ->and($children)->toHaveCount(2)
        ->and($children->pluck('name')->all())->toContain('Web', $apiChannel->name)
        ->and($children->pluck('parent_slug')->unique()->all())->toBe([$platform->slug])
        ->and($stats->search(fn ($row) => !empty($row['is_sales_channel'])))
        ->toBeGreaterThan($stats->search(fn ($row) => ($row['slug'] ?? null) === $platform->slug));
});

test('platforms:backfill_sales_channel_time_series is dry run by default and backfills with --commit', function () {
    SeedSalesChannels::run($this->organisation->group);
    $platform   = Platform::where('type', PlatformTypeEnum::MANUAL)->first();
    $webChannel = SalesChannel::where('type', SalesChannelTypeEnum::WEBSITE)->first();

    StoreInvoice::make()->action($this->customer, array_merge(Invoice::factory()->definition(), [
        'platform_id'      => $platform->id,
        'sales_channel_id' => $webChannel->id,
    ]));

    PlatformSalesChannelTimeSeriesRecord::query()->delete();

    $this->artisan('platforms:backfill_sales_channel_time_series')->assertSuccessful();
    expect(PlatformSalesChannelTimeSeriesRecord::count())->toBe(0);

    $this->artisan('platforms:backfill_sales_channel_time_series', ['--commit' => true])->assertSuccessful();

    $apiChannel       = SalesChannel::where('type', SalesChannelTypeEnum::API)->first();
    $expectedWebSales = (float) Invoice::where('platform_id', $platform->id)
        ->where(fn ($query) => $query->whereNull('sales_channel_id')->orWhere('sales_channel_id', '!=', $apiChannel->id))
        ->where('in_process', false)
        ->sum('net_amount');

    $records    = PlatformSalesChannelTimeSeriesRecord::where('shop_id', $this->shop->id)->get();
    $webRecords = $records->where('sales_channel_id', $webChannel->id);
    expect($webRecords->where('frequency', 'D'))->toHaveCount(1)
        ->and($records->pluck('frequency')->unique()->sort()->values()->all())->toBe(['D', 'M', 'Q', 'W', 'Y'])
        ->and((float) $webRecords->firstWhere('frequency', 'Y')->sales_external)->toBe($expectedWebSales);
});

test('channel import never submits an order with no transactions', function () {
    $modelData = Order::factory()->definition();
    data_set($modelData, 'billing_address', new Address(Address::factory()->definition()));
    data_set($modelData, 'delivery_address', new Address(Address::factory()->definition()));
    $order = StoreOrder::make()->action($this->customer, $modelData);
    expect($order->transactions()->count())->toBe(0);

    $action = new class () {
        use \App\Actions\Ordering\Order\Traits\WithPayAndSubmitOrder;
    };
    $result = $action->payAndSubmitOrder($order);

    expect($result->state)->toBe(OrderStateEnum::CREATING)
        ->and($result->submitted_at)->toBeNull();
});

test('a part paid order that fails to submit is alerted, an unpaid one is not', function () {
    config(['services.discord.webhook_url' => 'https://discord.test/webhook']);
    Illuminate\Support\Facades\Queue::fake();

    $modelData = Order::factory()->definition();
    data_set($modelData, 'billing_address', new Address(Address::factory()->definition()));
    data_set($modelData, 'delivery_address', new Address(Address::factory()->definition()));
    $order = StoreOrder::make()->action($this->customer, $modelData);
    $order->update(['total_amount' => 19.88, 'payment_amount' => 0]);

    $action = new class () {
        use \App\Actions\Ordering\Order\Traits\WithPayAndSubmitOrder;

        public function alert(Order $order, Throwable $e): void
        {
            $this->alertPaidOrderNotSubmitted($order, $e);
        }
    };

    $action->alert($order, new Exception('submit refused'));

    Illuminate\Support\Facades\Queue::assertNotPushed(Illuminate\Queue\CallQueuedClosure::class);

    $order->update(['payment_amount' => 5.00]);

    $action->alert($order, new Exception('submit refused'));

    Illuminate\Support\Facades\Queue::assertPushed(Illuminate\Queue\CallQueuedClosure::class, 1);
});

test('fulfilment gate holds order from warehouse until released', function () {
    $this->organisation->update(['settings' => array_merge($this->organisation->settings, ['fulfilment_gate' => true])]);

    $modelData = Order::factory()->definition();
    data_set($modelData, 'billing_address', new Address(Address::factory()->definition()));
    data_set($modelData, 'delivery_address', new Address(Address::factory()->definition()));

    $order = StoreOrder::make()->action($this->customer, $modelData);

    StoreTransaction::make()->action($order, $this->product->historicAsset, array_merge(
        Transaction::factory()->definition(),
        ['quantity_ordered' => 2]
    ));

    SubmitOrder::make()->action($order);
    $held = SendOrderToWarehouse::make()->action($order, []);
    $order->refresh();

    expect($held)->toBeNull()
        ->and($order->state)->toEqual(OrderStateEnum::SUBMITTED)
        ->and($order->at_gate_at)->not->toBeNull()
        ->and($order->deliveryNotes()->count())->toBe(0);

    $deliveryNote = \App\Actions\Ordering\Order\UpdateState\ReleaseOrderFromGate::make()->action($order);
    $order->refresh();

    expect($deliveryNote)->toBeInstanceOf(DeliveryNote::class)
        ->and($order->state)->toEqual(OrderStateEnum::IN_WAREHOUSE)
        ->and($order->at_gate_at)->toBeNull()
        ->and(\App\Models\Dispatching\FulfilmentGateRelease::where('order_id', $order->id)->count())->toBe(1);
});

test('fulfilment gate lets paid fully coverable order straight to warehouse', function () {
    $this->organisation->update(['settings' => array_merge($this->organisation->settings, ['fulfilment_gate' => true])]);

    $modelData = Order::factory()->definition();
    data_set($modelData, 'billing_address', new Address(Address::factory()->definition()));
    data_set($modelData, 'delivery_address', new Address(Address::factory()->definition()));

    $order = StoreOrder::make()->action($this->customer, $modelData);

    StoreTransaction::make()->action($order, $this->product->historicAsset, array_merge(
        Transaction::factory()->definition(),
        ['quantity_ordered' => 2]
    ));

    if (!$this->product->orgStocks()->count()) {
        $orgStock = \App\Models\Inventory\OrgStock::where('organisation_id', $this->organisation->id)->firstOrFail();
        $this->product->orgStocks()->attach($orgStock->id, ['quantity' => 1]);
    }
    $this->product->orgStocks()->update(['quantity_available' => 100000]);

    SubmitOrder::make()->action($order);
    $deliveryNote = SendOrderToWarehouse::make()->action($order, []);
    $order->refresh();

    expect($deliveryNote)->toBeInstanceOf(DeliveryNote::class)
        ->and($order->state)->toEqual(OrderStateEnum::IN_WAREHOUSE)
        ->and($order->at_gate_at)->toBeNull();
});

test('fulfilment gate auto releases paid order when stock arrives', function () {
    $this->organisation->update(['settings' => array_merge($this->organisation->settings, ['fulfilment_gate' => true])]);

    $modelData = Order::factory()->definition();
    data_set($modelData, 'billing_address', new Address(Address::factory()->definition()));
    data_set($modelData, 'delivery_address', new Address(Address::factory()->definition()));

    $order = StoreOrder::make()->action($this->customer, $modelData);

    StoreTransaction::make()->action($order, $this->product->historicAsset, array_merge(
        Transaction::factory()->definition(),
        ['quantity_ordered' => 2]
    ));

    if (!$this->product->orgStocks()->count()) {
        $orgStock = \App\Models\Inventory\OrgStock::where('organisation_id', $this->organisation->id)->firstOrFail();
        $this->product->orgStocks()->attach($orgStock->id, ['quantity' => 1]);
    }
    $this->product->orgStocks()->update(['quantity_available' => 0]);

    $order->update(['pay_status' => \App\Enums\Ordering\Order\OrderPayStatusEnum::PAID]);
    SubmitOrder::make()->action($order);
    $order->refresh();

    expect($order->at_gate_at)->not->toBeNull()
        ->and($order->state)->toEqual(OrderStateEnum::SUBMITTED);

    $this->product->orgStocks()->update(['quantity_available' => 100000]);
    \App\Actions\Dispatching\FulfilmentGate\ReleaseCoverableOrdersAtGate::run($this->organisation->id);
    $order->refresh();

    expect($order->state)->toEqual(OrderStateEnum::IN_WAREHOUSE)
        ->and($order->at_gate_at)->toBeNull()
        ->and($order->deliveryNotes()->count())->toBe(1);
});

test('make queue ranks paid blocked stock with suggested quantity', function () {
    $this->organisation->update(['settings' => array_merge($this->organisation->settings, ['fulfilment_gate' => true])]);

    $modelData = Order::factory()->definition();
    data_set($modelData, 'billing_address', new Address(Address::factory()->definition()));
    data_set($modelData, 'delivery_address', new Address(Address::factory()->definition()));

    $order = StoreOrder::make()->action($this->customer, $modelData);
    $transaction = StoreTransaction::make()->action($order, $this->product->historicAsset, array_merge(
        Transaction::factory()->definition(),
        ['quantity_ordered' => 5]
    ));
    $transaction->update(['net_amount' => 250]);

    if (!$this->product->orgStocks()->count()) {
        $orgStock = \App\Models\Inventory\OrgStock::where('organisation_id', $this->organisation->id)->firstOrFail();
        $this->product->orgStocks()->attach($orgStock->id, ['quantity' => 1]);
    }
    $orgStock = $this->product->orgStocks()->first();
    $this->product->orgStocks()->update(['quantity_available' => 0]);

    $production = $this->organisation->productions()->first()
        ?? \App\Actions\Production\Production\StoreProduction::make()->action($this->organisation, ['code' => 'MKQ', 'name' => 'MKQ']);
    $artefact = \App\Models\Production\Artefact::firstOrCreate(
        ['organisation_id' => $this->organisation->id, 'org_stock_id' => $orgStock->id],
        ['group_id' => $this->organisation->group_id, 'production_id' => $production->id, 'code' => 'MKQ-ART', 'name' => 'MKQ artefact']
    );

    $order->update(['pay_status' => \App\Enums\Ordering\Order\OrderPayStatusEnum::PAID]);
    SubmitOrder::make()->action($order);
    $order->refresh();
    expect($order->at_gate_at)->not->toBeNull();

    $queue = \App\Actions\Dispatching\FulfilmentGate\GetMakeQueue::make()->handle($this->organisation);
    $row = collect($queue->items())->firstWhere('org_stock_id', $orgStock->id);

    expect($row)->not->toBeNull()
        ->and((float) $row->blocked_paid_amount)->toBe(250.0)
        ->and((int) $row->suggested_quantity)->toBeGreaterThanOrEqual(5)
        ->and((float) $row->score)->toBeGreaterThan(0);
});

test('repair order charge flags sets premium flag from orphan charge line', function (Order $order) {
    $charge = StoreCharge::make()->action($order->shop, [
        'code'        => 'PREMIUM-REPAIR',
        'name'        => 'Premium Dispatch',
        'description' => 'premium dispatch',
        'state'       => ChargeStateEnum::ACTIVE,
        'trigger'     => ChargeTriggerEnum::ORDER,
        'type'        => ChargeTypeEnum::PREMIUM,
    ]);
    StoreTransactionFromCharge::make()->action($order, $charge, [
        'date'             => Carbon::now(),
        'quantity_ordered' => 1,
    ]);

    expect($order->refresh()->is_premium_dispatch)->toBeFalse();

    $this->artisan('repair:order_charge_flags')->assertSuccessful();
    expect($order->refresh()->is_premium_dispatch)->toBeFalse();

    $this->artisan('repair:order_charge_flags --commit')->assertSuccessful();
    expect($order->refresh()->is_premium_dispatch)->toBeTrue();
})->depends('create order');

function clearActiveGiftMessageCharges(Order $order): void
{
    $order->shop->charges()
        ->where('type', ChargeTypeEnum::GIFT_MESSAGE)
        ->where('state', ChargeStateEnum::ACTIVE)
        ->get()
        ->each(fn ($charge) => UpdateCharge::make()->action($charge, ['state' => ChargeStateEnum::DISCONTINUED]));
}

test('store charge with gift message type gets selected by customer trigger', function (Order $order) {
    clearActiveGiftMessageCharges($order);

    $charge = StoreCharge::make()->action($order->shop, [
        'code'        => 'GIFT-MESSAGE',
        'name'        => 'Gift message',
        'description' => 'gift message',
        'state'       => ChargeStateEnum::IN_PROCESS,
        'type'        => ChargeTypeEnum::GIFT_MESSAGE,
    ]);

    expect($charge->trigger)->toBe(ChargeTriggerEnum::SELECTED_BY_CUSTOMER);
})->depends('create order');

test('toggling gift message on an order adds and removes its charge transaction', function (Order $order) {
    clearActiveGiftMessageCharges($order);

    StoreCharge::make()->action($order->shop, [
        'code'        => 'GIFT-MESSAGE-TOGGLE',
        'name'        => 'Gift message',
        'description' => 'gift message',
        'state'       => ChargeStateEnum::ACTIVE,
        'type'        => ChargeTypeEnum::GIFT_MESSAGE,
        'settings'    => ['amount' => 1],
    ]);

    UpdateOrderGiftMessage::make()->handle($order, ['has_gift_message' => true]);

    $giftMessageTransaction = DB::table('transactions')
        ->where('order_id', $order->id)
        ->leftJoin('charges', 'transactions.model_id', '=', 'charges.id')
        ->where('model_type', 'Charge')
        ->where('charges.type', ChargeTypeEnum::GIFT_MESSAGE->value)
        ->value('transactions.id');

    expect($giftMessageTransaction)->not->toBeNull();

    UpdateOrderGiftMessage::make()->handle($order, ['has_gift_message' => false]);

    $giftMessageTransaction = DB::table('transactions')
        ->where('order_id', $order->id)
        ->leftJoin('charges', 'transactions.model_id', '=', 'charges.id')
        ->where('model_type', 'Charge')
        ->where('charges.type', ChargeTypeEnum::GIFT_MESSAGE->value)
        ->value('transactions.id');

    expect($giftMessageTransaction)->toBeNull();
})->depends('create order');

test('updating the gift message charge amount changes the amount added to the basket', function (Order $order) {
    clearActiveGiftMessageCharges($order);

    $charge = StoreCharge::make()->action($order->shop, [
        'code'        => 'GIFT-MESSAGE-AMOUNT',
        'name'        => 'Gift message',
        'description' => 'gift message',
        'state'       => ChargeStateEnum::ACTIVE,
        'type'        => ChargeTypeEnum::GIFT_MESSAGE,
        'settings'    => ['amount' => 1],
    ]);

    UpdateOrderGiftMessage::make()->handle($order, ['has_gift_message' => true]);
    $transaction = Transaction::where('order_id', $order->id)->where('model_id', $charge->id)->where('model_type', 'Charge')->first();
    expect((float) $transaction->gross_amount)->toBe(1.0);

    UpdateCharge::make()->action($charge, ['amount' => 3]);
    UpdateOrderGiftMessage::make()->handle($order, ['has_gift_message' => true]);

    $transaction->refresh();
    expect((float) $transaction->gross_amount)->toBe(3.0);

    UpdateOrderGiftMessage::make()->handle($order, ['has_gift_message' => false]);
})->depends('create order');

test('switching the gift message charge off removes it from the basket charges', function (Order $order) {
    clearActiveGiftMessageCharges($order);

    $charge = StoreCharge::make()->action($order->shop, [
        'code'        => 'GIFT-MESSAGE-STATE',
        'name'        => 'Gift message',
        'description' => 'gift message',
        'state'       => ChargeStateEnum::ACTIVE,
        'type'        => ChargeTypeEnum::GIFT_MESSAGE,
        'settings'    => ['amount' => 1],
    ]);

    expect($order->shop->charges()->where('type', ChargeTypeEnum::GIFT_MESSAGE)->where('state', ChargeStateEnum::ACTIVE)->first()?->id)->toBe($charge->id);

    UpdateCharge::make()->action($charge, ['state' => ChargeStateEnum::DISCONTINUED]);

    expect($order->shop->charges()->where('type', ChargeTypeEnum::GIFT_MESSAGE)->where('state', ChargeStateEnum::ACTIVE)->first())->toBeNull();
})->depends('create order');

test('gift message text saves and is cleared when the toggle is switched off', function (Order $order) {
    clearActiveGiftMessageCharges($order);

    StoreCharge::make()->action($order->shop, [
        'code'        => 'GIFT-MESSAGE-TEXT',
        'name'        => 'Gift message',
        'description' => 'gift message',
        'state'       => ChargeStateEnum::ACTIVE,
        'type'        => ChargeTypeEnum::GIFT_MESSAGE,
        'settings'    => ['amount' => 1],
    ]);

    UpdateOrderGiftMessage::make()->handle($order, ['has_gift_message' => true]);
    $order->update(['gift_message' => 'Happy Birthday!']);
    expect($order->refresh()->gift_message)->toBe('Happy Birthday!');

    UpdateOrderGiftMessage::make()->handle($order, ['has_gift_message' => false]);
    expect($order->refresh()->gift_message)->toBeNull();
})->depends('create order');

test('gift message pdf upload accepts a pdf and rejects a non pdf file', function (Order $order) {
    $pdf = UpdateRetinaOrderGiftMessagePdf::make()->handle($order, [
        'gift_message_pdf' => UploadedFile::fake()->create('message.pdf', 100, 'application/pdf'),
    ]);

    expect($pdf->attachments()->wherePivot('scope', 'GiftMessage')->exists())->toBeTrue();

    $validator = validator(
        ['gift_message_pdf' => UploadedFile::fake()->image('message.jpg')],
        UpdateRetinaOrderGiftMessagePdf::make()->rules()
    );

    expect($validator->fails())->toBeTrue();
})->depends('create order');

test('iris exposes the gift message text and pdf routes on the retina order gift message actions', function () {
    expect(\Illuminate\Support\Facades\Route::has('iris.models.order.update_gift_message_text'))->toBeTrue()
        ->and(\Illuminate\Support\Facades\Route::has('iris.models.order.update_gift_message_pdf'))->toBeTrue();

    $textRoute = \Illuminate\Support\Facades\Route::getRoutes()->getByName('iris.models.order.update_gift_message_text');
    $pdfRoute  = \Illuminate\Support\Facades\Route::getRoutes()->getByName('iris.models.order.update_gift_message_pdf');

    expect($textRoute->getActionName())->toContain(\App\Actions\Retina\Dropshipping\Orders\UpdateRetinaOrder::class)
        ->and($pdfRoute->getActionName())->toContain(UpdateRetinaOrderGiftMessagePdf::class);
});

test('iris exposes the order update route for basket delivery and other instructions', function () {
    $route = \Illuminate\Support\Facades\Route::getRoutes()->getByName('iris.models.order.update');

    expect($route)->not->toBeNull()
        ->and($route->methods())->toContain('PATCH')
        ->and($route->getActionName())->toContain(\App\Actions\Retina\Dropshipping\Orders\UpdateRetinaOrder::class);
});

test('retina order update stores basket delivery and other instructions', function (Order $order) {
    $order = \App\Actions\Retina\Dropshipping\Orders\UpdateRetinaOrder::make()->handle($order, [
        'shipping_notes' => 'Leave at back door',
        'customer_notes' => 'Please pack carefully',
    ]);

    expect($order->shipping_notes)->toBe('Leave at back door')
        ->and($order->customer_notes)->toBe('Please pack carefully');
})->depends('create order');

test('the generated gift message card pdf route returns a pdf for a text message', function (Order $order) {
    $order->update(['gift_message' => 'Happy Birthday!']);

    $response = PdfOrderGiftMessage::make()->handle($order);

    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
})->depends('create order');

test('checkout is blocked when the gift message toggle is on without a message or pdf', function (Order $order) {
    foreach ($order->attachments()->wherePivot('scope', 'GiftMessage')->get() as $attachment) {
        $order->attachments()->detach($attachment->id);
    }
    $order->update(['has_gift_message' => true, 'gift_message' => null]);

    expect(fn () => SubmitOrder::make()->handle($order))->toThrow(ValidationException::class);
})->depends('create order');

test('submitting an order stamps the customer permanent shipping label note unless the order has its own', function () {
    $this->customer->update(['shipping_notes' => 'Open Mon-Fri 9-5']);

    $newOrder = function () {
        $modelData = Order::factory()->definition();
        data_set($modelData, 'billing_address', new Address(Address::factory()->definition()));
        data_set($modelData, 'delivery_address', new Address(Address::factory()->definition()));

        return StoreOrder::make()->action($this->customer, $modelData);
    };

    $order = $newOrder();
    expect($order->shipping_notes)->toBe('Open Mon-Fri 9-5');
    $order = SubmitOrder::make()->action($order);
    expect($order->shipping_notes)->toBe('Open Mon-Fri 9-5');

    $this->customer->update(['shipping_notes' => null]);
    $order = $newOrder();
    expect($order->shipping_notes)->toBeNull();
    $this->customer->update(['shipping_notes' => 'Open Mon-Fri 9-5']);
    $order = SubmitOrder::make()->action($order->refresh());
    expect($order->shipping_notes)->toBe('Open Mon-Fri 9-5');

    $order = $newOrder();
    UpdateOrder::make()->action($order, ['shipping_notes' => 'Leave at reception']);
    $order = SubmitOrder::make()->action($order->refresh());
    expect($order->shipping_notes)->toBe('Leave at reception');

    $this->customer->update(['shipping_notes' => null]);
});

test('paying with balance sends the order to the warehouse only when the balance covers it', function () {
    $newSubmittedOrder = function () {
        $modelData = Order::factory()->definition();
        data_set($modelData, 'billing_address', new Address(Address::factory()->definition()));
        data_set($modelData, 'delivery_address', new Address(Address::factory()->definition()));
        $order = StoreOrder::make()->action($this->customer, $modelData);
        StoreTransaction::make()->action($order, $this->product->historicAsset, Transaction::factory()->definition());

        return SubmitOrder::make()->action($order->refresh());
    };

    $balanceAccount = $this->shop->paymentAccountShops()->where('type', PaymentAccountTypeEnum::ACCOUNT)->first()->paymentAccount;
    $topUp          = function (float $amount) use ($balanceAccount) {
        $payment = StorePayment::make()->action($this->customer, $balanceAccount, [
            'amount'    => $amount,
            'reference' => 'ref-bal-'.Str::ulid(),
            'status'    => PaymentStatusEnum::SUCCESS->value,
            'state'     => PaymentStateEnum::COMPLETED->value,
        ]);
        StoreCreditTransaction::make()->action($this->customer, [
            'payment_id' => $payment->id,
            'amount'     => $amount,
            'date'       => now(),
            'type'       => CreditTransactionTypeEnum::TOP_UP,
        ]);
    };

    $order = $newSubmittedOrder();
    expect((float) $order->total_amount)->toBeGreaterThan(1);

    $topUp(1);
    $result = PayOrderWithCustomerBalance::make()->initialisationFromShop($this->shop, [])->handle($order->fresh());
    $order->refresh();
    expect($result['success'])->toBeTrue($result['reason'])
        ->and($order->state)->toBe(OrderStateEnum::SUBMITTED)
        ->and($order->pay_status)->toBe(OrderPayStatusEnum::UNPAID);

    $topUp((float) $order->total_amount);
    PayOrderWithCustomerBalance::make()->initialisationFromShop($this->shop, [])->handle($order->refresh());
    $order->refresh();
    expect($order->pay_status)->toBe(OrderPayStatusEnum::PAID)
        ->and($order->state)->toBe(OrderStateEnum::IN_WAREHOUSE);
});

test('a staff recorded payment sends a submitted order to the warehouse only once it is fully paid', function () {
    $modelData = Order::factory()->definition();
    data_set($modelData, 'billing_address', new Address(Address::factory()->definition()));
    data_set($modelData, 'delivery_address', new Address(Address::factory()->definition()));
    $order = StoreOrder::make()->action($this->customer, $modelData);
    StoreTransaction::make()->action($order, $this->product->historicAsset, Transaction::factory()->definition());
    $order = SubmitOrder::make()->action($order->refresh());
    expect($order->state)->toBe(OrderStateEnum::SUBMITTED)
        ->and((float) $order->total_amount)->toBeGreaterThan(1);

    $bankAccount = StoreOrgPaymentServiceProviderAccount::make()->action(
        $this->organisation,
        PaymentServiceProvider::where('type', PaymentServiceProviderTypeEnum::BANK->value)->first(),
        [
            'code' => 'BANK'.mt_rand(1000, 9999),
            'name' => 'Bank transfer',
        ]
    );
    $payByBank = fn (float $amount) => PayOrder::make()->action($order->refresh(), $bankAccount, [
        'amount'    => $amount,
        'reference' => 'BT-'.Str::ulid(),
        'status'    => PaymentStatusEnum::SUCCESS,
        'state'     => PaymentStateEnum::COMPLETED,
    ]);

    $payByBank(1);
    expect($order->refresh()->state)->toBe(OrderStateEnum::SUBMITTED)
        ->and($order->pay_status)->toBe(OrderPayStatusEnum::UNPAID);

    $payByBank(round((float) $order->total_amount - 1, 2));
    expect($order->refresh()->pay_status)->toBe(OrderPayStatusEnum::PAID)
        ->and($order->state)->toBe(OrderStateEnum::IN_WAREHOUSE);
});

test('an accounting supervisor can send an unpaid order to the warehouse, leaving who and why in the internal notes', function () {
    $modelData = Order::factory()->definition();
    data_set($modelData, 'billing_address', new Address(Address::factory()->definition()));
    data_set($modelData, 'delivery_address', new Address(Address::factory()->definition()));
    $order = StoreOrder::make()->action($this->customer, $modelData);
    StoreTransaction::make()->action($order, $this->product->historicAsset, Transaction::factory()->definition());
    $order = SubmitOrder::make()->action($order->refresh());
    expect($order->state)->toBe(OrderStateEnum::SUBMITTED)
        ->and($order->pay_status)->not->toBe(OrderPayStatusEnum::PAID);

    expect(fn () => SendUnpaidOrderToWarehouse::make()->action($order, $this->user, ['reason' => '']))
        ->toThrow(ValidationException::class);

    SendUnpaidOrderToWarehouse::make()->action($order, $this->user, ['reason' => 'Customer on 30 day terms']);
    $order->refresh();
    expect($order->state)->toBe(OrderStateEnum::IN_WAREHOUSE)
        ->and($order->internal_notes)->toContain('Customer on 30 day terms')
        ->and($order->internal_notes)->toContain($this->user->contact_name ?: $this->user->username);

    expect(fn () => SendUnpaidOrderToWarehouse::make()->action($order, $this->user, ['reason' => 'again']))
        ->toThrow(ValidationException::class);
});

test('a credit line lets the customer order on account down to minus the limit, never beyond', function () {
    $newBasket = function () {
        $modelData = Order::factory()->definition();
        data_set($modelData, 'billing_address', new Address(Address::factory()->definition()));
        data_set($modelData, 'delivery_address', new Address(Address::factory()->definition()));
        $order = StoreOrder::make()->action($this->customer, $modelData);
        StoreTransaction::make()->action($order, $this->product->historicAsset, Transaction::factory()->definition());

        return $order->refresh();
    };

    $this->customer->refresh();
    if ((float) $this->customer->balance != 0) {
        StoreCreditTransaction::make()->action($this->customer, [
            'amount' => -$this->customer->balance,
            'date'   => now(),
            'type'   => CreditTransactionTypeEnum::ADJUST,
        ]);
    }
    expect((float) $this->customer->refresh()->balance)->toBe(0.0);

    $order = $newBasket();
    $total = (float) $order->total_amount;

    $result = PayRetinaOrderWithBalance::make()->handle($order);
    expect($result['success'])->toBeFalse()
        ->and($order->refresh()->state)->toBe(OrderStateEnum::CREATING);

    UpdateCustomer::make()->action($this->customer, ['credit_limit' => $total - 0.01, 'payment_terms_days' => 30]);
    $result = PayRetinaOrderWithBalance::make()->handle($order->refresh());
    expect($result['success'])->toBeFalse()
        ->and($order->refresh()->state)->toBe(OrderStateEnum::CREATING);

    UpdateCustomer::make()->action($this->customer, ['credit_limit' => $total]);
    $result = PayRetinaOrderWithBalance::make()->handle($order->refresh());
    expect($result['success'])->toBeTrue($result['reason'])
        ->and($order->refresh()->state)->not->toBe(OrderStateEnum::CREATING)
        ->and($order->pay_status)->toBe(OrderPayStatusEnum::PAID)
        ->and((float) $this->customer->refresh()->balance)->toBe(-$total)
        ->and($this->customer->spendableBalance())->toBe(0.0);

    $secondOrder = SubmitOrder::make()->action($newBasket());
    $result      = PayOrderWithCustomerBalance::make()->initialisationFromShop($this->shop, [])->handle($secondOrder->refresh());
    expect($result['success'])->toBeFalse()
        ->and((float) $this->customer->refresh()->balance)->toBe(-$total);

    UpdateCustomer::make()->action($this->customer, ['credit_limit' => $total * 3]);
    $result = PayOrderWithCustomerBalance::make()->initialisationFromShop($this->shop, [])->handle($secondOrder->refresh(), canUseCredit: false);
    expect($result['success'])->toBeFalse()
        ->and((float) $this->customer->refresh()->balance)->toBe(-$total);

    $result = PayOrderWithCustomerBalance::make()->initialisationFromShop($this->shop, [])->handle($secondOrder->refresh());
    expect($result['success'])->toBeTrue($result['reason'])
        ->and($secondOrder->refresh()->pay_status)->toBe(OrderPayStatusEnum::PAID)
        ->and((float) $this->customer->refresh()->balance)->toBe(round(-$total - (float) $secondOrder->total_amount, 2));

    UpdateCustomer::make()->action($this->customer, ['credit_limit' => 0, 'payment_terms_days' => null]);
});

test('turning on recargo de equivalencia propagates to baskets migrated from aurora in aiku shops', function () {
    $this->shop->update(['is_aiku' => true]);
    $this->customer->refresh();
    $modelData = Order::factory()->definition();
    data_set($modelData, 'billing_address', new Address(Address::factory()->definition()));
    data_set($modelData, 'delivery_address', new Address(Address::factory()->definition()));
    $order = StoreOrder::make()->action($this->customer, $modelData);
    $order->update(['source_id' => '3:999999']);
    expect($order->is_re)->toBeFalse();

    UpdateCustomer::make()->action($this->customer, ['is_re' => true]);

    expect($order->refresh()->is_re)->toBeTrue();
});

test('UI shop dashboard widgets endpoint returns every widget for an interval', function () {
    $this->withoutExceptionHandling();
    StoreInvoice::make()->action($this->customer, Invoice::factory()->definition());

    $dashboard = get(route('grp.org.shops.show.dashboard.show', [$this->organisation->slug, $this->shop->slug]));
    $dashboard->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('dashboard.super_blocks.0.widgets_route.name', 'grp.org.shops.show.dashboard.widgets')
            ->etc()
    );

    $response = getJson(route('grp.org.shops.show.dashboard.widgets', [$this->organisation->slug, $this->shop->slug, 'interval' => '1y']));
    $response->assertOk()
        ->assertJsonPath('interval', '1y')
        ->assertJsonPath('currency_code', $this->shop->currency->code)
        ->assertJsonStructure([
            'from', 'to', 'channels', 'top_customers', 'top_products', 'top_families', 'out_of_stock', 'top_webpages',
            'email' => ['totals', 'mailshots'],
            'marketing' => ['totals', 'channels'],
            'subscriptions' => ['registrations', 'unsubscribed', 'net'],
            'routes' => ['customers', 'product', 'family', 'marketing'],
        ]);

    expect(collect($response->json('channels'))->sum('invoices'))->toBeGreaterThanOrEqual(1);

    $allTime = getJson(route('grp.org.shops.show.dashboard.widgets', [$this->organisation->slug, $this->shop->slug, 'interval' => 'all']));
    $allTime->assertOk()->assertJsonPath('from', null);
    expect($allTime->json('subscriptions.registrations'))->toBeGreaterThanOrEqual(1);
});

test('export flag follows the customs territory of the organisation', function () {
    $addressIn = fn (string $code, ?string $postalCode = null) => new Address(array_merge(
        Address::factory()->definition(),
        ['country_code' => $code, 'country_id' => Country::where('code', $code)->value('id'), 'postal_code' => $postalCode ?? fake()->postcode]
    ));
    $canaries = $addressIn('ES', '35001');
    $madrid   = $addressIn('ES', '28001');
    $jersey   = $addressIn('JE');
    $london   = $addressIn('GB');
    $usa      = $addressIn('US');

    expect(\App\Actions\Ordering\Order\SetOrderDeliveryCountry::isExportDelivery('GB', $london))->toBeFalse()
        ->and(\App\Actions\Ordering\Order\SetOrderDeliveryCountry::isExportDelivery('GB', $jersey))->toBeTrue()
        ->and(\App\Actions\Ordering\Order\SetOrderDeliveryCountry::isExportDelivery('GB', $madrid))->toBeTrue()
        ->and(\App\Actions\Ordering\Order\SetOrderDeliveryCountry::isExportDelivery('SK', $madrid))->toBeFalse()
        ->and(\App\Actions\Ordering\Order\SetOrderDeliveryCountry::isExportDelivery('SK', $canaries))->toBeTrue()
        ->and(\App\Actions\Ordering\Order\SetOrderDeliveryCountry::isExportDelivery('SK', $london))->toBeTrue()
        ->and(\App\Actions\Ordering\Order\SetOrderDeliveryCountry::isExportDelivery('SK', $usa))->toBeTrue();

    $modelData = Order::factory()->definition();
    data_set($modelData, 'billing_address', new Address(Address::factory()->definition()));
    data_set($modelData, 'delivery_address', $usa);
    $order = StoreOrder::make()->action($this->customer, $modelData, strict: false);
    expect($order->is_export)->toBeTrue();

    $home = $addressIn($this->organisation->country->code);
    \App\Actions\Ordering\Order\UpdateOrderFixedAddress::make()->action($order, ['address' => $home, 'type' => 'delivery']);
    expect($order->refresh()->is_export)->toBe(\App\Actions\Ordering\Order\SetOrderDeliveryCountry::isExportDelivery($this->organisation->country->code, $home));

    $this->shop->update(['state' => \App\Enums\Catalogue\Shop\ShopStateEnum::OPEN]);
    $counts = \App\Actions\Ordering\Order\UI\IndexOrders::make()->scopeCounts($this->shop, 'in_basket');
    $creating = Order::where('shop_id', $this->shop->id)->where('state', OrderStateEnum::CREATING);
    expect($counts)->toBe([
        'domestic' => (clone $creating)->where('is_export', false)->count(),
        'export'   => (clone $creating)->where('is_export', true)->count(),
    ])->and($counts['domestic'] + $counts['export'])->toBeGreaterThan(0);
});


test('an order with no billing address is held instead of going to the warehouse', function () {
    $customer = freshCustomerLike($this->shop, $this->customer);
    $order    = StoreOrder::make()->action($customer, Order::factory()->definition());
    StoreTransaction::make()->action($order, $this->product->currentHistoricProduct, Transaction::factory()->definition());
    SubmitOrder::make()->action($order);

    $order->refresh();
    $order->billingAddress->update(['address_line_1' => '', 'address_line_2' => '', 'locality' => '', 'postal_code' => '', 'administrative_area' => '']);
    $order->unsetRelation('billingAddress');

    $deliveryNote = SendOrderToWarehouse::make()->action($order, []);
    $order->refresh();

    expect($deliveryNote)->toBeNull()
        ->and($order->state)->toEqual(OrderStateEnum::SUBMITTED)
        ->and($order->private_warehouse_note)->toContain('billing address is missing');
});

test('a collection invoice stores the collection address it was issued with', function () {
    $customer = createCustomer($this->shop);
    $order    = StoreOrder::make()->action($customer, Order::factory()->definition());
    StoreTransaction::make()->action($order, $this->product->currentHistoricProduct, Transaction::factory()->definition());

    $collectionAddress = \App\Models\Helpers\Address::create(array_merge(
        \App\Models\Helpers\Address::factory()->definition(),
        ['group_id' => $order->group_id, 'address_line_1' => 'Affinity Park']
    ));
    $order->shop->update(['collection_address_id' => $collectionAddress->id]);
    $order->update(['collection_address_id' => $collectionAddress->id]);

    SubmitOrder::make()->action($order);
    $invoice = GenerateInvoiceFromOrder::make()->action($order->refresh(), []);

    $collectionAddress->update(['address_line_1' => 'Somewhere else entirely']);

    expect($invoice->deliveryAddress?->address_line_1)->toBe('Affinity Park');
});

test('an order switched to collection is taxed where it is collected, not where the customer lives', function () {
    DB::beginTransaction();
    try {
        $spain = Country::where('code', 'ES')->first();
        $this->organisation->forceFill(['country_id' => $spain->id])->save();
        $spanishVat = TaxCategory::where('type', \App\Enums\Helpers\TaxCategories\TaxCategoryTypeEnum::STANDARD)->where('country_id', $spain->id)->where('status', true)->first();

        $addressIn = fn (string $postalCode) => \App\Models\Helpers\Address::create(array_merge(
            \App\Models\Helpers\Address::factory()->definition(),
            ['group_id' => $this->shop->group_id, 'country_code' => 'ES', 'country_id' => $spain->id, 'postal_code' => $postalCode]
        ));
        $showroom = $addressIn('29004');
        $this->shop->update(['collection_address_id' => $showroom->id]);

        $customer = freshCustomerLike($this->shop, $this->customer);
        $order    = StoreOrder::make()->action($customer, Order::factory()->definition());
        StoreTransaction::make()->action($order, $this->product->currentHistoricProduct, Transaction::factory()->definition());
        $ceuta = $addressIn('51001');
        $order->update(['billing_address_id' => $ceuta->id, 'delivery_address_id' => $ceuta->id]);
        ResetOrderTaxCategory::run($order->refresh());
        expect($order->refresh()->tax_category_id)->toBe(1);

        UpdateOrder::make()->action($order, ['collection_address_id' => $ceuta->id]);
        $order->refresh();
        expect($order->collection_address_id)->toBe($showroom->id)
            ->and($order->tax_category_id)->toBe($spanishVat->id)
            ->and((float)$order->tax_amount)->toBeGreaterThan(0.0)
            ->and($order->taxableDeliveryAddress(new \App\Models\Helpers\TaxNumber(['valid' => true]))->id)->toBe($ceuta->id);

        UpdateOrder::make()->action($order, ['collection_address_id' => null]);
        expect($order->refresh()->tax_category_id)->toBe(1);

        $order->updateQuietly(['state' => OrderStateEnum::FINALISED]);
        UpdateOrder::make()->action($order->refresh(), ['collection_address_id' => $ceuta->id]);
        ResetOrderTaxCategory::run($order->refresh());
        expect($order->refresh()->tax_category_id)->toBe(1);
    } finally {
        DB::rollBack();
    }
});

test('ticking collection on a delivery note stores the shop collection address, not the customer address', function () {
    DB::beginTransaction();
    try {
        $spain = Country::where('code', 'ES')->first();
        $this->organisation->forceFill(['country_id' => $spain->id])->save();
        $addressIn = fn (string $postalCode) => \App\Models\Helpers\Address::create(array_merge(
            \App\Models\Helpers\Address::factory()->definition(),
            ['group_id' => $this->shop->group_id, 'country_code' => 'ES', 'country_id' => $spain->id, 'postal_code' => $postalCode]
        ));
        $showroom = $addressIn('29004');
        $this->shop->update(['collection_address_id' => $showroom->id]);

        $customer = freshCustomerLike($this->shop, $this->customer);
        $order    = StoreOrder::make()->action($customer, Order::factory()->definition());
        StoreTransaction::make()->action($order, $this->product->currentHistoricProduct, Transaction::factory()->definition());
        $ceuta = $addressIn('51001');
        $order->update(['billing_address_id' => $ceuta->id, 'delivery_address_id' => $ceuta->id]);
        ResetOrderTaxCategory::run($order->refresh());
        SubmitOrder::make()->action($order->refresh());
        $deliveryNote = $order->refresh()->deliveryNotes()->first() ?? SendOrderToWarehouse::make()->action($order, []);
        expect($order->refresh()->tax_category_id)->toBe(1);

        \App\Actions\Dispatching\DeliveryNote\UpdateDeliveryNote::make()->action($deliveryNote, ['collection_address_id' => $ceuta->id]);

        expect($deliveryNote->refresh()->collection_address_id)->toBe($showroom->id);
    } finally {
        DB::rollBack();
    }
});

test('a held order goes to the warehouse once its address is put on it', function () {
    $customer = freshCustomerLike($this->shop, $this->customer);
    $order    = StoreOrder::make()->action($customer, Order::factory()->definition());
    StoreTransaction::make()->action($order, $this->product->currentHistoricProduct, Transaction::factory()->definition());
    SubmitOrder::make()->action($order);

    $order->refresh();
    $order->billingAddress->update(['address_line_1' => '', 'address_line_2' => '', 'locality' => '', 'postal_code' => '', 'administrative_area' => '']);
    $order->unsetRelation('billingAddress');
    payHeldOrder($order);

    expect(SendOrderToWarehouse::make()->action($order, []))->toBeNull()
        ->and($order->refresh()->state)->toEqual(OrderStateEnum::SUBMITTED);

    $realAddress = array_merge(\App\Models\Helpers\Address::factory()->definition(), ['address_line_1' => '31 Bradley Road']);

    /** A B2B order carries one address row for both sides, so it stays held until that row is real.
     * A dropshipping order has two different rows and each side is checked on its own. */
    UpdateOrderBillingAddress::make()->action($order, ['address' => $realAddress]);
    expect($order->refresh()->state)->toEqual(OrderStateEnum::SUBMITTED);

    UpdateOrderDeliveryAddress::make()->action($order, ['address' => $realAddress, 'update_parent' => false]);

    expect($order->refresh()->state)->toEqual(OrderStateEnum::IN_WAREHOUSE)
        ->and($order->deliveryNotes()->count())->toBe(1);
});

test('the warehouse can be sent an order without an address on purpose', function () {
    $customer = freshCustomerLike($this->shop, $this->customer);
    $order    = StoreOrder::make()->action($customer, Order::factory()->definition());
    StoreTransaction::make()->action($order, $this->product->currentHistoricProduct, Transaction::factory()->definition());
    SubmitOrder::make()->action($order);

    $order->refresh();
    $order->billingAddress->update(['address_line_1' => '', 'address_line_2' => '', 'locality' => '', 'postal_code' => '', 'administrative_area' => '']);
    $order->unsetRelation('billingAddress');
    $order->update(['pay_status' => OrderPayStatusEnum::PAID]);

    expect(SendOrderToWarehouse::make()->action($order, []))->toBeNull();

    $deliveryNote = SendOrderToWarehouse::make()->action($order, [], withoutAnAddress: true);

    expect($deliveryNote)->toBeInstanceOf(DeliveryNote::class)
        ->and($order->refresh()->state)->toEqual(OrderStateEnum::IN_WAREHOUSE);
});

test('send anyway only shows on an order missing an address', function () {
    $customer = freshCustomerLike($this->shop, $this->customer);
    $order    = StoreOrder::make()->action($customer, Order::factory()->definition());
    StoreTransaction::make()->action($order, $this->product->currentHistoricProduct, Transaction::factory()->definition());
    SubmitOrder::make()->action($order);
    $order->refresh();

    $hasSendAnyway = fn (Order $order) => collect(\App\Actions\Ordering\Order\UI\GetEcomOrderActions::run($order, true))
        ->contains(fn ($action) => ($action['key'] ?? null) === 'send-to-warehouse-without-an-address');

    expect($hasSendAnyway($order))->toBeFalse();

    $order->billingAddress->update(['address_line_1' => '', 'address_line_2' => '', 'locality' => '', 'postal_code' => '', 'administrative_area' => '']);
    $order->unsetRelation('billingAddress');

    expect($hasSendAnyway($order))->toBeTrue();
});

/** An address in the fixture customer's country and postcode, so the shop can price shipping for it */
function heldOrderAddressLike(\App\Models\CRM\Customer $customer, array $overrides = []): array
{
    return array_merge(
        $customer->address->only(['address_line_1', 'address_line_2', 'sorting_code', 'postal_code', 'dependent_locality', 'locality', 'administrative_area', 'country_code', 'country_id']),
        $overrides
    );
}

function freshCustomerLike(\App\Models\Catalogue\Shop $shop, \App\Models\CRM\Customer $template): \App\Models\CRM\Customer
{
    return \App\Actions\CRM\Customer\StoreCustomer::make()->action($shop, array_merge(
        \App\Models\CRM\Customer::factory()->definition(),
        ['contact_address' => heldOrderAddressLike($template, ['address_line_1' => fake()->unique()->streetAddress()])]
    ));
}

function orderForAFreshCustomerWithNoBillingAddress(\App\Models\Catalogue\Shop $shop, $historicAsset, \App\Models\CRM\Customer $template, bool $separateDeliveryAddress = false): Order
{
    $customer = freshCustomerLike($shop, $template);
    $order    = StoreOrder::make()->action($customer, Order::factory()->definition());
    StoreTransaction::make()->action($order, $historicAsset, Transaction::factory()->definition());

    if ($separateDeliveryAddress) {
        UpdateOrderDeliveryAddress::make()->action($order, [
            'address'       => heldOrderAddressLike($template, ['address_line_1' => '9 End Customer Road', 'address_line_2' => 'Unit '.fake()->unique()->numberBetween(1, 9999999)]),
            'update_parent' => false,
        ]);
        $order->refresh();
    }

    $order->billingAddress->update(['address_line_1' => '', 'address_line_2' => '', 'locality' => '', 'postal_code' => '', 'administrative_area' => '']);
    payHeldOrder($order);

    return $order->refresh();
}

/** Paid for real, because every totals recalculation reads pay_status from the payments; twice the total so a new address changing the tax keeps it paid */
function payHeldOrder(Order $order): void
{
    $paymentAccount = StoreOrgPaymentServiceProviderAccount::make()->action(
        $order->organisation,
        PaymentServiceProvider::where('type', PaymentServiceProviderTypeEnum::CASH->value)->first(),
        ['code' => 'HLD'.fake()->unique()->numberBetween(10000, 99999), 'name' => 'Held order cash']
    );

    PayOrder::make()->action($order, $paymentAccount, [
        'amount'    => round($order->refresh()->total_amount * 2, 2),
        'reference' => 'HELD-'.uniqid(),
        'status'    => PaymentStatusEnum::SUCCESS,
        'state'     => PaymentStateEnum::COMPLETED,
    ]);
}

test('a customer who pays with no address is never refused, the order is held instead', function () {
    $order = orderForAFreshCustomerWithNoBillingAddress($this->shop, $this->product->currentHistoricProduct, $this->customer);

    SubmitOrder::make()->action($order);
    $order->refresh();

    expect($order->state)->toEqual(OrderStateEnum::SUBMITTED)
        ->and($order->deliveryNotes()->count())->toBe(0)
        ->and($order->private_warehouse_note)->toContain(SendOrderToWarehouse::HELD_MARKER)
        ->and($order->private_warehouse_note)->toContain('billing address');
});

test('the held warning is written once and replaces an older wording, however often the order is retried', function () {
    $order = orderForAFreshCustomerWithNoBillingAddress($this->shop, $this->product->currentHistoricProduct, $this->customer);
    SubmitOrder::make()->action($order);

    $order->refresh()->update(['private_warehouse_note' => 'Keep this — ⚠️ Order held: the customer has no address, ask them for it before picking']);
    SendOrderToWarehouse::make()->action($order->refresh(), []);
    SendOrderToWarehouse::make()->action($order->refresh(), []);

    $note = (string)$order->refresh()->private_warehouse_note;

    expect(substr_count($note, SendOrderToWarehouse::HELD_MARKER))->toBe(1)
        ->and($note)->toStartWith('Keep this — ')
        ->and($note)->toContain('billing address is missing')
        ->and($note)->not->toContain('the customer has no address');
});

test('the held warning comes off cleanly and keeps what staff wrote', function () {
    expect(SendOrderToWarehouse::withoutHeldNote('Keep this — ⚠️ Order held: the customer has no address, ask them for it before picking'))->toBe('Keep this')
        ->and(SendOrderToWarehouse::withoutHeldNote("⚠️ Order held: the customer has no address, ask them for it before picking \nI send a ticket for it"))->toBe('I send a ticket for it')
        ->and(SendOrderToWarehouse::withoutHeldNote('A — ⚠️ Order held: the billing address is missing — B'))->toBe('A — B')
        ->and(SendOrderToWarehouse::withoutHeldNote('⚠️ Order held: the billing address is missing'))->toBeNull()
        ->and(SendOrderToWarehouse::withoutHeldNote(null))->toBeNull();
});

test('fixing the customer releases an order held on its billing address, and the warning never reaches the delivery note', function () {
    $order = orderForAFreshCustomerWithNoBillingAddress($this->shop, $this->product->currentHistoricProduct, $this->customer);
    SubmitOrder::make()->action($order);
    expect($order->refresh()->state)->toEqual(OrderStateEnum::SUBMITTED);

    \App\Actions\CRM\Customer\UpdateCustomer::make()->action($order->customer, [
        'contact_address' => heldOrderAddressLike($this->customer, ['address_line_1' => '1 Fixed Street']),
    ]);

    $order->refresh();
    $deliveryNote = $order->deliveryNotes()->first();

    expect($order->state)->toEqual(OrderStateEnum::IN_WAREHOUSE)
        ->and($deliveryNote)->toBeInstanceOf(DeliveryNote::class)
        ->and((string)$order->private_warehouse_note)->not->toContain(SendOrderToWarehouse::HELD_MARKER)
        ->and((string)$deliveryNote->private_warehouse_note)->not->toContain(SendOrderToWarehouse::HELD_MARKER);
});

test('an order with its own delivery address keeps it when the customer is fixed', function () {
    $order = orderForAFreshCustomerWithNoBillingAddress($this->shop, $this->product->currentHistoricProduct, $this->customer, separateDeliveryAddress: true);
    SubmitOrder::make()->action($order);
    $order->refresh();

    expect($order->state)->toEqual(OrderStateEnum::SUBMITTED)
        ->and($order->billing_address_id)->not->toBe($order->delivery_address_id);

    $deliveryAddressId = $order->delivery_address_id;

    \App\Actions\CRM\Customer\UpdateCustomer::make()->action($order->customer, [
        'contact_address' => heldOrderAddressLike($this->customer, ['address_line_1' => '1 Fixed Street']),
    ]);

    $order->refresh();

    expect($order->state)->toEqual(OrderStateEnum::IN_WAREHOUSE)
        ->and($order->delivery_address_id)->toBe($deliveryAddressId)
        ->and($order->deliveryAddress->address_line_1)->toBe('9 End Customer Road');
});

test('a submitted order whose street sits in the town box is left alone when the customer changes address', function () {
    $customer = freshCustomerLike($this->shop, $this->customer);
    $order    = StoreOrder::make()->action($customer, Order::factory()->definition());
    StoreTransaction::make()->action($order, $this->product->currentHistoricProduct, Transaction::factory()->definition());
    SubmitOrder::make()->action($order);

    $order->refresh();
    $order->billingAddress->update(['address_line_1' => '', 'locality' => 'Rear of 230 Church Lane']);
    $billingAddressId = $order->billing_address_id;

    \App\Actions\CRM\Customer\UpdateCustomer::make()->action($customer, [
        'contact_address' => heldOrderAddressLike($this->customer, ['address_line_1' => '1 Fixed Street']),
    ]);

    $order->refresh();

    expect($order->state)->toEqual(OrderStateEnum::SUBMITTED)
        ->and($order->billing_address_id)->toBe($billingAddressId)
        ->and($order->billingAddress->locality)->toBe('Rear of 230 Church Lane');
});

test('an address change on an order fetched from aurora never pushes it to the warehouse', function () {
    $order = orderForAFreshCustomerWithNoBillingAddress($this->shop, $this->product->currentHistoricProduct, $this->customer, separateDeliveryAddress: true);
    SubmitOrder::make()->action($order);
    $order->refresh()->update(['source_id' => '9990001']);

    \App\Actions\Ordering\Order\UpdateOrderFixedAddress::make()->action($order->refresh(), [
        'address' => new \App\Models\Helpers\Address(heldOrderAddressLike($this->customer, ['address_line_1' => '1 Fixed Street'])),
        'type'    => 'billing',
    ]);

    expect($order->refresh()->state)->toEqual(OrderStateEnum::SUBMITTED)
        ->and($order->deliveryNotes()->count())->toBe(0);
});

test('a decision to send without an address survives a later retry', function () {
    $order = orderForAFreshCustomerWithNoBillingAddress($this->shop, $this->product->currentHistoricProduct, $this->customer);
    SubmitOrder::make()->action($order);

    $order->refresh()->update(['private_warehouse_note' => SendOrderToWarehouse::SENT_WITHOUT_AN_ADDRESS_MARKER.', the customer could not be reached']);

    expect(SendOrderToWarehouse::make()->action($order->refresh(), []))->toBeInstanceOf(DeliveryNote::class)
        ->and($order->refresh()->state)->toEqual(OrderStateEnum::IN_WAREHOUSE);
});

/** A customer whose last delivered order went to one address while their default, never delivered to, is another */
function customerWithANeverDeliveredDefault(\App\Models\Catalogue\Shop $shop, \App\Models\CRM\Customer $template): array
{
    $customer = freshCustomerLike($shop, $template);

    $lastDelivered = StoreOrder::make()->action($customer, Order::factory()->definition());
    $deliveredTo = \App\Models\Helpers\Address::create(heldOrderAddressLike($template, [
        'address_line_1' => '19 Periwinkle Gardens '.fake()->unique()->numberBetween(1, 9999999),
        'postal_code'    => 'NN14 2AH',
        'group_id'       => $lastDelivered->group_id,
    ]));
    $lastDelivered->forceFill(['state' => OrderStateEnum::DISPATCHED, 'delivery_address_id' => $deliveredTo->id])->saveQuietly();

    $basket = StoreOrder::make()->action($customer->refresh(), Order::factory()->definition());

    return [$customer, $lastDelivered->refresh(), $basket->refresh()];
}

test('an order going to a default that never received a delivery shows where the last order went', function () {
    [, $lastDelivered, $basket] = customerWithANeverDeliveredDefault($this->shop, $this->customer);
    $warning = \App\Actions\Ordering\Order\UI\GetEarlierDeliveryAddressWarning::class;

    $forCustomer = $warning::run($basket, withCustomerActions: true);
    $forStaff    = $warning::run($basket);

    expect($forCustomer)->not->toBeNull()
        ->and($forCustomer['previous_order_reference'])->toBe($lastDelivered->reference)
        ->and($forCustomer['previous_address'])->toContain('19 Periwinkle Gardens')
        ->and($forCustomer['confirmed'])->toBeFalse()
        ->and($forCustomer['actions']['confirm_route']['name'])->toBe('retina.models.order.delivery_address_confirm')
        ->and($forCustomer['actions']['use_previous_route']['name'])->toBe('retina.models.order.delivery_address_use_previous')
        ->and($forStaff['actions'])->toBeNull()
        ->and(\App\Actions\Ordering\Order\UI\GetOrderDeliveryAddressManagement::run($basket)['addresses']['earlier_delivery_address'])->toBe($forStaff);
});

test('no earlier address note when the address has been delivered to, is not the default, or is a collection', function () {
    $warning = fn (Order $order) => \App\Actions\Ordering\Order\UI\GetEarlierDeliveryAddressWarning::run($order->refresh());

    /** A parcel already reached this address once, so it is a real address of theirs */
    [$customer, , $basket] = customerWithANeverDeliveredDefault($this->shop, $this->customer);
    $older = StoreOrder::make()->action($customer, Order::factory()->definition());
    $older->forceFill(['state' => OrderStateEnum::DISPATCHED, 'delivery_address_id' => $basket->delivery_address_id, 'created_at' => now()->subYear()])->saveQuietly();
    expect($warning($basket))->toBeNull();

    /** The customer typed a different address on this order, so it is their choice, not a stale default */
    [, , $basket] = customerWithANeverDeliveredDefault($this->shop, $this->customer);
    UpdateOrderDeliveryAddress::make()->action($basket, ['address' => heldOrderAddressLike($this->customer, ['address_line_1' => 'Chosen on this order '.fake()->unique()->numberBetween(1, 9999999)])]);
    expect($warning($basket))->toBeNull();

    /** A collection is not delivered anywhere */
    [, , $basket] = customerWithANeverDeliveredDefault($this->shop, $this->customer);
    $basket->forceFill(['collection_address_id' => $basket->delivery_address_id])->saveQuietly();
    expect($warning($basket))->toBeNull();
});

test('a customer confirming the address hides the note from them and tells staff', function () {
    [, , $basket] = customerWithANeverDeliveredDefault($this->shop, $this->customer);

    \App\Actions\Retina\Ordering\ConfirmRetinaOrderDeliveryAddress::make()->handle($basket);

    $forCustomer = \App\Actions\Ordering\Order\UI\GetEarlierDeliveryAddressWarning::run($basket->refresh(), withCustomerActions: true);

    expect($forCustomer['confirmed'])->toBeTrue()
        ->and($forCustomer['actions'])->toBeNull();
});

test('a customer can send the order to the address their last order went to in one step', function () {
    [, , $basket] = customerWithANeverDeliveredDefault($this->shop, $this->customer);

    \App\Actions\Retina\Ordering\UseRetinaOrderPreviousDeliveryAddress::make()->handle($basket);
    $basket->refresh();

    expect($basket->deliveryAddress->address_line_1)->toStartWith('19 Periwinkle Gardens')
        ->and($basket->deliveryAddress->postal_code)->toBe('NN14 2AH')
        ->and(\App\Actions\Ordering\Order\UI\GetEarlierDeliveryAddressWarning::run($basket, withCustomerActions: true))->toBeNull();
});

test('retina basket lines resolve their webpage and image without a query per line', function () {
    createWebsite($this->shop);
    $basket   = StoreOrder::make()->action($this->customer, Order::factory()->definition());
    $webpages = [];
    [, $bulk] = createProduct($this->shop);
    foreach (range(1, 3) as $quantity) {
        $product = StoreProduct::make()->action($bulk->family, array_merge(
            Product::factory()->definition(),
            ['trade_units' => [['id' => $bulk->tradeUnits->first()->id, 'quantity' => 1]], 'price' => 2]
        ));
        $webpages[$product->code] = StoreProductWebpage::make()->action($product);
        DB::table('webpages')->insert(array_merge(
            Arr::except($webpages[$product->code]->getAttributes(), ['id']),
            ['url' => $webpages[$product->code]->url.'-old', 'slug' => $webpages[$product->code]->slug.'-old', 'state' => 'closed']
        ));
        $transactionData                 = Transaction::factory()->definition();
        $transactionData['quantity_ordered'] = $quantity;
        $transactionData['order_id']         = $basket->id;
        StoreTransaction::make()->action($basket, $product->currentHistoricProduct, $transactionData);
    }

    $fakeRoute = new \Illuminate\Routing\Route('GET', '/fake-retina-basket', []);
    $fakeRoute->name('retina.ecom.basket.show');
    app('request')->setRouteResolver(fn () => $fakeRoute);

    $lines = IndexBasketTransactions::run($basket->fresh());

    DB::enableQueryLog();
    DB::flushQueryLog();
    $rows = RetinaEcomBasketTransactionsResources::collection($lines)->resolve();
    $queriesWhileResolving = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($lines->total())->toBe(3)
        ->and($rows)->toHaveCount(3)
        ->and($queriesWhileResolving)->toBe(0);

    foreach ($rows as $row) {
        $webpage = $webpages[$row['asset_code']];
        expect($row['webpage_url'])->toBe($webpage->canonical_url)
            ->and($row['image'])->toBeNull();
    }
});

test('a basket line may exceed stock, is zeroed while out of stock and restored when back, never blocking the order', function () {
    $basket = StoreOrder::make()->action($this->customer, Order::factory()->definition());
    [, $bulk] = createProduct($this->shop);
    $lowStock = StoreProduct::make()->action($bulk->family, array_merge(
        Product::factory()->definition(),
        ['trade_units' => [['id' => $bulk->tradeUnits->first()->id, 'quantity' => 1]], 'price' => 2]
    ));
    $lowStock->update(['status' => ProductStatusEnum::FOR_SALE]);
    $lowStockData                     = Transaction::factory()->definition();
    $lowStockData['quantity_ordered'] = 3;
    $lowStockData['order_id']         = $basket->id;
    $lowStockLine                     = StoreTransaction::make()->action($basket, $lowStock->currentHistoricProduct, $lowStockData);
    DB::table('products')->where('id', $lowStock->id)->update(['available_quantity' => 5]);

    $lowStockLine = RetinaEcomUpdateTransaction::make()->action($lowStockLine, $this->customer, ['quantity_ordered' => 7]);
    expect((float) $lowStockLine->quantity_ordered)->toBe(7.0);

    $issues = fn () => (new class () {
        use \App\Actions\Traits\WithBasketStockIssues;

        public function issues(Order $order): array
        {
            return $this->getBasketStockIssues($order);
        }
    })->issues($basket->fresh());

    expect($issues()['low_stock'])->toHaveCount(1)
        ->and($issues()['low_stock'][0]['code'])->toBe($lowStock->code)
        ->and($issues()['low_stock'][0]['quantity_ordered'])->toBe(7.0)
        ->and($issues()['low_stock'][0]['available_quantity'])->toBe(5.0)
        ->and($issues()['out_of_stock'])->toBe([]);

    DB::table('products')->where('id', $lowStock->id)->update(['available_quantity' => 0]);

    SyncBasketLinesWithProductStock::run($lowStock->fresh());
    $lowStockLine = $lowStockLine->fresh();

    expect((float) $lowStockLine->quantity_ordered)->toBe(0.0)
        ->and((float) $lowStockLine->net_amount)->toBe(0.0)
        ->and((float) $lowStockLine->estimated_weight)->toBe(0.0)
        ->and((float) $basket->fresh()->goods_amount)->toBe(0.0)
        ->and((float) Arr::get($lowStockLine->data, SyncBasketLinesWithProductStock::HELD_QUANTITY_KEY))->toBe(7.0)
        ->and($issues()['low_stock'])->toBe([])
        ->and($issues()['out_of_stock'][0]['code'])->toBe($lowStock->code)
        ->and($issues()['out_of_stock'][0]['held_quantity'])->toBe(7.0);

    DB::table('products')->where('id', $lowStock->id)->update(['available_quantity' => 5]);
    SyncBasketLinesWithProductStock::run($lowStock->fresh());
    $lowStockLine = $lowStockLine->fresh();

    expect((float) $lowStockLine->quantity_ordered)->toBe(7.0)
        ->and((float) $lowStockLine->net_amount)->toBe(14.0)
        ->and((float) $basket->fresh()->goods_amount)->toBe(14.0)
        ->and(Arr::get($lowStockLine->data, SyncBasketLinesWithProductStock::HELD_QUANTITY_KEY))->toBeNull()
        ->and($issues()['low_stock'][0]['held_quantity'])->toBe(0.0);

    DB::table('products')->where('id', $lowStock->id)->update(['available_quantity' => 0]);
    SyncBasketLinesWithProductStock::run($lowStock->fresh());
    expect(fn () => RetinaEcomUpdateTransaction::make()->action($lowStockLine->fresh(), $this->customer, ['quantity_ordered' => 3]))
        ->toThrow(ValidationException::class)
        ->and((float) Arr::get($lowStockLine->fresh()->data, SyncBasketLinesWithProductStock::HELD_QUANTITY_KEY))->toBe(7.0);

    DB::table('products')->where('id', $lowStock->id)->update(['available_quantity' => 2]);
    $lowStockLine = RetinaEcomUpdateTransaction::make()->action($lowStockLine->fresh(), $this->customer, ['quantity_ordered' => 3]);

    expect((float) $lowStockLine->quantity_ordered)->toBe(3.0)
        ->and(Arr::get($lowStockLine->fresh()->data, SyncBasketLinesWithProductStock::HELD_QUANTITY_KEY))->toBeNull();

    $goneProduct = StoreProduct::make()->action($bulk->family, array_merge(
        Product::factory()->definition(),
        ['trade_units' => [['id' => $bulk->tradeUnits->first()->id, 'quantity' => 1]], 'price' => 2]
    ));
    $goneData                     = Transaction::factory()->definition();
    $goneData['quantity_ordered'] = 4;
    $goneData['order_id']         = $basket->id;
    $goneLine                     = StoreTransaction::make()->action($basket, $goneProduct->currentHistoricProduct, $goneData);
    DB::table('products')->where('id', $goneProduct->id)->update(['available_quantity' => 0]);
    SyncBasketLinesWithProductStock::run($goneProduct->fresh());

    expect($basket->fresh()->stats->number_item_transactions)->toBe(1);

    $submitted = SubmitOrder::run($basket->fresh());

    expect($submitted->state)->toBe(OrderStateEnum::SUBMITTED)
        ->and($goneLine->fresh()->trashed())->toBeTrue()
        ->and($submitted->transactions()->where('model_type', 'Product')->count())->toBe(1);
});

test('a customer cannot raise a basket line of an out of stock product, nor change an order after it is submitted', function () {
    $website = createWebsite($this->shop);
    $website->update(['status' => true]);
    $webUser = createWebUser($this->customer);
    $basket  = StoreOrder::make()->action($this->customer, Order::factory()->definition());
    [, $bulk] = createProduct($this->shop);
    $product  = StoreProduct::make()->action($bulk->family, array_merge(
        Product::factory()->definition(),
        ['trade_units' => [['id' => $bulk->tradeUnits->first()->id, 'quantity' => 1]], 'price' => 2]
    ));
    $product->update(['status' => ProductStatusEnum::FOR_SALE]);
    $lineData                     = Transaction::factory()->definition();
    $lineData['quantity_ordered'] = 3;
    $lineData['order_id']         = $basket->id;
    $line                         = StoreTransaction::make()->action($basket, $product->currentHistoricProduct, $lineData);
    $url                          = 'http://'.$website->domain.'/app/models/transaction/'.$line->id;

    DB::table('products')->where('id', $product->id)->update(['available_quantity' => 2, 'is_on_demand' => false]);
    $this->actingAs($webUser, 'retina')->patch($url, ['quantity_ordered' => 5])->assertSessionHasNoErrors();
    expect((float) $line->fresh()->quantity_ordered)->toBe(5.0);

    DB::table('products')->where('id', $product->id)->update(['available_quantity' => 0]);
    $this->actingAs($webUser, 'retina')->patch($url, ['quantity_ordered' => 6])->assertSessionHasErrors('message');
    $this->actingAs($webUser, 'retina')->patch($url, ['units_ordered' => 60])->assertSessionHasNoErrors();
    expect((float) $line->fresh()->quantity_ordered)->toBe(5.0);

    $otherShop = $this->shop->replicate(['ulid'])->fill(['code' => 'oth'.$line->id, 'slug' => 'oth-'.$line->id]);
    $otherShop->saveQuietly();
    $otherShopProduct = StoreProduct::make()->action($bulk->family, array_merge(
        Product::factory()->definition(),
        ['trade_units' => [['id' => $bulk->tradeUnits->first()->id, 'quantity' => 1]], 'price' => 2]
    ));
    DB::table('products')->where('id', $otherShopProduct->id)->update(['shop_id' => $otherShop->id, 'status' => ProductStatusEnum::FOR_SALE->value, 'available_quantity' => 10]);
    expect($otherShopProduct->fresh()->shop_id)->not->toBeNull()->not->toBe($this->shop->id)
        ->and(fn () => StoreRetinaTransaction::run($basket, ['historic_asset_id' => $otherShopProduct->current_historic_asset_id, 'quantity' => 1]))
        ->toThrow(ValidationException::class);

    $csv = tempnam(sys_get_temp_dir(), 'order-transactions').'.csv';
    file_put_contents($csv, "code,quantity\n".$product->code.",9\n");
    $upload = ImportTransactionInOrder::make()->action($basket, ['file' => new \Illuminate\Http\UploadedFile($csv, 'transactions.csv', 'text/csv', null, true)], byCustomer: true);
    expect($upload->number_fails)->toBe(1)
        ->and((float) $line->fresh()->quantity_ordered)->toBe(5.0);

    $upload = ImportTransactionInOrder::make()->action($basket, ['file' => new \Illuminate\Http\UploadedFile($csv, 'transactions.csv', 'text/csv', null, true)]);
    expect($upload->number_success)->toBe(1)
        ->and((float) $line->fresh()->quantity_ordered)->toBe(9.0);
    UpdateTransaction::make()->action($line->fresh(), ['quantity_ordered' => 5]);

    $this->actingAs($webUser, 'retina')->patch($url, ['quantity_ordered' => 4.5])->assertSessionHasErrors('quantity_ordered');
    DB::table('transactions')->where('id', $line->id)->update(['is_gift' => true]);
    $this->actingAs($webUser, 'retina')->patch($url, ['quantity_ordered' => 4])->assertSessionHasErrors('message');
    DB::table('transactions')->where('id', $line->id)->update(['is_gift' => false]);
    expect((float) $line->fresh()->quantity_ordered)->toBe(5.0);

    $this->actingAs($webUser, 'retina')
        ->postJson('http://'.$website->domain.'/models/'.$line->id.'/update-transaction', ['quantity_ordered' => 6])
        ->assertStatus(422)
        ->assertJsonValidationErrors('message');
    expect(fn () => UpdateRetinaTransaction::run($line->fresh(), ['quantity_ordered' => 6]))->toThrow(ValidationException::class)
        ->and((float) $line->fresh()->quantity_ordered)->toBe(5.0);

    $this->customer->update(['current_order_in_basket_id' => $basket->id]);
    $product->update(['status' => ProductStatusEnum::OUT_OF_STOCK]);
    StoreEcomBasketTransaction::make()->handle($this->customer->fresh(), $product->fresh(), ['quantity' => 4]);
    expect((float) $line->fresh()->quantity_ordered)->toBe(4.0);

    DB::table('products')->where('id', $product->id)->update(['is_on_demand' => true]);
    $this->actingAs($webUser, 'retina')->patch($url, ['quantity_ordered' => 6])->assertSessionHasNoErrors();
    expect((float) $line->fresh()->quantity_ordered)->toBe(6.0);

    $basket->update(['state' => OrderStateEnum::SUBMITTED]);
    $this->actingAs($webUser, 'retina')->patch($url, ['quantity_ordered' => 1])->assertSessionHasErrors('message');
    expect((float) $line->fresh()->quantity_ordered)->toBe(6.0)
        ->and(fn () => StoreRetinaTransaction::run($basket->fresh(), ['historic_asset_id' => $product->current_historic_asset_id, 'quantity' => 1]))
        ->toThrow(ValidationException::class)
        ->and(fn () => ImportRetinaOrderTransaction::run($basket->fresh(), []))
        ->toThrow(ValidationException::class);

    $this->actingAs($webUser, 'retina')
        ->post('http://'.$website->domain.'/app/models/order/'.$basket->id.'/transaction/add', ['historic_asset_id' => $product->current_historic_asset_id])
        ->assertSessionHasErrors('message');
    expect((float) $line->fresh()->quantity_ordered)->toBe(6.0);
});

test('a product that is not for sale cannot be added to a basket', function () {
    $basket = StoreOrder::make()->action($this->customer, Order::factory()->definition());
    $this->customer->update(['current_order_in_basket_id' => $basket->id]);
    [, $bulk] = createProduct($this->shop);
    $product  = StoreProduct::make()->action($bulk->family, array_merge(
        Product::factory()->definition(),
        ['trade_units' => [['id' => $bulk->tradeUnits->first()->id, 'quantity' => 1]], 'price' => 2]
    ));

    $product->update(['status' => ProductStatusEnum::FOR_SALE]);
    $line = StoreEcomBasketTransaction::make()->handle($this->customer->fresh(), $product->fresh(), ['quantity' => 2]);
    expect((float) $line->quantity_ordered)->toBe(2.0);

    foreach ([ProductStatusEnum::OUT_OF_STOCK, ProductStatusEnum::COMING_SOON, ProductStatusEnum::DISCONTINUED, ProductStatusEnum::NOT_FOR_SALE] as $status) {
        $product->update(['status' => $status]);
        expect(fn () => StoreEcomBasketTransaction::make()->handle($this->customer->fresh(), $product->fresh(), ['quantity' => 3]))
            ->toThrow(ValidationException::class);
    }
});

test('products added from a stale customer after the basket was deleted all land in one new basket', function () {
    $this->customer->update(['current_order_in_basket_id' => null]);
    $staleCustomer = $this->customer->fresh();
    [, $bulk] = createProduct($this->shop);
    [$firstProduct, $secondProduct] = collect([1, 2])->map(fn () => tap(StoreProduct::make()->action($bulk->family, array_merge(
        Product::factory()->definition(),
        ['trade_units' => [['id' => $bulk->tradeUnits->first()->id, 'quantity' => 1]], 'price' => 2]
    )))->update(['status' => ProductStatusEnum::FOR_SALE]))->all();

    $firstLine  = StoreEcomBasketTransaction::make()->handle($this->customer->fresh(), $firstProduct->fresh(), ['quantity' => 1]);
    $secondLine = StoreEcomBasketTransaction::make()->handle($staleCustomer, $secondProduct->fresh(), ['quantity' => 1]);

    expect($secondLine->order_id)->toBe($firstLine->order_id)
        ->and($this->customer->fresh()->current_order_in_basket_id)->toBe($firstLine->order_id);
});

test('an exclusive product can be added only by its own customer, and only while in stock', function () {
    $owner = StoreCustomer::make()->action($this->shop, Customer::factory()->definition());
    $other = StoreCustomer::make()->action($this->shop, Customer::factory()->definition());
    foreach ([$owner, $other] as $customer) {
        $basket = StoreOrder::make()->action($customer, Order::factory()->definition());
        $customer->update(['current_order_in_basket_id' => $basket->id]);
    }
    [, $bulk]  = createProduct($this->shop);
    $exclusive = StoreProduct::make()->action($bulk->family, array_merge(
        Product::factory()->definition(),
        ['trade_units' => [['id' => $bulk->tradeUnits->first()->id, 'quantity' => 1]], 'price' => 2]
    ));
    $exclusive->update(['status' => ProductStatusEnum::NOT_FOR_SALE, 'is_for_sale' => false, 'exclusive_for_customer_id' => $owner->id]);
    $exclusive->exclusiveCustomers()->attach($owner->id);
    DB::table('products')->where('id', $exclusive->id)->update(['available_quantity' => 10]);

    $line = StoreEcomBasketTransaction::make()->handle($owner->fresh(), $exclusive->fresh(), ['quantity' => 2]);
    expect((float) $line->quantity_ordered)->toBe(2.0)
        ->and(fn () => StoreEcomBasketTransaction::make()->handle($other->fresh(), $exclusive->fresh(), ['quantity' => 2]))
        ->toThrow(ValidationException::class);

    DB::table('products')->where('id', $exclusive->id)->update(['available_quantity' => 0]);
    expect(fn () => StoreEcomBasketTransaction::make()->handle($owner->fresh(), $exclusive->fresh(), ['quantity' => 3]))
        ->toThrow(ValidationException::class);

    DB::table('products')->where('id', $exclusive->id)->update(['is_on_demand' => true]);
    $line = StoreEcomBasketTransaction::make()->handle($owner->fresh(), $exclusive->fresh(), ['quantity' => 4]);
    SyncBasketLinesWithProductStock::run($exclusive->fresh());
    expect((float) $line->fresh()->quantity_ordered)->toBe(4.0);
});

test('discontinued products are removed from baskets only when run live, submitted orders keep them', function () {
    [, $bulk]     = createProduct($this->shop);
    $discontinued = StoreProduct::make()->action($bulk->family, array_merge(
        Product::factory()->definition(),
        ['trade_units' => [['id' => $bulk->tradeUnits->first()->id, 'quantity' => 1]], 'price' => 2]
    ));

    $addLine = function (Order $order, Product $product) {
        $data             = Transaction::factory()->definition();
        $data['order_id'] = $order->id;

        return StoreTransaction::make()->action($order, $product->currentHistoricProduct, $data);
    };

    $basket = StoreOrder::make()->action($this->customer, Order::factory()->definition());
    $discontinuedLine = $addLine($basket, $discontinued);
    $keptLine         = $addLine($basket, $this->product);

    $submitted = StoreOrder::make()->action($this->customer, Order::factory()->definition());
    $submittedLine = $addLine($submitted, $discontinued);
    $submitted->update(['state' => OrderStateEnum::SUBMITTED]);

    $discontinued->update(['state' => ProductStateEnum::DISCONTINUED]);

    expect(RemoveDiscontinuedProductsFromBaskets::run($this->shop, false))->toBe(1)
        ->and(Transaction::find($discontinuedLine->id))->not->toBeNull();

    expect(RemoveDiscontinuedProductsFromBaskets::run($this->shop, true))->toBe(1)
        ->and(Transaction::find($discontinuedLine->id))->toBeNull()
        ->and(Transaction::find($keptLine->id))->not->toBeNull()
        ->and(Transaction::find($submittedLine->id))->not->toBeNull()
        ->and(RemoveDiscontinuedProductsFromBaskets::run($this->shop, false))->toBe(0);
});

test('a packed order shipped by us offers the invoice button once the packer recorded parcels', function () {
    $customer = freshCustomerLike($this->shop, $this->customer);
    $order    = StoreOrder::make()->action($customer, Order::factory()->definition());
    StoreTransaction::make()->action($order, $this->product->currentHistoricProduct, Transaction::factory()->definition());
    SubmitOrder::make()->action($order);
    $order->refresh();
    $order->update(['pay_status' => OrderPayStatusEnum::PAID]);

    $deliveryNote = SendOrderToWarehouse::make()->action($order, []);
    $order->refresh()->update(['state' => OrderStateEnum::PACKED, 'is_shipping_by_external' => false]);

    $hasInvoice = fn (Order $order) => collect(\App\Actions\Ordering\Order\UI\GetEcomOrderActions::run($order, true))
        ->contains(fn ($action) => ($action['key'] ?? null) === 'action');

    expect($hasInvoice($order->fresh()))->toBeFalse();

    $deliveryNote->update(['parcels' => [['weight' => 11.01, 'dimensions' => [39, 39, 57]]]]);

    expect($hasInvoice($order->fresh()))->toBeTrue();

    /** Export orders are invoiced before the carrier label exists: the note waits packed, then finalises without invoicing twice */
    $order = FinaliseOrder::make()->action($order->fresh());
    expect($order->state)->toBe(OrderStateEnum::FINALISED)
        ->and($deliveryNote->fresh()->state)->not->toBe(DeliveryNoteStateEnum::FINALISED);

    $shipper = StoreShipper::make()->action($order->organisation, ['code' => 'exp3220', 'name' => 'exp3220', 'trade_as' => 'exp3220']);
    StoreShipment::make()->action($deliveryNote->fresh(), $shipper, ['reference' => 'exp3220', 'tracking' => 'exp3220']);

    $deliveryNote = \App\Actions\Dispatching\DeliveryNote\UpdateState\FinaliseDeliveryNote::make()->action($deliveryNote->fresh());
    expect($deliveryNote->state)->toBe(DeliveryNoteStateEnum::FINALISED)
        ->and($order->invoices()->count())->toBe(1);
});

test('the shop orders list flags a partner order and the channel filter separates it from direct ones', function () {
    $adminGuest = createAdminGuest($this->group);
    actingAs($adminGuest->getUser());

    /** The orders index only lists open shops, the fixture shop is still in process */
    $this->shop->update(['state' => ShopStateEnum::OPEN]);

    $intercompany = SalesChannel::where('group_id', $this->group->id)->where('code', 'intercompany')->first()
        ?? StoreSalesChannel::make()->action($this->group, [
            'code' => 'intercompany',
            'name' => 'Intercompany',
            'type' => SalesChannelTypeEnum::OTHER,
        ]);

    $partnerOrder = StoreOrder::make()->action(
        freshCustomerLike($this->shop, $this->customer),
        [...Order::factory()->definition(), 'sales_channel_id' => $intercompany->id]
    );
    $directOrder = StoreOrder::make()->action(
        freshCustomerLike($this->shop, $this->customer),
        Order::factory()->definition()
    );

    $url = route('grp.org.shops.show.ordering.orders.index', [
        'organisation' => $this->organisation->slug,
        'shop'         => $this->shop->slug,
    ]);

    $flagsIn = function (string $query) use ($url) {
        $response = get($url.$query);
        $response->assertOk();

        return collect($response->viewData('page')['props']['data']['data'])
            ->pluck('is_intercompany', 'reference');
    };

    $unfiltered = $flagsIn('');
    expect($unfiltered->get($partnerOrder->reference))->toBeTrue()
        ->and($unfiltered->get($directOrder->reference))->toBeFalse();

    $partnerOnly = $flagsIn('?orders_elements[channel]=partner');
    expect($partnerOnly->get($partnerOrder->reference))->toBeTrue()
        ->and($partnerOnly->has($directOrder->reference))->toBeFalse();

    $directOnly = $flagsIn('?orders_elements[channel]=direct');
    expect($directOnly->get($directOrder->reference))->toBeFalse()
        ->and($directOnly->has($partnerOrder->reference))->toBeFalse();
});

test('the shop orders list sends the warehouse note so its icon shows next to the order', function () {
    $adminGuest = createAdminGuest($this->group);
    actingAs($adminGuest->getUser());

    $this->shop->update(['state' => ShopStateEnum::OPEN]);

    $order = StoreOrder::make()->action(freshCustomerLike($this->shop, $this->customer), Order::factory()->definition());
    $order->updateQuietly(['private_warehouse_note' => 'Fragile, pack twice']);

    $response = get(route('grp.org.shops.show.ordering.orders.index', [
        'organisation' => $this->organisation->slug,
        'shop'         => $this->shop->slug,
    ]));
    $response->assertOk();

    $warehouseNotes = collect($response->viewData('page')['props']['data']['data'])
        ->pluck('private_warehouse_note', 'reference');

    expect($warehouseNotes->get($order->reference))->toBe('Fragile, pack twice');
});

test('org and group amounts of orders and invoices use the whole exchange rate', function () {
    $orgExchange = 0.0025371234;
    $grpExchange = 0.0021456789;

    $modelData = Order::factory()->definition();
    data_set($modelData, 'billing_address', new Address(Address::factory()->definition()));
    data_set($modelData, 'delivery_address', new Address(Address::factory()->definition()));
    $order = StoreOrder::make()->action($this->customer, $modelData);

    $transaction = StoreTransaction::make()->action($order, $this->product->historicAsset, Transaction::factory()->definition());
    $order->transactions()->whereNot('id', $transaction->id)->delete();
    $transaction->updateQuietly(['gross_amount' => 1000000, 'net_amount' => 1000000]);
    $order->updateQuietly(['amount_off' => 0, 'org_exchange' => $orgExchange, 'grp_exchange' => $grpExchange]);

    CalculateOrderTotalAmounts::make()->handle($order->refresh(), false, false);
    $order->refresh();

    expect($order->org_exchange)->toBe('0.0025371234')
        ->and($order->grp_exchange)->toBe('0.0021456789')
        ->and((float) $order->net_amount)->toBe(1000000.0)
        ->and((float) $order->org_net_amount)->toBe(2537.12)
        ->and((float) $order->grp_net_amount)->toBe(2145.68);

    $invoiceData = Invoice::factory()->definition();
    data_set($invoiceData, 'billing_address', new Address(Address::factory()->definition()));
    $invoice = StoreInvoice::make()->action($order, $invoiceData);
    StoreInvoiceTransaction::make()->action($invoice, $transaction, [
        'date'            => now(),
        'tax_category_id' => $transaction->tax_category_id,
        'quantity'        => 1,
        'gross_amount'    => 1000000,
        'net_amount'      => 1000000,
    ]);
    $invoice->updateQuietly(['amount_off' => 0, 'org_exchange' => $orgExchange, 'grp_exchange' => $grpExchange]);

    \App\Actions\Accounting\Invoice\CalculateInvoiceTotals::run($invoice->refresh());
    $invoice->refresh();

    expect((float) $invoice->net_amount)->toBe(1000000.0)
        ->and((float) $invoice->org_net_amount)->toBe(2537.12)
        ->and((float) $invoice->grp_net_amount)->toBe(2145.68);

    $transaction->updateQuietly(['quantity_ordered' => 100000, 'org_exchange' => $orgExchange, 'grp_exchange' => $grpExchange]);
    $order->updateQuietly(['state' => OrderStateEnum::PACKED]);

    UpdateOrderStateToHandling::make()->action($order->refresh());
    $transaction->refresh();

    expect((float) $transaction->net_amount)->toBeGreaterThan(0.0)
        ->and((float) $transaction->grp_net_amount)->toBe(round((float) $transaction->net_amount * $grpExchange, 2))
        ->and((float) $transaction->org_net_amount)->toBe(round((float) $transaction->net_amount * $orgExchange, 2));
});

test('b2b dashboard insights show the customer order overview, their regular products, favourites and when each is due again', function () {
    $customer = freshCustomerLike($this->shop, $this->customer);
    [, $product] = createProduct($this->shop);
    $product->update(['status' => ProductStatusEnum::FOR_SALE, 'price' => 10, 'available_quantity' => 1000]);

    foreach ([30, 10] as $daysAgo) {
        $order = StoreOrder::make()->action($customer, Order::factory()->definition());
        StoreTransaction::make()->action($order, $product->currentHistoricProduct, array_merge(Transaction::factory()->definition(), ['quantity_ordered' => 4]));
        $order->update(['state' => OrderStateEnum::DISPATCHED, 'date' => now()->subDays($daysAgo), 'net_amount' => 40]);
    }
    \App\Actions\CRM\Favourite\StoreFavourite::make()->action($customer, $product, []);

    $insights = \App\Actions\Retina\UI\Dashboard\GetRetinaB2BDashboardInsights::run($customer->fresh());
    $regular  = collect($insights['regulars'])->firstWhere('id', $product->id);

    expect($insights['kpis']['orders'])->toBe(2)
        ->and($insights['kpis']['total_orders'])->toBe(2)
        ->and($insights['kpis']['average_order'])->toEqual(40)
        ->and($insights['kpis']['days_since_last'])->toBe(10)
        ->and($insights)->not->toHaveKey('monthly')
        ->and($insights['recent_orders'])->toHaveCount(2)
        ->and($insights['recent_orders'][0])->toHaveKey('invoice')
        ->and(collect($insights['favourites'])->pluck('id')->all())->toBe([$product->id])
        ->and($insights['favourites'][0]['stock_status'])->toBe('in_stock')
        ->and($insights['favourites'][0]['has_reminder'])->toBeFalse()
        ->and($regular['orders'])->toBe(2)
        ->and($regular['average_quantity'])->toBe(4)
        ->and($regular['reorder_every_days'])->toBe(20)
        ->and($regular['days_until_due'])->toBe(10)
        ->and($regular['stock_status'])->toBe('in_stock')
        ->and($regular['is_purchasable'])->toBeTrue()
        ->and($regular['is_on_demand'])->toBeFalse()
        ->and($regular['has_reminder'])->toBeFalse()
        ->and($insights['recent_orders'][0]['date'])->toBe(now()->subDays(10)->toDateString())
        ->and(collect($insights['recommendations'])->every(fn ($product) => array_key_exists('quantity_in_basket', $product)))->toBeTrue();
});

test('b2b dashboard insights work for a customer who never ordered and for one who stopped ordering', function () {
    $newCustomer = freshCustomerLike($this->shop, $this->customer);
    $newInsights = \App\Actions\Retina\UI\Dashboard\GetRetinaB2BDashboardInsights::run($newCustomer);

    expect($newInsights['kpis']['orders'])->toBe(0)
        ->and($newInsights['kpis']['total_orders'])->toBe(0)
        ->and($newInsights['kpis']['average_order'])->toBeNull()
        ->and($newInsights['favourites'])->toBe([])
        ->and($newInsights['kpis']['last_order_at'])->toBeNull()
        ->and($newInsights['kpis']['is_lapsed'])->toBeFalse()
        ->and($newInsights['regulars'])->toBe([])
        ->and($newInsights['recent_orders'])->toBe([])
        ->and($newInsights['recommendations_source'])->toBe('shop_best_sellers');

    $lostCustomer = freshCustomerLike($this->shop, $this->customer);
    [, $product] = createProduct($this->shop);
    $product->update(['status' => ProductStatusEnum::FOR_SALE, 'price' => 10, 'available_quantity' => 1000]);
    $order = StoreOrder::make()->action($lostCustomer, Order::factory()->definition());
    StoreTransaction::make()->action($order, $product->currentHistoricProduct, Transaction::factory()->definition());
    $order->update(['state' => OrderStateEnum::DISPATCHED, 'date' => now()->subDays(900), 'net_amount' => 50]);

    $lostInsights = \App\Actions\Retina\UI\Dashboard\GetRetinaB2BDashboardInsights::run($lostCustomer->fresh());

    expect($lostInsights['kpis']['orders'])->toBe(0)
        ->and($lostInsights['kpis']['total_orders'])->toBe(1)
        ->and($lostInsights['kpis']['average_order'])->toEqual(50)
        ->and($lostInsights['kpis']['days_since_last'])->toBe(900)
        ->and($lostInsights['kpis']['is_lapsed'])->toBeTrue()
        ->and(collect($lostInsights['regulars'])->pluck('id')->all())->toBe([$product->id])
        ->and($lostInsights['recent_orders'])->toHaveCount(1)
        ->and($lostInsights['recommendations_source'])->toBe('bought_together');
});

test('b2b dashboard recommendations carry the customer favourites and basket so they render like any product card', function () {
    $customer      = freshCustomerLike($this->shop, $this->customer);
    $otherCustomer = freshCustomerLike($this->shop, $this->customer);
    [, $seed]      = createProduct($this->shop);
    $suggested     = StoreProduct::make()->action($seed->family, array_merge(
        Product::factory()->definition(),
        ['trade_units' => [['id' => $seed->tradeUnits->first()->id, 'quantity' => 1]], 'price' => 10]
    ));

    foreach ([$seed, $suggested] as $product) {
        $product->update(['state' => ProductStateEnum::ACTIVE, 'status' => ProductStatusEnum::FOR_SALE, 'is_for_sale' => true, 'has_live_webpage' => true, 'price' => 10, 'available_quantity' => 1000]);
    }

    $customerOrder = StoreOrder::make()->action($customer, Order::factory()->definition());
    StoreTransaction::make()->action($customerOrder, $seed->currentHistoricProduct, Transaction::factory()->definition());
    $customerOrder->update(['state' => OrderStateEnum::DISPATCHED, 'date' => now()->subDays(5), 'net_amount' => 10]);

    $otherOrder = StoreOrder::make()->action($otherCustomer, Order::factory()->definition());
    foreach ([$seed, $suggested] as $product) {
        StoreTransaction::make()->action($otherOrder, $product->currentHistoricProduct, Transaction::factory()->definition());
    }

    \App\Actions\CRM\Favourite\StoreFavourite::make()->action($customer, $suggested, []);

    $recommendation = collect(\App\Actions\Retina\UI\Dashboard\GetRetinaB2BDashboardInsights::run($customer->fresh())['recommendations'])
        ->firstWhere('id', $suggested->id);

    expect($recommendation)->not->toBeNull()
        ->and($recommendation['is_favourite'])->toBeTrue()
        ->and($recommendation['is_back_in_stock'])->toBeFalse()
        ->and($recommendation['transaction_id'])->toBeNull()
        ->and($recommendation['quantity_ordered'])->toBe(0)
        ->and($recommendation)->toHaveKeys(['stock', 'price', 'price_per_unit', 'product_offers_data']);
});

test('basket recommendations never suggest a product sold exclusively to another customer', function () {
    $customer      = freshCustomerLike($this->shop, $this->customer);
    $otherCustomer = freshCustomerLike($this->shop, $this->customer);
    [, $seed]      = createProduct($this->shop);
    [$exclusive, $public] = collect(range(1, 2))->map(fn () => StoreProduct::make()->action($seed->family, array_merge(
        Product::factory()->definition(),
        ['trade_units' => [['id' => $seed->tradeUnits->first()->id, 'quantity' => 1]], 'price' => 10]
    )))->all();

    foreach ([$seed, $exclusive, $public] as $product) {
        $product->update(['state' => ProductStateEnum::ACTIVE, 'status' => ProductStatusEnum::FOR_SALE, 'is_for_sale' => true, 'has_live_webpage' => true, 'price' => 10, 'available_quantity' => 1000]);
    }
    DB::table('product_has_exclusive_customers')->insert([
        'product_id'  => $exclusive->id,
        'customer_id' => $otherCustomer->id,
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    $order = StoreOrder::make()->action($otherCustomer, Order::factory()->definition());
    foreach ([$seed, $exclusive, $public] as $product) {
        StoreTransaction::make()->action($order, $product->currentHistoricProduct, Transaction::factory()->definition());
    }

    $recommend = fn (?int $customerId) => \App\Actions\Retina\Ecom\Basket\GetRetinaProductBasketRecommendations::make()
        ->handle($this->shop, [$seed->id], ['customer_id' => $customerId])->pluck('id');

    expect($recommend($customer->id))->toContain($public->id)->not->toContain($exclusive->id)
        ->and($recommend(null))->not->toContain($exclusive->id)
        ->and($recommend($otherCustomer->id))->toContain($exclusive->id);
});

test('b2b dashboard shows a voucher only once staff opt it in, and hides it after the customer used it', function () {
    if (!$this->shop->offerCampaigns()->where('type', \App\Enums\Discounts\OfferCampaign\OfferCampaignTypeEnum::VOUCHERS)->exists()) {
        \App\Actions\Discounts\OfferCampaign\SeedShopOfferCampaigns::run($this->shop);
    }
    $customer = freshCustomerLike($this->shop, $this->customer);
    $code     = 'DASH'.strtoupper(\Illuminate\Support\Str::random(6));

    $voucher = \App\Actions\Discounts\Offer\StoreVoucherOffers::make()->handle($this->shop, [
        'voucher'            => $code,
        'name'               => '15% off over 200',
        'offer_amount'       => 200,
        'can_customer_reuse' => false,
        'start_at'           => now()->subDay()->toDateTimeString(),
        'end_at'             => now()->addDays(10)->toDateTimeString(),
        'percentage_off'     => 15,
        'allowance_type'     => 'percentage_off',
        'target_type'        => 'shop',
        'target_id'          => $this->shop->id,
    ]);
    $voucher->update(['status' => true]);

    $dashboardVoucherCodes = fn () => collect(\App\Actions\Retina\UI\Dashboard\GetRetinaB2BDashboardInsights::run($customer->fresh())['vouchers'])->pluck('code')->all();

    expect($dashboardVoucherCodes())->not->toContain($code);

    \App\Actions\Discounts\Offer\UpdateOffer::make()->action($voucher, ['show_on_customer_dashboard' => true]);
    $shown = collect(\App\Actions\Retina\UI\Dashboard\GetRetinaB2BDashboardInsights::run($customer->fresh())['vouchers'])->firstWhere('code', $code);

    expect($voucher->fresh()->settings)->toMatchArray(['can_customer_reuse' => false, 'show_on_customer_dashboard' => true])
        ->and($shown['percentage_off'])->toEqual(0.15)
        ->and($shown['min_amount'])->toEqual(200)
        ->and($shown['is_whole_order'])->toBeTrue()
        ->and($shown['expires_at'])->toBe(now()->addDays(10)->toDateString());

    $order = StoreOrder::make()->action($customer, Order::factory()->definition());
    $order->update(['state' => OrderStateEnum::DISPATCHED, 'offer_voucher_id' => $voucher->id]);

    expect($dashboardVoucherCodes())->not->toContain($code);
});

test('ordering a past order again fills the basket once, however many times it is pressed', function () {
    $customer = freshCustomerLike($this->shop, $this->customer);
    [, $product] = createProduct($this->shop);
    $product->update(['status' => ProductStatusEnum::FOR_SALE, 'price' => 10, 'available_quantity' => 1000]);

    $pastOrder = StoreOrder::make()->action($customer, Order::factory()->definition());
    StoreTransaction::make()->action($pastOrder, $product->currentHistoricProduct, array_merge(Transaction::factory()->definition(), ['quantity_ordered' => 6]));
    $pastOrder->update(['state' => OrderStateEnum::DISPATCHED]);

    $first  = \App\Actions\Retina\Ecom\Orders\RepeatRetinaEcomOrder::make()->handle($customer->fresh(), $pastOrder);
    $second = \App\Actions\Retina\Ecom\Orders\RepeatRetinaEcomOrder::make()->handle($customer->fresh(), $pastOrder);

    $basket = $customer->fresh()->orderInBasket;
    $line   = $basket->transactions()->where('model_type', 'Product')->where('model_id', $product->id)->first();

    expect($first)->toBe(['added' => 1, 'skipped' => []])
        ->and($second['added'])->toBe(1)
        ->and($pastOrder->fresh()->state)->toBe(OrderStateEnum::DISPATCHED)
        ->and($basket->id)->not->toBe($pastOrder->id)
        ->and((float) $line->quantity_ordered)->toEqual(6.0)
        ->and((float) $line->net_amount)->toBeGreaterThan(0.0)
        ->and((float) $basket->goods_amount)->toEqual((float) $line->net_amount);

    $product->update(['status' => ProductStatusEnum::DISCONTINUED]);
    expect(\App\Actions\Retina\Ecom\Orders\RepeatRetinaEcomOrder::make()->handle($customer->fresh(), $pastOrder)['skipped'])
        ->toBe([['code' => $product->code, 'name' => $product->name]]);
});

test('a claim refunded to balance is paid out of the card payment and leaves nothing due on the order', function () {
    $order = StoreOrder::make()->action(freshCustomerLike($this->shop, $this->customer), Order::factory()->definition());
    StoreTransaction::make()->action($order, $this->product->historicAsset, array_merge(Transaction::factory()->definition(), ['quantity_ordered' => 2]));

    $attachedOrgStock = null;
    if (!$this->product->orgStocks()->count()) {
        $attachedOrgStock = OrgStock::where('organisation_id', $this->organisation->id)->firstOrFail();
        $this->product->orgStocks()->attach($attachedOrgStock->id, ['quantity' => 1]);
    }
    $this->product->orgStocks()->update(['quantity_available' => 100000]);

    SubmitOrder::make()->action($order);
    $deliveryNote = SendOrderToWarehouse::make()->action($order->refresh(), []);
    $item         = $deliveryNote->deliveryNoteItems()->firstOrFail();
    $item->update(['quantity_picked' => $item->quantity_required, 'quantity_dispatched' => $item->quantity_required]);
    $invoice      = GenerateInvoiceFromOrder::make()->action($order->refresh());
    $claimed      = [['id' => $item->id, 'quantity' => 1]];

    expect((float) $invoice->total_amount)->toBeGreaterThan(0.0)
        ->and(fn () => RefundClaimToBalance::make()->handle($order->refresh(), $claimed))->toThrow(ValidationException::class, 'no payment left')
        ->and($order->invoices()->where('type', InvoiceTypeEnum::REFUND)->count())->toBe(0);

    $cardAccount = StoreOrgPaymentServiceProviderAccount::make()->action(
        $this->organisation,
        PaymentServiceProvider::where('type', PaymentServiceProviderTypeEnum::CASH->value)->first(),
        ['code' => 'CLM'.mt_rand(1000, 9999), 'name' => 'Claim card account']
    );
    $payment = PayInvoice::make()->action($invoice->refresh(), $cardAccount, [
        'amount' => $invoice->total_amount,
        'status' => PaymentStatusEnum::SUCCESS->value,
        'state'  => PaymentStateEnum::COMPLETED->value,
    ]);

    $refund = RefundClaimToBalance::make()->handle($order->refresh(), $claimed);

    $credit = CreditTransaction::where('customer_id', $order->customer_id)->latest('id')->first();
    expect($refund->pay_status)->toBe(InvoicePayStatusEnum::PAID)
        ->and((float) $refund->total_amount)->toBeLessThan(0.0)
        ->and((float) $order->customer->refresh()->balance)->toBe(abs((float) $refund->total_amount))
        ->and($credit->type)->toBe(CreditTransactionTypeEnum::PAY_RETURN)
        ->and($credit->payment->original_payment_id)->toBe($payment->id)
        ->and($credit->payment->paymentAccount->type)->toBe(PaymentAccountTypeEnum::ACCOUNT)
        ->and(StoreOrderPaymentLink::amountDue($order->refresh()))->toBe(0.0);

    if ($attachedOrgStock) {
        $this->product->orgStocks()->detach($attachedOrgStock->id);
    }
});

test('staff see the customer balance on a basket so a phone payment can use it first', function () {
    $modelData = Order::factory()->definition();
    data_set($modelData, 'billing_address', new Address(Address::factory()->definition()));
    data_set($modelData, 'delivery_address', new Address(Address::factory()->definition()));

    $basket = StoreOrder::make()->action($this->customer, $modelData);
    $originalBalance = $this->customer->balance;
    $this->customer->update(['balance' => 10.46]);

    try {
        get(route('grp.org.shops.show.ordering.orders.show', [$this->organisation->slug, $this->shop->slug, $basket->slug]))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('basket_customer_balance', fn ($balance) => (float) $balance === 10.46)->etc());

        $basket->update(['state' => OrderStateEnum::IN_WAREHOUSE]);

        get(route('grp.org.shops.show.ordering.orders.show', [$this->organisation->slug, $this->shop->slug, $basket->slug]))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('basket_customer_balance', null)->etc());
    } finally {
        $this->customer->update(['balance' => $originalBalance]);
    }
});

describe('pre-orders (HELP-3432)', function () {
    beforeEach(function () {
        $this->shop->update(['settings' => array_merge($this->shop->settings ?? [], ['pre_orders' => [
            'enabled'                => true,
            'deposit_percentage'     => 30,
            'full_payment_below'     => 50,
            'default_lead_time_days' => 60,
            'dispatch_range_weeks'   => 2,
        ]])]);
        $this->shop->refresh();

        $this->preOrderProduct = function (array $flags, float $price = 100) {
            [, $bulk] = createProduct($this->shop);
            $product = StoreProduct::make()->action($bulk->family, array_merge(
                Product::factory()->definition(),
                ['trade_units' => [['id' => $bulk->tradeUnits->first()->id, 'quantity' => 1]], 'price' => $price]
            ));
            $orgStock = \App\Models\Inventory\OrgStock::where('organisation_id', $this->organisation->id)->whereNotIn('id', DB::table('product_has_org_stocks')->pluck('org_stock_id'))->first()
                ?? \App\Models\Inventory\OrgStock::where('organisation_id', $this->organisation->id)->firstOrFail();
            DB::table('product_has_org_stocks')->where('product_id', $product->id)->delete();
            $product->orgStocks()->attach($orgStock->id, ['quantity' => 1]);
            $orgStock->update(['quantity_available' => 0, 'quantity_reserved_for_pre_orders' => 0]);
            DB::table('products')->where('id', $product->id)->update(array_merge([
                'state'              => ProductStateEnum::ACTIVE->value,
                'status'             => ProductStatusEnum::OUT_OF_STOCK->value,
                'is_for_sale'        => true,
                'available_quantity' => 0,
            ], $flags));

            return $product->fresh();
        };

        $this->fundedCustomer = function (float $amount) {
            $customer = freshCustomerLike($this->shop, $this->customer);
            StoreCreditTransaction::make()->action($customer, [
                'amount' => $amount,
                'date'   => now(),
                'type'   => CreditTransactionTypeEnum::ADD_FUNDS_OTHER,
            ]);

            return $customer->refresh();
        };
    });

    afterEach(function () {
        $this->shop->update(['settings' => Arr::except($this->shop->settings ?? [], 'pre_orders')]);
    });

    test('the shop settings switch turns pre-orders on and off and stores the pallet rates', function () {
        $editShop = \App\Actions\Catalogue\Shop\UI\EditShop::make();
        $section  = collect((new ReflectionMethod($editShop, 'preOrderSettingsFields'))->invoke($editShop, $this->shop));
        expect($section['fields']['pre_order_enabled']['type'])->toBe('toggle');

        \App\Actions\Catalogue\Shop\UpdateShop::make()->action($this->shop, ['pre_order_enabled' => false]);
        expect($this->shop->refresh()->hasPreOrders())->toBeFalse();

        \App\Actions\Catalogue\Shop\UpdateShop::make()->action($this->shop, [
            'pre_order_enabled'      => true,
            'pre_order_pallet_rates' => [['country_code' => 'DE', 'amount' => '150']],
        ]);
        $this->shop->refresh();

        expect($this->shop->hasPreOrders())->toBeTrue()
            ->and(\App\Actions\Ordering\PreOrder\GetProductPreOrder::make()->palletEstimate($this->shop, 'DE'))->toBe(150.0)
            ->and($this->shop->preOrderSetting('deposit_percentage'))->toBe(30);
    });

    test('only flagged products of a shop with pre-orders on can be bought beyond stock, within their maximum per order', function () {
        $purchasable = new class () {
            use \App\Actions\Traits\WithCustomerPurchasableProduct;

            public function check(Product $product, $customer, $quantity = null): void
            {
                $this->ensureProductIsPurchasableByCustomer($product, $customer, $quantity);
            }
        };

        $plain = ($this->preOrderProduct)([]);
        expect(fn () => $purchasable->check($plain, $this->customer))->toThrow(ValidationException::class);

        $backOrder = ($this->preOrderProduct)(['is_back_order' => true, 'max_quantity_per_order' => 5]);
        $purchasable->check($backOrder, $this->customer, 5);
        expect(fn () => $purchasable->check($backOrder, $this->customer, 6))->toThrow(ValidationException::class);

        $this->shop->update(['settings' => array_merge($this->shop->settings, ['pre_orders' => ['enabled' => false]])]);
        expect(fn () => $purchasable->check($backOrder->fresh(), $this->customer, 1))->toThrow(ValidationException::class);

        DB::table('products')->where('id', $backOrder->id)->update(['status' => ProductStatusEnum::FOR_SALE->value, 'available_quantity' => 100]);
        $purchasable->check($backOrder->fresh(), $this->customer, 6);
    });

    test('the dispatch estimate is a range in weeks from the product, then the supplier, then the shop lead time', function () {
        $madeToOrder = ($this->preOrderProduct)(['is_made_to_order' => true]);
        $preOrder    = \App\Actions\Ordering\PreOrder\GetProductPreOrder::run($madeToOrder);

        expect($preOrder['type'])->toBe('made_to_order')
            ->and($preOrder['lead_time_days'])->toBe(60)
            ->and($preOrder['dispatch_from_weeks'])->toBe(9)
            ->and($preOrder['dispatch_to_weeks'])->toBe(11)
            ->and($preOrder['deposit_percentage'])->toBe(30.0);

        DB::table('products')->where('id', $madeToOrder->id)->update(['pre_order_lead_time_days' => 84, 'pre_order_deposit_percentage' => 50]);
        $preOrder = \App\Actions\Ordering\PreOrder\GetProductPreOrder::run($madeToOrder->fresh());

        expect($preOrder['dispatch_from_weeks'])->toBe(12)
            ->and($preOrder['dispatch_to_weeks'])->toBe(14)
            ->and($preOrder['deposit_percentage'])->toBe(50.0);
    });

    test('a mixed basket pays the deposit, splits at submit, holds the pre-order until its goods arrive and releases it once the balance is paid', function () {
        $inStock = ($this->preOrderProduct)([]);
        DB::table('products')->where('id', $inStock->id)->update(['status' => ProductStatusEnum::FOR_SALE->value, 'available_quantity' => 100]);
        $inStock->orgStocks()->update(['quantity_available' => 100]);
        $madeToOrder = ($this->preOrderProduct)(['is_made_to_order' => true]);

        $customer = ($this->fundedCustomer)(10000);
        $basket   = StoreOrder::make()->action($customer, Order::factory()->definition());
        StoreTransaction::make()->action($basket, $inStock->fresh()->currentHistoricProduct, array_merge(Transaction::factory()->definition(), ['quantity_ordered' => 1]));
        StoreTransaction::make()->action($basket, $madeToOrder->currentHistoricProduct, array_merge(Transaction::factory()->definition(), ['quantity_ordered' => 2]));
        $basket->refresh();

        $basketPreOrders = \App\Actions\Ordering\PreOrder\GetBasketPreOrders::run($basket);
        $taxFactor       = (float) $basket->total_amount / (float) $basket->net_amount;

        expect($basketPreOrders['has_pre_orders'])->toBeTrue()
            ->and($basketPreOrders['has_in_stock_lines'])->toBeTrue()
            ->and($basketPreOrders['deferred_amount'])->toBe(round(200 * 0.7 * $taxFactor, 2))
            ->and(\App\Actions\Ordering\PreOrder\GetOrderAmountToPayNow::run($basket))->toBe((float) $basket->total_amount);

        \App\Actions\Ordering\PreOrder\AcceptBasketPreOrderTerms::run($basket, false);
        expect(\App\Actions\Ordering\PreOrder\GetBasketPreOrders::run($basket->fresh())['is_accepted'])->toBeTrue()
            ->and(\App\Actions\Ordering\PreOrder\GetOrderAmountToPayNow::run($basket->fresh()))->toBe(round((float) $basket->total_amount - $basketPreOrders['deferred_amount'], 2));

        $result = PayRetinaOrderWithBalance::make()->handle($basket->fresh());
        expect($result['success'])->toBeTrue();

        $parent   = $basket->fresh();
        $preOrder = $parent->splitPreOrder;
        $child    = $preOrder->order;

        expect($parent->state)->not->toBe(OrderStateEnum::CREATING)
            ->and($parent->transactions()->where('model_type', 'Product')->pluck('model_id')->all())->toBe([$inStock->id])
            ->and($child->transactions()->where('model_type', 'Product')->pluck('model_id')->all())->toBe([$madeToOrder->id])
            ->and($child->state)->toBe(OrderStateEnum::SUBMITTED)
            ->and($preOrder->state)->toBe(\App\Enums\Ordering\PreOrder\PreOrderStateEnum::WAITING_FOR_GOODS)
            ->and((float) $parent->payment_amount)->toBe((float) $parent->total_amount)
            ->and((float) $child->payment_amount)->toBeGreaterThan(0.0)
            ->and((float) $child->payment_amount)->toBeLessThan((float) $child->total_amount)
            ->and($child->deliveryNotes()->count())->toBe(0)
            ->and($preOrder->terms)->not->toBeEmpty()
            ->and(Arr::get($child->transactions()->where('model_type', 'Product')->first()->data, 'pre_order.type'))->toBe('made_to_order');

        $madeToOrder->orgStocks()->update(['quantity_available' => 2]);
        \App\Actions\Ordering\PreOrder\AllocatePreOrderStock::run();
        $preOrder->refresh();

        expect($preOrder->state)->toBe(\App\Enums\Ordering\PreOrder\PreOrderStateEnum::BALANCE_REQUESTED)
            ->and($preOrder->goods_arrived_at)->not->toBeNull()
            ->and((float) $madeToOrder->orgStocks()->first()->quantity_reserved_for_pre_orders)->toBe(2.0);

        PayOrderWithCustomerBalance::make()->handle($child->fresh());
        $preOrder->refresh();

        expect($preOrder->state)->toBe(\App\Enums\Ordering\PreOrder\PreOrderStateEnum::RELEASED)
            ->and((float) $madeToOrder->orgStocks()->first()->quantity_reserved_for_pre_orders)->toBe(0.0)
            ->and($child->fresh()->state)->toBe(OrderStateEnum::IN_WAREHOUSE);
    });

    test('placing a pre-order basket with balance needs the terms accepted, and staff must unlock the pre-order for themselves to change its money', function () {
        $madeToOrder = ($this->preOrderProduct)(['is_made_to_order' => true]);
        $customer    = ($this->fundedCustomer)(10000);
        $basket      = StoreOrder::make()->action($customer, Order::factory()->definition());
        StoreTransaction::make()->action($basket, $madeToOrder->currentHistoricProduct, array_merge(Transaction::factory()->definition(), ['quantity_ordered' => 1]));

        $refused = PayRetinaOrderWithBalance::make()->handle($basket->fresh());
        expect($refused['success'])->toBeFalse()
            ->and($basket->fresh()->state)->toBe(OrderStateEnum::CREATING)
            ->and((float) $basket->fresh()->payment_amount)->toBe(0.0);

        \App\Actions\Ordering\PreOrder\AcceptBasketPreOrderTerms::run($basket->fresh(), false);
        expect(PayRetinaOrderWithBalance::make()->handle($basket->fresh())['success'])->toBeTrue();

        $order    = $basket->fresh();
        $preOrder = $order->preOrder;
        $other    = new \App\Models\SysAdmin\User();
        $other->id = $this->user->id + 100000;

        $middleware = new \App\Http\Middleware\EnsurePreOrderIsUnlocked();
        $through    = function (string $routeName, $user, array $input = []) use ($middleware, $order) {
            $request = \Illuminate\Http\Request::create('/pre-order-lock', 'PATCH', $input);
            $route   = (new \Illuminate\Routing\Route('PATCH', '/pre-order-lock', []))->name($routeName)->bind($request);
            $route->setParameter('order', $order);
            $request->setRouteResolver(fn () => $route);
            $request->setUserResolver(fn () => $user);

            return $middleware->handle($request, fn () => 'passed');
        };

        expect($preOrder->isLocked())->toBeTrue()
            ->and(fn () => $through('grp.models.order.update_insurance', $this->user))->toThrow(ValidationException::class)
            ->and(fn () => $through('grp.models.order.update', $this->user, ['collection_address_id' => null]))->toThrow(ValidationException::class)
            ->and($through('grp.models.order.update', $this->user, ['internal_notes' => 'call first']))->toBe('passed')
            ->and($through('grp.models.order.payment.store', $this->user))->toBe('passed');

        \App\Actions\Ordering\PreOrder\UnlockPreOrder::run($preOrder, $this->user);
        $preOrder->refresh();

        expect($through('grp.models.order.update_insurance', $this->user))->toBe('passed')
            ->and(fn () => $through('grp.models.order.update_insurance', $other))->toThrow(ValidationException::class)
            ->and($preOrder->unlockedUntil()->isFuture())->toBeTrue()
            ->and($order->audits()->where('event', 'pre_order_unlocked')->exists())->toBeTrue();

        \App\Actions\Ordering\PreOrder\UnlockPreOrder::run($preOrder, $this->user, unlock: false);
        $preOrder->refresh();
        expect(fn () => $through('grp.models.order.update_insurance', $this->user))->toThrow(ValidationException::class);

        $preOrder->update(['data' => array_merge($preOrder->data, ['unlock' => ['user_id' => $this->user->id, 'until' => now()->subMinute()->toIso8601String()]])]);
        expect($preOrder->fresh()->canBeEditedBy($this->user))->toBeFalse();

        $preOrder->update(['state' => \App\Enums\Ordering\PreOrder\PreOrderStateEnum::RELEASED]);
        expect($through('grp.models.order.update_insurance', $other))->toBe('passed');
    });

    test('an unpaid balance is reminded on the shop days and the pre-order cancelled keeping the deposit after the limit', function () {
        $madeToOrder = ($this->preOrderProduct)(['is_made_to_order' => true]);
        $customer    = ($this->fundedCustomer)(10000);
        $basket      = StoreOrder::make()->action($customer, Order::factory()->definition());
        StoreTransaction::make()->action($basket, $madeToOrder->currentHistoricProduct, array_merge(Transaction::factory()->definition(), ['quantity_ordered' => 1]));
        \App\Actions\Ordering\PreOrder\AcceptBasketPreOrderTerms::run($basket->fresh(), false);
        PayRetinaOrderWithBalance::make()->handle($basket->fresh());
        $preOrder = $basket->fresh()->preOrder;
        $paid     = (float) $preOrder->order->payment_amount;
        $deposit  = (float) Arr::get($preOrder->data, 'made_to_order_deposit_amount');

        $madeToOrder->orgStocks()->update(['quantity_available' => 1]);
        \App\Actions\Ordering\PreOrder\AllocatePreOrderStock::run();
        expect($preOrder->refresh()->state)->toBe(\App\Enums\Ordering\PreOrder\PreOrderStateEnum::BALANCE_REQUESTED);

        $this->travel(3)->days();
        \App\Actions\Ordering\PreOrder\ProcessPreOrders::run();
        expect($preOrder->refresh()->balance_first_reminder_sent_at)->not->toBeNull()
            ->and($preOrder->balance_second_reminder_sent_at)->toBeNull();

        $this->travel(11)->days();
        $balanceBefore = (float) $customer->refresh()->balance;
        \App\Actions\Ordering\PreOrder\ProcessPreOrders::run();
        $this->travelBack();

        expect($preOrder->refresh()->state)->toBe(\App\Enums\Ordering\PreOrder\PreOrderStateEnum::CANCELLED)
            ->and($preOrder->cancellation_reason)->toBe('balance_not_paid')
            ->and(round((float) $customer->refresh()->balance - $balanceBefore, 2))->toBe(round($paid - $deposit, 2))
            ->and((float) $preOrder->order->refresh()->payment_amount)->toBe($deposit)
            ->and((float) $madeToOrder->orgStocks()->first()->quantity_reserved_for_pre_orders)->toBe(0.0);
    });

    test('cancelling a trade made-to-order pre-order refunds everything until the supplier is ordered, then keeps the deposit', function () {
        $madeToOrder = ($this->preOrderProduct)(['is_made_to_order' => true]);

        $placePreOrder = function () use ($madeToOrder) {
            $customer = ($this->fundedCustomer)(10000);
            $basket   = StoreOrder::make()->action($customer, Order::factory()->definition());
            StoreTransaction::make()->action($basket, $madeToOrder->currentHistoricProduct, array_merge(Transaction::factory()->definition(), ['quantity_ordered' => 1]));
            \App\Actions\Ordering\PreOrder\AcceptBasketPreOrderTerms::run($basket->fresh(), false);
            PayRetinaOrderWithBalance::make()->handle($basket->fresh());

            return $basket->fresh()->preOrder;
        };

        $preOrder = $placePreOrder();
        $order    = $preOrder->order;
        $paid     = (float) $order->payment_amount;
        $deposit  = (float) Arr::get($preOrder->data, 'made_to_order_deposit_amount');
        expect($preOrder->parent_order_id)->toBeNull()
            ->and($deposit)->toBe(round(100 * 0.3 * (float) $order->total_amount / (float) $order->net_amount, 2))
            ->and($paid)->toBe(round((float) $order->total_amount - (float) $preOrder->deferred_amount, 2))
            ->and(\App\Actions\Ordering\PreOrder\CancelPreOrder::make()->refundAmount($preOrder, \App\Enums\Ordering\PreOrder\PreOrderCancellationReasonEnum::CUSTOMER_REQUEST))->toBe($paid);

        \App\Actions\Ordering\PreOrder\MarkPreOrdersSupplierOrdered::run([$preOrder->id]);
        $preOrder->refresh();

        expect(\App\Actions\Ordering\PreOrder\CancelPreOrder::make()->refundAmount($preOrder, \App\Enums\Ordering\PreOrder\PreOrderCancellationReasonEnum::CUSTOMER_REQUEST))->toBe(round($paid - $deposit, 2))
            ->and(\App\Actions\Ordering\PreOrder\CancelPreOrder::make()->refundAmount($preOrder, \App\Enums\Ordering\PreOrder\PreOrderCancellationReasonEnum::SUPPLIER_CANNOT_SUPPLY))->toBe($paid);

        $balanceBefore = (float) $preOrder->customer->refresh()->balance;
        \App\Actions\Ordering\PreOrder\CancelPreOrder::run($preOrder, \App\Enums\Ordering\PreOrder\PreOrderCancellationReasonEnum::SUPPLIER_CANNOT_SUPPLY);

        expect($preOrder->refresh()->state)->toBe(\App\Enums\Ordering\PreOrder\PreOrderStateEnum::CANCELLED)
            ->and($preOrder->order->refresh()->state)->toBe(OrderStateEnum::CANCELLED)
            ->and(round((float) $preOrder->customer->refresh()->balance - $balanceBefore, 2))->toBe($paid);
    });

    test('a basket with pre-order lines and no accepted terms is never split, charges in full, and cannot be placed from Retina', function () {
        $madeToOrder = ($this->preOrderProduct)(['is_made_to_order' => true]);
        $customer    = ($this->fundedCustomer)(10000);
        $basket      = StoreOrder::make()->action($customer, Order::factory()->definition());
        StoreTransaction::make()->action($basket, $madeToOrder->currentHistoricProduct, array_merge(Transaction::factory()->definition(), ['quantity_ordered' => 1]));
        $basket->refresh();

        expect(\App\Actions\Ordering\PreOrder\GetOrderAmountToPayNow::run($basket))->toBe((float) $basket->total_amount)
            ->and(\App\Actions\Retina\GetRetinaPaymentMethods::make()->checkoutPaymentAccountShops($basket))->toBeEmpty()
            ->and(fn () => \App\Actions\Retina\Dropshipping\Orders\SubmitRetinaOrder::make()->handle($basket->fresh()))->toThrow(ValidationException::class)
            ->and($basket->fresh()->state)->toBe(OrderStateEnum::CREATING);

        $channelOrder = SubmitOrder::make()->action($basket->fresh());

        expect($channelOrder->fresh()->preOrder)->toBeNull()
            ->and($channelOrder->splitPreOrder)->toBeNull()
            ->and(\App\Models\Ordering\PreOrder::where('customer_id', $customer->id)->exists())->toBeFalse();
    });

    test('a cancelled pre-order stays cancelled, and a release needs the balance paid and happens once', function () {
        $madeToOrder = ($this->preOrderProduct)(['is_made_to_order' => true]);

        $placePreOrder = function () use ($madeToOrder) {
            $customer = ($this->fundedCustomer)(10000);
            $basket   = StoreOrder::make()->action($customer, Order::factory()->definition());
            StoreTransaction::make()->action($basket, $madeToOrder->currentHistoricProduct, array_merge(Transaction::factory()->definition(), ['quantity_ordered' => 1]));
            \App\Actions\Ordering\PreOrder\AcceptBasketPreOrderTerms::run($basket->fresh(), false);
            PayRetinaOrderWithBalance::make()->handle($basket->fresh());

            return $basket->fresh()->preOrder;
        };

        $cancelled = $placePreOrder();
        $stale     = \App\Models\Ordering\PreOrder::find($cancelled->id);
        \App\Actions\Ordering\PreOrder\CancelPreOrder::run($cancelled, \App\Enums\Ordering\PreOrder\PreOrderCancellationReasonEnum::SUPPLIER_CANNOT_SUPPLY);

        expect(fn () => \App\Actions\Ordering\PreOrder\ArrivePreOrder::run($stale))->toThrow(ValidationException::class)
            ->and(fn () => \App\Actions\Ordering\PreOrder\SetPreOrderPalletQuote::run($stale, 99))->toThrow(ValidationException::class)
            ->and(fn () => \App\Actions\Ordering\PreOrder\ReleasePreOrder::run($stale))->toThrow(ValidationException::class)
            ->and(fn () => \App\Actions\Ordering\PreOrder\CancelPreOrder::run($stale, \App\Enums\Ordering\PreOrder\PreOrderCancellationReasonEnum::LATE))->toThrow(ValidationException::class)
            ->and($cancelled->refresh()->state)->toBe(\App\Enums\Ordering\PreOrder\PreOrderStateEnum::CANCELLED)
            ->and($cancelled->goods_arrived_at)->toBeNull()
            ->and($cancelled->order->deliveryNotes()->count())->toBe(0);

        $preOrder = $placePreOrder();
        expect(fn () => \App\Actions\Ordering\PreOrder\ReleasePreOrder::run($preOrder))->toThrow(ValidationException::class)
            ->and($preOrder->refresh()->state)->toBe(\App\Enums\Ordering\PreOrder\PreOrderStateEnum::WAITING_FOR_GOODS);

        PayOrderWithCustomerBalance::make()->handle($preOrder->order->fresh());
        $stale = \App\Models\Ordering\PreOrder::find($preOrder->id);
        \App\Actions\Ordering\PreOrder\ReleasePreOrder::run($preOrder);

        expect(fn () => \App\Actions\Ordering\PreOrder\ReleasePreOrder::run($stale))->toThrow(ValidationException::class)
            ->and($preOrder->refresh()->state)->toBe(\App\Enums\Ordering\PreOrder\PreOrderStateEnum::RELEASED)
            ->and($preOrder->order->deliveryNotes()->count())->toBe(1);
    });

    test('a balance not paid in time keeps at most the made-to-order deposit, and nothing for dropshipping', function () {
        $madeToOrder = ($this->preOrderProduct)(['is_made_to_order' => true]);
        $customer    = ($this->fundedCustomer)(10000);
        $basket      = StoreOrder::make()->action($customer, Order::factory()->definition());
        StoreTransaction::make()->action($basket, $madeToOrder->currentHistoricProduct, array_merge(Transaction::factory()->definition(), ['quantity_ordered' => 1]));
        \App\Actions\Ordering\PreOrder\AcceptBasketPreOrderTerms::run($basket->fresh(), false);
        PayRetinaOrderWithBalance::make()->handle($basket->fresh());
        $preOrder = $basket->fresh()->preOrder;
        $paid     = (float) $preOrder->order->payment_amount;
        $deposit  = (float) Arr::get($preOrder->data, 'made_to_order_deposit_amount');
        $reason   = \App\Enums\Ordering\PreOrder\PreOrderCancellationReasonEnum::BALANCE_NOT_PAID;

        expect(\App\Actions\Ordering\PreOrder\CancelPreOrder::make()->refundAmount($preOrder, $reason))->toBe(round($paid - $deposit, 2));

        $preOrder->is_trade = false;
        expect(\App\Actions\Ordering\PreOrder\CancelPreOrder::make()->refundAmount($preOrder, $reason))->toBe($paid)
            ->and(\App\Actions\Ordering\PreOrder\CancelPreOrder::make()->refundAmount($preOrder, \App\Enums\Ordering\PreOrder\PreOrderCancellationReasonEnum::CUSTOMER_REQUEST))->toBe(0.0);
    });

    test('a line split between stock and pre-order keeps its free goods on the in-stock order only', function () {
        $backOrder = ($this->preOrderProduct)(['is_back_order' => true, 'available_quantity' => 1]);
        $backOrder->orgStocks()->update(['quantity_available' => 1]);
        $customer = ($this->fundedCustomer)(10000);
        $basket   = StoreOrder::make()->action($customer, Order::factory()->definition());
        $line     = StoreTransaction::make()->action($basket, $backOrder->fresh()->currentHistoricProduct, array_merge(Transaction::factory()->definition(), ['quantity_ordered' => 3]));
        $line->update(['quantity_bonus' => 1]);
        \App\Actions\Ordering\PreOrder\AcceptBasketPreOrderTerms::run($basket->fresh(), false);

        $preOrder     = \App\Actions\Ordering\PreOrder\SplitOrderPreOrders::run($basket->fresh());
        $preOrderLine = $preOrder->order->transactions()->where('model_type', 'Product')->first();

        expect((float) $line->refresh()->quantity_ordered)->toBe(1.0)
            ->and((float) $line->quantity_bonus)->toBe(1.0)
            ->and((float) $preOrderLine->quantity_ordered)->toBe(2.0)
            ->and((float) $preOrderLine->quantity_bonus)->toBe(0.0);
    });

    test('paying with balance twice from the same page charges once', function () {
        $order = StoreOrder::make()->action(($this->fundedCustomer)(10000), Order::factory()->definition());
        StoreTransaction::make()->action($order, $this->product->historicAsset, array_merge(Transaction::factory()->definition(), ['quantity_ordered' => 1]));
        $staleOrder = $order->fresh();

        PayOrderWithCustomerBalance::make()->handle($staleOrder);
        $second = PayOrderWithCustomerBalance::make()->handle($staleOrder);

        expect($second['success'])->toBeFalse()
            ->and((float) $order->fresh()->payment_amount)->toBe((float) $order->fresh()->total_amount);
    });
});

test('submitting an ecom order broadcasts a new order alert sized against the shop', function () {
    Event::fake([BroadcastNewOrderAlert::class]);
    $this->shop->update(['settings' => array_merge($this->shop->settings, ['order_alerts' => ['small_below' => 1000000, 'big_above' => 2000000]])]);

    $order = StoreOrder::make()->action($this->customer, Order::factory()->definition());
    SubmitOrder::make()->action($order);

    Event::assertDispatched(BroadcastNewOrderAlert::class, fn (BroadcastNewOrderAlert $event) => $event->shopId === $this->shop->id
        && $event->alert['types'] === [OrderAlertTypeEnum::ECOM_SMALL->value]
        && $event->alert['reference'] === $order->reference
        && str_ends_with($event->alert['url'], '/orders/'.$order->slug)
        && $event->broadcastOn()[0]->name === 'private-grp.shop.'.$this->shop->id.'.new-orders');

    $this->shop->update(['settings' => Arr::except($this->shop->fresh()->settings, 'order_alerts')]);
});

test('ecom order size follows the nightly limits, and is normal while a shop has too few orders', function () {
    $order = new Order(['net_amount' => 500]);
    $order->setRelation('shop', new Shop(['type' => ShopTypeEnum::B2B, 'settings' => ['order_alerts' => ['small_below' => 50, 'big_above' => 400]]]));
    expect(SendNewOrderAlert::make()->ecomSize($order))->toBe(OrderAlertTypeEnum::ECOM_BIG);

    $order->net_amount = 20;
    expect(SendNewOrderAlert::make()->ecomSize($order))->toBe(OrderAlertTypeEnum::ECOM_SMALL);

    $order->net_amount = 100;
    expect(SendNewOrderAlert::make()->ecomSize($order))->toBe(OrderAlertTypeEnum::ECOM_NORMAL);

    $order->setRelation('shop', new Shop(['type' => ShopTypeEnum::B2B, 'settings' => ['order_alerts' => ['orders' => 3]]]));
    $order->net_amount = 99999;
    expect(SendNewOrderAlert::make()->ecomSize($order))->toBe(OrderAlertTypeEnum::ECOM_NORMAL);

    CalculateShopOrderAlertSizes::run($this->shop);
    $sizes = Arr::get($this->shop->fresh()->settings, 'order_alerts');
    expect(isset($sizes['big_above'], $sizes['small_below']))->toBe($sizes['orders'] >= CalculateShopOrderAlertSizes::MIN_ORDERS);
    $this->shop->update(['settings' => Arr::except($this->shop->fresh()->settings, 'order_alerts')]);
});

test('dropshipping rings only for an unpaid order or the first order of a connected store', function () {
    $dropshippingShop = new Shop(['type' => ShopTypeEnum::DROPSHIPPING]);

    $firstUnpaid = new Order(['state' => OrderStateEnum::SUBMITTED]);
    $firstUnpaid->customer_sales_channel_id = PHP_INT_MAX;
    $firstUnpaid->setRelation('shop', $dropshippingShop);
    $firstUnpaid->setRelation('platform', new Platform(['type' => PlatformTypeEnum::SHOPIFY]));

    expect(SendNewOrderAlert::make()->alertTypes($firstUnpaid))->toBe([OrderAlertTypeEnum::DROPSHIPPING_FIRST_CHANNEL_ORDER, OrderAlertTypeEnum::DROPSHIPPING_UNPAID]);

    $paidManual = new Order(['state' => OrderStateEnum::IN_WAREHOUSE, 'pay_status' => OrderPayStatusEnum::PAID]);
    $paidManual->customer_sales_channel_id = PHP_INT_MAX;
    $paidManual->setRelation('shop', $dropshippingShop);
    $paidManual->setRelation('platform', new Platform(['type' => PlatformTypeEnum::MANUAL]));

    expect(SendNewOrderAlert::make()->alertTypes($paidManual))->toBe([]);
});

test('only users who can see the shop orders may listen to its new order alerts', function () {
    $nobody = new User(['group_id' => $this->group->id]);

    expect(GetUserOrderAlerts::make()->canHear($this->user, $this->shop->id))->toBeTrue()
        ->and(GetUserOrderAlerts::make()->canHear($nobody, $this->shop->id))->toBeFalse()
        ->and(GetUserOrderAlerts::run($nobody)['shops'])->toBe([]);
});

test('order alert settings are saved on the user and reach the layout', function () {
    $types = [
        'ecom_small'  => ['enabled' => false, 'sound' => 'bell'],
        'ecom_normal' => ['enabled' => true, 'sound' => 'coins'],
        'ecom_big'    => ['enabled' => true, 'sound' => 'oh_yeah', 'muted' => true],
    ];
    $shopState = $this->shop->state;
    $this->shop->update(['state' => ShopStateEnum::OPEN]);

    actingAs($this->user)
        ->patchJson(route('grp.models.profile.update'), ['order_alerts' => [
            'shops' => [$this->shop->id, 999999],
            'types' => $types,
            'popup' => ['show' => true],
        ]])
        ->assertSuccessful();

    $user = $this->user->fresh();

    expect(Arr::get($user->settings, 'order_alerts.shops'))->toBe([$this->shop->id])
        ->and(Arr::get($user->settings, 'order_alerts.types.ecom_big'))->toEqual(['enabled' => true, 'sound' => 'oh_yeah', 'muted' => true])
        ->and(GetUserOrderAlerts::run($user)['sounds']['ecom_big'])->toBe('silent')
        ->and(Arr::get($user->settings, 'order_alerts.types.dropshipping_unpaid.enabled'))->toBeFalse()
        ->and(array_keys(GetUserOrderAlerts::run($user)['shops']))->toBe([$this->shop->id])
        ->and(GetUserOrderAlerts::run($user)['shops'][$this->shop->id])->toEqualCanonicalizing(['ecom_normal', 'ecom_big'])
        ->and(GetUserOrderAlerts::run($user)['popup'])->toEqual(['show' => true])
        ->and(GetUserOrderAlerts::run($user)['sounds']['ecom_normal'])->toBe('coins');

    actingAs($this->user)
        ->patchJson(route('grp.models.profile.update'), ['order_alerts' => ['types' => ['ecom_big' => ['sound' => 'air-horn']], 'popup' => ['show' => 'maybe']]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['order_alerts.types.ecom_big.sound', 'order_alerts.popup.show']);

    $user->update(['settings' => Arr::except($user->settings, 'order_alerts')]);
    $this->shop->update(['state' => $shopState]);
});

test('staff entered and partner orders do not ring, and a failing alert never stops the order', function () {
    Event::fake([BroadcastNewOrderAlert::class]);

    $phoneOrder = new Order();
    $phoneOrder->setRelation('salesChannel', new SalesChannel(['type' => SalesChannelTypeEnum::PHONE]));
    SendNewOrderAlert::run($phoneOrder);

    $partnerOrder = new Order();
    $partnerOrder->setRelation('salesChannel', new SalesChannel(['type' => SalesChannelTypeEnum::OTHER, 'code' => 'intercompany']));
    SendNewOrderAlert::run($partnerOrder);

    $brokenOrder = new Order(['net_amount' => 10]);
    $brokenOrder->setRelation('shop', new Shop(['type' => ShopTypeEnum::B2B]));
    $brokenOrder->setRelation('currency', null);
    SendNewOrderAlert::run($brokenOrder);

    Event::assertNotDispatched(BroadcastNewOrderAlert::class);
});

test('role defaults ring for big orders on the admin shops and never for small ones', function () {
    $shopState = $this->shop->state;
    $this->shop->update(['state' => ShopStateEnum::OPEN]);

    $defaults = GetUserOrderAlerts::make()->defaultShopTypes($this->user);

    $this->shop->update(['state' => $shopState]);

    expect($defaults)->toHaveKey($this->shop->id)
        ->and($defaults[$this->shop->id])->toContain(OrderAlertTypeEnum::ECOM_BIG->value)
        ->and($defaults[$this->shop->id])->not->toContain(OrderAlertTypeEnum::ECOM_SMALL->value);
});
