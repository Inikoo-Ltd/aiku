<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\CRM\Customer\StoreCustomer;
use App\Actions\Dispatching\Shipment\StoreShipment;
use App\Actions\Dispatching\Shipper\StoreShipper;
use App\Actions\Dropshipping\Ankorstore\GetAnkorstoreOrders;
use App\Actions\Dropshipping\Ankorstore\ShipAnkorstoreOrder;
use App\Actions\Helpers\TaxNumber\StoreTaxNumber;
use App\Models\Helpers\Country;
use App\Enums\Ordering\Order\OrderPayDetailedStatusEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\SalesChannel\SalesChannelTypeEnum;
use App\Models\CRM\Customer;
use App\Models\Ordering\Order;
use App\Models\Ordering\SalesChannel;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    [$this->organisation, , $this->shop] = createShop();
    createWarehouse();

    SalesChannel::where('group_id', $this->shop->group_id)->where('code', 'ankorstore')->first()
        ?? \App\Actions\Ordering\SalesChannel\StoreSalesChannel::make()->action($this->shop->group, [
            'code' => 'ankorstore',
            'name' => 'Ankorstore',
            'type' => SalesChannelTypeEnum::MARKETPLACE,
        ]);

    [, $this->product] = createProduct($this->shop);
    $this->product->updateQuietly(['code' => 'ANKTEST-01', 'units' => 3]);

    $GLOBALS['ankorstoreTestCurrency'] = $this->shop->currency->code;
    $this->price                       = (float)$this->product->currentHistoricProduct->price;

    $settings = $this->shop->settings;
    data_set($settings, 'ankorstore', ['client_id' => 'test-id', 'client_secret' => 'test-secret', 'import_from' => now()->subDay()->toIso8601String()]);
    $this->shop->update(['settings' => $settings]);

    $this->ankorstoreOrders   = [];
    $this->sent               = [];
    $this->refuseTransition   = false;
    Http::fake([
        '*ankorstore.com/oauth/token' => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
        '*ankorstore.com/api/v1/*'    => function (Request $request) {
            $path = Str::after(parse_url($request->url(), PHP_URL_PATH), '/api/v1/');
            if ($request->method() !== 'GET') {
                if ($this->refuseTransition && str_ends_with($path, '/-actions/transition')) {
                    return Http::response(['errors' => [['detail' => 'down']]], 503);
                }
                $this->sent[] = [$request->method(), $path, $request->data()];
            }

            if ($path === 'orders') {
                parse_str((string)parse_url($request->url(), PHP_URL_QUERY), $query);
                $orders = array_values(array_filter($this->ankorstoreOrders, fn ($order) => $order['data']['attributes']['status'] === $query['filter']['status']));

                return Http::response(['data' => array_column($orders, 'data'), 'meta' => ['page' => ['hasMore' => false]]]);
            }

            if (str_ends_with($path, '/ship/custom')) {
                return Http::response(['data' => ['attributes' => ['shippingOverview' => ['latestQuote' => ['id' => 'quote-1', 'provider' => 'custom']]]]]);
            }

            $id = Str::after($path, 'orders/');

            return Http::response($this->ankorstoreOrders[$id] ?? ['data' => ['attributes' => ['status' => 'brand_confirmed']]]);
        },
    ]);
});

afterEach(function () {
    $settings = $this->shop->refresh()->settings;
    unset($settings['ankorstore']);
    $this->shop->update(['settings' => $settings]);
});

