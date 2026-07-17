<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 17 Jul 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Accounting\OrderPaymentApiPoint\StoreOrderPaymentApiPoint;
use App\Actions\Accounting\TopUpPaymentApiPoint\StoreTopUpPaymentApiPoint;
use App\Enums\Accounting\TopUpPaymentApiPoint\TopUpPaymentApiPointStateEnum;
use App\Models\Accounting\TopUpPaymentApiPoint;
use App\Actions\Accounting\OrgPaymentServiceProvider\StoreOrgPaymentServiceProvider;
use App\Actions\Accounting\Payment\CheckoutCom\PreProcessCheckoutComPaymentGatewayLog;
use App\Actions\Accounting\Payment\CheckoutCom\ProcessCheckoutComPaymentGatewayLog;
use App\Actions\Accounting\Payment\CheckoutCom\SweepStuckCheckoutComPaymentApiPoints;
use App\Actions\Accounting\PaymentAccount\StorePaymentAccount;
use App\Actions\Accounting\PaymentAccountShop\StorePaymentAccountShop;
use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Enums\Accounting\OrderPaymentApiPoint\OrderPaymentApiPointStateEnum;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Enums\Accounting\PaymentAccountShop\PaymentAccountShopStateEnum;
use App\Enums\Accounting\PaymentGatewayLog\PaymentGatewayLogStateEnum;
use App\Enums\Accounting\PaymentGatewayLog\PaymentGatewayLogStatusEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Accounting\OrderPaymentApiPoint;
use App\Models\Accounting\PaymentAccount;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\Accounting\PaymentServiceProvider;
use App\Models\Helpers\Address;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;

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

    $this->group = $this->organisation->group;

    list(
        $this->tradeUnit,
        $this->product
    ) = createProduct($this->shop);

    $this->customer = createCustomer($this->shop);

    config(['app.server_name' => 'test-server']);

    actingAs($this->user);
});

function createCheckoutPaymentAccountShop($organisation, $shop): PaymentAccountShop
{
    $paymentServiceProvider = PaymentServiceProvider::where('code', 'checkout')->first();

    $orgPaymentServiceProvider = StoreOrgPaymentServiceProvider::make()->action(
        paymentServiceProvider: $paymentServiceProvider,
        organisation: $organisation,
        modelData: PaymentServiceProvider::factory()->definition()
    );

    $paymentAccountData = PaymentAccount::factory()->definition();
    data_set($paymentAccountData, 'type', PaymentAccountTypeEnum::CHECKOUT->value);

    $paymentAccount = StorePaymentAccount::make()->action($orgPaymentServiceProvider, $paymentAccountData);

    return StorePaymentAccountShop::make()->action(
        $paymentAccount,
        $shop,
        [
            'currency_id' => $shop->currency_id,
            'state'       => PaymentAccountShopStateEnum::ACTIVE
        ]
    );
}

function createOrderWithCheckoutApiPoint($customer, $product, PaymentAccountShop $paymentAccountShop): array
{
    $modelData = Order::factory()->definition();
    data_set($modelData, 'billing_address', new Address(Address::factory()->definition()));
    data_set($modelData, 'delivery_address', new Address(Address::factory()->definition()));

    $order = StoreOrder::make()->action($customer, $modelData);

    StoreTransaction::make()->action($order, $product->historicAsset, Transaction::factory()->definition());
    $order->refresh();

    $orderPaymentApiPoint = StoreOrderPaymentApiPoint::run($order);
    $orderPaymentApiPoint->update([
        'data' => [
            'payment_methods' => [
                'checkout' => $paymentAccountShop->id
            ]
        ]
    ]);

    return [$order, $orderPaymentApiPoint];
}

function fakeCheckoutComWebhookPayload(string $eventType, string $eventId, string $paymentId, OrderPaymentApiPoint $orderPaymentApiPoint, int $amount): array
{
    return [
        'id'   => $eventId,
        'type' => $eventType,
        'data' => [
            'id'           => $paymentId,
            'amount'       => $amount,
            'currency'     => $orderPaymentApiPoint->order->currency->code,
            'processed_on' => now()->toIso8601String(),
            'metadata'     => [
                'origin'       => 'aiku',
                'operation'    => 'order',
                'api_point_id' => $orderPaymentApiPoint->id,
                'environment'  => app()->environment(),
                'server'       => config('app.server_name'),
            ],
        ],
    ];
}

