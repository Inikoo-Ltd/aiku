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
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Enums\Accounting\PaymentAccountShop\PaymentAccountShopStateEnum;
use App\Enums\Accounting\PaymentGatewayLog\PaymentGatewayLogStateEnum;
use App\Enums\Accounting\PaymentGatewayLog\PaymentGatewayLogStatusEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Accounting\OrderPaymentApiPoint;
use App\Models\Accounting\Payment;
use App\Models\Accounting\PaymentAccount;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\Accounting\PaymentServiceProvider;
use App\Models\Helpers\Address;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
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

    $this->group = $this->organisation->group;

    list(
        $this->tradeUnit,
        $this->product
    ) = createProduct($this->shop);

    $this->customer = createCustomer($this->shop);

    config(['app.server_name' => 'test-server']);
    config(['app.sandbox.checkout_com.payment_channel' => 'pc_test_channel']);

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
        ->and($order->payments()->first()->reference)->toBe('pay_test_recovery')
        ->and(Payment::where('reference', 'pay_test_recovery')->count())->toBe(1)
        ->and(Payment::where('reference', 'pay_test_recovery')->first()->status)->toBe(PaymentStatusEnum::SUCCESS);
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

    GetCurrencyExchange::shouldRun()->andReturn(1);

    $payload = fakeCheckoutComWebhookPayload('payment_declined', 'evt_test_declined', 'pay_test_declined', $orderPaymentApiPoint, 2500);
    $payload['data']['source']           = ['type' => 'card', 'scheme' => 'VISA'];
    $payload['data']['response_summary'] = 'Insufficient Funds';
    $payload['data']['processed_on']     = '2026-03-04T10:20:30Z';

    $paymentGatewayLog = $this->group->paymentGatewayLogs()->create([
        'payload' => $payload,
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

    /** The decline is kept as a failed attempt for the payment methods report, never attached to the order */
    $failedPayment = Payment::where('reference', 'pay_test_declined')->first();
    expect($failedPayment)->not->toBeNull()
        ->and($failedPayment->status)->toBe(PaymentStatusEnum::FAIL)
        ->and($failedPayment->state)->toBe(PaymentStateEnum::DECLINED)
        ->and($failedPayment->method)->toBe('card')
        ->and($failedPayment->sub_method)->toBe('visa')
        ->and((float) $failedPayment->amount)->toBe(25.0)
        ->and($failedPayment->data['checkout_com']['response_summary'])->toBe('Insufficient Funds')
        ->and($failedPayment->date->toDateString())->toBe('2026-03-04')
        ->and($failedPayment->orders()->count())->toBe(0);

    /** The same decline delivered again (duplicate webhook, or redirect after webhook) is stored once */
    $duplicateLog = $this->group->paymentGatewayLogs()->create([
        'payload' => array_merge($payload, ['id' => 'evt_test_declined_again']),
        'gateway' => 'checkout-com',
    ]);
    PreProcessCheckoutComPaymentGatewayLog::run($duplicateLog);

    expect(Payment::where('reference', 'pay_test_declined')->count())->toBe(1);
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
    GetCurrencyExchange::shouldRun()->andReturn(1);

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
        ->and((float)$this->customer->balance)->toBe($balanceBefore)
        ->and(Payment::where('reference', 'pay_test_topup_declined')->where('status', PaymentStatusEnum::FAIL)->exists())->toBeTrue();
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

test('partial card payment settles remainder with balance without float precision errors', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1);

    $paymentAccountShop = createCheckoutPaymentAccountShop($this->organisation, $this->shop);

    list($order, $orderPaymentApiPoint) = createOrderWithCheckoutApiPoint($this->customer, $this->product, $paymentAccountShop);

    $order->update(['total_amount' => 19.88]);
    DB::table('customers')->where('id', $this->customer->id)->update(['balance' => 5.00]);

    ProcessCheckoutComPaymentGatewayLog::partialMock()
        ->shouldReceive('getCheckOutPayment')
        ->andReturn([
            'id'     => 'pay_test_partial_settle',
            'status' => 'Captured',
            'amount' => 1984,
            'source' => ['type' => 'card'],
        ]);

    $paymentGatewayLog = $this->group->paymentGatewayLogs()->create([
        'payload' => fakeCheckoutComWebhookPayload('payment_captured', 'evt_test_partial_settle', 'pay_test_partial_settle', $orderPaymentApiPoint, 1984),
        'gateway' => 'checkout-com',
    ]);

    PreProcessCheckoutComPaymentGatewayLog::run($paymentGatewayLog);

    $paymentGatewayLog->refresh();
    $orderPaymentApiPoint->refresh();
    $order->refresh();

    $balancePayment = $order->payments()->where('payments.reference', 'like', 'cu-%')->first();

    expect($paymentGatewayLog->state)->toBe(PaymentGatewayLogStateEnum::PROCESSED)
        ->and($paymentGatewayLog->status)->toBe(PaymentGatewayLogStatusEnum::OK)
        ->and($orderPaymentApiPoint->state)->toBe(OrderPaymentApiPointStateEnum::SUCCESS)
        ->and($order->state)->toBeIn([OrderStateEnum::SUBMITTED, OrderStateEnum::IN_WAREHOUSE])
        ->and($balancePayment)->not->toBeNull()
        ->and((float)$balancePayment->amount)->toBe(0.04)
        ->and((float)$order->payment_amount)->toBe(19.88);
});

test('settling with zero balance creates no payment record', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1);

    $paymentAccountShop = createCheckoutPaymentAccountShop($this->organisation, $this->shop);

    list($order) = createOrderWithCheckoutApiPoint($this->customer, $this->product, $paymentAccountShop);

    $order->update(['total_amount' => 19.88]);
    DB::table('customers')->where('id', $this->customer->id)->update(['balance' => 0]);

    $result = App\Actions\Retina\Dropshipping\Orders\SettleRetinaOrderWithBalance::run($order->refresh());

    expect($result['success'])->toBeTrue()
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

/** The shipped test dump still carries the old foreign key from
 * mit_saved_cards.payment_account_shop_id onto customers, so the fixture stores the customer
 * id there; nothing in this path reads the column back. Pass the real payment account shop
 * once the dumps are regenerated against the corrected constraint. */