function ankorstoreOrder(int $reference, string $email, array $lines, string $status = 'ankor_confirmed', ?string $vatNumber = null, float $brandTotal = 0): array
{
    $id       = (string)Str::uuid();
    $included = [[
        'type'       => 'retailers',
        'id'         => 'retailer-'.$reference,
        'attributes' => ['email' => $email, 'firstName' => 'Alice', 'lastName' => 'Amblard', 'storeName' => 'Alice Amblard', 'vatNumber' => $vatNumber, 'phoneNumberE164' => '+33603259628'],
    ]];
    $items = [];
    foreach ($lines as $index => [$sku, $quantity, $unitMultiplier]) {
        $items[]    = ['type' => 'order-items', 'id' => "item-$reference-$index"];
        $included[] = ['type' => 'order-items', 'id' => "item-$reference-$index", 'attributes' => ['quantity' => $quantity, 'multipliedQuantity' => $quantity * $unitMultiplier], 'relationships' => ['productVariant' => ['data' => ['type' => 'productVariants', 'id' => "variant-$reference-$index"]]]];
        $included[] = ['type' => 'productVariants', 'id' => "variant-$reference-$index", 'attributes' => ['sku' => $sku], 'relationships' => ['product' => ['data' => ['type' => 'products', 'id' => "product-$reference-$index"]]]];
        $included[] = ['type' => 'products', 'id' => "product-$reference-$index", 'attributes' => ['unitMultiplier' => $unitMultiplier]];
    }

    return [
        'data'     => [
            'type'          => 'orders',
            'id'            => $id,
            'attributes'    => [
                'reference'               => $reference,
                'masterOrderId'           => (string)Str::uuid(),
                'status'                  => $status,
                'brandCurrency'           => $GLOBALS['ankorstoreTestCurrency'],
                'brandTotalAmount'        => (int)round($brandTotal * 100),
                'submittedAt'             => now()->toIso8601String(),
                'billingName'             => 'Alice Amblard',
                'billingOrganisationName' => 'Synergie Pro',
                'billingStreet'           => '15 rue des lavandieres',
                'billingPostalCode'       => '34160',
                'billingCity'             => 'Castries',
                'billingCountryCode'      => 'FR',
                'shippingOverview'        => ['shipToAddress' => ['name' => 'Alice Amblard', 'organisationName' => "L'Univers d'Alice", 'street' => '22 Rue Antonin Paris', 'city' => 'Sommieres', 'postalCode' => '30250', 'countryCode' => 'FR']],
            ],
            'relationships' => ['retailer' => ['data' => ['type' => 'retailers', 'id' => 'retailer-'.$reference]], 'orderItems' => ['data' => $items]],
        ],
        'included' => $included,
    ];
}