test('webhook payment_captured pays and submits the order', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1);

    $paymentAccountShop = createCheckoutPaymentAccountShop($this->organisation, $this->shop);
    list($order, $orderPaymentApiPoint) = createOrderWithCheckoutApiPoint($this->customer, $this->product, $paymentAccountShop);

    expect($order->state)->toBe(OrderStateEnum::CREATING)
        ->and($orderPaymentApiPoint->state)->toBe(OrderPaymentApiPointStateEnum::IN_PROCESS);

    ProcessCheckoutComPaymentGatewayLog::partialMock()
        ->shouldReceive('getCheckOutPayment')
        ->andReturn([
            'id'     => 'pay_test_captured',
            'status' => 'Captured',
            'amount' => 2500,
            'source' => ['type' => 'card'],
        ]);

    $paymentGatewayLog = $this->group->paymentGatewayLogs()->create([
        'payload' => fakeCheckoutComWebhookPayload('payment_captured', 'evt_test_captured', 'pay_test_captured', $orderPaymentApiPoint, 2500),
        'gateway' => 'checkout-com',
    ]);

    PreProcessCheckoutComPaymentGatewayLog::run($paymentGatewayLog);

    $paymentGatewayLog->refresh();
    $orderPaymentApiPoint->refresh();
    $order->refresh();

    expect($paymentGatewayLog->state)->toBe(PaymentGatewayLogStateEnum::PROCESSED)
        ->and($paymentGatewayLog->status)->toBe(PaymentGatewayLogStatusEnum::OK)
        ->and($paymentGatewayLog->operation)->toBe('order')
        ->and($paymentGatewayLog->order_id)->toBe($order->id)
        ->and($paymentGatewayLog->payment_id)->not->toBeNull()
        ->and($orderPaymentApiPoint->state)->toBe(OrderPaymentApiPointStateEnum::SUCCESS)
        ->and($order->state)->toBe(OrderStateEnum::SUBMITTED)
        ->and($order->payments()->count())->toBe(1)
        ->and($order->payments()->first()->reference)->toBe('pay_test_captured')
        ->and((float)$order->payment_amount)->toBe(25.0);

    return [$order, $orderPaymentApiPoint, $paymentAccountShop];
});

test('duplicated webhook event is flagged and does not double pay', function (array $context) {
    list($order, $orderPaymentApiPoint) = $context;

    $duplicatedLog = $this->group->paymentGatewayLogs()->create([
        'payload' => fakeCheckoutComWebhookPayload('payment_captured', 'evt_test_captured', 'pay_test_captured', $orderPaymentApiPoint, 2500),
        'gateway' => 'checkout-com',
    ]);

    PreProcessCheckoutComPaymentGatewayLog::run($duplicatedLog);

    $duplicatedLog->refresh();
    $order->refresh();

    expect($duplicatedLog->status)->toBe(PaymentGatewayLogStatusEnum::DUPLICATED)
        ->and($duplicatedLog->state)->toBe(PaymentGatewayLogStateEnum::PROCESSED)
        ->and($order->payments()->count())->toBe(1)
        ->and((float)$order->payment_amount)->toBe(25.0);

    return $context;
})->depends('webhook payment_captured pays and submits the order');

test('second capture event for an already paid api point does not double pay', function (array $context) {
    list($order, $orderPaymentApiPoint) = $context;

    $secondLog = $this->group->paymentGatewayLogs()->create([
        'payload' => fakeCheckoutComWebhookPayload('payment_captured', 'evt_test_captured_2', 'pay_test_captured', $orderPaymentApiPoint, 2500),
        'gateway' => 'checkout-com',
    ]);

    PreProcessCheckoutComPaymentGatewayLog::run($secondLog);

    $secondLog->refresh();
    $order->refresh();

    expect($secondLog->state)->toBe(PaymentGatewayLogStateEnum::PROCESSED)
        ->and($secondLog->status)->toBe(PaymentGatewayLogStatusEnum::OK)
        ->and($order->payments()->count())->toBe(1);

    return $context;
})->depends('webhook payment_captured pays and submits the order');

test('failure event after success does not clobber the api point', function (array $context) {
    list($order, $orderPaymentApiPoint) = $context;

    $failureLog = $this->group->paymentGatewayLogs()->create([
        'payload' => fakeCheckoutComWebhookPayload('payment_declined', 'evt_test_late_decline', 'pay_test_captured', $orderPaymentApiPoint, 2500),
        'gateway' => 'checkout-com',
    ]);

    PreProcessCheckoutComPaymentGatewayLog::run($failureLog);

    $failureLog->refresh();
    $orderPaymentApiPoint->refresh();
    $order->refresh();

    expect($failureLog->state)->toBe(PaymentGatewayLogStateEnum::PROCESSED)
        ->and($orderPaymentApiPoint->state)->toBe(OrderPaymentApiPointStateEnum::SUCCESS)
        ->and($order->state)->toBe(OrderStateEnum::SUBMITTED);
})->depends('webhook payment_captured pays and submits the order');