function createMitSavedCard($customer, int $priority): App\Models\Accounting\MitSavedCard
{
    return $customer->mitSavedCard()->create([
        'group_id'                => $customer->group_id,
        'organisation_id'         => $customer->organisation_id,
        'shop_id'                 => $customer->shop_id,
        'payment_account_shop_id' => $customer->id,
        'ulid'                    => (string)Illuminate\Support\Str::ulid(),
        'state'                   => App\Enums\Accounting\MitSavedCard\MitSavedCardStateEnum::SUCCESS->value,
        'token'                   => 'tok_mit_'.$priority,
        'priority'                => $priority,
    ]);
}

test('a declined mit card falls through to the next card by priority', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1);

    $paymentAccountShop = createCheckoutPaymentAccountShop($this->organisation, $this->shop);
    list($order) = createOrderWithCheckoutApiPoint($this->customer, $this->product, $paymentAccountShop);

    $order->update(['total_amount' => 19.88]);
    DB::table('customers')->where('id', $this->customer->id)->update(['balance' => 0]);

    createMitSavedCard($this->customer, 1);
    createMitSavedCard($this->customer, 2);

    $chargedAmounts = [];

    App\Actions\Retina\Dropshipping\Orders\PayOrderWithMitCard::partialMock()
        ->shouldReceive('requestMitPayment')
        ->andReturnUsing(function ($secretKey, $request) use (&$chargedAmounts) {
            $chargedAmounts[] = $request->amount;

            return count($chargedAmounts) == 1
                ? ['id' => 'pay_mit_declined', 'status' => 'Declined', 'approved' => false, 'amount' => 0, 'source' => ['type' => 'card']]
                : ['id' => 'pay_mit_second_card', 'status' => 'Authorized', 'approved' => true, 'amount' => $request->amount, 'source' => ['type' => 'card']];
        });

    App\Actions\Retina\Dropshipping\Orders\PayOrderAsync::run($order->refresh());

    $order->refresh();

    expect($chargedAmounts)->toBe([1988, 1988])
        ->and($order->payments()->where('payments.reference', 'pay_mit_declined')->first()->status)->toBe(PaymentStatusEnum::FAIL)
        ->and($order->payments()->where('payments.reference', 'pay_mit_second_card')->first()->status)->toBe(PaymentStatusEnum::SUCCESS)
        ->and((float)$order->payment_amount)->toBe(19.88);
});

test('mit card is charged only the amount still outstanding after balance', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1);

    $paymentAccountShop = createCheckoutPaymentAccountShop($this->organisation, $this->shop);
    list($order) = createOrderWithCheckoutApiPoint($this->customer, $this->product, $paymentAccountShop);

    $order->update(['total_amount' => 19.88]);
    $balanceBefore = (float)$this->customer->balance;
    DB::table('customers')->where('id', $this->customer->id)->update(['balance' => $balanceBefore + 5.00]);

    createMitSavedCard($this->customer, 1);

    $outstandingAtCharge = null;

    App\Actions\Retina\Dropshipping\Orders\PayOrderWithMitCard::partialMock()
        ->shouldReceive('handle')
        ->andReturnUsing(function ($order) use (&$outstandingAtCharge) {
            $outstandingAtCharge = round($order->total_amount - $order->payment_amount, 2);

            return ['status' => 'ok'];
        });

    App\Actions\Retina\Dropshipping\Orders\PayOrderAsync::run($order->refresh());

    $order->refresh();

    expect($outstandingAtCharge)->toBe(14.88)
        ->and((float)$order->payment_amount)->toBe(5.0)
        ->and((float)$order->payments()->where('payments.reference', 'like', 'cu-%')->first()->amount)->toBe(5.0);
});

test('an already paid order is not charged to the card again', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1);

    $paymentAccountShop = createCheckoutPaymentAccountShop($this->organisation, $this->shop);
    list($order) = createOrderWithCheckoutApiPoint($this->customer, $this->product, $paymentAccountShop);

    $order->update(['total_amount' => 19.88]);
    DB::table('customers')->where('id', $this->customer->id)->update(['balance' => 0]);

    $payment = App\Actions\Accounting\Payment\StorePayment::make()->action($this->customer, $paymentAccountShop->paymentAccount, [
        'reference'               => 'pay_already_settled',
        'amount'                  => 19.88,
        'status'                  => PaymentStatusEnum::SUCCESS,
        'state'                   => PaymentStateEnum::COMPLETED,
        'type'                    => App\Enums\Accounting\Payment\PaymentTypeEnum::PAYMENT,
        'payment_account_shop_id' => $paymentAccountShop->id,
    ]);
    App\Actions\Ordering\Order\AttachPaymentToOrder::make()->action($order, $payment, ['amount' => $payment->amount]);

    createMitSavedCard($this->customer, 1);

    App\Actions\Retina\Dropshipping\Orders\PayOrderWithMitCard::partialMock()
        ->shouldNotReceive('requestMitPayment');

    App\Actions\Retina\Dropshipping\Orders\PayOrderAsync::run($order->refresh());

    $order->refresh();

    expect((float)$order->payment_amount)->toBe(19.88)
        ->and($order->payments()->count())->toBe(1);
});

test('a declined card does not corrupt the amount still outstanding', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1);

    $paymentAccountShop = createCheckoutPaymentAccountShop($this->organisation, $this->shop);
    list($order) = createOrderWithCheckoutApiPoint($this->customer, $this->product, $paymentAccountShop);

    $order->update(['total_amount' => 19.88]);
    DB::table('customers')->where('id', $this->customer->id)->update(['balance' => 5.00]);

    createMitSavedCard($this->customer, 1);

    App\Actions\Retina\Dropshipping\Orders\PayOrderWithMitCard::partialMock()
        ->shouldReceive('requestMitPayment')
        ->andReturn(['id' => 'pay_mit_declined_only', 'status' => 'Declined', 'approved' => false, 'amount' => 0, 'source' => ['type' => 'card']]);

    App\Actions\Retina\Dropshipping\Orders\PayOrderAsync::run($order->refresh());

    $order->refresh();

    expect((float)$order->payment_amount)->toBe(5.0)
        ->and($order->pay_status)->toBe(App\Enums\Ordering\Order\OrderPayStatusEnum::UNPAID);
});

