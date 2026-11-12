<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026 18:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Accounting\BankConnection\StoreBankConnection;
use App\Actions\Accounting\BankConnection\SyncBankConnection;
use App\Actions\Accounting\BankConnection\Wise\SyncWiseBankConnection;
use App\Enums\Accounting\BankConnection\BankConnectionProviderEnum;
use App\Enums\Accounting\BankConnection\BankConnectionStateEnum;
use App\Enums\Accounting\BankTransaction\BankTransactionDirectionEnum;
use App\Enums\Accounting\BankTransaction\BankTransactionStatusEnum;
use App\Actions\Accounting\BankTransaction\MatchBankTransactions;
use App\Enums\Accounting\BankTransaction\BankTransactionMatchStateEnum;
use App\Models\Accounting\BankConnection;
use App\Models\Accounting\BankTransaction;
use App\Models\Helpers\Currency;
use App\Actions\Ordering\Order\StoreOrder;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Helpers\Address;
use App\Actions\Accounting\BankConnection\SetBankConnectionAutoPay;
use App\Actions\Accounting\BankTransaction\PayMatchedBankTransactions;
use App\Actions\Accounting\OrgPaymentServiceProvider\StoreOrgPaymentServiceProviderAccount;
use App\Actions\Accounting\Payment\CancelPayment;
use App\Actions\Accounting\Payment\RefundPaymentToBalance;
use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Ordering\Order\UpdateState\CancelOrder;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Enums\Ordering\Order\OrderPayStatusEnum;
use App\Models\Accounting\PaymentServiceProvider;
use App\Models\Ordering\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Actions\Ordering\Order\GenerateInvoiceFromOrder;
use App\Actions\Ordering\Order\UpdateOrderPaymentsStatus;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Models\Ordering\Transaction;
use Illuminate\Support\Facades\Http;

uses()->group('base');

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    list($this->organisation, , $this->shop) = createShop();
});

function wiseActivity(string $id, string $type, string $amount, string $title, string $status = 'COMPLETED', ?string $resourceId = null, string $updatedOn = '2026-11-10T10:05:00.071Z'): array
{
    return [
        'id'              => $id,
        'type'            => $type,
        'resource'        => ['type' => $type === 'TRANSFER' ? 'TRANSFER' : 'CARD_TRANSACTION', 'id' => $resourceId ?? $id],
        'title'           => "<strong>$title</strong>",
        'description'     => '',
        'primaryAmount'   => $amount,
        'secondaryAmount' => '',
        'status'          => $status,
        'createdOn'       => '2026-11-10T10:00:00.483Z',
        'updatedOn'       => $updatedOn,
    ];
}

test('wise display amounts are parsed strictly', function () {
    $parser = SyncWiseBankConnection::make();

    expect($parser->parseAmount('<positive>+ 1,312,516 HUF</positive>'))->toBe([true, 1312516.0, 'HUF'])
        ->and($parser->parseAmount('11.99 EUR'))->toBe([false, 11.99, 'EUR'])
        ->and($parser->parseAmount('<strikethrough>99.50 GBP</strikethrough>'))->toBe([false, 99.5, 'GBP'])
        ->and($parser->parseAmount(''))->toBeNull()
        ->and($parser->parseAmount("1\u{00A0}312\u{00A0}516 HUF"))->toBeNull()
        ->and($parser->parseAmount('1.312.516,00 EUR'))->toBeNull();
});