test('a second distinct captured payment is recorded without double submitting the order', function (array $context) {
    list($order, $orderPaymentApiPoint) = $context;

    GetCurrencyExchange::shouldRun()->andReturn(1);

    ProcessCheckoutComPaymentGatewayLog::partialMock()
        ->shouldReceive('getCheckOutPayment')
        ->andReturn([
            'id'     => 'pay_test_second_charge',
            'status' => 'Captured',
            'amount' => 2500,
            'source' => ['type' => 'card'],
        ]);

    $secondChargeLog = $this->group->paymentGatewayLogs()->create([
        'payload' => fakeCheckoutComWebhookPayload('payment_captured', 'evt_test_second_charge', 'pay_test_second_charge', $orderPaymentApiPoint, 2500),
        'gateway' => 'checkout-com',
    ]);

    PreProcessCheckoutComPaymentGatewayLog::run($secondChargeLog);

    $secondChargeLog->refresh();
    $order->refresh();

    expect($secondChargeLog->state)->toBe(PaymentGatewayLogStateEnum::PROCESSED)
        ->and($secondChargeLog->status)->toBe(PaymentGatewayLogStatusEnum::OK)
        ->and($order->payments()->count())->toBe(2)
        ->and($order->state)->toBe(OrderStateEnum::SUBMITTED)
        ->and((float)$order->payment_amount)->toBe(50.0);
})->depends('webhook payment_captured pays and submits the order');

test('declined redirect followed by captured webhook recovers the order', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1);

    $paymentAccountShop = createCheckoutPaymentAccountShop($this->organisation, $this->shop);
    list($order, $orderPaymentApiPoint) = createOrderWithCheckoutApiPoint($this->customer, $this->product, $paymentAccountShop);

    $declinedLog = $this->group->paymentGatewayLogs()->create([
        'payload' => fakeCheckoutComWebhookPayload('payment_declined', 'evt_test_recovery_decline', 'pay_test_recovery', $orderPaymentApiPoint, 2500),
        'gateway' => 'checkout-com',
    ]);
    PreProcessCheckoutComPaymentGatewayLog::run($declinedLog);

    $orderPaymentApiPoint->refresh();
    expect($orderPaymentApiPoint->state)->toBe(OrderPaymentApiPointStateEnum::FAILURE);

    ProcessCheckoutComPaymentGatewayLog::partialMock()
        ->shouldReceive('getCheckOutPayment')
        ->andReturn([
            'id'     => 'pay_test_recovery',
            'status' => 'Captured',
            'amount' => 2500,
            'source' => ['type' => 'card'],
        ]);

    $capturedLog = $this->group->paymentGatewayLogs()->create([
        'payload' => fakeCheckoutComWebhookPayload('payment_captured', 'evt_test_recovery_capture', 'pay_test_recovery', $orderPaymentApiPoint, 2500),
        'gateway' => 'checkout-com',
    ]);
    PreProcessCheckoutComPaymentGatewayLog::run($capturedLog);

    $capturedLog->refresh();
    $orderPaymentApiPoint->refresh();
    $order->refresh();

    expect($capturedLog->state)->toBe(PaymentGatewayLogStateEnum::PROCESSED)
        ->and($capturedLog->status)->toBe(PaymentGatewayLogStatusEnum::OK)
        ->and($orderPaymentApiPoint->state)->toBe(OrderPaymentApiPointStateEnum::SUCCESS)
        ->and($order->state)->toBe(OrderStateEnum::SUBMITTED)
        ->and($order->payments()->count())->toBe(1)
        ->and($order->payments()->first()->reference)->toBe('pay_test_recovery');
});

test('failure redirect does not clobber a webhook confirmed success', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1);

    $paymentAccountShop = createCheckoutPaymentAccountShop($this->organisation, $this->shop);
    list($order, $orderPaymentApiPoint) = createOrderWithCheckoutApiPoint($this->customer, $this->product, $paymentAccountShop);

    ProcessCheckoutComPaymentGatewayLog::partialMock()
        ->shouldReceive('getCheckOutPayment')
        ->andReturn([
            'id'     => 'pay_test_late_failure',
            'status' => 'Captured',
            'amount' => 2500,
            'source' => ['type' => 'card'],
        ]);

    $capturedLog = $this->group->paymentGatewayLogs()->create([
        'payload' => fakeCheckoutComWebhookPayload('payment_captured', 'evt_test_late_failure_capture', 'pay_test_late_failure', $orderPaymentApiPoint, 2500),
        'gateway' => 'checkout-com',
    ]);
    PreProcessCheckoutComPaymentGatewayLog::run($capturedLog);

    $orderPaymentApiPoint->refresh();
    expect($orderPaymentApiPoint->state)->toBe(OrderPaymentApiPointStateEnum::SUCCESS);

    $redirectedApiPoint = App\Actions\Accounting\OrderPaymentApiPoint\WebHooks\CheckoutComOrderPaymentFailure::make()->handle(
        $orderPaymentApiPoint,
        ['cko-payment-id' => 'pay_test_late_failure']
    );

    $orderPaymentApiPoint->refresh();
    $order->refresh();

    expect($redirectedApiPoint->state)->toBe(OrderPaymentApiPointStateEnum::SUCCESS)
        ->and($orderPaymentApiPoint->state)->toBe(OrderPaymentApiPointStateEnum::SUCCESS)
        ->and($order->state)->toBe(OrderStateEnum::SUBMITTED);
});