test('a staff payment link is bound to the order and its capture pays a submitted order without touching its state', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1);

    $paymentAccountShop = createCheckoutPaymentAccountShop($this->organisation, $this->shop);
    list($order) = createOrderWithCheckoutApiPoint($this->customer, $this->product, $paymentAccountShop);
    $order->update(['state' => OrderStateEnum::IN_WAREHOUSE, 'total_amount' => 174.26, 'payment_amount' => 165.52]);

    $sentRequest = null;
    App\Actions\Accounting\OrderPaymentApiPoint\StoreOrderPaymentLink::partialMock()
        ->shouldReceive('createCheckoutComPaymentLink')
        ->once()
        ->andReturnUsing(function ($paymentAccountShop, $paymentLinkRequest) use (&$sentRequest) {
            $sentRequest = $paymentLinkRequest;

            return ['id' => 'pl_test_link', '_links' => ['redirect' => ['href' => 'https://pay.sandbox.checkout.com/link/pl_test_link']]];
        });

    $orderPaymentApiPoint = App\Actions\Accounting\OrderPaymentApiPoint\StoreOrderPaymentLink::make()->action($order);

    expect($sentRequest->amount)->toBe(874)
        ->and($sentRequest->reference)->toBe($order->reference)
        ->and($sentRequest->metadata['operation'])->toBe('order')
        ->and($sentRequest->metadata['api_point_id'])->toBe($orderPaymentApiPoint->id)
        ->and($orderPaymentApiPoint->data['payment_link']['url'])->toBe('https://pay.sandbox.checkout.com/link/pl_test_link')
        ->and($orderPaymentApiPoint->data['payment_methods']['checkout'])->toBe(App\Actions\Accounting\OrderPaymentApiPoint\StoreOrderPaymentLink::checkoutPaymentAccountShop($order)->id);

    ProcessCheckoutComPaymentGatewayLog::partialMock()
        ->shouldReceive('getCheckOutPayment')
        ->andReturn([
            'id'     => 'pay_test_link_captured',
            'status' => 'Captured',
            'amount' => 874,
            'source' => ['type' => 'card'],
        ]);

    $paymentGatewayLog = $this->group->paymentGatewayLogs()->create([
        'payload' => fakeCheckoutComWebhookPayload('payment_captured', 'evt_test_link_captured', 'pay_test_link_captured', $orderPaymentApiPoint, 874),
        'gateway' => 'checkout-com',
    ]);

    PreProcessCheckoutComPaymentGatewayLog::run($paymentGatewayLog);

    $order->refresh();

    expect($paymentGatewayLog->refresh()->status)->toBe(PaymentGatewayLogStatusEnum::OK)
        ->and($orderPaymentApiPoint->refresh()->state)->toBe(OrderPaymentApiPointStateEnum::SUCCESS)
        ->and($order->state)->toBe(OrderStateEnum::IN_WAREHOUSE)
        ->and($order->payments()->where('reference', 'pay_test_link_captured')->count())->toBe(1);
});

test('a card payment on a submitted order paid by bank sends it to the warehouse', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1);
    createWarehouse();

    $paymentAccountShop = createCheckoutPaymentAccountShop($this->organisation, $this->shop);
    list($order, $orderPaymentApiPoint) = createOrderWithCheckoutApiPoint($this->customer, $this->product, $paymentAccountShop);

    $order = App\Actions\Ordering\Order\UpdateState\SubmitOrder::make()->action($order);
    expect($order->state)->toBe(OrderStateEnum::SUBMITTED);

    $orderAmountInCents = (int)round((float)$order->total_amount * 100);

    ProcessCheckoutComPaymentGatewayLog::partialMock()
        ->shouldReceive('getCheckOutPayment')
        ->andReturn([
            'id'     => 'pay_test_submitted_then_card',
            'status' => 'Captured',
            'amount' => $orderAmountInCents,
            'source' => ['type' => 'card'],
        ]);

    $paymentGatewayLog = $this->group->paymentGatewayLogs()->create([
        'payload' => fakeCheckoutComWebhookPayload('payment_captured', 'evt_test_submitted_then_card', 'pay_test_submitted_then_card', $orderPaymentApiPoint, $orderAmountInCents),
        'gateway' => 'checkout-com',
    ]);

    PreProcessCheckoutComPaymentGatewayLog::run($paymentGatewayLog);

    $order->refresh();

    expect($paymentGatewayLog->refresh()->status)->toBe(PaymentGatewayLogStatusEnum::OK)
        ->and($order->pay_status->value)->toBe('paid')
        ->and($order->state)->toBe(OrderStateEnum::IN_WAREHOUSE)
        ->and($order->deliveryNotes()->count())->toBe(1);
});

test('a payment link is refused when the order owes nothing', function () {
    $paymentAccountShop = createCheckoutPaymentAccountShop($this->organisation, $this->shop);
    list($order) = createOrderWithCheckoutApiPoint($this->customer, $this->product, $paymentAccountShop);
    $order->update(['total_amount' => 20, 'payment_amount' => 20]);

    expect(fn () => App\Actions\Accounting\OrderPaymentApiPoint\StoreOrderPaymentLink::make()->action($order))
        ->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
});

test('a refund Checkout.com declines later is undone on the payment and the order', function (array $context) {
    list($order, $orderPaymentApiPoint) = $context;
    GetCurrencyExchange::shouldRun()->andReturn(1);

    $payment       = $order->payments()->where('reference', 'pay_test_captured')->first();
    $paidBefore    = (float) $order->refresh()->payment_amount;
    $refundPayment = \App\Actions\Accounting\Payment\StorePayment::make()->action($order->customer, $payment->paymentAccount, [
        'amount'              => -10,
        'reference'           => 'act_test_refund_declined',
        'status'              => PaymentStatusEnum::SUCCESS->value,
        'state'               => PaymentStateEnum::COMPLETED->value,
        'type'                => \App\Enums\Accounting\Payment\PaymentTypeEnum::REFUND,
        'original_payment_id' => $payment->id,
    ]);
    $payment->update(['total_refund' => 10, 'with_refund' => true]);
    \App\Actions\Ordering\Order\AttachPaymentToOrder::make()->action($order, $refundPayment, []);
    expect((float) $order->refresh()->payment_amount)->toBe($paidBefore - 10);

    $payload                     = fakeCheckoutComWebhookPayload('payment_refund_declined', 'evt_test_refund_declined', 'pay_test_captured', $orderPaymentApiPoint, 1000);
    $payload['data']['action_id'] = 'act_test_refund_declined';
    $declinedLog                 = $this->group->paymentGatewayLogs()->create(['payload' => $payload, 'gateway' => 'checkout-com']);

    PreProcessCheckoutComPaymentGatewayLog::run($declinedLog);

    expect($declinedLog->refresh()->status)->toBe(PaymentGatewayLogStatusEnum::OK)
        ->and($declinedLog->payment_id)->toBe($refundPayment->id)
        ->and($refundPayment->refresh()->status)->toBe(PaymentStatusEnum::FAIL)
        ->and($refundPayment->state)->toBe(PaymentStateEnum::DECLINED)
        ->and((float) $payment->refresh()->total_refund)->toBe(0.0)
        ->and($payment->with_refund)->toBeFalse()
        ->and((float) $order->refresh()->payment_amount)->toBe($paidBefore);
})->depends('webhook payment_captured pays and submits the order');

