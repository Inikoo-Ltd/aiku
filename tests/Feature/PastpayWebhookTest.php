<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 17 Jul 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Accounting\OrderPaymentApiPoint\StoreOrderPaymentApiPoint;
use App\Actions\Accounting\OrderPaymentApiPoint\WebHooks\PastpayOrderPaymentFailure;
use App\Actions\Accounting\OrderPaymentApiPoint\WebHooks\PastpayOrderPaymentSuccess;
use App\Actions\Accounting\Invoice\StoreInvoice;
use App\Actions\Accounting\OrgPaymentServiceProvider\StoreOrgPaymentServiceProvider;
use App\Actions\Accounting\Payment\PastPay\FinalizeOrderWithPastpay;
use App\Actions\Accounting\PaymentAccount\StorePaymentAccount;
use App\Actions\Accounting\PaymentAccountShop\StorePaymentAccountShop;
use App\Actions\Accounting\PaymentAccountShop\UI\GetRetinaPaymentAccountShopData;
use App\Actions\Accounting\PaymentAccountShop\UpdatePaymentAccountShop;
use App\Actions\Billables\Charge\StoreCharge;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Enums\Accounting\OrderPaymentApiPoint\OrderPaymentApiPointStateEnum;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Enums\Accounting\PaymentAccountShop\PaymentAccountShopStateEnum;
use App\Enums\Catalogue\Charge\ChargeStateEnum;
use App\Enums\Catalogue\Charge\ChargeTriggerEnum;
use App\Enums\Catalogue\Charge\ChargeTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\PaymentAccount;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\Accounting\PaymentServiceProvider;
use App\Models\Helpers\Address;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Illuminate\Support\Arr;

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

    actingAs($this->user);
});

function createPastpayPaymentAccountShop($organisation, $shop): PaymentAccountShop
{
    $paymentServiceProvider = PaymentServiceProvider::where('code', 'pastpay')->first();

    $orgPaymentServiceProvider = StoreOrgPaymentServiceProvider::make()->action(
        paymentServiceProvider: $paymentServiceProvider,
        organisation: $organisation,
        modelData: PaymentServiceProvider::factory()->definition()
    );

    $paymentAccountData = PaymentAccount::factory()->definition();
    data_set($paymentAccountData, 'type', PaymentAccountTypeEnum::PASTPAY->value);

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

function createOrderWithPastpayApiPoint($customer, $product, PaymentAccountShop $paymentAccountShop): array
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
            'pastpay' => [
                'payment_account_shop_id' => $paymentAccountShop->id,
                'charges'                 => 0,
                'term_days'               => 30,
                'to_pay'                  => 25.00,
            ]
        ]
    ]);

    return [$order, $orderPaymentApiPoint];
}

function fakePastpayOrder(Order $order, string $status): array
{
    return [
        'data' => [
            'orderId'         => $order->reference,
            'debtorTaxNumber' => '10307078',
            'totalPrice'      => [
                'amount'   => 25.00,
                'currency' => $order->currency->code,
            ],
            'createdAt'       => now()->toIso8601String(),
            'status'          => $status,
        ]
    ];
}

test('approved pastpay order stores payment, marks api point success and submits order', function () {
    $paymentAccountShop = createPastpayPaymentAccountShop($this->organisation, $this->shop);
    list($order, $orderPaymentApiPoint) = createOrderWithPastpayApiPoint($this->customer, $this->product, $paymentAccountShop);

    PastpayOrderPaymentSuccess::partialMock()
        ->shouldReceive('pastpayGetOrder')
        ->andReturn(fakePastpayOrder($order, 'reserved'));

    $result = PastpayOrderPaymentSuccess::make()->handle($orderPaymentApiPoint);

    $orderPaymentApiPoint->refresh();
    $order->refresh();

    expect($result['status'])->toBe('success')
        ->and($orderPaymentApiPoint->state)->toBe(OrderPaymentApiPointStateEnum::SUCCESS)
        ->and($order->state)->toBe(OrderStateEnum::SUBMITTED)
        ->and($order->payments()->where('reference', $order->reference)->count())->toBe(1);
});