test('an Ankorstore order is imported for the retailer at shop prices, accepted and shipped with our tracking; one that does not fit is skipped, a hand keyed one is left, a cancelled one is cancelled', function () {
    $reference = random_int(10000000, 99999999);
    $unknown   = $reference + 1;
    $handKeyed = $reference + 2;
    $email     = 'retailer'.$reference.'@example.com';

    $order        = ankorstoreOrder($reference, $email, [['ANKTEST-01', 2, 3]], brandTotal: 2 * $this->price + 5);
    $unknownOrder = ankorstoreOrder($unknown, $email, [['NOPE-01', 1, 1]]);
    $handOrder    = ankorstoreOrder($handKeyed, $email, [['anktest-01', 1, 1]], 'brand_confirmed', brandTotal: $this->price);
    $paidByHand   = $reference + 3;
    $paidOrder    = ankorstoreOrder($paidByHand, $email, [['ANKTEST-01', 1, 1]], 'brand_confirmed', brandTotal: $this->price);
    $this->ankorstoreOrders = [
        $order['data']['id']        => $order,
        $unknownOrder['data']['id'] => $unknownOrder,
        $handOrder['data']['id']    => $handOrder,
        $paidOrder['data']['id']    => $paidOrder,
    ];

    $existingCustomer = StoreCustomer::make()->action($this->shop, array_merge(Customer::factory()->definition(), ['email' => 'Retailer'.$reference.'@Example.com']));
    $handKeyedOrder   = \App\Actions\Ordering\Order\StoreOrder::make()->action($existingCustomer, ['customer_reference' => (string)$handKeyed]);
    $paidByHandOrder  = \App\Actions\Ordering\Order\StoreOrder::make()->action($existingCustomer, []);
    $payment          = \App\Actions\Accounting\Payment\StorePayment::make()->action($existingCustomer, $this->shop->paymentAccountShops()->first()->paymentAccount, [
        'amount'    => 10,
        'reference' => (string)$paidByHand,
        'status'    => \App\Enums\Accounting\Payment\PaymentStatusEnum::SUCCESS,
        'state'     => \App\Enums\Accounting\Payment\PaymentStateEnum::COMPLETED,
    ]);
    \App\Actions\Ordering\Order\AttachPaymentToOrder::make()->action($paidByHandOrder, $payment, ['amount' => 10]);

    GetAnkorstoreOrders::run($this->shop);
    $this->ankorstoreOrders[$order['data']['id']]['data']['attributes']['status'] = 'brand_confirmed';
    GetAnkorstoreOrders::run($this->shop);

    $imported = Order::where('shop_id', $this->shop->id)->where('external_id', (string)$reference)->sole();
    $line     = $imported->transactions()->where('model_type', 'Product')->sole();

    expect($imported->customer_id)->toBe($existingCustomer->id)
        ->and($imported->isFromAnkorstore())->toBeTrue()
        ->and($imported->salesChannel->code)->toBe('ankorstore')
        ->and($imported->deliveryAddress->postal_code)->toBe('30250')
        ->and($imported->state)->toBe(OrderStateEnum::IN_WAREHOUSE)
        ->and($imported->pay_detailed_status)->toBe(OrderPayDetailedStatusEnum::TO_BE_PAID_BY_MARKETPLACE)
        ->and((float)$line->quantity_ordered)->toBe(2.0)
        ->and((float)$line->net_amount)->toBe(round(2 * (float)$this->product->currentHistoricProduct->price, 2))
        ->and(Order::where('shop_id', $this->shop->id)->whereIn('external_id', [(string)$unknown, (string)$handKeyed, (string)$paidByHand])->exists())->toBeFalse()
        ->and((float)$imported->transactions()->where('model_type', 'Charge')->sum('net_amount'))->toBe(5.0)
        ->and((float)$imported->goods_amount + 5)->toBe(round(2 * $this->price + 5, 2))
        ->and($handKeyedOrder->refresh()->external_id)->toBeNull()
        ->and($this->shop->refresh()->settings['ankorstore']['skipped_orders'][$unknown])->toMatchArray(['handed_to_staff' => true])
        ->and($this->shop->settings['ankorstore']['skipped_orders'][$unknown]['reason'])->toContain('NOPE-01 is not a product of the shop')
        ->and(collect($this->sent)->where(1, 'orders/'.$order['data']['id'].'/-actions/transition')->count())->toBe(1);

    $deliveryNote = $imported->deliveryNotes()->sole();
    $deliveryNote->update(['parcels' => [['weight' => 14.6, 'dimensions' => [35, 35, 35], 'boxes' => 2]]]);
    $shipper = StoreShipper::make()->action($this->organisation, ['code' => 'AK'.Str::upper(Str::random(4)), 'name' => 'DPD', 'trade_as' => 'DPD']);
    StoreShipment::make()->action($deliveryNote->refresh(), $shipper, ['tracking' => '06585622551953']);
    expect(collect($this->sent)->where(1, 'orders/'.$order['data']['id'].'/ship/custom'))->toBeEmpty();

    expect(ShipAnkorstoreOrder::run($deliveryNote->refresh())['status'])->toBe('success');

    $shipCalls = collect($this->sent)->whereIn(1, ['orders/'.$order['data']['id'].'/ship/custom', 'shipping-quotes/quote-1/confirm'])->values();
    expect($shipCalls)->toHaveCount(2)
        ->and($shipCalls[0][2]['shipping']['parcels'])->toHaveCount(2)
        ->and($shipCalls[0][2]['shipping']['parcels'][0])->toMatchArray(['length' => 35, 'weight' => 14600, 'massUnit' => 'g'])
        ->and($shipCalls[1][2]['tracking'])->toBe(['trackingProvider' => 'DPD', 'trackingNumber' => '06585622551953']);

    $cancelled = ankorstoreOrder($reference, $email, [['ANKTEST-01', 2, 3]], 'cancelled');
    $this->ankorstoreOrders = [$cancelled['data']['id'] => $cancelled];
    $imported->deliveryNotes()->sole()->shipments()->detach();
    GetAnkorstoreOrders::run($this->shop);

    expect($imported->refresh()->state)->toBe(OrderStateEnum::CANCELLED);
});