test('a top up paid by Checkout.com is refunded by API from the customer credit', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1);

    $payment  = Payment::where('reference', 'pay_test_topup')->where('type', \App\Enums\Accounting\Payment\PaymentTypeEnum::PAYMENT)->firstOrFail();
    $customer = $payment->customer;
    $ledger   = fn () => round((float) App\Models\Accounting\CreditTransaction::where('customer_id', $customer->id)->sum('amount'), 2);
    if ($ledger() < 25) {
        \App\Actions\Accounting\CreditTransaction\StoreCreditTransaction::make()->action($customer, [
            'amount' => round(25 - $ledger() + 10, 2),
            'type'   => \App\Enums\Accounting\CreditTransaction\CreditTransactionTypeEnum::ADD_FUNDS_OTHER,
        ], notifyCustomer: false);
    }
    $before = $ledger();

    $options = \App\Actions\Accounting\Payment\RefundPaymentByApi::refundOptions($payment);
    expect($options['refund_invoices'])->toBe([])
        ->and($options['blocked'])->toBeNull()
        ->and($options['credit']['max'])->toBe(min(25.0, $before));

    \App\Actions\Accounting\Payment\RefundPaymentByApi::partialMock()->shouldReceive('refundAtCheckout')->once()->andReturn('act_test_topup_refund');
    $refundPayment = \App\Actions\Accounting\Payment\RefundPaymentByApi::make()->handle($payment, ['amount' => 25, 'from_credit' => true]);

    expect($refundPayment->reference)->toBe('act_test_topup_refund')
        ->and((float) $refundPayment->amount)->toBe(-25.0)
        ->and($ledger())->toBe(round($before - 25, 2))
        ->and((float) $customer->refresh()->balance)->toBe(round($before - 25, 2))
        ->and((float) $payment->refresh()->total_refund)->toBe(25.0)
        ->and(\App\Actions\Accounting\Payment\RefundPaymentByApi::creditRefundableLeft($payment))->toBe(0.0)
        ->and(\App\Actions\Accounting\Payment\RefundPaymentByApi::refundOptions($payment))->toBeNull()
        ->and(App\Models\Accounting\CreditTransaction::where('payment_id', $refundPayment->id)->value('type'))->toBe(\App\Enums\Accounting\CreditTransaction\CreditTransactionTypeEnum::MONEY_BACK);
})->depends('webhook payment_captured processes a top up');

test('a live order payment is refused until the order is cancelled, then refunded from credit, and a declined refund puts the credit back once', function (array $context) {
    list($order, $orderPaymentApiPoint) = $context;
    GetCurrencyExchange::shouldRun()->andReturn(1);

    $refundByApi = \App\Actions\Accounting\Payment\RefundPaymentByApi::class;
    $payment     = $order->payments()->where('reference', 'pay_test_captured')->firstOrFail();
    $ledger      = fn () => round((float) App\Models\Accounting\CreditTransaction::where('customer_id', $order->customer_id)->sum('amount'), 2);

    $live = $refundByApi::refundOptions($payment);
    expect($live['credit'])->toBeNull()
        ->and($live['refund_invoices'])->toBe([])
        ->and($live['blocked'])->toContain('cancel it first')
        ->and(fn () => $refundByApi::make()->handle($payment, ['amount' => 5, 'from_credit' => true]))->toThrow(Illuminate\Validation\ValidationException::class, 'not in the customer credit');

    \App\Actions\Ordering\Order\UpdateState\CancelOrder::make()->action($order->refresh());
    $balance     = $ledger();
    $totalRefund = (float) $payment->refresh()->total_refund;
    $paidOrder   = (float) $order->refresh()->payment_amount;
    $refundRows  = Payment::where('original_payment_id', $payment->id)->count();

    expect($refundByApi::refundOptions($payment)['credit']['max'])->toBe(round(min(25 - $totalRefund, $balance), 2))
        ->and(fn () => $refundByApi::make()->handle($payment, ['amount' => 30, 'from_credit' => true]))->toThrow(Illuminate\Validation\ValidationException::class);

    $keys = [];
    $refundByApi::partialMock()->shouldReceive('refundAtCheckout')->once()
        ->andReturnUsing(function ($payment, $amount, $reference, $key) use (&$keys) {
            $keys[] = $key;
            throw Illuminate\Validation\ValidationException::withMessages(['amount' => 'Checkout.com refused the refund: refund_not_allowed']);
        });
    expect(fn () => $refundByApi::make()->handle($payment, ['amount' => 20, 'from_credit' => true]))->toThrow(Illuminate\Validation\ValidationException::class, 'refund_not_allowed')
        ->and($ledger())->toBe($balance)
        ->and((float) $payment->refresh()->total_refund)->toBe($totalRefund)
        ->and(Payment::where('original_payment_id', $payment->id)->count())->toBe($refundRows);

    $refundByApi::partialMock()->shouldReceive('refundAtCheckout')->once()
        ->andReturnUsing(function ($payment, $amount, $reference, $key) use (&$keys) {
            $keys[] = $key;

            return 'act_test_credit_refund';
        });
    $refundPayment = $refundByApi::make()->handle($payment->refresh(), ['amount' => 20, 'from_credit' => true]);

    expect($refundPayment->reference)->toBe('act_test_credit_refund')
        ->and($keys[0])->toBe($keys[1])
        ->and($ledger())->toBe(round($balance - 20, 2))
        ->and((float) $payment->refresh()->total_refund)->toBe($totalRefund + 20)
        ->and((float) $order->refresh()->payment_amount)->toBe($paidOrder)
        ->and($refundByApi::creditRefundableLeft($payment))->toBe(round(25 - 20 - $totalRefund, 2));

    $payload                      = fakeCheckoutComWebhookPayload('payment_refund_declined', 'evt_test_credit_refund_declined', 'pay_test_captured', $orderPaymentApiPoint, 2000);
    $payload['data']['action_id'] = 'act_test_credit_refund';
    $declinedLog                  = $this->group->paymentGatewayLogs()->create(['payload' => $payload, 'gateway' => 'checkout-com']);
    PreProcessCheckoutComPaymentGatewayLog::run($declinedLog);

    expect($declinedLog->refresh()->status)->toBe(PaymentGatewayLogStatusEnum::OK)
        ->and($refundPayment->refresh()->status)->toBe(PaymentStatusEnum::FAIL)
        ->and($ledger())->toBe($balance)
        ->and((float) $payment->refresh()->total_refund)->toBe($totalRefund)
        ->and($refundByApi::creditRefundableLeft($payment))->toBe(round(25 - $totalRefund, 2))
        ->and($refundByApi::idempotencyKey($payment, 20))->not->toBe($keys[0]);

    $payload['id'] = 'evt_test_credit_refund_declined_again';
    $againLog      = $this->group->paymentGatewayLogs()->create(['payload' => $payload, 'gateway' => 'checkout-com']);
    PreProcessCheckoutComPaymentGatewayLog::run($againLog);

    expect($againLog->refresh()->status)->toBe(PaymentGatewayLogStatusEnum::NA)
        ->and($ledger())->toBe($balance);
})->depends('webhook payment_captured pays and submits the order');