test('pastpay term charge is added to the order on success', function () {
    $paymentAccountShop = createPastpayPaymentAccountShop($this->organisation, $this->shop);
    list($order, $orderPaymentApiPoint) = createOrderWithPastpayApiPoint($this->customer, $this->product, $paymentAccountShop);

    $charge = StoreCharge::make()->action($this->shop, [
        'code'        => 'Pastpay-30-'.$this->shop->code,
        'name'        => 'Pastpay charge (30 days)',
        'description' => 'Pastpay charge (30 days)',
        'state'       => ChargeStateEnum::ACTIVE,
        'trigger'     => ChargeTriggerEnum::PAYMENT_ACCOUNT,
        'type'        => ChargeTypeEnum::PAYMENT,
    ]);

    $orderPaymentApiPoint->update([
        'data' => [
            'pastpay' => [
                'payment_account_shop_id' => $paymentAccountShop->id,
                'charges'                 => 0.55,
                'term_days'               => 30,
                'to_pay'                  => 25.55,
            ]
        ]
    ]);

    PastpayOrderPaymentSuccess::partialMock()
        ->shouldReceive('pastpayGetOrder')
        ->andReturn(fakePastpayOrder($order, 'reserved'));

    $result = PastpayOrderPaymentSuccess::make()->handle($orderPaymentApiPoint);

    $order->refresh();

    $chargeTransaction = $order->transactions()
        ->where('model_type', 'Charge')
        ->where('model_id', $charge->id)
        ->first();

    $expectedNet = round(0.55 / (1 + (float) $order->taxCategory->rate), 2);

    expect($result['status'])->toBe('success')
        ->and($chargeTransaction)->not->toBeNull()
        ->and((float) $chargeTransaction->net_amount)->toBe($expectedNet);
});

test('success redirect is idempotent', function () {
    $paymentAccountShop = createPastpayPaymentAccountShop($this->organisation, $this->shop);
    list($order, $orderPaymentApiPoint) = createOrderWithPastpayApiPoint($this->customer, $this->product, $paymentAccountShop);

    PastpayOrderPaymentSuccess::partialMock()
        ->shouldReceive('pastpayGetOrder')
        ->andReturn(fakePastpayOrder($order, 'reserved'));

    PastpayOrderPaymentSuccess::make()->handle($orderPaymentApiPoint);
    $result = PastpayOrderPaymentSuccess::make()->handle($orderPaymentApiPoint->refresh());

    $order->refresh();

    expect($result['status'])->toBe('success')
        ->and($order->payments()->where('reference', $order->reference)->count())->toBe(1);
});

test('non approved pastpay status marks api point as failure and does not submit order', function () {
    $paymentAccountShop = createPastpayPaymentAccountShop($this->organisation, $this->shop);
    list($order, $orderPaymentApiPoint) = createOrderWithPastpayApiPoint($this->customer, $this->product, $paymentAccountShop);

    PastpayOrderPaymentSuccess::partialMock()
        ->shouldReceive('pastpayGetOrder')
        ->andReturn(fakePastpayOrder($order, 'rejected'));

    $result = PastpayOrderPaymentSuccess::make()->handle($orderPaymentApiPoint);

    $orderPaymentApiPoint->refresh();
    $order->refresh();

    expect($result['status'])->toBe('failure')
        ->and($orderPaymentApiPoint->state)->toBe(OrderPaymentApiPointStateEnum::FAILURE)
        ->and($order->state)->toBe(OrderStateEnum::CREATING)
        ->and($order->payments()->count())->toBe(0);
});

test('failure redirect recovers an approved pastpay order', function () {
    $paymentAccountShop = createPastpayPaymentAccountShop($this->organisation, $this->shop);
    list($order, $orderPaymentApiPoint) = createOrderWithPastpayApiPoint($this->customer, $this->product, $paymentAccountShop);

    PastpayOrderPaymentFailure::partialMock()
        ->shouldReceive('pastpayGetOrder')
        ->andReturn(fakePastpayOrder($order, 'reserved'));

    $resultApiPoint = PastpayOrderPaymentFailure::make()->handle($orderPaymentApiPoint);

    $order->refresh();

    expect($resultApiPoint->state)->toBe(OrderPaymentApiPointStateEnum::SUCCESS)
        ->and($order->state)->toBe(OrderStateEnum::SUBMITTED)
        ->and($order->payments()->where('reference', $order->reference)->count())->toBe(1);
});