test('wise activities become the lines they moved on our balances', function () {
    $parser = SyncWiseBankConnection::make();
    $activity = fn (string $type, string $primary, string $secondary, string $title = '') => ['type' => $type, 'title' => $title, 'primaryAmount' => $primary, 'secondaryAmount' => $secondary];

    expect($parser->legs($activity('INTERBALANCE', '50,000 GBP', '', 'To <strong>Savings</strong>')))
        ->toBe([
            ''    => [BankTransactionDirectionEnum::OUT, 50000.0, 'GBP', null],
            '#in' => [BankTransactionDirectionEnum::IN, 50000.0, 'GBP', 'jar'],
        ])
        ->and($parser->legs($activity('INTERBALANCE', '10,000 GBP', '', 'To <strong>GBP</strong>')))
        ->toBe([
            ''    => [BankTransactionDirectionEnum::OUT, 10000.0, 'GBP', 'jar'],
            '#in' => [BankTransactionDirectionEnum::IN, 10000.0, 'GBP', null],
        ])
        ->and($parser->legs($activity('INTERBALANCE', '10,000 GBP', '', 'From <strong>Savings</strong>')))
        ->toBe('balance move not understood: From Savings');

    expect($parser->legs($activity('TRANSFER', '834,739.27 INR', '8,000 EUR')))
        ->toBe(['' => [BankTransactionDirectionEnum::OUT, 8000.0, 'EUR']])
        ->and($parser->legs($activity('TRANSFER', '<positive>+ 44,509.17 CZK</positive>', '44,604.47 CZK')))
        ->toBe(['' => [BankTransactionDirectionEnum::IN, 44509.17, 'CZK']])
        ->and($parser->legs($activity('CARD_PAYMENT', '11.99 EUR', '')))
        ->toBe(['' => [BankTransactionDirectionEnum::OUT, 11.99, 'EUR']])
        ->and($parser->legs($activity('INTERBALANCE', '20,000 USD', '6,498,489 HUF')))
        ->toBe([
            ''    => [BankTransactionDirectionEnum::OUT, 6498489.0, 'HUF'],
            '#in' => [BankTransactionDirectionEnum::IN, 20000.0, 'USD'],
        ]);
});

test('a wise connection mirrors every activity once, with transfer references', function () {
    Http::fake([
        'api.wise.com/v2/profiles'            => Http::response([
            ['id' => 111, 'type' => 'PERSONAL'],
            ['id' => 222, 'type' => 'BUSINESS'],
        ]),
        'api.wise.com/v1/profiles/222/activities*' => Http::sequence()
            ->push(['cursor' => 'next', 'activities' => [
                wiseActivity('a1', 'TRANSFER', '<positive>+ 577.30 PLN</positive>', 'Jan Kowalski', resourceId: '9001'),
                wiseActivity('a2', 'CARD_PAYMENT', '11.99 EUR', 'Shop &amp; Co'),
            ]])
            ->push(['cursor' => null, 'activities' => [
                wiseActivity('a3', 'TRANSFER', '250.00 EUR', 'Supplier Ltd', 'CANCELLED', '9002'),
            ]])
            ->push(['cursor' => null, 'activities' => [
                wiseActivity('a1', 'TRANSFER', '<positive>+ 577.30 PLN</positive>', 'Jan Kowalski', resourceId: '9001'),
            ]])
            ->push(['cursor' => null, 'activities' => [
                wiseActivity('a3', 'TRANSFER', '250.00 EUR', 'Supplier Ltd', 'COMPLETED', '9002', '2026-11-11T08:00:00.312Z'),
                wiseActivity('a4', 'TRANSFER', '<positive>+ 10.00 XYZ</positive>', 'Unknown', resourceId: '9003'),
            ]]),
        'api.wise.com/v1/transfers/9001'      => Http::response(['id' => 9001, 'reference' => 'PL31515']),
        'api.wise.com/v1/transfers/9002'      => Http::response(['errors' => [['code' => 'forbidden.error']]], 403),
    ]);

    $bankConnection = StoreBankConnection::run($this->organisation, [
        'provider'    => BankConnectionProviderEnum::WISE,
        'name'        => 'Wise SK',
        'credentials' => 'test-token',
    ]);

    expect($bankConnection->external_id)->toBe('222')
        ->and($bankConnection->getRawOriginal('credentials'))->not->toBe('test-token');

    expect(SyncBankConnection::run($bankConnection, true))->toBe(['synced' => 3, 'skipped' => 0, 'skipped_examples' => []]);

    $lines = BankTransaction::where('bank_connection_id', $bankConnection->id)->get()->keyBy('provider_transaction_id');

    expect($lines)->toHaveCount(3)
        ->and($lines['a1']->direction)->toBe(BankTransactionDirectionEnum::IN)
        ->and((float)$lines['a1']->amount)->toBe(577.3)
        ->and($lines['a1']->currency->code)->toBe('PLN')
        ->and($lines['a1']->reference)->toBe('PL31515')
        ->and($lines['a1']->counterparty_name)->toBe('Jan Kowalski')
        ->and($lines['a1']->status)->toBe(BankTransactionStatusEnum::BOOKED)
        ->and((float)$lines['a2']->amount)->toBe(-11.99)
        ->and($lines['a2']->counterparty_name)->toBe('Shop & Co')
        ->and($lines['a3']->status)->toBe(BankTransactionStatusEnum::CANCELLED)
        ->and($lines['a3']->reference)->toBeNull();

    expect(SyncBankConnection::run($bankConnection->refresh()))->toBe(['synced' => 0, 'skipped' => 0, 'skipped_examples' => []])
        ->and(BankTransaction::where('bank_connection_id', $bankConnection->id)->count())->toBe(3)
        ->and($bankConnection->refresh()->state)->toBe(BankConnectionStateEnum::ACTIVE);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/activities') && str_contains($request->url(), 'since='));

    expect(SyncBankConnection::run($bankConnection->refresh()))->toBe(['synced' => 1, 'skipped' => 1, 'skipped_examples' => ['a4: unknown currency: XYZ']])
        ->and($lines['a3']->refresh()->status)->toBe(BankTransactionStatusEnum::BOOKED)
        ->and(BankTransaction::where('bank_connection_id', $bankConnection->id)->count())->toBe(3);
});

