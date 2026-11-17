<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Accounting\BankTransaction\MatchBankTransactions;
use App\Actions\CRM\Customer\StoreCustomer;
use App\Actions\Dropshipping\Orderchamp\GetOrderchampOrders;
use App\Actions\Helpers\TaxNumber\StoreTaxNumber;
use App\Enums\Accounting\BankConnection\BankConnectionProviderEnum;
use App\Enums\Accounting\BankTransaction\BankTransactionDirectionEnum;
use App\Enums\Accounting\BankTransaction\BankTransactionMatchStateEnum;
use App\Enums\Accounting\BankTransaction\BankTransactionStatusEnum;
use App\Enums\Ordering\Order\OrderPayDetailedStatusEnum;
use App\Enums\Ordering\Order\OrderPayStatusEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\SalesChannel\SalesChannelTypeEnum;
use App\Models\Accounting\BankConnection;
use App\Models\Accounting\BankTransaction;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Helpers\Country;
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

    SalesChannel::where('group_id', $this->shop->group_id)->where('code', 'orderchamp')->first()
        ?? \App\Actions\Ordering\SalesChannel\StoreSalesChannel::make()->action($this->shop->group, [
            'code' => 'orderchamp',
            'name' => 'OrderChamp',
            'type' => SalesChannelTypeEnum::MARKETPLACE,
        ]);

    $this->customer = Customer::where('shop_id', $this->shop->id)->where('name', 'Orderchamp B.V. (NL)')->first();
    if (!$this->customer) {
        $this->customer = StoreCustomer::make()->action($this->shop, array_merge(Customer::factory()->definition(), [
            'contact_name' => 'Orderchamp B.V. (NL)',
            'company_name' => 'Orderchamp B.V. (NL)',
        ]));
        StoreTaxNumber::make()->handle($this->customer, ['number' => '859194437B01', 'country_id' => Country::where('code', 'NL')->value('id')], strict: false);
    }

    [, $this->product] = createProduct($this->shop);
    $this->product->updateQuietly(['code' => 'OCTEST-01', 'units' => 6]);

    $settings = $this->shop->settings;
    data_set($settings, 'orderchamp', ['access_token' => 'test-token', 'import_from' => now()->subDay()->toIso8601String()]);
    $this->shop->update(['settings' => $settings]);

    $this->orderchampOrders    = [];
    $this->orderchampCancelled = [];
    $this->settlements         = [];
    Http::fake([
        'api.orderchamp.com/*' => function (Request $request) {
            $query = $request['query'];
            $edges = fn (array $nodes, string $key) => ['data' => [$key => ['pageInfo' => ['hasNextPage' => false, 'endCursor' => null], 'edges' => array_map(fn ($node) => ['node' => $node], $nodes)]]];

            return Http::response(match (true) {
                str_contains($query, 'settlements') => $edges($this->settlements, 'settlements'),
                str_contains($query, 'includeCancelled') => $edges($this->orderchampCancelled, 'orders'),
                default => $edges($this->orderchampOrders, 'orders'),
            });
        },
    ]);
});

afterEach(function () {
    $settings = $this->shop->refresh()->settings;
    unset($settings['orderchamp']);
    $this->shop->update(['settings' => $settings]);
});

function orderchampOrder(string $number, array $lines, array $extra = []): array
{
    return array_merge([
        'id'              => base64_encode('Order:'.$number),
        'number'          => $number,
        'createdAt'       => now()->toIso8601ZuluString(),
        'isTest'          => false,
        'isCancelled'     => false,
        'currency'        => 'EUR',
        'totalPrice'      => (string)round(collect($lines)->sum('subtotalPrice'), 2),
        'commissionPrice' => '4.14',
        'companyName'     => 'Natyra',
        'companyPhone'    => '+32 475 00 00 00',
        'firstName'       => 'Sharon',
        'lastName'        => 'Van Hecke',
        'note'            => null,
        'productsCount'   => count($lines),
        'shippingAddress' => ['companyName' => 'Natyra', 'firstName' => 'Sharon', 'lastName' => 'Van Hecke', 'street' => 'Blauwstraat', 'houseNumber' => '4', 'addressLine2' => null, 'postalCode' => '2850', 'city' => 'Boom', 'region' => null, 'country' => 'BE'],
        'billingAddress'  => ['companyName' => 'Orderchamp B.V.', 'vatNumber' => 'NL859194437B01', 'country' => 'NL'],
        'products'        => ['edges' => array_map(fn (array $line) => ['node' => array_merge(['id' => (string)Str::uuid(), 'title' => 'Test'], $line)], $lines)],
    ], $extra);
}

function orderchampBankLine(Shop $shop, string $reference, float $amount): BankTransaction
{
    $bankConnection = BankConnection::create([
        'group_id'        => $shop->group_id,
        'organisation_id' => $shop->organisation_id,
        'provider'        => BankConnectionProviderEnum::WISE,
        'name'            => 'Orderchamp matching',
        'external_id'     => (string)random_int(10000, 99999),
    ]);

    return BankTransaction::create([
        'group_id'                => $shop->group_id,
        'organisation_id'         => $shop->organisation_id,
        'bank_connection_id'      => $bankConnection->id,
        'provider_transaction_id' => (string)Str::uuid(),
        'provider_type'           => 'TRANSFER',
        'direction'               => BankTransactionDirectionEnum::IN,
        'amount'                  => $amount,
        'currency_id'             => $shop->currency_id,
        'status'                  => BankTransactionStatusEnum::BOOKED,
        'counterparty_name'       => 'ORDERCHAMP BV',
        'reference'               => $reference,
        'booked_at'               => now(),
    ]);
}