test('a cancel that kept part of the money caps the refund from credit, an API refund cannot be cancelled and a cancelled payment cannot be refunded', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1);
    $refundByApi = \App\Actions\Accounting\Payment\RefundPaymentByApi::class;

    $paymentAccountShop = createCheckoutPaymentAccountShop($this->organisation, $this->shop);
    list($order, $orderPaymentApiPoint) = createOrderWithCheckoutApiPoint($this->customer, $this->product, $paymentAccountShop);
    ProcessCheckoutComPaymentGatewayLog::partialMock()->shouldReceive('getCheckOutPayment')
        ->andReturn(['id' => 'pay_test_partial_cancel', 'status' => 'Captured', 'amount' => 2500, 'source' => ['type' => 'card']]);
    PreProcessCheckoutComPaymentGatewayLog::run($this->group->paymentGatewayLogs()->create([
        'payload' => fakeCheckoutComWebhookPayload('payment_captured', 'evt_test_partial_cancel', 'pay_test_partial_cancel', $orderPaymentApiPoint, 2500),
        'gateway' => 'checkout-com',
    ]));
    $payment = Payment::where('reference', 'pay_test_partial_cancel')->firstOrFail();

    \App\Actions\Ordering\Order\UpdateState\CancelOrder::make()->action($order->refresh(), ['refund_amount' => 10]);

    expect($refundByApi::creditRefundableLeft($payment))->toBe(10.0)
        ->and(fn () => $refundByApi::make()->handle($payment, ['amount' => 11, 'from_credit' => true]))->toThrow(Illuminate\Validation\ValidationException::class);

    $refundByApi::partialMock()->shouldReceive('refundAtCheckout')->once()->andReturn('act_test_partial_cancel');
    $refundPayment = $refundByApi::make()->handle($payment, ['amount' => 10, 'from_credit' => true]);

    expect($refundPayment->data['refunded_by_api'])->toBeTrue()
        ->and($refundByApi::creditRefundableLeft($payment->refresh()))->toBe(0.0)
        ->and(fn () => \App\Actions\Accounting\Payment\CancelPayment::make()->handle($refundPayment))->toThrow(Illuminate\Validation\ValidationException::class, 'cannot be cancelled here')
        ->and($refundPayment->refresh()->state)->toBe(PaymentStateEnum::COMPLETED);

    $refunded = $this->group->paymentGatewayLogs()->create([
        'payload' => [...fakeCheckoutComWebhookPayload('payment_refunded', 'evt_test_unknown_refund', 'pay_test_partial_cancel', $orderPaymentApiPoint, 500), 'data' => [...fakeCheckoutComWebhookPayload('payment_refunded', 'evt_test_unknown_refund', 'pay_test_partial_cancel', $orderPaymentApiPoint, 500)['data'], 'action_id' => 'act_made_in_dashboard']],
        'gateway' => 'checkout-com',
    ]);
    PreProcessCheckoutComPaymentGatewayLog::run($refunded);
    expect($refunded->refresh()->state)->toBe(PaymentGatewayLogStateEnum::PROCESSED)
        ->and($refunded->status)->toBe(PaymentGatewayLogStatusEnum::NA);

    $payment->refresh()->update(['total_refund' => 0]);
    expect($refundByApi::alreadyRefunded($payment))->toBe(10.0)
        ->and($refundByApi::refundOptions($payment)['left_on_payment'])->toBe(15.0)
        ->and(fn () => $refundByApi::make()->handle($payment, ['amount' => 16, 'from_credit' => true]))->toThrow(Illuminate\Validation\ValidationException::class, 'more than is left')
        ->and(fn () => \App\Actions\Accounting\Payment\RefundPaymentToBalance::lockForRefund($payment, null, 16))->toThrow(Illuminate\Validation\ValidationException::class, 'more than is left');

    $cancelledPayment = Payment::where('reference', 'pay_test_captured')->firstOrFail();
    $cancelledPayment->update(['state' => PaymentStateEnum::CANCELLED]);
    expect($refundByApi::refundOptions($cancelledPayment))->toBeNull()
        ->and(fn () => $refundByApi::make()->handle($cancelledPayment, ['amount' => 1, 'from_credit' => true]))->toThrow(Illuminate\Validation\ValidationException::class, 'Only a successful payment');
});