test('webhook payment_declined marks the api point as failure and leaves the order in basket', function () {
    $paymentAccountShop = createCheckoutPaymentAccountShop($this->organisation, $this->shop);
    list($order, $orderPaymentApiPoint) = createOrderWithCheckoutApiPoint($this->customer, $this->product, $paymentAccountShop);

    $paymentGatewayLog = $this->group->paymentGatewayLogs()->create([
        'payload' => fakeCheckoutComWebhookPayload('payment_declined', 'evt_test_declined', 'pay_test_declined', $orderPaymentApiPoint, 2500),
        'gateway' => 'checkout-com',
    ]);

    PreProcessCheckoutComPaymentGatewayLog::run($paymentGatewayLog);

    $paymentGatewayLog->refresh();
    $orderPaymentApiPoint->refresh();
    $order->refresh();

    expect($paymentGatewayLog->state)->toBe(PaymentGatewayLogStateEnum::PROCESSED)
        ->and($paymentGatewayLog->status)->toBe(PaymentGatewayLogStatusEnum::OK)
        ->and($orderPaymentApiPoint->state)->toBe(OrderPaymentApiPointStateEnum::FAILURE)
        ->and($order->state)->toBe(OrderStateEnum::CREATING)
        ->and($order->payments()->count())->toBe(0);
});

test('captured event with pending gateway status is left for retry', function () {
    $paymentAccountShop = createCheckoutPaymentAccountShop($this->organisation, $this->shop);
    list($order, $orderPaymentApiPoint) = createOrderWithCheckoutApiPoint($this->customer, $this->product, $paymentAccountShop);

    ProcessCheckoutComPaymentGatewayLog::partialMock()
        ->shouldReceive('getCheckOutPayment')
        ->andReturn([
            'id'     => 'pay_test_pending',
            'status' => 'Pending',
            'amount' => 2500,
        ]);

    $paymentGatewayLog = $this->group->paymentGatewayLogs()->create([
        'payload' => fakeCheckoutComWebhookPayload('payment_captured', 'evt_test_pending', 'pay_test_pending', $orderPaymentApiPoint, 2500),
        'gateway' => 'checkout-com',
    ]);

    PreProcessCheckoutComPaymentGatewayLog::run($paymentGatewayLog);

    $paymentGatewayLog->refresh();
    $orderPaymentApiPoint->refresh();

    expect($paymentGatewayLog->state)->toBe(PaymentGatewayLogStateEnum::PREPROCESSED)
        ->and($paymentGatewayLog->status)->toBe(PaymentGatewayLogStatusEnum::PROCESSING)
        ->and($orderPaymentApiPoint->state)->toBe(OrderPaymentApiPointStateEnum::IN_PROCESS);
});

function createTopUpApiPointForCheckout($customer, PaymentAccountShop $paymentAccountShop): TopUpPaymentApiPoint
{
    $topUpPaymentApiPoint = StoreTopUpPaymentApiPoint::run($customer, ['amount' => 25]);
    $topUpPaymentApiPoint->update([
        'data' => [
            'payment_account_shop_id' => [
                'checkout' => $paymentAccountShop->id
            ]
        ]
    ]);

    return $topUpPaymentApiPoint;
}

function fakeTopUpWebhookPayload(string $eventType, string $eventId, string $paymentId, TopUpPaymentApiPoint $topUpPaymentApiPoint, int $amount): array
{
    return [
        'id'   => $eventId,
        'type' => $eventType,
        'data' => [
            'id'           => $paymentId,
            'amount'       => $amount,
            'currency'     => $topUpPaymentApiPoint->customer->shop->currency->code,
            'processed_on' => now()->toIso8601String(),
            'metadata'     => [
                'origin'         => 'aiku',
                'operation'      => 'top_up',
                'api_point_ulid' => $topUpPaymentApiPoint->ulid,
                'environment'    => app()->environment(),
                'server'         => config('app.server_name'),
            ],
        ],
    ];
}

test('webhook payment_captured processes a top up', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1);

    $paymentAccountShop   = createCheckoutPaymentAccountShop($this->organisation, $this->shop);
    $topUpPaymentApiPoint = createTopUpApiPointForCheckout($this->customer, $paymentAccountShop);
    $balanceBefore        = (float)$this->customer->balance;

    ProcessCheckoutComPaymentGatewayLog::partialMock()
        ->shouldReceive('getCheckOutPayment')
        ->andReturn([
            'id'     => 'pay_test_topup',
            'status' => 'Captured',
            'amount' => 2500,
            'source' => ['type' => 'card'],
        ]);

    $paymentGatewayLog = $this->group->paymentGatewayLogs()->create([
        'payload' => fakeTopUpWebhookPayload('payment_captured', 'evt_test_topup', 'pay_test_topup', $topUpPaymentApiPoint, 2500),
        'gateway' => 'checkout-com',
    ]);

    PreProcessCheckoutComPaymentGatewayLog::run($paymentGatewayLog);

    $paymentGatewayLog->refresh();
    $topUpPaymentApiPoint->refresh();
    $this->customer->refresh();

    $creditTransaction = App\Models\Accounting\CreditTransaction::find(data_get($topUpPaymentApiPoint->data, 'credit_transaction_id'));

    expect($paymentGatewayLog->state)->toBe(PaymentGatewayLogStateEnum::PROCESSED)
        ->and($paymentGatewayLog->status)->toBe(PaymentGatewayLogStatusEnum::OK)
        ->and($paymentGatewayLog->payment_id)->not->toBeNull()
        ->and($topUpPaymentApiPoint->state)->toBe(TopUpPaymentApiPointStateEnum::SUCCESS)
        ->and($creditTransaction)->not->toBeNull()
        ->and((float)$creditTransaction->amount)->toBe(25.0)
        ->and((float)$this->customer->balance)->toBe($balanceBefore + 25.0);

    return [$topUpPaymentApiPoint, $paymentAccountShop, (float)$this->customer->balance];
});