test('an Orderchamp order is imported at Orderchamp prices, billed to the Orderchamp company and labelled for the retailer; one that does not fit is skipped, a cancelled one is cancelled', function () {
    $number  = 'OC'.random_int(1000000, 9999999);
    $unknown = 'OC'.random_int(1000000, 9999999);
    $this->orderchampOrders = [
        orderchampOrder($number, [['sku' => 'octest-01 ', 'quantity' => 12, 'caseQuantity' => 6, 'subtotalPrice' => '16.56']]),
        orderchampOrder($unknown, [['sku' => 'NOPE-01', 'quantity' => 4, 'caseQuantity' => 4, 'subtotalPrice' => '9.00']]),
    ];

    GetOrderchampOrders::run($this->shop);
    GetOrderchampOrders::run($this->shop);

    $orders = Order::where('shop_id', $this->shop->id)->where('external_id', $number)->get();
    expect($orders)->toHaveCount(1);
    $order       = $orders->first();
    $transaction = $order->transactions()->where('model_type', 'Product')->sole();

    expect($order->customer_id)->toBe($this->customer->id)
        ->and($order->customer_reference)->toBe($number)
        ->and((float)$order->total_amount)->toBe(16.56)
        ->and((float)$order->commission_amount)->toBe(4.14)
        ->and($order->company_name)->toBe('Natyra')
        ->and($order->contact_name)->toBe('Sharon Van Hecke')
        ->and($order->deliveryAddress->country_code)->toBe('BE')
        ->and($order->deliveryNotes()->sole()->only(['company_name', 'contact_name', 'phone']))->toBe(['company_name' => 'Natyra', 'contact_name' => 'Sharon Van Hecke', 'phone' => '+32 475 00 00 00'])
        ->and($order->state)->toBe(OrderStateEnum::IN_WAREHOUSE)
        ->and($order->pay_detailed_status)->toBe(OrderPayDetailedStatusEnum::TO_BE_PAID_BY_MARKETPLACE)
        ->and((float)$transaction->quantity_ordered)->toBe(2.0)
        ->and((float)$transaction->net_amount)->toBe(16.56)
        ->and(Order::where('shop_id', $this->shop->id)->where('external_id', $unknown)->exists())->toBeFalse()
        ->and($this->shop->refresh()->settings['orderchamp']['skipped_orders'][$unknown]['reason'])->toContain('NOPE-01 is not a product of the shop');

    $this->orderchampOrders    = [];
    $this->orderchampCancelled = [orderchampOrder($number, [], ['isCancelled' => true])];
    GetOrderchampOrders::run($this->shop);

    expect($order->refresh()->state)->toBe(OrderStateEnum::CANCELLED);

    $handKeyed = 'OC'.random_int(1000000, 9999999);
    $order->updateQuietly(['external_id' => null, 'customer_reference' => $handKeyed]);
    $this->orderchampOrders    = [orderchampOrder($handKeyed, [['sku' => 'OCTEST-01', 'quantity' => 6, 'caseQuantity' => 6, 'subtotalPrice' => '8.28']])];
    $this->orderchampCancelled = [];
    GetOrderchampOrders::run($this->shop);

    expect(Order::where('shop_id', $this->shop->id)->where('external_id', $handKeyed)->exists())->toBeFalse();
});

test('an Orderchamp settlement pays every imported order it lists, on one bank line', function () {
    $first  = 'OC'.random_int(1000000, 9999999);
    $second = 'OC'.random_int(1000000, 9999999);
    $this->orderchampOrders = [
        orderchampOrder($first, [['sku' => 'OCTEST-01', 'quantity' => 6, 'caseQuantity' => 6, 'subtotalPrice' => '10.00']]),
        orderchampOrder($second, [['sku' => 'OCTEST-01', 'quantity' => 18, 'caseQuantity' => 6, 'subtotalPrice' => '30.00']]),
    ];
    GetOrderchampOrders::run($this->shop);
    $orders = Order::where('shop_id', $this->shop->id)->whereIn('external_id', [$first, $second])->get()->keyBy('external_id');

    $settlement        = 'STL'.random_int(100000, 999999);
    $this->settlements = [[
        'number'   => $settlement,
        'amount'   => '33.72',
        'currency'      => 'EUR',
        'invoicesCount' => 2,
        'invoices'      => ['edges' => [
            ['node' => ['totalPrice' => '8.43', 'order' => ['number' => $first]]],
            ['node' => ['totalPrice' => '25.29', 'order' => ['number' => $second]]],
        ]],
    ]];

    $paid    = orderchampBankLine($this->shop, "Orderchamp NL payment: $settlement-1/$settlement-1", 33.72);
    $unknown = orderchampBankLine($this->shop, 'Orderchamp NL payment: STL1-1/STL1-1', 50);
    MatchBankTransactions::run($paid->bankConnection);
    MatchBankTransactions::run($unknown->bankConnection);

    $paid->refresh();
    expect($paid->match_state)->toBe(BankTransactionMatchStateEnum::PAID_BY_MARKETPLACE)
        ->and($paid->match_data['order_ids'])->toEqualCanonicalizing($orders->pluck('id')->all())
        ->and($paid->match_data['difference'])->toEqual(0)
        ->and($unknown->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::UNMATCHED);

    foreach ($orders as $order) {
        expect($order->refresh()->pay_status)->toBe(OrderPayStatusEnum::PAID)
            ->and($order->pay_detailed_status)->toBe(OrderPayDetailedStatusEnum::PAID_BY_MARKETPLACE);
    }
});