test('a full sync re-derives lines stored by an older parser, a jar move becoming two lines', function () {
    $jarMove = array_merge(
        wiseActivity('j1', 'INTERBALANCE', '50,000 GBP', 'Savings'),
        ['title' => 'To <strong>Savings</strong>', 'resource' => ['type' => 'BALANCE_TRANSACTION', 'id' => 'j1']]
    );

    Http::fake([
        'api.wise.com/v2/profiles'                 => Http::response([['id' => 555, 'type' => 'BUSINESS']]),
        'api.wise.com/v1/profiles/555/activities*' => Http::response(['cursor' => null, 'activities' => [$jarMove]]),
    ]);

    $bankConnection = StoreBankConnection::run($this->organisation, [
        'provider'    => BankConnectionProviderEnum::WISE,
        'name'        => 'Wise jars',
        'credentials' => 'jar-token',
    ]);

    BankTransaction::create([
        'group_id'                => $bankConnection->group_id,
        'organisation_id'         => $bankConnection->organisation_id,
        'bank_connection_id'      => $bankConnection->id,
        'provider_transaction_id' => 'j1',
        'provider_type'           => 'INTERBALANCE',
        'direction'               => BankTransactionDirectionEnum::OUT,
        'amount'                  => -50000,
        'currency_id'             => Currency::where('code', 'GBP')->value('id'),
        'status'                  => BankTransactionStatusEnum::BOOKED,
        'booked_at'               => now(),
        'data'                    => ['activity' => $jarMove],
    ]);

    expect(SyncBankConnection::run($bankConnection, true)['synced'])->toBe(1)
        ->and(SyncBankConnection::run($bankConnection->refresh(), true)['synced'])->toBe(0);

    $lines = BankTransaction::where('bank_connection_id', $bankConnection->id)->get()->keyBy('provider_transaction_id');

    expect($lines)->toHaveCount(2)
        ->and((float)$lines['j1']->amount)->toBe(-50000.0)
        ->and($lines['j1']->account_reference)->toBeNull()
        ->and((float)$lines['j1#in']->amount)->toBe(50000.0)
        ->and($lines['j1#in']->account_reference)->toBe('jar');
});

function bankMatchingOrder(\App\Models\CRM\Customer $customer, float $total): \App\Models\Ordering\Order
{
    $order = StoreOrder::make()->action($customer, [
        'date'             => date('Y-m-d'),
        'delivery_address' => new Address(Address::factory()->definition()),
        'billing_address'  => new Address(Address::factory()->definition()),
    ]);
    $order->updateQuietly([
        'reference'      => 'TST'.random_int(100000, 999999),
        'state'          => OrderStateEnum::SUBMITTED,
        'total_amount'   => $total,
        'payment_amount' => 0,
    ]);

    return $order->refresh();
}

function bankMatchingConnection(\App\Models\SysAdmin\Organisation $organisation): BankConnection
{
    return BankConnection::create([
        'group_id'        => $organisation->group_id,
        'organisation_id' => $organisation->id,
        'provider'        => BankConnectionProviderEnum::WISE,
        'name'            => 'Wise matching',
        'external_id'     => (string)random_int(1000, 9999),
    ]);
}