function invoiceCapturedOrder($test, string $reference, int $cents, ?string $refundAction = 'act_test_excess_refund'): array
{
    GetCurrencyExchange::shouldRun()->andReturn(1);

    $paymentAccountShop = createCheckoutPaymentAccountShop($test->organisation, $test->shop);
    list($order, $orderPaymentApiPoint) = createOrderWithCheckoutApiPoint($test->customer, $test->product, $paymentAccountShop);
    ProcessCheckoutComPaymentGatewayLog::partialMock()->shouldReceive('getCheckOutPayment')
        ->andReturn(['id' => $reference, 'status' => 'Captured', 'amount' => $cents, 'source' => ['type' => 'card']]);
    PreProcessCheckoutComPaymentGatewayLog::run($test->group->paymentGatewayLogs()->create([
        'payload' => fakeCheckoutComWebhookPayload('payment_captured', 'evt_'.$reference, $reference, $orderPaymentApiPoint, $cents),
        'gateway' => 'checkout-com',
    ]));

    $deliveryNoteIds = $order->deliveryNotes()->pluck('delivery_notes.id');
    if ($deliveryNoteIds->isNotEmpty()) {
        DB::table('delivery_note_items')->whereIn('delivery_note_id', $deliveryNoteIds)->delete();
        DB::table('delivery_notes')->whereIn('id', $deliveryNoteIds)->delete();
        $order->update(['state' => OrderStateEnum::CREATING]);
    }

    $calls = new ArrayObject();
    \App\Actions\Accounting\Payment\RefundPaymentByApi::partialMock()->shouldReceive('refundAtCheckout')
        ->andReturnUsing(function ($payment, $amount) use ($calls, $refundAction) {
            if ($refundAction === 'unknown') {
                throw App\Exceptions\CheckoutRefundOutcomeUnknown::withMessages(['amount' => 'No answer from Checkout.com']);
            }
            if ($refundAction === null) {
                throw Illuminate\Validation\ValidationException::withMessages(['amount' => 'Checkout.com refused the refund: refund_not_allowed']);
            }
            $calls->append([$payment->reference, $amount]);

            return $refundAction.count($calls);
        });

    return [$order->refresh(), Payment::where('reference', $reference)->firstOrFail(), $calls];
}

function excessCredit(Order $order): float
{
    return round((float) App\Models\Accounting\CreditTransaction::where('customer_id', $order->customer_id)
        ->where('type', \App\Enums\Accounting\CreditTransaction\CreditTransactionTypeEnum::FROM_EXCESS)
        ->whereIn('payment_id', $order->payments()->select('payments.id'))->sum('amount'), 2);
}

test('money for items not shipped stays in credit when no setting asks for a refund', function () {
    list($order, $payment, $calls) = invoiceCapturedOrder($this, 'pay_test_excess_off', 200000);

    \App\Actions\Ordering\Order\GenerateInvoiceFromOrder::make()->action($order);

    expect(excessCredit($order))->toBeGreaterThan(0)
        ->and($calls->getArrayCopy())->toBe([])
        ->and(Illuminate\Support\Arr::has($order->refresh()->data, 'excess_refund'))->toBeFalse()
        ->and((float) $payment->refresh()->total_refund)->toBe(0.0);
});

test('a shop set to refund sends the money for items not shipped back to the card and records it once', function () {
    $this->shop->update(['settings' => [...$this->shop->settings, 'excess_refund' => ['mode' => 'refund']]]);
    list($order, $payment, $calls) = invoiceCapturedOrder($this, 'pay_test_excess_shop', 200000);

    \App\Actions\Ordering\Order\GenerateInvoiceFromOrder::make()->action($order);
    $excess = excessCredit($order);
    $ledger = round((float) App\Models\Accounting\CreditTransaction::where('customer_id', $order->customer_id)->sum('amount'), 2);

    $record = $order->refresh()->data['excess_refund'];
    expect($excess)->toBeGreaterThan(0)
        ->and($record['state'])->toBe('refunded')
        ->and($record['source'])->toBe('shop')
        ->and($record['action_ids'])->toBe(['act_test_excess_refund1'])
        ->and($calls->getArrayCopy())->toBe([['pay_test_excess_shop', $excess]])
        ->and((float) $payment->refresh()->total_refund)->toBe($excess)
        ->and(\App\Actions\Accounting\Payment\RefundPaymentByApi::creditRefundableLeft($payment))->toBe(0.0);

    \App\Actions\Ordering\Order\RefundExcessToCard::make()->handle($order, $excess);
    \App\Actions\Ordering\Order\RefundExcessToCard::make()->handle($order, $excess);

    expect($calls)->toHaveCount(1)
        ->and(round((float) App\Models\Accounting\CreditTransaction::where('customer_id', $order->customer_id)->sum('amount'), 2))->toBe($ledger);
});

test('a customer set to keep credit overrides a shop set to refund', function () {
    $this->shop->update(['settings' => [...$this->shop->settings, 'excess_refund' => ['mode' => 'refund']]]);
    $this->customer->update(['settings' => [...$this->customer->settings, 'excess_refund_mode' => 'credit']]);
    list($order, $payment, $calls) = invoiceCapturedOrder($this, 'pay_test_excess_cust_credit', 200000);

    \App\Actions\Ordering\Order\GenerateInvoiceFromOrder::make()->action($order);

    expect($calls->getArrayCopy())->toBe([])
        ->and(Illuminate\Support\Arr::has($order->refresh()->data, 'excess_refund'))->toBeFalse();
});

test('a customer set to refund overrides a shop set to keep credit', function () {
    $this->customer->update(['settings' => [...$this->customer->settings, 'excess_refund_mode' => 'refund']]);
    list($order, $payment, $calls) = invoiceCapturedOrder($this, 'pay_test_excess_cust_refund', 200000);

    \App\Actions\Ordering\Order\GenerateInvoiceFromOrder::make()->action($order);

    expect($calls)->toHaveCount(1)
        ->and($order->refresh()->data['excess_refund']['source'])->toBe('customer')
        ->and($order->data['excess_refund']['state'])->toBe('refunded');
});

test('a refused refund keeps the money in credit and records the failure', function () {
    $this->shop->update(['settings' => [...$this->shop->settings, 'excess_refund' => ['mode' => 'refund']]]);
    list($order, $payment) = invoiceCapturedOrder($this, 'pay_test_excess_refused', 200000, null);
    $ledgerBefore = round((float) App\Models\Accounting\CreditTransaction::where('customer_id', $order->customer_id)->sum('amount'), 2);

    \App\Actions\Ordering\Order\GenerateInvoiceFromOrder::make()->action($order);
    $excess = excessCredit($order);

    $record = $order->refresh()->data['excess_refund'];
    expect($record['state'])->toBe('failed')
        ->and($record['reason'])->toContain('refund_not_allowed')
        ->and(round((float) App\Models\Accounting\CreditTransaction::where('customer_id', $order->customer_id)->sum('amount'), 2))->toBe(round($ledgerBefore + $excess, 2))
        ->and((float) $payment->refresh()->total_refund)->toBe(0.0);
});