test('failure redirect never clobbers a successful api point', function () {
    $paymentAccountShop = createPastpayPaymentAccountShop($this->organisation, $this->shop);
    list($order, $orderPaymentApiPoint) = createOrderWithPastpayApiPoint($this->customer, $this->product, $paymentAccountShop);

    $orderPaymentApiPoint->update(['state' => OrderPaymentApiPointStateEnum::SUCCESS]);

    $resultApiPoint = PastpayOrderPaymentFailure::make()->handle($orderPaymentApiPoint);

    expect($resultApiPoint->state)->toBe(OrderPaymentApiPointStateEnum::SUCCESS);
});

test('pastpay invoice uses payment account shop footer and finalizes with base64 pdf', function () {
    $paymentAccountShop = createPastpayPaymentAccountShop($this->organisation, $this->shop);
    PaymentAccountShop::where('shop_id', $this->shop->id)
        ->where('type', PaymentAccountTypeEnum::PASTPAY)
        ->update(['invoice_footer' => 'Pay to PastPay bank account XYZ']);

    if (!$this->customer->taxNumber) {
        $this->customer->taxNumber()->create([
            'number'       => '10307078',
            'country_code' => 'HU',
        ]);
        $this->customer->refresh();
    }

    list($order, $orderPaymentApiPoint) = createOrderWithPastpayApiPoint($this->customer, $this->product, $paymentAccountShop);

    PastpayOrderPaymentSuccess::partialMock()
        ->shouldReceive('pastpayGetOrder')
        ->andReturn(fakePastpayOrder($order, 'reserved'));
    PastpayOrderPaymentSuccess::make()->handle($orderPaymentApiPoint);
    $order->refresh();

    expect($order->is_pastpay)->toBeTrue();

    $finalizePayload = null;
    FinalizeOrderWithPastpay::partialMock()
        ->shouldReceive('getInvoicePdfContent')->andReturn('PDFBYTES')
        ->shouldReceive('pastpayPost')
        ->withArgs(function ($endpoint, $payload) use (&$finalizePayload) {
            $finalizePayload = $payload;

            return str_contains($endpoint, '/finalize');
        })
        ->andReturn(['message' => 'Order created']);

    $invoiceData = Invoice::factory()->definition();
    data_set($invoiceData, 'billing_address', new Address(Address::factory()->definition()));
    $invoice = StoreInvoice::make()->action($order, $invoiceData);

    expect($invoice->is_pastpay)->toBeTrue()
        ->and($finalizePayload)->not->toBeNull()
        ->and($invoice->footer)->toBe('Pay to PastPay bank account XYZ')
        ->and($finalizePayload['invoicePdf'])->toBe('data:application/pdf;base64,'.base64_encode('PDFBYTES'))
        ->and($finalizePayload['invoiceNo'])->toBe($invoice->reference)
        ->and($finalizePayload['totalPrice']['amount'])->toBe((float) $invoice->total_amount)
        ->and(isset($finalizePayload['termDays']))->toBeFalse();
});

test('updating pastpay shop account stores creditor tax number on parent payment account', function () {
    $paymentAccountShop = createPastpayPaymentAccountShop($this->organisation, $this->shop);
    $paymentAccountShop->update(['data' => ['charges' => ['options' => [['days' => 30, 'charge' => '1']]]]]);

    UpdatePaymentAccountShop::make()->action($paymentAccountShop, [
        'pastpay_tax_number' => 'SK2120525440',
        'is_active'          => false,
    ]);

    $paymentAccountShop->refresh();

    expect(Arr::get($paymentAccountShop->paymentAccount->data, 'tax_number'))->toBe('SK2120525440')
        ->and(Arr::get($paymentAccountShop->data, 'charges.options.0.days'))->toBe(30)
        ->and($paymentAccountShop->state)->toBe(PaymentAccountShopStateEnum::INACTIVE);

    expect(fn () => UpdatePaymentAccountShop::make()->action($paymentAccountShop, [
        'is_active' => true,
    ]))->toThrow(Illuminate\Validation\ValidationException::class);

    UpdatePaymentAccountShop::make()->action($paymentAccountShop, [
        'is_active'      => true,
        'invoice_footer' => '<p>Pay to PastPay</p>',
    ]);

    expect($paymentAccountShop->refresh()->state)->toBe(PaymentAccountShopStateEnum::ACTIVE);
});