test('incoming bank transfers are matched to the order their reference names, nothing is paid', function () {
    $order          = bankMatchingOrder(createCustomer($this->shop), 100);
    $bankConnection = bankMatchingConnection($this->organisation);

    $line = fn (string $id, ?string $reference, float $amount, ?int $currencyId = null, string $type = 'TRANSFER') => BankTransaction::create([
        'group_id'                => $bankConnection->group_id,
        'organisation_id'         => $bankConnection->organisation_id,
        'bank_connection_id'      => $bankConnection->id,
        'provider_transaction_id' => $id,
        'provider_type'           => $type,
        'direction'               => BankTransactionDirectionEnum::IN,
        'amount'                  => $amount,
        'currency_id'             => $currencyId ?? $order->currency_id,
        'status'                  => BankTransactionStatusEnum::BOOKED,
        'reference'               => $reference,
        'booked_at'               => now(),
    ]);

    $otherCurrencyId = Currency::where('id', '!=', $order->currency_id)->value('id');

    $lines = [
        'fee'       => $line('m1', 'Payment for order '.strtolower($order->reference), 99.50),
        'second'    => $line('m2', $order->reference, 99.50),
        'over'      => $line('m3', $order->reference, 150),
        'under'     => $line('m4', $order->reference, 80),
        'currency'  => $line('m5', $order->reference, 100, $otherCurrencyId),
        'none'      => $line('m6', 'Thank you', 100),
        'unknown'   => $line('m7', 'ZZZ99999999', 100),
        'card'      => $line('m8', $order->reference, 100, type: 'CARD_PAYMENT'),
    ];

    expect(MatchBankTransactions::run($bankConnection))->toBe(['matched' => 1, 'review' => 4, 'unmatched' => 2]);

    $lines = collect($lines)->map(fn (BankTransaction $bankTransaction) => $bankTransaction->refresh());

    expect($lines->map(fn ($bankTransaction) => $bankTransaction->match_state?->value)->all())->toBe([
        'fee'      => 'matched',
        'second'   => 'review',
        'over'     => 'review',
        'under'    => 'review',
        'currency' => 'review',
        'none'     => 'unmatched',
        'unknown'  => 'unmatched',
        'card'     => null,
    ])
        ->and($lines['fee']->order_id)->toBe($order->id)
        ->and($lines['fee']->payment_id)->toBeNull()
        ->and($lines['fee']->match_data['difference'])->toBe(-0.5)
        ->and($lines['second']->match_data['reason'])->toBe('another bank line is already matched to this order')
        ->and($order->refresh()->payment_amount)->toEqual(0);

    $basket = bankMatchingOrder(createCustomer($this->shop), 100);
    $basket->updateQuietly(['state' => OrderStateEnum::CREATING]);
    $settled = bankMatchingOrder(createCustomer($this->shop), 100);
    $settled->updateQuietly(['payment_amount' => 100]);

    $basketLine  = $line('m9', $basket->reference, 100);
    $settledLine = $line('m10', $settled->reference, 100);

    expect(MatchBankTransactions::run($bankConnection))->toBe(['review' => 2])
        ->and($basketLine->refresh()->match_data['reason'])->toBe('order still in the basket')
        ->and($settledLine->refresh()->match_data['reason'])->toBe('order owes nothing but has no payment');
});