test('only the part of the excess that was paid by card is refunded, the bank paid part stays in credit', function () {
    $this->shop->update(['settings' => [...$this->shop->settings, 'excess_refund' => ['mode' => 'refund']]]);
    list($order, $payment, $calls) = invoiceCapturedOrder($this, 'pay_test_excess_mixed', 1000);

    $bank = App\Actions\Accounting\OrgPaymentServiceProvider\StoreOrgPaymentServiceProviderAccount::make()->action(
        $this->organisation,
        PaymentServiceProvider::where('type', App\Enums\Accounting\PaymentServiceProvider\PaymentServiceProviderTypeEnum::BANK->value)->first(),
        ['code' => 'ACC'.mt_rand(1000, 9999), 'name' => 'Bank Account Excess Mixed']
    );
    App\Actions\Ordering\Order\PayOrder::make()->action($order, $bank, [
        'amount' => 2000.00,
        'status' => PaymentStatusEnum::SUCCESS,
        'state'  => PaymentStateEnum::COMPLETED,
    ]);

    \App\Actions\Ordering\Order\GenerateInvoiceFromOrder::make()->action($order->refresh());

    $record = $order->refresh()->data['excess_refund'];
    expect(excessCredit($order))->toBeGreaterThan(10.0)
        ->and($calls->getArrayCopy())->toBe([['pay_test_excess_mixed', 10.0]])
        ->and($record['state'])->toBe('refunded')
        ->and((float) $record['refunded'])->toBe(10.0)
        ->and($record['reason'])->not->toBeNull();
});

test('refunding a balance to card sends back as much as it can, keeps the rest and gifted credit, and never takes the balance below zero', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1);
    $refundBalance      = \App\Actions\Accounting\Payment\RefundCustomerBalanceByApi::class;
    $customer           = \App\Actions\CRM\Customer\StoreCustomer::make()->action($this->shop, \App\Models\CRM\Customer::factory()->definition());
    $paymentAccountShop = createCheckoutPaymentAccountShop($this->organisation, $this->shop);

    $captured = ['pay_test_balance_older' => 2500, 'pay_test_balance_newer' => 2000];
    ProcessCheckoutComPaymentGatewayLog::partialMock()->shouldReceive('getCheckOutPayment')
        ->andReturnUsing(fn ($paymentAccountShop, $id) => ['id' => $id, 'status' => 'Captured', 'amount' => $captured[$id], 'source' => ['type' => 'card']]);
    foreach ($captured as $reference => $amount) {
        list($order, $orderPaymentApiPoint) = createOrderWithCheckoutApiPoint($customer, $this->product, $paymentAccountShop);
        PreProcessCheckoutComPaymentGatewayLog::run($this->group->paymentGatewayLogs()->create([
            'payload' => fakeCheckoutComWebhookPayload('payment_captured', 'evt_'.$reference, $reference, $orderPaymentApiPoint, $amount),
            'gateway' => 'checkout-com',
        ]));
        \App\Actions\Ordering\Order\UpdateState\CancelOrder::make()->action($order->refresh());
    }
    Payment::where('reference', 'pay_test_balance_older')->update(['date' => now()->subDays(2)]);
    \App\Actions\Accounting\CreditTransaction\StoreCreditTransaction::make()->action($customer, [
        'amount' => 7,
        'type'   => \App\Enums\Accounting\CreditTransaction\CreditTransactionTypeEnum::ADD_FUNDS_OTHER,
    ], notifyCustomer: false);

    $plan = $refundBalance::plan($customer);
    expect($plan['balance'])->toBe(52.0)
        ->and($plan['to_card'])->toBe(45.0)
        ->and($plan['stays'])->toBe(7.0)
        ->and(array_column($plan['refunds'], 'reference'))->toBe(['pay_test_balance_newer', 'pay_test_balance_older']);

    \App\Actions\Accounting\Payment\RefundPaymentByApi::partialMock()->shouldReceive('refundAtCheckout')->twice()
        ->andReturnUsing(function ($payment) {
            if ($payment->reference === 'pay_test_balance_newer') {
                throw Illuminate\Validation\ValidationException::withMessages(['amount' => 'Checkout.com refused the refund: refund_not_allowed']);
            }

            return 'act_test_balance_older';
        });
    $result = $refundBalance::make()->handle($customer, 45);

    expect($result['to_card'])->toBe(25.0)
        ->and(array_column($result['refunded'], 'action_id'))->toBe(['act_test_balance_older'])
        ->and(array_column($result['failed'], 'reference'))->toBe(['pay_test_balance_newer'])
        ->and($result['failed'][0]['reason'])->toContain('refund_not_allowed')
        ->and($result['balance'])->toBe(27.0)
        ->and($refundBalance::plan($customer)['to_card'])->toBe(20.0);

    \App\Actions\Accounting\CreditTransaction\StoreCreditTransaction::make()->action($customer, [
        'amount' => -24,
        'type'   => \App\Enums\Accounting\CreditTransaction\CreditTransactionTypeEnum::PAYMENT,
    ], notifyCustomer: false);
    $plan = $refundBalance::plan($customer);
    expect($plan['balance'])->toBe(3.0)
        ->and($plan['gifted'])->toBe(7.0)
        ->and($plan['to_card'])->toBe(0.0)
        ->and($plan['stays'])->toBe(3.0)
        ->and($refundBalance::make()->handle($customer, 3)['refunded'])->toBe([])
        ->and($refundBalance::ledgerBalance($customer))->toBe(3.0);
});