test('editing pastpay credit terms upserts the per-term charges', function () {
    $paymentAccountShop = createPastpayPaymentAccountShop($this->organisation, $this->shop);

    expect($this->shop->charges()->where('code', 'like', 'Pastpay-45-%')->exists())->toBeFalse();

    UpdatePaymentAccountShop::make()->action($paymentAccountShop, [
        'pastpay_charges' => [
            ['days' => 45, 'charge' => '2'],
        ],
    ]);

    $charge = $this->shop->charges()->where('code', 'like', 'Pastpay-45-%')->first();

    expect($charge)->not->toBeNull()
        ->and($charge->state)->toBe(ChargeStateEnum::ACTIVE)
        ->and($charge->type)->toBe(ChargeTypeEnum::PAYMENT->value);

    $charge->update(['state' => ChargeStateEnum::DISCONTINUED]);

    UpdatePaymentAccountShop::make()->action($paymentAccountShop, [
        'pastpay_charges' => [
            ['days' => 45, 'charge' => '2.5'],
        ],
    ]);

    expect($charge->refresh()->state)->toBe(ChargeStateEnum::ACTIVE)
        ->and($this->shop->charges()->where('code', 'like', 'Pastpay-45-%')->count())->toBe(1);
});

test('pastpay is hidden at checkout unless account is active and fully configured', function () {
    $paymentAccountShop = createPastpayPaymentAccountShop($this->organisation, $this->shop);
    list($order, $orderPaymentApiPoint) = createOrderWithPastpayApiPoint($this->customer, $this->product, $paymentAccountShop);

    if (!$this->customer->taxNumber) {
        $this->customer->taxNumber()->create(['number' => '10307078', 'country_code' => 'HU']);
        $order->customer->refresh();
    }

    expect(GetRetinaPaymentAccountShopData::run($order, $paymentAccountShop, $orderPaymentApiPoint))->toBeNull();

    $paymentAccountShop->update([
        'data'           => ['charges' => ['options' => [['days' => 30, 'charge' => '1']]]],
        'invoice_footer' => '<p>Pay to PastPay</p>',
    ]);
    $paymentAccount = $paymentAccountShop->paymentAccount;
    $paymentAccount->update(['data' => array_merge($paymentAccount->data ?? [], ['tax_number' => 'SK2120525440'])]);

    expect(GetRetinaPaymentAccountShopData::run($order, $paymentAccountShop->refresh(), $orderPaymentApiPoint))
        ->toBeArray()
        ->and(GetRetinaPaymentAccountShopData::run($order, $paymentAccountShop, $orderPaymentApiPoint)['key'])->toBe('pastpay');

    $paymentAccountShop->update(['state' => PaymentAccountShopStateEnum::INACTIVE]);

    expect(GetRetinaPaymentAccountShopData::run($order, $paymentAccountShop->refresh(), $orderPaymentApiPoint))->toBeNull();
});

test('unprocessed pastpay status marks api point as failure', function () {
    $paymentAccountShop = createPastpayPaymentAccountShop($this->organisation, $this->shop);
    list($order, $orderPaymentApiPoint) = createOrderWithPastpayApiPoint($this->customer, $this->product, $paymentAccountShop);

    PastpayOrderPaymentFailure::partialMock()
        ->shouldReceive('pastpayGetOrder')
        ->andReturn(fakePastpayOrder($order, 'timeout'));

    $resultApiPoint = PastpayOrderPaymentFailure::make()->handle($orderPaymentApiPoint);

    expect($resultApiPoint->state)->toBe(OrderPaymentApiPointStateEnum::FAILURE)
        ->and($order->refresh()->payments()->count())->toBe(0);
});