test('a matching decision is taken again when the bank cancels the line', function () {
    $order = bankMatchingOrder(createCustomer($this->shop), 250);

    $incoming = fn (string $status, string $updatedOn) => array_merge(
        wiseActivity('c1', 'TRANSFER', '<positive>+ 250.00 '.$order->currency->code.'</positive>', 'Customer', $status, '7001', $updatedOn),
    );

    Http::fake([
        'api.wise.com/v2/profiles'                 => Http::response([['id' => 777, 'type' => 'BUSINESS']]),
        'api.wise.com/v1/profiles/777/activities*' => Http::sequence()
            ->push(['cursor' => null, 'activities' => [$incoming('COMPLETED', '2026-11-10T10:05:00.071Z')]])
            ->push(['cursor' => null, 'activities' => [$incoming('CANCELLED', '2026-11-11T09:00:00.512Z')]]),
        'api.wise.com/v1/transfers/7001'           => Http::response(['id' => 7001, 'reference' => $order->reference]),
    ]);

    $bankConnection = StoreBankConnection::run($this->organisation, [
        'provider'    => BankConnectionProviderEnum::WISE,
        'name'        => 'Wise cancels',
        'credentials' => 'cancel-token',
    ]);

    SyncBankConnection::run($bankConnection, true);
    $line = BankTransaction::where('bank_connection_id', $bankConnection->id)->sole();

    expect($line->match_state)->toBe(BankTransactionMatchStateEnum::MATCHED)
        ->and($line->order_id)->toBe($order->id);

    SyncBankConnection::run($bankConnection->refresh());

    expect($line->refresh()->status)->toBe(BankTransactionStatusEnum::CANCELLED)
        ->and($line->match_state)->toBeNull()
        ->and($line->order_id)->toBeNull();
});

test('auto-pay records the money received and absorbs the bank fee, only for lines after it was switched on', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1.0);

    $order          = bankMatchingOrder(createCustomer($this->shop), 100);
    $older          = bankMatchingOrder(createCustomer($this->shop), 40);
    $bankConnection = bankMatchingConnection($this->organisation);
    $bankAccount    = StoreOrgPaymentServiceProviderAccount::make()->action(
        $this->organisation,
        PaymentServiceProvider::where('code', 'bank')->firstOrFail(),
        ['code' => 'wise'.random_int(1000, 9999), 'name' => 'Wise test']
    );

    $line = fn (string $id, Order $order, float $amount, $bookedAt) => BankTransaction::create([
        'group_id'                => $bankConnection->group_id,
        'organisation_id'         => $bankConnection->organisation_id,
        'bank_connection_id'      => $bankConnection->id,
        'provider_transaction_id' => $id,
        'provider_type'           => 'TRANSFER',
        'direction'               => BankTransactionDirectionEnum::IN,
        'amount'                  => $amount,
        'currency_id'             => $order->currency_id,
        'status'                  => BankTransactionStatusEnum::BOOKED,
        'reference'               => $order->reference,
        'booked_at'               => $bookedAt,
    ]);

    $beforeSwitchOn = $line('p0', $older, 40, now()->subDay());

    SetBankConnectionAutoPay::run($bankConnection, [$order->currency->code => $bankAccount->code]);
    $bankConnection->refresh();

    $paidLine = $line('p1', $order, 99.50, now()->addMinute());

    MatchBankTransactions::run($bankConnection);
    expect(PayMatchedBankTransactions::run($bankConnection))->toBe(['paid' => 1]);

    $paidLine->refresh();
    $order->refresh();

    expect($beforeSwitchOn->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::MATCHED)
        ->and($beforeSwitchOn->payment_id)->toBeNull()
        ->and((float)$paidLine->payment->amount)->toBe(99.5)
        ->and($paidLine->payment->payment_account_id)->toBe($bankAccount->id)
        ->and((float)$paidLine->bankFeePayment->amount)->toBe(0.5)
        ->and($paidLine->bankFeePayment->paymentAccount->type)->toBe(PaymentAccountTypeEnum::BANK_FEE)
        ->and($order->pay_status)->toBe(OrderPayStatusEnum::PAID)
        ->and((float)$order->payment_amount)->toBe(100.0)
        ->and(PayMatchedBankTransactions::run($bankConnection))->toBe([]);

    expect(fn () => DB::transaction(fn () => RefundPaymentToBalance::lockForRefund($paidLine->bankFeePayment, null, 0.5)))
        ->toThrow(ValidationException::class);

    $topUp = $line('p2', $order, 0.50, now()->addMinutes(2));
    MatchBankTransactions::run($bankConnection);
    expect($topUp->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::REVIEW)
        ->and($topUp->match_data['reason'])->toBe('a bank fee was absorbed on this order, the customer may be paying it');

    CancelOrder::make()->action($order->refresh());

    expect($paidLine->bankFeePayment->refresh()->state)->toBe(PaymentStateEnum::CANCELLED)
        ->and((float)$order->refresh()->payments()->where('payments.type', PaymentTypeEnum::REFUND)->sum('payments.amount'))->toBe(-99.5);
});