test('duplicate top up capture does not double credit', function (array $context) {
    list($topUpPaymentApiPoint, $paymentAccountShop, $balanceAfterTopUp) = $context;

    $secondLog = $this->group->paymentGatewayLogs()->create([
        'payload' => fakeTopUpWebhookPayload('payment_captured', 'evt_test_topup_2', 'pay_test_topup', $topUpPaymentApiPoint, 2500),
        'gateway' => 'checkout-com',
    ]);

    PreProcessCheckoutComPaymentGatewayLog::run($secondLog);

    $secondLog->refresh();
    $customer = $topUpPaymentApiPoint->customer->refresh();

    expect($secondLog->state)->toBe(PaymentGatewayLogStateEnum::PROCESSED)
        ->and($secondLog->status)->toBe(PaymentGatewayLogStatusEnum::OK)
        ->and((float)$customer->balance)->toBe($balanceAfterTopUp);
})->depends('webhook payment_captured processes a top up');

test('webhook payment_declined marks the top up api point as failure', function () {
    $paymentAccountShop   = createCheckoutPaymentAccountShop($this->organisation, $this->shop);
    $topUpPaymentApiPoint = createTopUpApiPointForCheckout($this->customer, $paymentAccountShop);
    $balanceBefore        = (float)$this->customer->balance;

    $paymentGatewayLog = $this->group->paymentGatewayLogs()->create([
        'payload' => fakeTopUpWebhookPayload('payment_declined', 'evt_test_topup_declined', 'pay_test_topup_declined', $topUpPaymentApiPoint, 2500),
        'gateway' => 'checkout-com',
    ]);

    PreProcessCheckoutComPaymentGatewayLog::run($paymentGatewayLog);

    $paymentGatewayLog->refresh();
    $topUpPaymentApiPoint->refresh();
    $this->customer->refresh();

    expect($paymentGatewayLog->state)->toBe(PaymentGatewayLogStateEnum::PROCESSED)
        ->and($paymentGatewayLog->status)->toBe(PaymentGatewayLogStatusEnum::OK)
        ->and($topUpPaymentApiPoint->state)->toBe(TopUpPaymentApiPointStateEnum::FAILURE)
        ->and((float)$this->customer->balance)->toBe($balanceBefore);
});

test('sweeper recovers a stuck order whose payment was captured', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1);

    $paymentAccountShop = createCheckoutPaymentAccountShop($this->organisation, $this->shop);
    list($order, $orderPaymentApiPoint) = createOrderWithCheckoutApiPoint($this->customer, $this->product, $paymentAccountShop);

    $order->update(['reference' => uniqid('sweep-test-')]);
    $orderPaymentApiPoint->update(['created_at' => now()->subHours(2)]);

    SweepStuckCheckoutComPaymentApiPoints::partialMock()
        ->shouldReceive('getCheckOutPaymentsByReference')
        ->andReturnUsing(function ($paymentAccountShop, $reference) use ($order, $orderPaymentApiPoint) {
            if ($reference != $order->reference) {
                return ['data' => []];
            }

            return [
                'data' => [
                    [
                        'id'       => 'pay_test_swept',
                        'status'   => 'Captured',
                        'amount'   => 2500,
                        'source'   => ['type' => 'card'],
                        'metadata' => ['api_point_id' => $orderPaymentApiPoint->id],
                    ]
                ]
            ];
        });

    $stats = SweepStuckCheckoutComPaymentApiPoints::run();

    $orderPaymentApiPoint->refresh();
    $order->refresh();

    expect($stats['orders_recovered'])->toBe(1)
        ->and($orderPaymentApiPoint->state)->toBe(OrderPaymentApiPointStateEnum::SUCCESS)
        ->and($order->state)->toBe(OrderStateEnum::SUBMITTED)
        ->and($order->payments()->where('reference', 'pay_test_swept')->count())->toBe(1);
});