test('an Ankorstore order for a new retailer creates the customer, and a pack size that is not the product units is skipped', function () {
    $reference = random_int(10000000, 99999999);
    $wrongPack = $reference + 1;
    $email     = 'new'.$reference.'@example.com';

    $cheaper  = $reference + 2;
    $order    = ankorstoreOrder($reference, $email, [['ANKTEST-01', 1, 1]], brandTotal: $this->price);
    $wrong    = ankorstoreOrder($wrongPack, $email, [['ANKTEST-01', 1, 6]], brandTotal: $this->price);
    $cheap    = ankorstoreOrder($cheaper, $email, [['ANKTEST-01', 1, 1]], brandTotal: $this->price - 1);
    $this->ankorstoreOrders = [$order['data']['id'] => $order, $wrong['data']['id'] => $wrong, $cheap['data']['id'] => $cheap];

    GetAnkorstoreOrders::run($this->shop);

    $imported = Order::where('shop_id', $this->shop->id)->where('external_id', (string)$reference)->sole();
    expect($imported->customer->email)->toBe($email)
        ->and($imported->customer->company_name)->toBe("L'Univers d'Alice")
        ->and($imported->customer->address->country_code)->toBe('FR')
        ->and($this->shop->refresh()->settings['ankorstore']['skipped_orders'][$wrongPack]['reason'])->toContain('ANKTEST-01 comes in packs of 3, Ankorstore sells packs of 6')
        ->and($this->shop->settings['ankorstore']['skipped_orders'][$cheaper]['reason'])->toContain("less than the shop's prices")
        ->and(Order::where('shop_id', $this->shop->id)->where('external_id', (string)$cheaper)->exists())->toBeFalse();

    $this->product->updateQuietly(['units' => 6]);
    GetAnkorstoreOrders::run($this->shop);

    expect(Order::where('shop_id', $this->shop->id)->where('external_id', (string)$wrongPack)->exists())->toBeFalse();
});

test('an Ankorstore retailer under another email is found by its VAT number, and an order Ankorstore refused to accept is accepted on the next run', function () {
    $reference = random_int(10000000, 99999999);
    $vatDigits = (string)random_int(10000000000, 99999999999);

    $customer = StoreCustomer::make()->action($this->shop, Customer::factory()->definition());
    StoreTaxNumber::make()->handle($customer, ['number' => $vatDigits, 'country_id' => Country::where('code', 'FR')->value('id')], strict: false);

    $order = ankorstoreOrder($reference, 'other'.$reference.'@example.com', [['ANKTEST-01', 1, 1]], 'ankor_confirmed', 'FR '.$vatDigits, $this->price);
    $this->ankorstoreOrders = [$order['data']['id'] => $order];
    $this->refuseTransition = true;

    GetAnkorstoreOrders::run($this->shop);

    $imported = Order::where('shop_id', $this->shop->id)->where('external_id', (string)$reference)->sole();
    expect($imported->customer_id)->toBe($customer->id)
        ->and($this->shop->refresh()->settings['ankorstore']['skipped_orders'][$reference]['reason'])->toContain('but not sent on');

    $this->refuseTransition = false;
    GetAnkorstoreOrders::run($this->shop);

    expect(collect($this->sent)->where(1, 'orders/'.$order['data']['id'].'/-actions/transition')->count())->toBe(1)
        ->and(data_get($this->shop->refresh()->settings, "ankorstore.skipped_orders.$reference"))->toBeNull()
        ->and(Order::where('shop_id', $this->shop->id)->where('external_id', (string)$reference)->count())->toBe(1);
});