test('cancelling a payment recorded from a bank line takes its absorbed fee with it and sends the line to review', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1.0);

    $order          = bankMatchingOrder(createCustomer($this->shop), 200);
    $bankConnection = bankMatchingConnection($this->organisation);
    $bankAccount    = StoreOrgPaymentServiceProviderAccount::make()->action(
        $this->organisation,
        PaymentServiceProvider::where('code', 'bank')->firstOrFail(),
        ['code' => 'wise'.random_int(1000, 9999), 'name' => 'Wise cancel test']
    );
    SetBankConnectionAutoPay::run($bankConnection, [$order->currency->code => $bankAccount->code]);

    $bankTransaction = BankTransaction::create([
        'group_id'                => $bankConnection->group_id,
        'organisation_id'         => $bankConnection->organisation_id,
        'bank_connection_id'      => $bankConnection->id,
        'provider_transaction_id' => 'x1',
        'provider_type'           => 'TRANSFER',
        'direction'               => BankTransactionDirectionEnum::IN,
        'amount'                  => 199,
        'currency_id'             => $order->currency_id,
        'status'                  => BankTransactionStatusEnum::BOOKED,
        'reference'               => $order->reference,
        'booked_at'               => now()->addMinute(),
    ]);

    MatchBankTransactions::run($bankConnection->refresh());
    expect(PayMatchedBankTransactions::run($bankConnection))->toBe(['paid' => 1]);

    $bankTransaction->refresh();
    $bankFeePayment = $bankTransaction->bankFeePayment;

    CancelPayment::make()->handle($bankTransaction->payment);

    expect($bankFeePayment->refresh()->state)->toBe(PaymentStateEnum::CANCELLED)
        ->and($bankTransaction->refresh()->payment_id)->toBeNull()
        ->and($bankTransaction->bank_fee_payment_id)->toBeNull()
        ->and($bankTransaction->match_state)->toBe(BankTransactionMatchStateEnum::REVIEW)
        ->and((float)$order->refresh()->payment_amount)->toBe(0.0)
        ->and(PayMatchedBankTransactions::run($bankConnection))->toBe([]);

    $bankTransaction->update(['match_state' => null]);
    MatchBankTransactions::run($bankConnection);

    expect($bankTransaction->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::REVIEW)
        ->and($bankTransaction->match_data['reason'])->toBe('a payment from this line on this order was cancelled');
});

test('a second transfer of the same amount for an order already paid from the bank goes to review', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1.0);

    $order          = bankMatchingOrder(createCustomer($this->shop), 80);
    $bankConnection = bankMatchingConnection($this->organisation);
    $bankAccount    = StoreOrgPaymentServiceProviderAccount::make()->action(
        $this->organisation,
        PaymentServiceProvider::where('code', 'bank')->firstOrFail(),
        ['code' => 'wise'.random_int(1000, 9999), 'name' => 'Wise duplicate test']
    );
    SetBankConnectionAutoPay::run($bankConnection, [$order->currency->code => $bankAccount->code]);
    $bankConnection->refresh();

    $line = fn (string $id) => BankTransaction::create([
        'group_id'                => $bankConnection->group_id,
        'organisation_id'         => $bankConnection->organisation_id,
        'bank_connection_id'      => $bankConnection->id,
        'provider_transaction_id' => $id,
        'provider_type'           => 'TRANSFER',
        'direction'               => BankTransactionDirectionEnum::IN,
        'amount'                  => 80,
        'currency_id'             => $order->currency_id,
        'status'                  => BankTransactionStatusEnum::BOOKED,
        'reference'               => $order->reference,
        'booked_at'               => now()->addMinute(),
    ]);

    $first = $line('d1');
    MatchBankTransactions::run($bankConnection);
    PayMatchedBankTransactions::run($bankConnection);

    $second = $line('d2');
    MatchBankTransactions::run($bankConnection);

    expect($first->refresh()->payment_id)->not->toBeNull()
        ->and($second->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::REVIEW)
        ->and($second->match_data['reason'])->toBe('a second transfer of the same amount, the first one already paid this order');
});