test('sweeper leaves abandoned baskets alone and does not recheck them', function () {
    $paymentAccountShop = createCheckoutPaymentAccountShop($this->organisation, $this->shop);
    list($order, $orderPaymentApiPoint) = createOrderWithCheckoutApiPoint($this->customer, $this->product, $paymentAccountShop);

    $orderPaymentApiPoint->update(['created_at' => now()->subHours(2)]);

    SweepStuckCheckoutComPaymentApiPoints::partialMock()
        ->shouldReceive('getCheckOutPaymentsByReference')
        ->andReturn(['data' => []]);

    $stats = SweepStuckCheckoutComPaymentApiPoints::run();

    $orderPaymentApiPoint->refresh();
    $order->refresh();

    expect($stats['orders_recovered'])->toBe(0)
        ->and($orderPaymentApiPoint->state)->toBe(OrderPaymentApiPointStateEnum::IN_PROCESS)
        ->and(data_get($orderPaymentApiPoint->data, 'swept_at'))->not->toBeNull()
        ->and($order->state)->toBe(OrderStateEnum::CREATING);

    $statsSecondRun = SweepStuckCheckoutComPaymentApiPoints::run();
    expect($statsSecondRun['orders_checked'])->toBe(0);
});

test('sweeper recovers a stuck top up whose payment was captured', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1);

    $paymentAccountShop   = createCheckoutPaymentAccountShop($this->organisation, $this->shop);
    $topUpPaymentApiPoint = createTopUpApiPointForCheckout($this->customer, $paymentAccountShop);
    $balanceBefore        = (float)$this->customer->balance;

    $topUpPaymentApiPoint->update(['created_at' => now()->subHours(2)]);

    SweepStuckCheckoutComPaymentApiPoints::partialMock()
        ->shouldReceive('getCheckOutPaymentsByReference')
        ->andReturnUsing(function ($paymentAccountShop, $reference) use ($topUpPaymentApiPoint) {
            if ($reference != $topUpPaymentApiPoint->ulid) {
                return ['data' => []];
            }

            return [
                'data' => [
                    [
                        'id'     => 'pay_test_swept_topup',
                        'status' => 'Captured',
                        'amount' => 2500,
                        'source' => ['type' => 'card'],
                    ]
                ]
            ];
        });

    $stats = SweepStuckCheckoutComPaymentApiPoints::run();

    $topUpPaymentApiPoint->refresh();
    $this->customer->refresh();

    expect($stats['top_ups_recovered'])->toBe(1)
        ->and($topUpPaymentApiPoint->state)->toBe(TopUpPaymentApiPointStateEnum::SUCCESS)
        ->and((float)$this->customer->balance)->toBe($balanceBefore + 25.0);
});

function fakeMitWebhookPayload(string $eventType, string $eventId, string $paymentId, Order $order, int $amount): array
{
    return [
        'id'   => $eventId,
        'type' => $eventType,
        'data' => [
            'id'           => $paymentId,
            'amount'       => $amount,
            'currency'     => $order->currency->code,
            'processed_on' => now()->toIso8601String(),
            'metadata'     => [
                'origin'      => 'aiku',
                'operation'   => 'mit',
                'order_id'    => $order->id,
                'environment' => app()->environment(),
                'server'      => config('app.server_name'),
            ],
        ],
    ];
}

test('mit captured webhook links the existing payment record', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1);

    $paymentAccountShop = createCheckoutPaymentAccountShop($this->organisation, $this->shop);
    list($order) = createOrderWithCheckoutApiPoint($this->customer, $this->product, $paymentAccountShop);

    $payment = App\Actions\Accounting\Payment\StorePayment::make()->action($this->customer, $paymentAccountShop->paymentAccount, [
        'reference'               => 'pay_mit_linked',
        'amount'                  => 25,
        'status'                  => App\Enums\Accounting\Payment\PaymentStatusEnum::SUCCESS,
        'state'                   => App\Enums\Accounting\Payment\PaymentStateEnum::COMPLETED,
        'type'                    => App\Enums\Accounting\Payment\PaymentTypeEnum::PAYMENT,
        'payment_account_shop_id' => $paymentAccountShop->id,
    ]);

    $paymentGatewayLog = $this->group->paymentGatewayLogs()->create([
        'payload' => fakeMitWebhookPayload('payment_captured', 'evt_test_mit_linked', 'pay_mit_linked', $order, 2500),
        'gateway' => 'checkout-com',
    ]);

    PreProcessCheckoutComPaymentGatewayLog::run($paymentGatewayLog);

    $paymentGatewayLog->refresh();

    expect($paymentGatewayLog->state)->toBe(PaymentGatewayLogStateEnum::PROCESSED)
        ->and($paymentGatewayLog->status)->toBe(PaymentGatewayLogStatusEnum::OK)
        ->and($paymentGatewayLog->operation)->toBe('mit')
        ->and($paymentGatewayLog->payment_id)->toBe($payment->id);
});

