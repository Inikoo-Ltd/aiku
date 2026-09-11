<?php

use App\Actions\CRM\Customer\GetCustomerAcquisitionGtmData;
use App\Actions\CRM\Customer\StoreCustomer;
use App\Actions\Retina\Accounting\Payment\PlaceOrderPayByBank;
use App\Models\CRM\Customer;
use App\Models\Ordering\Order;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;

uses()->group('base');

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    list(
        $this->organisation,
        $this->user,
        $this->shop
    ) = createShop();

    actingAs($this->user);
});

function createGtmAcquisitionCustomer($shop): Customer
{
    return StoreCustomer::make()->action($shop, Customer::factory()->definition());
}

function placeGtmAcquisitionOrder(Customer $customer, $shop): Order
{
    createDispatchedOrderFor($customer, $shop, now()->toDateTimeString(), 'submitted');

    return Order::where('customer_id', $customer->id)->latest('id')->firstOrFail();
}

test('first ever purchase is a new customer with no lifetime value', function () {
    $customer = createGtmAcquisitionCustomer($this->shop);
    $order    = placeGtmAcquisitionOrder($customer, $this->shop);

    expect(GetCustomerAcquisitionGtmData::run($order))->toBe([
        'new_customer'            => true,
        'customer_lifetime_value' => 0.0,
    ]);
});

test('customer with an earlier placed order inside the inactivity window is returning', function () {
    $customer = createGtmAcquisitionCustomer($this->shop);
    createDispatchedOrderFor($customer, $this->shop, now()->subDays(30)->toDateTimeString());
    $order = placeGtmAcquisitionOrder($customer, $this->shop);

    expect(GetCustomerAcquisitionGtmData::run($order)['new_customer'])->toBeFalse();
});

test('customer whose last purchase is older than the inactivity window is new again', function () {
    $customer         = createGtmAcquisitionCustomer($this->shop);
    $lastPurchaseDate = now()->subDays(GetCustomerAcquisitionGtmData::NEW_CUSTOMER_INACTIVITY_DAYS + 1)->toDateTimeString();
    createDispatchedOrderFor($customer, $this->shop, $lastPurchaseDate);
    createInvoiceFor($customer, $this->shop, $lastPurchaseDate, 120.25);
    $order = placeGtmAcquisitionOrder($customer, $this->shop);

    expect(GetCustomerAcquisitionGtmData::run($order))->toBe([
        'new_customer'            => true,
        'customer_lifetime_value' => 120.25,
    ]);
});

test('cancelled orders and abandoned baskets do not make a customer returning', function () {
    $customer = createGtmAcquisitionCustomer($this->shop);
    createDispatchedOrderFor($customer, $this->shop, now()->subDays(10)->toDateTimeString(), 'cancelled');
    createDispatchedOrderFor($customer, $this->shop, now()->subDays(5)->toDateTimeString(), 'creating');
    $order = placeGtmAcquisitionOrder($customer, $this->shop);

    expect(GetCustomerAcquisitionGtmData::run($order)['new_customer'])->toBeTrue();
});

test('invoice history without matching orders makes a customer returning', function () {
    $customer = createGtmAcquisitionCustomer($this->shop);
    createInvoiceFor($customer, $this->shop, now()->subDays(100)->toDateTimeString(), 80);
    $order = placeGtmAcquisitionOrder($customer, $this->shop);

    expect(GetCustomerAcquisitionGtmData::run($order)['new_customer'])->toBeFalse();
});

test('lifetime value sums issued invoices net of refunds and ignores invoices in process', function () {
    $customer = createGtmAcquisitionCustomer($this->shop);
    createInvoiceFor($customer, $this->shop, now()->subDays(200)->toDateTimeString(), 100.40);
    createInvoiceFor($customer, $this->shop, now()->subDays(100)->toDateTimeString(), 150.40);
    createInvoiceFor($customer, $this->shop, now()->subDays(50)->toDateTimeString(), 999, true);
    createInvoiceFor($customer, $this->shop, now()->subDays(20)->toDateTimeString(), -20);
    DB::table('invoices')->where('customer_id', $customer->id)->where('total_amount', -20)->update(['type' => 'refund']);
    $order = placeGtmAcquisitionOrder($customer, $this->shop);

    expect(GetCustomerAcquisitionGtmData::run($order)['customer_lifetime_value'])->toBe(230.8);
});

test('customer status is unspecified when the order has no customer', function () {
    expect(GetCustomerAcquisitionGtmData::run(new Order()))->toBe([
        'new_customer'            => null,
        'customer_lifetime_value' => null,
    ]);
});

test('order placed redirection flashes the purchase event with customer acquisition data', function () {
    $customer = createGtmAcquisitionCustomer($this->shop);
    createDispatchedOrderFor($customer, $this->shop, now()->subDays(60)->toDateTimeString());
    createInvoiceFor($customer, $this->shop, now()->subDays(60)->toDateTimeString(), 75.5);
    $order = placeGtmAcquisitionOrder($customer, $this->shop);

    PlaceOrderPayByBank::make()->htmlResponse([
        'success' => true,
        'order'   => $order,
    ]);

    $gtm = session()->get('gtm');

    expect($gtm['event'])->toBe('purchase')
        ->and($gtm['key'])->toBe('retina_dropshipping_order_placed')
        ->and($gtm['data_to_submit']['ecommerce'])->toMatchArray([
            'transaction_id'          => $order->id,
            'value'                   => (float)$order->total_amount,
            'currency'                => $this->shop->currency->code,
            'items'                   => [],
            'new_customer'            => false,
            'customer_lifetime_value' => 75.5,
        ]);
});