test('invoicing an order paid with an absorbed bank fee leaves its stored pay status true to its payments', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1.0);

    $customer       = createCustomer($this->shop);
    [, $product]    = createProduct($this->shop);
    $order          = bankMatchingOrder($customer, 0);
    $transaction = StoreTransaction::make()->action($order, $product->historicAsset, array_merge(Transaction::factory()->definition(), ['quantity_ordered' => 10]));
    $order->refresh();
    $order->updateQuietly(['state' => OrderStateEnum::SUBMITTED]);

    $bankConnection = bankMatchingConnection($this->organisation);
    $bankAccount    = StoreOrgPaymentServiceProviderAccount::make()->action(
        $this->organisation,
        PaymentServiceProvider::where('code', 'bank')->firstOrFail(),
        ['code' => 'wise'.random_int(1000, 9999), 'name' => 'Wise invoice test']
    );
    SetBankConnectionAutoPay::run($bankConnection, [$order->currency->code => $bankAccount->code]);
    $bankConnection->refresh();

    $owed     = (float)$order->total_amount;
    $received = round($owed - min(0.5, round($owed * 0.009, 2)), 2);

    BankTransaction::create([
        'group_id'                => $bankConnection->group_id,
        'organisation_id'         => $bankConnection->organisation_id,
        'bank_connection_id'      => $bankConnection->id,
        'provider_transaction_id' => 'i1',
        'provider_type'           => 'TRANSFER',
        'direction'               => BankTransactionDirectionEnum::IN,
        'amount'                  => $received,
        'currency_id'             => $order->currency_id,
        'status'                  => BankTransactionStatusEnum::BOOKED,
        'reference'               => $order->reference,
        'booked_at'               => now()->addMinute(),
    ]);

    MatchBankTransactions::run($bankConnection);
    expect(PayMatchedBankTransactions::run($bankConnection))->toBe(['paid' => 1]);

    $transaction->updateQuietly(['quantity_ordered' => 5]);
    $invoice = GenerateInvoiceFromOrder::make()->action($order->refresh());

    expect((float)$invoice->total_amount)->toBeLessThan($received);

    $stored = $order->refresh()->only(['pay_status', 'pay_detailed_status', 'payment_amount']);
    $fresh  = UpdateOrderPaymentsStatus::run($order->refresh())->only(['pay_status', 'pay_detailed_status', 'payment_amount']);

    expect($stored)->toEqual($fresh);
});

test('order numbers are read from bank references as whole words', function () {
    $matcher = MatchBankTransactions::make();

    expect($matcher->orderReferenceTokens('PL31515'))->toBe(['PL31515'])
        ->and($matcher->orderReferenceTokens('orders pl31515, DEU123456 thanks'))->toBe(['PL31515', 'DEU123456'])
        ->and($matcher->orderReferenceTokens('SK2120525440-20261109-01'))->toBe([])
        ->and($matcher->orderReferenceTokens(null))->toBe([]);
});

test('the scheduled sync leaves connections that were never backfilled alone', function () {
    Http::fake([
        'api.wise.com/v2/profiles' => Http::response([['id' => 444, 'type' => 'BUSINESS']]),
    ]);

    $bankConnection = StoreBankConnection::run($this->organisation, [
        'provider'    => BankConnectionProviderEnum::WISE,
        'name'        => 'Wise new',
        'credentials' => 'new-token',
    ]);

    $this->artisan('bank-connections:sync')->run();

    expect($bankConnection->refresh()->last_synced_at)->toBeNull();
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/profiles/444/'));
});

test('a failing bank sync marks the connection as error', function () {
    Http::fake([
        'api.wise.com/v2/profiles'  => Http::response([['id' => 333, 'type' => 'BUSINESS']]),
        'api.wise.com/v1/profiles/*' => Http::response(['error' => 'invalid_token'], 401),
    ]);

    $bankConnection = StoreBankConnection::run($this->organisation, [
        'provider'    => BankConnectionProviderEnum::WISE,
        'name'        => 'Wise broken',
        'credentials' => 'bad-token',
    ]);

    expect(SyncBankConnection::run($bankConnection))->toBeNull()
        ->and($bankConnection->refresh()->state)->toBe(BankConnectionStateEnum::ERROR)
        ->and($bankConnection->last_error)->not->toBeNull();
});