test('mit captured webhook recovers a lost payment without submitting the order', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1);

    $paymentAccountShop = createCheckoutPaymentAccountShop($this->organisation, $this->shop);
    list($order) = createOrderWithCheckoutApiPoint($this->customer, $this->product, $paymentAccountShop);

    ProcessCheckoutComPaymentGatewayLog::partialMock()
        ->shouldReceive('getCheckOutPayment')
        ->andReturn([
            'id'     => 'pay_mit_lost',
            'status' => 'Captured',
            'amount' => 2500,
            'source' => ['type' => 'card'],
        ]);

    $paymentGatewayLog = $this->group->paymentGatewayLogs()->create([
        'payload' => fakeMitWebhookPayload('payment_captured', 'evt_test_mit_lost', 'pay_mit_lost', $order, 2500),
        'gateway' => 'checkout-com',
    ]);

    PreProcessCheckoutComPaymentGatewayLog::run($paymentGatewayLog);

    $paymentGatewayLog->refresh();
    $order->refresh();

    expect($paymentGatewayLog->state)->toBe(PaymentGatewayLogStateEnum::PROCESSED)
        ->and($paymentGatewayLog->status)->toBe(PaymentGatewayLogStatusEnum::OK)
        ->and($paymentGatewayLog->payment_id)->not->toBeNull()
        ->and($order->payments()->where('reference', 'pay_mit_lost')->count())->toBe(1)
        ->and($order->state)->toBe(OrderStateEnum::CREATING);
});

test('mit captured webhook sends a late paid submitted order to the warehouse', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1);
    createWarehouse();

    $paymentAccountShop = createCheckoutPaymentAccountShop($this->organisation, $this->shop);
    list($order) = createOrderWithCheckoutApiPoint($this->customer, $this->product, $paymentAccountShop);

    $order = App\Actions\Ordering\Order\UpdateState\SubmitOrder::make()->action($order);
    expect($order->state)->toBe(OrderStateEnum::SUBMITTED);

    $orderAmountInCents = (int)round((float)$order->total_amount * 100);

    ProcessCheckoutComPaymentGatewayLog::partialMock()
        ->shouldReceive('getCheckOutPayment')
        ->andReturn([
            'id'     => 'pay_mit_late_paid',
            'status' => 'Captured',
            'amount' => $orderAmountInCents,
            'source' => ['type' => 'card'],
        ]);

    $paymentGatewayLog = $this->group->paymentGatewayLogs()->create([
        'payload' => fakeMitWebhookPayload('payment_captured', 'evt_test_mit_late_paid', 'pay_mit_late_paid', $order, $orderAmountInCents),
        'gateway' => 'checkout-com',
    ]);

    PreProcessCheckoutComPaymentGatewayLog::run($paymentGatewayLog);

    $paymentGatewayLog->refresh();
    $order->refresh();

    expect($paymentGatewayLog->state)->toBe(PaymentGatewayLogStateEnum::PROCESSED)
        ->and($paymentGatewayLog->status)->toBe(PaymentGatewayLogStatusEnum::OK)
        ->and($order->pay_status->value)->toBe('paid')
        ->and($order->state)->toBe(OrderStateEnum::IN_WAREHOUSE)
        ->and($order->deliveryNotes()->count())->toBe(1);
});

test('mit capture declined after being recorded as paid flags the log for review', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1);

    $paymentAccountShop = createCheckoutPaymentAccountShop($this->organisation, $this->shop);
    list($order) = createOrderWithCheckoutApiPoint($this->customer, $this->product, $paymentAccountShop);

    $payment = App\Actions\Accounting\Payment\StorePayment::make()->action($this->customer, $paymentAccountShop->paymentAccount, [
        'reference'               => 'pay_mit_ghost',
        'amount'                  => 25,
        'status'                  => App\Enums\Accounting\Payment\PaymentStatusEnum::SUCCESS,
        'state'                   => App\Enums\Accounting\Payment\PaymentStateEnum::COMPLETED,
        'type'                    => App\Enums\Accounting\Payment\PaymentTypeEnum::PAYMENT,
        'payment_account_shop_id' => $paymentAccountShop->id,
    ]);

    $paymentGatewayLog = $this->group->paymentGatewayLogs()->create([
        'payload' => fakeMitWebhookPayload('payment_capture_declined', 'evt_test_mit_ghost', 'pay_mit_ghost', $order, 2500),
        'gateway' => 'checkout-com',
    ]);

    PreProcessCheckoutComPaymentGatewayLog::run($paymentGatewayLog);

    $paymentGatewayLog->refresh();

    expect($paymentGatewayLog->state)->toBe(PaymentGatewayLogStateEnum::PROCESSED)
        ->and($paymentGatewayLog->status)->toBe(PaymentGatewayLogStatusEnum::FAIL)
        ->and($paymentGatewayLog->payment_id)->toBe($payment->id);
});