test('refunding a balance to card plans a shared cancelled order once, stops on an unclear answer and never sends more than confirmed', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1);
    $refundBalance      = \App\Actions\Accounting\Payment\RefundCustomerBalanceByApi::class;
    $customer           = \App\Actions\CRM\Customer\StoreCustomer::make()->action($this->shop, \App\Models\CRM\Customer::factory()->definition());
    $paymentAccountShop = createCheckoutPaymentAccountShop($this->organisation, $this->shop);

    list($order, $orderPaymentApiPoint) = createOrderWithCheckoutApiPoint($customer, $this->product, $paymentAccountShop);
    $captured = ['pay_test_shared_first' => 2500, 'pay_test_shared_second' => 2500];
    ProcessCheckoutComPaymentGatewayLog::partialMock()->shouldReceive('getCheckOutPayment')
        ->andReturnUsing(fn ($paymentAccountShop, $id) => ['id' => $id, 'status' => 'Captured', 'amount' => $captured[$id], 'source' => ['type' => 'card']]);
    foreach ($captured as $reference => $amount) {
        PreProcessCheckoutComPaymentGatewayLog::run($this->group->paymentGatewayLogs()->create([
            'payload' => fakeCheckoutComWebhookPayload('payment_captured', 'evt_'.$reference, $reference, $orderPaymentApiPoint, $amount),
            'gateway' => 'checkout-com',
        ]));
    }
    Payment::where('reference', 'pay_test_shared_first')->update(['date' => now()->subDay()]);
    \App\Actions\Ordering\Order\UpdateState\CancelOrder::make()->action($order->refresh(), ['refund_amount' => 40]);

    $plan = $refundBalance::plan($customer);
    expect($refundBalance::ledgerBalance($customer))->toBe(40.0)
        ->and($plan['to_card'])->toBeLessThanOrEqual(40.0)
        ->and(array_sum(array_column($plan['refunds'], 'amount')))->toBe($plan['to_card']);

    \App\Actions\Accounting\Payment\RefundPaymentByApi::partialMock()->shouldReceive('refundAtCheckout')->once()
        ->andThrow(\App\Exceptions\CheckoutRefundOutcomeUnknown::withMessages(['amount' => 'No answer from Checkout.com']));
    $result = $refundBalance::make()->handle($customer, $plan['to_card']);
    expect($result['stopped'])->toBeTrue()
        ->and($result['refunded'])->toBe([])
        ->and($result['unknown'])->toHaveCount(1)
        ->and($result['balance'])->toBe(40.0);

    \App\Actions\Accounting\Payment\RefundPaymentByApi::partialMock()->shouldReceive('refundAtCheckout')->once()->andReturn('act_test_shared_confirmed');
    $result = $refundBalance::make()->handle($customer, 10);
    expect($result['to_card'])->toBe(10.0)
        ->and($result['balance'])->toBe(30.0);

    \App\Actions\Accounting\Payment\RefundPaymentByApi::partialMock()->shouldReceive('refundAtCheckout')->andReturnUsing(fn ($payment, $amount) => 'act_test_shared_'.$payment->id.'_'.(int) round($amount * 100));
    for ($click = 0; $click < 3; $click++) {
        $plan = $refundBalance::plan($customer);
        if ($plan['to_card'] > 0) {
            $refundBalance::make()->handle($customer, $plan['to_card']);
        }
    }
    expect($refundBalance::ledgerBalance($customer))->toBe(0.0)
        ->and(round(abs((float) Payment::whereIn('original_payment_id', Payment::whereIn('reference', array_keys($captured))->pluck('id'))->where('status', PaymentStatusEnum::SUCCESS)->sum('amount')), 2))->toBe(40.0);
});

test('an unanswered refund is recorded as unknown, never retried and leaves the credit untouched', function () {
    $this->shop->update(['settings' => [...$this->shop->settings, 'excess_refund' => ['mode' => 'refund']]]);
    list($order, $payment, $calls) = invoiceCapturedOrder($this, 'pay_test_excess_unknown', 200000, 'unknown');

    \App\Actions\Ordering\Order\GenerateInvoiceFromOrder::make()->action($order);
    $excess = excessCredit($order);
    \App\Actions\Ordering\Order\RefundExcessToCard::make()->handle($order, $excess);

    expect($order->refresh()->data['excess_refund']['state'])->toBe('unknown')
        ->and((float) $payment->refresh()->total_refund)->toBe(0.0)
        ->and($excess)->toBeGreaterThan(0);
});

test('the local QA command makes card top ups and order payments that can be refunded by API, links a payment to the sandbox and puts it back', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1);
    $command  = \App\Actions\Accounting\Payment\CheckoutCom\LinkPaymentToCheckoutSandbox::class;
    $customer = \App\Actions\CRM\Customer\StoreCustomer::make()->action($this->shop, \App\Models\CRM\Customer::factory()->definition());
    $paymentAccountShop = createCheckoutPaymentAccountShop($this->organisation, $this->shop);

    $ids = ['pay_test_qa_topup', 'pay_test_qa_order', 'pay_test_qa_linked'];
    $command::partialMock()->shouldReceive('payInSandbox')->times(3)
        ->andReturnUsing(function () use (&$ids) {
            return ['id' => array_shift($ids), 'source' => ['type' => 'card', 'scheme' => 'Visa']];
        });

    $topUp = $command::make()->topUp($customer, 25);
    expect($topUp->reference)->toBe('pay_test_qa_topup')
        ->and((float) $customer->refresh()->balance)->toBe(25.0)
        ->and(\App\Actions\Accounting\Payment\RefundPaymentByApi::creditRefundableLeft($topUp))->toBe(25.0);

    list($order) = createOrderWithCheckoutApiPoint($customer, $this->product, $paymentAccountShop);
    $orderPayment = $command::make()->payOrder($order->refresh());
    expect($orderPayment->reference)->toBe('pay_test_qa_order')
        ->and((float) $orderPayment->amount)->toBe(round((float) $order->total_amount, 2))
        ->and($order->refresh()->payments()->where('payments.id', $orderPayment->id)->exists())->toBeTrue()
        ->and(\App\Actions\Accounting\Payment\RefundPaymentByApi::refundOptions($orderPayment)['blocked'])->toContain('cancel it first');

    $orderPayment->update(['reference' => 'pay_test_qa_original']);
    $command::make()->handle($orderPayment->refresh());
    expect($orderPayment->refresh()->reference)->toBe('pay_test_qa_linked')
        ->and($command::make()->restore($orderPayment))->toBe('pay_test_qa_original')
        ->and($orderPayment->refresh()->reference)->toBe('pay_test_qa_original')
        ->and($command::make()->restore($orderPayment))->toBeNull();
});