test('pending poll on a fresh api point completes the payment of the original api point', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1);

    $paymentAccountShop = createCheckoutPaymentAccountShop($this->organisation, $this->shop);
    list($order, $originalApiPoint) = createOrderWithCheckoutApiPoint($this->customer, $this->product, $paymentAccountShop);

    $freshApiPoint = StoreOrderPaymentApiPoint::run($order);
    $freshApiPoint->update([
        'data' => [
            'payment_methods' => [
                'checkout' => $paymentAccountShop->id
            ]
        ]
    ]);

    App\Actions\Accounting\OrderPaymentApiPoint\WebHooks\CheckoutComOrderPaymentCompleted::partialMock()
        ->shouldReceive('getCheckOutPayment')
        ->andReturn([
            'id'       => 'pay_test_pending_poll',
            'status'   => 'Captured',
            'amount'   => 2500,
            'source'   => ['type' => 'card'],
            'metadata' => ['api_point_id' => $originalApiPoint->id],
        ]);

    $result = App\Actions\Accounting\OrderPaymentApiPoint\WebHooks\CheckoutComOrderPaymentCompleted::make()->handle(
        $freshApiPoint,
        ['cko-payment-id' => 'pay_test_pending_poll']
    );

    $originalApiPoint->refresh();
    $order->refresh();

    expect($result['status'])->toBe('success')
        ->and($originalApiPoint->state)->toBe(OrderPaymentApiPointStateEnum::SUCCESS)
        ->and($order->state)->toBe(OrderStateEnum::SUBMITTED)
        ->and($order->payments()->where('reference', 'pay_test_pending_poll')->count())->toBe(1);
});

test('pending poll rejects a payment belonging to another order', function () {
    $paymentAccountShop = createCheckoutPaymentAccountShop($this->organisation, $this->shop);
    list($order, $orderPaymentApiPoint) = createOrderWithCheckoutApiPoint($this->customer, $this->product, $paymentAccountShop);
    list($otherOrder, $otherApiPoint) = createOrderWithCheckoutApiPoint($this->customer, $this->product, $paymentAccountShop);

    App\Actions\Accounting\OrderPaymentApiPoint\WebHooks\CheckoutComOrderPaymentCompleted::partialMock()
        ->shouldReceive('getCheckOutPayment')
        ->andReturn([
            'id'       => 'pay_test_foreign_order',
            'status'   => 'Captured',
            'amount'   => 2500,
            'source'   => ['type' => 'card'],
            'metadata' => ['api_point_id' => $otherApiPoint->id],
        ]);

    $result = App\Actions\Accounting\OrderPaymentApiPoint\WebHooks\CheckoutComOrderPaymentCompleted::make()->handle(
        $orderPaymentApiPoint,
        ['cko-payment-id' => 'pay_test_foreign_order']
    );

    $order->refresh();

    expect($result['status'])->toBe('error')
        ->and($order->state)->toBe(OrderStateEnum::CREATING)
        ->and($order->payments()->count())->toBe(0);
});

test('events from another developer machine are not processed', function () {
    $paymentAccountShop = createCheckoutPaymentAccountShop($this->organisation, $this->shop);
    list($order, $orderPaymentApiPoint) = createOrderWithCheckoutApiPoint($this->customer, $this->product, $paymentAccountShop);

    $payload = fakeCheckoutComWebhookPayload('payment_captured', 'evt_test_foreign_server', 'pay_test_foreign_server', $orderPaymentApiPoint, 2500);
    data_set($payload, 'data.metadata.server', 'some-other-dev-machine');

    $paymentGatewayLog = $this->group->paymentGatewayLogs()->create([
        'payload' => $payload,
        'gateway' => 'checkout-com',
    ]);

    PreProcessCheckoutComPaymentGatewayLog::run($paymentGatewayLog);

    $paymentGatewayLog->refresh();
    $orderPaymentApiPoint->refresh();

    expect($paymentGatewayLog->state)->not->toBe(PaymentGatewayLogStateEnum::PROCESSED)
        ->and($orderPaymentApiPoint->state)->toBe(OrderPaymentApiPointStateEnum::IN_PROCESS);
});

test('production events are not processed outside production', function () {
    $paymentAccountShop = createCheckoutPaymentAccountShop($this->organisation, $this->shop);
    list($order, $orderPaymentApiPoint) = createOrderWithCheckoutApiPoint($this->customer, $this->product, $paymentAccountShop);

    $payload = fakeCheckoutComWebhookPayload('payment_captured', 'evt_test_prod_env', 'pay_test_prod_env', $orderPaymentApiPoint, 2500);
    data_set($payload, 'data.metadata.environment', 'production');

    $paymentGatewayLog = $this->group->paymentGatewayLogs()->create([
        'payload' => $payload,
        'gateway' => 'checkout-com',
    ]);

    PreProcessCheckoutComPaymentGatewayLog::run($paymentGatewayLog);

    $paymentGatewayLog->refresh();
    $orderPaymentApiPoint->refresh();

    expect($paymentGatewayLog->state)->not->toBe(PaymentGatewayLogStateEnum::PROCESSED)
        ->and($orderPaymentApiPoint->state)->toBe(OrderPaymentApiPointStateEnum::IN_PROCESS);
});
