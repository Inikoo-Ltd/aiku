<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026 18:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Accounting\BankConnection\StoreBankConnection;
use App\Actions\Accounting\Reports\BankFees\IndexBankFees;
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
use App\Models\Helpers\CurrencyExchange;
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
use App\Actions\Accounting\BankTransaction\PayBankTransactionToCustomerCredit;
use App\Http\Resources\Accounting\BankIncomingResource;
use App\Models\Accounting\CreditTransaction;
use App\Actions\Accounting\BankTransaction\PayBankTransactionToOrder;
use Carbon\Carbon;
use App\Actions\SysAdmin\Organisation\StoreOrganisation;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\Config;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses()->group('base');

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    list($this->organisation, , $this->shop) = createShop();
    $this->wiseBalances       = [['currency' => 'EUR', 'amount' => ['value' => 0], 'reservedAmount' => ['value' => 0]]];
    $this->wiseBalancesStatus = 200;
    $this->wiseTransfers      = [];
    Http::fake([
        'api.wise.com/v4/profiles/*/balances*' => fn () => Http::response($this->wiseBalances, $this->wiseBalancesStatus),
        'api.wise.com/v1/transfers?*'          => fn () => Http::response($this->wiseTransfers),
    ]);
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

test('sent transfers missing from the wise feed are added from the transfers list, and transfers older than their balance moved no balance', function () {
    $transfer = fn (int $id, float $value, int $targetAccount, string $created = '2026-07-13 18:04:26', string $reference = '') => [
        'id' => $id, 'status' => 'outgoing_payment_sent', 'sourceCurrency' => 'EUR', 'sourceValue' => $value, 'targetAccount' => $targetAccount, 'created' => $created, 'reference' => $reference,
    ];
    $feed = [
        wiseActivity('t1', 'TRANSFER', '120 EUR', 'Supplier Ltd', resourceId: '8001'),
        wiseActivity('i1', 'TRANSFER', '<positive>+ 300 EUR</positive>', 'Customer', resourceId: '8002'),
        wiseActivity('o1', 'TRANSFER', '100,000 EUR', 'Ourselves', resourceId: '8003'),
    ];
    Http::fake([
        'api.wise.com/v2/profiles'                 => Http::response([['id' => 666, 'type' => 'BUSINESS']]),
        'api.wise.com/v1/profiles/666/activities*' => Http::sequence()
            ->push(['cursor' => null, 'activities' => $feed])
            ->push(['cursor' => null, 'activities' => $feed])
            ->push(['cursor' => null, 'activities' => [...$feed, wiseActivity('m1', 'TRANSFER', '50,000 EUR', 'Lender Ltd', resourceId: '8004')]]),
        'api.wise.com/v1/transfers/8001'           => Http::response($transfer(8001, 120, 501)),
        'api.wise.com/v1/transfers/8002'           => Http::response($transfer(8002, 300, 900)),
        'api.wise.com/v1/transfers/8003'           => Http::response($transfer(8003, 100000, 502, '2015-01-19 10:56:53')),
        'api.wise.com/v1/transfers/8004'           => Http::response($transfer(8004, 50000, 777, reference: 'Loan')),
        'api.wise.com/v1/accounts/777'             => Http::response(['id' => 777, 'accountHolderName' => 'Lender Ltd']),
    ]);
    $this->wiseTransfers = [$transfer(8001, 120, 501), $transfer(8002, 300, 900), $transfer(8003, 100000, 502, '2015-01-19 10:56:53'), $transfer(8004, 50000, 777, reference: 'Loan'), $transfer(8005, 40, 900)];
    $this->wiseBalances  = [['currency' => 'EUR', 'type' => 'STANDARD', 'creationTime' => '2017-12-05T10:00:00Z', 'amount' => ['value' => 0], 'reservedAmount' => ['value' => 0]]];

    $bankConnection = StoreBankConnection::run($this->organisation, [
        'provider'    => BankConnectionProviderEnum::WISE,
        'name'        => 'Wise gaps',
        'credentials' => 'gap-token',
    ]);

    expect(SyncBankConnection::run($bankConnection, true)['synced'])->toBe(3);
    $lines = BankTransaction::where('bank_connection_id', $bankConnection->id)->get()->keyBy('provider_transaction_id');
    expect($lines->keys()->sort()->values()->all())->toBe(['i1', 't1', 'transfer:8004'])
        ->and((float)$lines['transfer:8004']->amount)->toBe(-50000.0)
        ->and($lines['transfer:8004']->direction)->toBe(BankTransactionDirectionEnum::OUT)
        ->and($lines['transfer:8004']->currency->code)->toBe('EUR')
        ->and($lines['transfer:8004']->counterparty_name)->toBe('Lender Ltd')
        ->and($lines['transfer:8004']->reference)->toBe('Loan')
        ->and($lines['transfer:8004']->status)->toBe(BankTransactionStatusEnum::BOOKED)
        ->and($lines['transfer:8004']->booked_at->toDateString())->toBe('2026-07-13');

    expect(SyncBankConnection::run($bankConnection->refresh(), true)['synced'])->toBe(0)
        ->and(BankTransaction::where('bank_connection_id', $bankConnection->id)->count())->toBe(3);

    SyncBankConnection::run($bankConnection->refresh(), true);
    expect(BankTransaction::where('bank_connection_id', $bankConnection->id)->pluck('provider_transaction_id')->sort()->values()->all())->toBe(['i1', 'm1', 't1'])
        ->and((float)BankTransaction::where('bank_connection_id', $bankConnection->id)->where('provider_transaction_id', 'm1')->value('amount'))->toBe(-50000.0);
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

    actingAs(createAdminGuest($this->organisation->group)->getUser());
    $paymentsBefore = $order->payments()->count();
    foreach ([99.5, 100] as $amount) {
        post(route('grp.models.order.payment.store', [$order->id, $bankAccount->id]), ['amount' => $amount, 'status' => 'success', 'state' => 'completed'])
            ->assertSessionHasErrors('amount');
    }
    expect($order->payments()->count())->toBe($paymentsBefore);

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

test('bank lines for orders staff already paid by hand leave the review queue, and a review line is never promoted to matched', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1.0);

    $bankConnection = bankMatchingConnection($this->organisation);
    $bankAccount    = StoreOrgPaymentServiceProviderAccount::make()->action(
        $this->organisation,
        PaymentServiceProvider::where('code', 'bank')->firstOrFail(),
        ['code' => 'hand'.random_int(1000, 9999), 'name' => 'Hand paid test']
    );

    $payByHand = function (\App\Models\Ordering\Order $order, float $amount) use ($bankAccount) {
        $payment = \App\Actions\Accounting\Payment\StorePayment::make()->action($order->customer, $bankAccount, [
            'amount' => $amount,
            'status' => \App\Enums\Accounting\Payment\PaymentStatusEnum::SUCCESS,
            'state'  => \App\Enums\Accounting\Payment\PaymentStateEnum::COMPLETED,
        ]);
        \App\Actions\Ordering\Order\AttachPaymentToOrder::make()->action($order, $payment, []);
    };

    $line = fn (string $id, string $reference, float $amount, ?int $currencyId = null) => BankTransaction::create([
        'group_id'                => $bankConnection->group_id,
        'organisation_id'         => $bankConnection->organisation_id,
        'bank_connection_id'      => $bankConnection->id,
        'provider_transaction_id' => $id,
        'provider_type'           => 'TRANSFER',
        'direction'               => BankTransactionDirectionEnum::IN,
        'amount'                  => $amount,
        'currency_id'             => $currencyId ?? $this->shop->currency_id,
        'status'                  => BankTransactionStatusEnum::BOOKED,
        'reference'               => $reference,
        'booked_at'               => now(),
    ]);

    $later     = bankMatchingOrder(createCustomer($this->shop), 100.78);
    $currency  = bankMatchingOrder(createCustomer($this->shop), 300);
    $cancelled = bankMatchingOrder(createCustomer($this->shop), 95.50);
    $first     = bankMatchingOrder(createCustomer($this->shop), 40);
    $second    = bankMatchingOrder(createCustomer($this->shop), 60);
    $unpaid    = bankMatchingOrder(createCustomer($this->shop), 80);
    $rounded   = bankMatchingOrder(createCustomer($this->shop), 100.78);
    $partly    = bankMatchingOrder(createCustomer($this->shop), 100);

    $payByHand($currency, 300);
    $payByHand($cancelled, 55);
    $cancelled->updateQuietly(['state' => OrderStateEnum::CANCELLED]);
    $payByHand($first, 40);
    $payByHand($second, 60);

    $lines = [
        'later'     => $line('h1', $later->reference, 101.50),
        'currency'  => $line('h2', $currency->reference, 26.50, Currency::where('id', '!=', $this->shop->currency_id)->value('id')),
        'cancelled' => $line('h3', $cancelled->reference, 55),
        'several'   => $line('h4', $first->reference.' '.$second->reference, 100),
        'unpaid'    => $line('h5', $unpaid->reference, 80),
        'rounded'   => $line('h6', $rounded->reference, 101),
        'short'     => $line('h7', $partly->reference, 50),
    ];
    MatchBankTransactions::run($bankConnection);

    expect($lines['later']->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::REVIEW)
        ->and($lines['currency']->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::REVIEW)
        ->and($lines['cancelled']->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::ALREADY_PAID)
        ->and($lines['several']->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::ALREADY_PAID)
        ->and($lines['unpaid']->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::MATCHED)
        ->and($lines['rounded']->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::MATCHED)
        ->and($lines['rounded']->match_data['difference'])->toBe(0.22);

    $lines['unpaid']->update(['match_state' => BankTransactionMatchStateEnum::REVIEW]);
    $payByHand($later, 100.78);
    $payByHand($partly, 100);

    expect(MatchBankTransactions::run($bankConnection))->toBe(['already_paid' => 1])
        ->and($lines['later']->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::ALREADY_PAID)
        ->and($lines['later']->match_data['reason'])->toBe('order already settled')
        ->and($lines['unpaid']->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::REVIEW)
        ->and($lines['short']->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::REVIEW);
});

test('invoicing an order paid with an absorbed bank fee leaves its stored pay status true to its payments', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1.0);

    $customer       = createCustomer($this->shop);
    [, $product]    = createProduct($this->shop);
    $order          = bankMatchingOrder($customer, 0);
    $transaction = StoreTransaction::make()->action($order, $product->historicAsset, array_merge(Transaction::factory()->definition(), ['quantity_ordered' => 100]));
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

    $transaction->updateQuietly(['quantity_ordered' => 1]);
    $invoice = GenerateInvoiceFromOrder::make()->action($order->refresh());

    expect((float)$invoice->total_amount)->toBeLessThan($received);

    $stored = $order->refresh()->only(['pay_status', 'pay_detailed_status', 'payment_amount']);
    $fresh  = UpdateOrderPaymentsStatus::run($order->refresh())->only(['pay_status', 'pay_detailed_status', 'payment_amount']);

    expect($stored)->toEqual($fresh);
});

test('staff record a bank payment from the review queue on the order they name, or set it aside', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1.0);

    $order          = bankMatchingOrder(createCustomer($this->shop), 50);
    $bankConnection = bankMatchingConnection($this->organisation);
    $bankAccount    = StoreOrgPaymentServiceProviderAccount::make()->action(
        $this->organisation,
        PaymentServiceProvider::where('code', 'bank')->firstOrFail(),
        ['code' => 'wise'.random_int(1000, 9999), 'name' => 'Wise queue test']
    );
    SetBankConnectionAutoPay::run($bankConnection, [$order->currency->code => $bankAccount->code]);
    SetBankConnectionAutoPay::run($bankConnection->refresh(), [], off: true);
    $bankConnection->refresh();

    $line = fn (string $id, float $amount, ?int $currencyId = null) => BankTransaction::create([
        'group_id'                => $bankConnection->group_id,
        'organisation_id'         => $bankConnection->organisation_id,
        'bank_connection_id'      => $bankConnection->id,
        'provider_transaction_id' => $id,
        'provider_type'           => 'TRANSFER',
        'direction'               => BankTransactionDirectionEnum::IN,
        'amount'                  => $amount,
        'currency_id'             => $currencyId ?? $order->currency_id,
        'status'                  => BankTransactionStatusEnum::BOOKED,
        'reference'               => 'Thank you',
        'booked_at'               => now(),
        'match_state'             => BankTransactionMatchStateEnum::REVIEW,
        'match_data'              => ['reason' => 'no order number in the reference'],
    ]);

    $wrongCurrency = $line('q0', 49.60, Currency::where('id', '!=', $order->currency_id)->value('id'));
    expect(fn () => PayBankTransactionToOrder::make()->handle($wrongCurrency, ['order_reference' => $order->reference]))
        ->toThrow(ValidationException::class);

    $paid = $line('q1', 49.60);
    PayBankTransactionToOrder::make()->handle($paid, ['order_reference' => strtolower($order->reference), 'absorb_bank_fee' => true]);

    $paid->refresh();
    expect($paid->match_state)->toBe(BankTransactionMatchStateEnum::MATCHED)
        ->and($paid->order_id)->toBe($order->id)
        ->and((float)$paid->payment->amount)->toBe(49.6)
        ->and((float)$paid->bankFeePayment->amount)->toBe(0.4)
        ->and($order->refresh()->pay_status)->toBe(OrderPayStatusEnum::PAID);

    $partOrder = bankMatchingOrder(createCustomer($this->shop), 100);
    $partPayment = $line('q4', 50);
    PayBankTransactionToOrder::make()->handle($partPayment, ['order_reference' => $partOrder->reference, 'absorb_bank_fee' => true]);
    expect($partPayment->refresh()->bank_fee_payment_id)->toBeNull()
        ->and((float)$partOrder->refresh()->payment_amount)->toBe(50.0);

    actingAs(createAdminGuest($this->organisation->group)->getUser());
    post(route('grp.models.order.payment.store', [$partOrder->id, $bankAccount->id]), ['amount' => 60, 'status' => 'success', 'state' => 'completed'])
        ->assertSessionHasErrors('amount');
    post(route('grp.models.order.payment.store', [$partOrder->id, $bankAccount->id]), ['amount' => 50, 'status' => 'success', 'state' => 'completed'])
        ->assertSessionHasNoErrors();
    expect((float)$partOrder->refresh()->payment_amount)->toBe(100.0);

    $basket = bankMatchingOrder(createCustomer($this->shop), 30);
    $basket->updateQuietly(['state' => OrderStateEnum::CREATING]);
    expect(fn () => PayBankTransactionToOrder::make()->handle($line('q5', 30), ['order_reference' => $basket->reference]))
        ->toThrow(ValidationException::class);
    expect(fn () => PayBankTransactionToOrder::make()->handle($line('q6', 49.60), ['order_reference' => $order->reference]))
        ->toThrow(ValidationException::class);

    $twin      = bankMatchingOrder(createCustomer($this->shop), 20);
    $twinOther = bankMatchingOrder(createCustomer($this->shop), 20);
    $twinOther->updateQuietly(['reference' => $twin->reference]);
    expect(fn () => PayBankTransactionToOrder::make()->handle($line('q7', 20), ['order_reference' => $twin->reference]))
        ->toThrow(ValidationException::class);
    $suggested = $line('q8', 20);
    $suggested->update(['order_id' => $twinOther->id]);
    PayBankTransactionToOrder::make()->handle($suggested, ['order_reference' => $twin->reference]);
    expect($suggested->refresh()->order_id)->toBe($twinOther->id);
    $picked = $line('q10', 20);
    PayBankTransactionToOrder::make()->handle($picked, ['order_id' => $twin->id, 'order_reference' => $twin->reference]);
    expect($picked->refresh()->order_id)->toBe($twin->id);

    $payout = $line('q2', 900);
    actingAs(createAdminGuest($this->organisation->group)->getUser());

    post(route('grp.models.org.bank_transaction.dismiss', [$this->organisation->id, $payout->id]), ['note' => 'Faire payout'])->assertRedirect();
    $this->artisan('bank-transactions:match', ['bankConnection' => $bankConnection->id, '--reset' => true])->run();

    expect($payout->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::DISMISSED)
        ->and($payout->match_data['dismissed_note'])->toBe('Faire payout');

    post(route('grp.models.org.bank_transaction.restore', [$this->organisation->id, $payout->id]))->assertRedirect();
    expect($payout->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::UNMATCHED);
    $payout->update(['match_state' => BankTransactionMatchStateEnum::DISMISSED]);

    $cancelledUnpaid = bankMatchingOrder(createCustomer($this->shop), 25);
    $cancelledUnpaid->updateQuietly(['state' => OrderStateEnum::CANCELLED]);
    $forCancelled = $line('q12', 25);
    $forCancelled->update(['order_id' => $cancelledUnpaid->id]);
    post(route('grp.models.org.bank_transaction.dismiss', [$this->organisation->id, $forCancelled->id]), ['note' => 'Already recorded on the order by hand', 'already_paid' => true])
        ->assertSessionHasErrors('note');
    expect($forCancelled->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::REVIEW);

    $forPartOrder = $line('q13', 100);
    $forPartOrder->update(['order_id' => $partOrder->id]);
    post(route('grp.models.org.bank_transaction.dismiss', [$this->organisation->id, $forPartOrder->id]), ['note' => 'Already recorded on the order by hand', 'already_paid' => true])
        ->assertSessionHasNoErrors();
    expect($forPartOrder->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::ALREADY_PAID)
        ->and($forPartOrder->match_data['dismissed_note'])->toBe('Already recorded on the order by hand');

    $owing = bankMatchingOrder(createCustomer($this->shop), 70);
    $owing->customer->updateQuietly(['name' => 'Natursens Quokka d.o.o.']);
    $settledOrder = bankMatchingOrder($owing->customer, 40);
    $settledOrder->updateQuietly(['payment_amount' => 40]);
    $cancelledOrder = bankMatchingOrder($owing->customer, 40);
    $cancelledOrder->updateQuietly(['state' => OrderStateEnum::CANCELLED]);

    $candidates = fn (string $search) => get(route('grp.json.bank_transaction.order_candidates', [$this->organisation->id, $payout->id, 'search' => $search]))
        ->assertOk()
        ->json();

    $byName = collect($candidates('NATURSENS QUOKKA D.O.O.'))->pluck('id');
    expect($byName)->toContain($owing->id)
        ->and($byName)->not->toContain($settledOrder->id)
        ->and($byName)->not->toContain($cancelledOrder->id)
        ->and(collect($candidates($owing->reference))->pluck('id')->all())->toBe([$owing->id])
        ->and($candidates('Zzqx Nobody'))->toBe([])
        ->and($candidates($owing->reference)[0]['owed'])->toBe(70)
        ->and($candidates('N'))->toBe([]);

    $otherCurrencyLine = $line('q11', 70, Currency::where('id', '!=', $order->currency_id)->value('id'));
    expect(get(route('grp.json.bank_transaction.order_candidates', [$this->organisation->id, $otherCurrencyLine->id, 'search' => $owing->reference]))->json())->toBe([]);

    $toReview = $line('q3', 12);
    $this->artisan('bank-transactions:match', ['bankConnection' => $bankConnection->id])->run();
    $toReview->update(['match_state' => BankTransactionMatchStateEnum::REVIEW, 'match_data' => ['reason' => 'pays less than owed']]);

    actingAs(createAdminGuest($this->organisation->group)->getUser());
    Config::set('inertia.pages.paths', [resource_path('js/Pages/Grp')]);

    $old = $line('q9', 75);
    $old->update(['booked_at' => now()->subYears(3), 'match_state' => BankTransactionMatchStateEnum::REVIEW, 'match_data' => ['reason' => 'order cancelled']]);

    get(route('grp.org.accounting.bank_incoming.needs_person', [$this->organisation->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Org/Accounting/BankIncoming')
            ->where('canEdit', true)
            ->where('period', '90d')
            ->where('section', 'needs_person')
            ->where('sectionTotals.needs_person.count', fn ($count) => $count >= 1)
            ->where('sectionTotals', fn ($totals) => collect($totals)->keys()->all() === ['customers', 'marketplaces', 'payment_accounts', 'other', 'needs_person'])
            ->where('pageHead.subNavigation', fn ($navigation) => collect($navigation)->pluck('route.name')->all() === collect(['index', 'customers', 'marketplaces', 'payment_accounts', 'other', 'needs_person'])->map(fn ($name) => "grp.org.accounting.bank_incoming.$name")->all()
                && collect($navigation)->last()['number'] >= 1)
            ->where('queryBuilderProps.default.columns', fn ($columns) => collect($columns)->pluck('key')->contains('actions') && !collect($columns)->pluck('key')->contains('reason'))
            ->where('data.data', fn ($rows) => collect($rows)->pluck('id')->contains($toReview->id)
                && !collect($rows)->pluck('id')->contains($old->id)
                && !collect($rows)->pluck('id')->contains($paid->id)
                && !collect($rows)->pluck('id')->contains($payout->id)));

    get(route('grp.org.accounting.bank_incoming.needs_person', [$this->organisation->slug, 'period' => 'all']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('period', 'all')
            ->where('data.data', fn ($rows) => collect($rows)->pluck('id')->contains($old->id)));

    $orgCurrency     = $this->organisation->currency;
    $foreignCurrency = Currency::whereNotIn('code', [$orgCurrency->code, config('app.currency_exchange.pivot')])->firstOrFail();
    $foreign         = $line('fx1', 400, $foreignCurrency->id);
    $orgRate         = $orgCurrency->code === config('app.currency_exchange.pivot') ? 1 : 2;
    foreach ([[$foreignCurrency, 4], [$orgCurrency, $orgRate]] as [$currency, $rate]) {
        CurrencyExchange::updateOrCreate(['currency_id' => $currency->id, 'date' => now()->subDays(3)->toDateString()], ['exchange' => $rate, 'source' => 'test']);
    }
    $expectedOrgAmount = 400 * $orgRate / 4;
    $orgCurrencyLine = $line('fx2', 12, $orgCurrency->id);

    $props = fn (array $query) => get(route('grp.org.accounting.bank_incoming.needs_person', [$this->organisation->slug, ...$query]))->viewData('page')['props'];
    $rows  = collect($props([])['data']['data'])->keyBy('id');
    expect($rows[$foreign->id]['org_amount'])->toEqual($expectedOrgAmount)
        ->and($rows[$foreign->id]['org_currency_code'])->toBe($orgCurrency->code)
        ->and($rows[$orgCurrencyLine->id]['org_amount'])->toBeNull()
        ->and($props([])['currencyTotals'][$foreignCurrency->code]['total'])->toBeGreaterThanOrEqual($expectedOrgAmount)
        ->and($props([])['sectionTotals']['needs_person']['total'])->toBeGreaterThanOrEqual($expectedOrgAmount + 12);

    $inRange = collect($props(['min' => $expectedOrgAmount - 1, 'max' => $expectedOrgAmount + 1])['data']['data'])->pluck('id');
    expect($inRange)->toContain($foreign->id)->not->toContain($orgCurrencyLine->id)
        ->and(collect($props(['min' => 10, 'max' => 13])['data']['data'])->pluck('id'))->toContain($orgCurrencyLine->id)->not->toContain($foreign->id)->not->toContain($toReview->id)
        ->and($props(['min' => 'abc'])['min'])->toBeNull();
    $foreign->delete();
    $orgCurrencyLine->delete();

    $toReview->update(['reference' => 'Faire EWACREAMZ3']);
    $ids = fn (array $query) => collect(get(route('grp.org.accounting.bank_incoming.needs_person', [$this->organisation->slug, ...$query]))->viewData('page')['props']['data']['data'])->pluck('id');

    expect($ids(['filter' => ['global' => 'ewacreamz3']]))->toContain($toReview->id)->not->toContain($old->id)
        ->and($ids(['filter' => ['global' => '12,00']]))->toContain($toReview->id)->not->toContain($old->id)
        ->and($ids(['period' => 'all', 'filter' => ['global' => $bankConnection->name]]))->toContain($toReview->id, $old->id)
        ->and($ids(['currency' => $order->currency->code]))->toContain($toReview->id)
        ->and($ids(['currency' => $otherCurrencyLine->currency->code]))->not->toContain($toReview->id)
        ->and($ids(['from' => now()->subYears(3)->subDay()->toDateString(), 'to' => now()->subYears(2)->toDateString()]))->toContain($old->id)->not->toContain($toReview->id);
});

test('staff add a bank payment that pays no order to the customer credit', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1.0);

    $customer       = createCustomer($this->shop);
    $bankConnection = bankMatchingConnection($this->organisation);
    $bankAccount    = StoreOrgPaymentServiceProviderAccount::make()->action(
        $this->organisation,
        PaymentServiceProvider::where('code', 'bank')->firstOrFail(),
        ['code' => 'wise'.random_int(1000, 9999), 'name' => 'Wise credit test']
    );
    SetBankConnectionAutoPay::run($bankConnection, [$this->shop->currency->code => $bankAccount->code]);
    SetBankConnectionAutoPay::run($bankConnection->refresh(), [], off: true);
    $bankConnection->refresh();

    $line = fn (string $id, ?int $currencyId = null) => BankTransaction::create([
        'group_id'                => $bankConnection->group_id,
        'organisation_id'         => $bankConnection->organisation_id,
        'bank_connection_id'      => $bankConnection->id,
        'provider_transaction_id' => $id.random_int(1000, 99999),
        'provider_type'           => 'TRANSFER',
        'direction'               => BankTransactionDirectionEnum::IN,
        'amount'                  => 75.5,
        'currency_id'             => $currencyId ?? $this->shop->currency_id,
        'status'                  => BankTransactionStatusEnum::BOOKED,
        'reference'               => 'Prepayment',
        'counterparty_name'       => $customer->name,
        'booked_at'               => now(),
        'match_state'             => BankTransactionMatchStateEnum::REVIEW,
        'match_data'              => ['reason' => 'no order number in the reference'],
    ]);

    $balanceBefore = (float)$customer->refresh()->balance;

    expect(fn () => PayBankTransactionToCustomerCredit::make()->handle($line('c0', Currency::where('id', '!=', $this->shop->currency_id)->value('id')), ['customer_id' => $customer->id]))
        ->toThrow(ValidationException::class);

    $credited = $line('c1');
    actingAs(createAdminGuest($this->organisation->group)->getUser());
    get(route('grp.json.bank_transaction.customer_candidates', [$this->organisation->id, $credited->id]))
        ->assertOk()
        ->assertJsonFragment(['id' => $customer->id]);
    post(route('grp.models.org.bank_transaction.customer_credit', [$this->organisation->id, $credited->id]), ['customer_id' => $customer->id])
        ->assertSessionHasNoErrors();

    $credited->refresh();
    expect($credited->match_state)->toBe(BankTransactionMatchStateEnum::MATCHED)
        ->and($credited->order_id)->toBeNull()
        ->and((float)$credited->payment->amount)->toBe(75.5)
        ->and($credited->payment->customer_id)->toBe($customer->id)
        ->and($credited->match_data['credit_customer_id'])->toBe($customer->id)
        ->and((float)$customer->refresh()->balance)->toBe(round($balanceBefore + 75.5, 2))
        ->and(CreditTransaction::find($credited->match_data['credit_transaction_id'])->payment_id)->toBe($credited->payment_id)
        ->and((new BankIncomingResource($credited))->toArray(request())['actionable'])->toBeFalse();

    expect(fn () => PayBankTransactionToCustomerCredit::make()->handle($credited, ['customer_id' => $customer->id]))
        ->toThrow(ValidationException::class);
    expect((float)$customer->refresh()->balance)->toBe(round($balanceBefore + 75.5, 2));

    expect(fn () => RefundPaymentToBalance::lockForRefund($credited->payment, null, 10))
        ->toThrow(ValidationException::class);

    CancelPayment::make()->handle($credited->payment);
    $credited->refresh();
    expect((float)$customer->refresh()->balance)->toBe(round($balanceBefore, 2))
        ->and($credited->payment_id)->toBeNull()
        ->and($credited->match_state)->toBe(BankTransactionMatchStateEnum::REVIEW)
        ->and($credited->match_data)->not->toHaveKey('credit_customer_name');
});

test('order numbers are read from bank references as whole words', function () {
    $matcher = MatchBankTransactions::make();

    expect($matcher->orderReferenceTokens('PL31515'))->toBe(['PL31515'])
        ->and($matcher->orderReferenceTokens('orders pl31515, DEU123456 thanks'))->toBe(['PL31515', 'DEU123456'])
        ->and($matcher->orderReferenceTokens('SK2120525440-20261109-01'))->toBe([])
        ->and($matcher->orderReferenceTokens('numer zamówienia PL 30569'))->toBe(['PL30569'])
        ->and($matcher->orderReferenceTokens('SVWZ+FR 26245, de-10288'))->toBe(['FR26245', 'DE10288'])
        ->and($matcher->orderReferenceTokens('PL 31515 PL31515'))->toBe(['PL31515'])
        ->and($matcher->orderReferenceTokens('AWFI223 775'))->toBe(['AWFI223', 'AWFI223775'])
        ->and($matcher->orderReferenceTokens('AWFi223 345/290'))->toBe(['AWFI223', 'AWFI223345'])
        ->and($matcher->orderReferenceTokens('PL 31515 123'))->toBe(['PL31515'])
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

test('the bank fees report sums fees the bank took and fees absorbed, per currency and month', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1.0);

    $order          = bankMatchingOrder(createCustomer($this->shop), 200);
    $bankConnection = bankMatchingConnection($this->organisation);
    $bankAccount    = StoreOrgPaymentServiceProviderAccount::make()->action(
        $this->organisation,
        PaymentServiceProvider::where('code', 'bank')->firstOrFail(),
        ['code' => 'wise'.random_int(1000, 9999), 'name' => 'Wise fees report']
    );
    SetBankConnectionAutoPay::run($bankConnection, [$order->currency->code => $bankAccount->code]);

    $otherOrganisationData = Organisation::factory()->definition();
    data_set($otherOrganisationData, 'code', 'fees'.random_int(1000, 9999));
    data_set($otherOrganisationData, 'type', OrganisationTypeEnum::SHOP);
    $otherOrganisation = StoreOrganisation::make()->action($this->organisation->group, $otherOrganisationData);
    $otherConnection   = bankMatchingConnection($otherOrganisation);

    $otherCurrency = Currency::where('id', '!=', $order->currency_id)->firstOrFail();

    $thisMonth = now()->startOfMonth()->addDays(10)->setTime(12, 0);
    $olderMonth = $thisMonth->copy()->subMonths(2);
    $emptyMonth = $thisMonth->copy()->subMonths(4);
    $from       = $thisMonth->copy()->subMonths(5)->startOfMonth()->toDateString();
    $to         = $thisMonth->copy()->endOfMonth()->toDateString();

    $report = function () use ($order, $otherCurrency, $from, $to) {
        $rows = IndexBankFees::make()->handle($this->organisation, $from, $to);
        $pick = fn (string $month, string $currencyCode) => $rows->first(fn ($row) => $row->month === $month && $row->currency_code === $currencyCode);
        $read = fn ($row) => [(float)($row->bank_fees ?? 0), (float)($row->bank_base ?? 0), (float)($row->absorbed_fees ?? 0)];

        return [
            'now'   => $read($pick(now()->format('Y-m'), $order->currency->code)),
            'older' => $read($pick(now()->subMonths(2)->startOfMonth()->format('Y-m'), $order->currency->code)),
            'empty' => $read($pick(now()->subMonths(4)->startOfMonth()->format('Y-m'), $order->currency->code)),
            'other' => $read($pick(now()->format('Y-m'), $otherCurrency->code)),
            'count' => $rows->count(),
        ];
    };
    $before = $report();

    $line = fn (BankConnection $connection, string $id, float $amount, float $fee, $bookedAt, ?string $reference = null, ?int $currencyId = null, BankTransactionStatusEnum $status = BankTransactionStatusEnum::BOOKED) => BankTransaction::create([
        'group_id'                => $connection->group_id,
        'organisation_id'         => $connection->organisation_id,
        'bank_connection_id'      => $connection->id,
        'provider_transaction_id' => $id,
        'provider_type'           => 'TRANSFER',
        'direction'               => BankTransactionDirectionEnum::IN,
        'amount'                  => $amount,
        'fee_amount'              => $fee,
        'currency_id'             => $currencyId ?? $order->currency_id,
        'status'                  => $status,
        'reference'               => $reference,
        'booked_at'               => $bookedAt,
    ]);

    $paidLine = $line($bankConnection, 'f1', 199, 0.50, now()->addMinute(), $order->reference);
    $line($bankConnection, 'f2', 100, 0.25, $thisMonth);
    $line($bankConnection, 'f3', 50, 1.00, $olderMonth);
    $line($bankConnection, 'f4', 80, 0, $thisMonth);
    $line($bankConnection, 'f5', 40, 0.10, $thisMonth, null, $otherCurrency->id);

    $line($otherConnection, 'x1', 500, 7, $thisMonth);
    $line($bankConnection, 'x2', 500, 7, $thisMonth, null, null, BankTransactionStatusEnum::PENDING);
    $line($bankConnection, 'x3', 500, 7, $thisMonth, null, null, BankTransactionStatusEnum::CANCELLED);
    $line($bankConnection, 'x4', 500, 7, Carbon::parse($from)->subDay()->setTime(12, 0));

    MatchBankTransactions::run($bankConnection->refresh());
    expect(PayMatchedBankTransactions::run($bankConnection))->toBe(['paid' => 1]);

    $paidLine->update(['booked_at' => $thisMonth]);
    $absorbedPayment = $paidLine->refresh()->bankFeePayment;
    $cancelledCopy   = $absorbedPayment->replicate(['slug'])->fill(['reference' => 'c'.random_int(1000, 9999), 'state' => PaymentStateEnum::CANCELLED]);
    $cancelledCopy->save();
    $deletedCopy = $absorbedPayment->replicate(['slug'])->fill(['reference' => 'd'.random_int(1000, 9999)]);
    $deletedCopy->save();
    $deletedCopy->delete();

    $emptyMonthCopy = $absorbedPayment->replicate(['slug'])->fill(['reference' => 'e'.random_int(1000, 9999), 'date' => $emptyMonth]);
    $emptyMonthCopy->save();

    $after = $report();
    $diff  = fn (string $key) => array_map(fn ($a, $b) => round($a - $b, 2), $after[$key], $before[$key]);

    expect($diff('now'))->toBe([0.75, 299.0, 1.0])
        ->and($diff('older'))->toBe([1.0, 50.0, 0.0])
        ->and($diff('empty'))->toBe([0.0, 0.0, 1.0])
        ->and($diff('other'))->toBe([0.1, 40.0, 0.0]);

    actingAs(createAdminGuest($this->organisation->group)->getUser());
    Config::set('inertia.pages.paths', [resource_path('js/Pages/Grp')]);

    get(route('grp.org.accounting.bank_fees.index', [$this->organisation->slug, 'from' => $to, 'to' => $from]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Org/Accounting/BankFees')
            ->where('range', ['from' => $from, 'to' => $to])
            ->where('data.data', function ($rows) use ($order) {
                $rows = collect($rows);
                $now  = $rows->first(fn ($row) => $row['month'] === now()->format('Y-m') && $row['currency_code'] === $order->currency->code);
                $none = $rows->first(fn ($row) => $row['month'] === now()->subMonths(4)->startOfMonth()->format('Y-m') && $row['currency_code'] === $order->currency->code);

                return abs($now['bank_fee_percentage'] - round($now['bank_fees'] / $now['bank_base'] * 100, 2)) < 0.001
                    && (float)$none['bank_base'] === 0.0 && $none['bank_fee_percentage'] === null;
            }));

    get(route('grp.org.accounting.bank_fees.index', [$this->organisation->slug, 'from' => 'garbage']))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('range.to', now()->toDateString()));
});

test('a faire payout marks its order paid by the marketplace whatever the amount, keeping the difference, with no payment', function () {
    $originalShop = $this->shop->only(['type', 'settings']);
    $this->shop->updateQuietly(['type' => \App\Enums\Catalogue\Shop\ShopTypeEnum::EXTERNAL, 'settings' => array_merge($this->shop->settings ?? [], ['faire' => ['order_from_days' => 6]])]);

    try {
        $faireOrder = function (float $total, float $commission) {
            $order = bankMatchingOrder(createCustomer($this->shop), $total);
            $order->updateQuietly([
                'reference'         => strtoupper(\Illuminate\Support\Str::random(10)),
                'state'             => OrderStateEnum::DISPATCHED,
                'commission_amount' => $commission,
            ]);

            return UpdateOrderPaymentsStatus::run($order->refresh());
        };
        $exact = $faireOrder(198.06, 29.71);
        $short = $faireOrder(331.60, 49.74);

        expect($exact->pay_status)->toBe(OrderPayStatusEnum::UNPAID)
            ->and($exact->pay_detailed_status)->toBe(\App\Enums\Ordering\Order\OrderPayDetailedStatusEnum::TO_BE_PAID_BY_MARKETPLACE);

        $bankConnection = bankMatchingConnection($this->organisation);
        $line = fn (string $id, Order $order, float $amount, string $counterpartyName = 'Faire Wholesale B. V.', ?string $reference = null) => BankTransaction::create([
            'group_id'                => $bankConnection->group_id,
            'organisation_id'         => $bankConnection->organisation_id,
            'bank_connection_id'      => $bankConnection->id,
            'provider_transaction_id' => $id,
            'provider_type'           => 'TRANSFER',
            'direction'               => BankTransactionDirectionEnum::IN,
            'amount'                  => $amount,
            'currency_id'             => $order->currency_id,
            'status'                  => BankTransactionStatusEnum::BOOKED,
            'counterparty_name'       => $counterpartyName,
            'reference'               => $reference ?? 'Faire '.$order->reference,
            'booked_at'               => now(),
        ]);

        $exactLine = $line('f1', $exact, 168.35);
        $shortLine = $line('f2', $short, 270.00);
        $viaAdyen  = $faireOrder(113.79, 17.07);
        $adyenLine = $line('f3', $viaAdyen, 96.72, 'ADYEN N.V.', 'FAIRE '.$viaAdyen->reference.'MC');

        expect(MatchBankTransactions::run($bankConnection))->toBe(['paid_by_marketplace' => 3])
            ->and($adyenLine->refresh()->order_id)->toBe($viaAdyen->id)
            ->and($adyenLine->match_data['difference'])->toEqual(0)
            ->and($exactLine->refresh()->order_id)->toBe($exact->id)
            ->and($exactLine->payment_id)->toBeNull()
            ->and($exactLine->match_data['commission'])->toBe(29.71)
            ->and($shortLine->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::PAID_BY_MARKETPLACE)
            ->and($shortLine->match_data['difference'])->toBe(-11.86)
            ->and($exact->refresh()->pay_status)->toBe(OrderPayStatusEnum::PAID)
            ->and($exact->pay_detailed_status)->toBe(\App\Enums\Ordering\Order\OrderPayDetailedStatusEnum::PAID_BY_MARKETPLACE)
            ->and((float)$exact->payment_amount)->toBe(0.0)
            ->and($exact->payments()->count())->toBe(0);

        $shortLine->update(['match_state' => BankTransactionMatchStateEnum::REVIEW, 'order_id' => null]);
        PayBankTransactionToOrder::make()->handle($shortLine, ['order_reference' => $short->reference]);

        expect($shortLine->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::PAID_BY_MARKETPLACE)
            ->and($shortLine->payment_id)->toBeNull()
            ->and($shortLine->match_data['difference'])->toBe(-11.86)
            ->and($short->refresh()->pay_detailed_status)->toBe(\App\Enums\Ordering\Order\OrderPayDetailedStatusEnum::PAID_BY_MARKETPLACE)
            ->and($short->payments()->count())->toBe(0);

        $cancelled = $faireOrder(50, 7.5);
        $cancelled->updateQuietly(['state' => OrderStateEnum::CANCELLED]);
        expect(UpdateOrderPaymentsStatus::run($cancelled->refresh())->pay_detailed_status)->toBe(\App\Enums\Ordering\Order\OrderPayDetailedStatusEnum::UNPAID);

        $exactLine->update(['match_state' => null, 'order_id' => null, 'match_data' => [], 'matched_at' => null]);
        MatchBankTransactions::make()->refreshMarketplaceOrders([$exact->id]);
        expect($exact->refresh()->pay_status)->toBe(OrderPayStatusEnum::UNPAID)
            ->and($exact->pay_detailed_status)->toBe(\App\Enums\Ordering\Order\OrderPayDetailedStatusEnum::TO_BE_PAID_BY_MARKETPLACE);
    } finally {
        $this->shop->updateQuietly($originalShop);
    }
});

test('an order in an ordinary shop reads as paid by the marketplace once a faire payout is linked to it', function () {
    $order = bankMatchingOrder(createCustomer($this->shop), 120);
    $order->updateQuietly(['state' => OrderStateEnum::DISPATCHED]);

    expect(UpdateOrderPaymentsStatus::run($order->refresh())->pay_detailed_status)->not->toBe(\App\Enums\Ordering\Order\OrderPayDetailedStatusEnum::TO_BE_PAID_BY_MARKETPLACE);

    $bankConnection = bankMatchingConnection($this->organisation);
    BankTransaction::create([
        'group_id'                => $bankConnection->group_id,
        'organisation_id'         => $bankConnection->organisation_id,
        'bank_connection_id'      => $bankConnection->id,
        'provider_transaction_id' => 'old-faire',
        'provider_type'           => 'TRANSFER',
        'direction'               => BankTransactionDirectionEnum::IN,
        'amount'                  => 99.60,
        'currency_id'             => $order->currency_id,
        'status'                  => BankTransactionStatusEnum::BOOKED,
        'counterparty_name'       => 'ADYEN N.V.',
        'reference'               => 'FAIRE 8MH5XCFQM2LY',
        'booked_at'               => now(),
        'match_state'             => BankTransactionMatchStateEnum::PAID_BY_MARKETPLACE,
        'order_id'                => $order->id,
    ]);

    $order = UpdateOrderPaymentsStatus::run($order->refresh());

    expect($order->pay_status)->toBe(OrderPayStatusEnum::PAID)
        ->and($order->pay_detailed_status)->toBe(\App\Enums\Ordering\Order\OrderPayDetailedStatusEnum::PAID_BY_MARKETPLACE)
        ->and((float)$order->payment_amount)->toBe(0.0)
        ->and($order->payments()->count())->toBe(0);
});

test('the faire payout backfill links old payouts and relabels unpaid faire orders, only with --commit', function () {
    $originalShop = $this->shop->only(['type', 'settings']);
    $this->shop->updateQuietly(['type' => \App\Enums\Catalogue\Shop\ShopTypeEnum::EXTERNAL, 'settings' => array_merge($this->shop->settings ?? [], ['faire' => ['order_from_days' => 6]])]);

    try {
        $faireOrder = function (float $total, float $commission) {
            $order = bankMatchingOrder(createCustomer($this->shop), $total);
            $order->updateQuietly([
                'reference'           => strtoupper(\Illuminate\Support\Str::random(10)),
                'state'               => OrderStateEnum::DISPATCHED,
                'commission_amount'   => $commission,
                'pay_detailed_status' => \App\Enums\Ordering\Order\OrderPayDetailedStatusEnum::UNPAID,
            ]);

            return $order->refresh();
        };
        $paidOut  = $faireOrder(100, 15);
        $awaiting = $faireOrder(80, 12);

        $bankConnection = bankMatchingConnection($this->organisation);
        $oldLine        = BankTransaction::create([
            'group_id'                => $bankConnection->group_id,
            'organisation_id'         => $bankConnection->organisation_id,
            'bank_connection_id'      => $bankConnection->id,
            'provider_transaction_id' => 'bf1',
            'provider_type'           => 'TRANSFER',
            'direction'               => BankTransactionDirectionEnum::IN,
            'amount'                  => 85,
            'currency_id'             => $paidOut->currency_id,
            'status'                  => BankTransactionStatusEnum::BOOKED,
            'counterparty_name'       => 'Faire Wholesale B. V.',
            'reference'               => 'Faire '.$paidOut->reference,
            'booked_at'               => now()->subMonths(3),
            'match_state'             => BankTransactionMatchStateEnum::UNMATCHED,
            'match_data'              => ['reason' => 'no order number in the reference'],
        ]);

        $dryRun = \App\Actions\Accounting\BankTransaction\BackfillFairePayouts::run(false);
        expect($dryRun['payouts paid_by_marketplace'] ?? 0)->toBeGreaterThanOrEqual(1)
            ->and($oldLine->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::UNMATCHED)
            ->and($awaiting->refresh()->pay_detailed_status)->toBe(\App\Enums\Ordering\Order\OrderPayDetailedStatusEnum::UNPAID);

        \App\Actions\Accounting\BankTransaction\BackfillFairePayouts::run(true);

        expect($oldLine->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::PAID_BY_MARKETPLACE)
            ->and($oldLine->order_id)->toBe($paidOut->id)
            ->and($oldLine->payment_id)->toBeNull()
            ->and($paidOut->refresh()->pay_detailed_status)->toBe(\App\Enums\Ordering\Order\OrderPayDetailedStatusEnum::PAID_BY_MARKETPLACE)
            ->and($paidOut->pay_status)->toBe(OrderPayStatusEnum::PAID)
            ->and((float)$paidOut->total_amount)->toBe(100.0)
            ->and($awaiting->refresh()->pay_detailed_status)->toBe(\App\Enums\Ordering\Order\OrderPayDetailedStatusEnum::TO_BE_PAID_BY_MARKETPLACE);
    } finally {
        $this->shop->updateQuietly($originalShop);
    }
});

test('checkout payouts pay no order, and their report says which payments they settled and what was kept', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1.0);
    $order          = bankMatchingOrder(createCustomer($this->shop), 100);
    $bankConnection = bankMatchingConnection($this->organisation);
    $line           = fn (string $id, string $name, string $reference, float $amount, array $extra = []) => BankTransaction::create(array_merge([
        'group_id'                => $bankConnection->group_id,
        'organisation_id'         => $bankConnection->organisation_id,
        'bank_connection_id'      => $bankConnection->id,
        'provider_transaction_id' => $id,
        'provider_type'           => 'TRANSFER',
        'direction'               => BankTransactionDirectionEnum::IN,
        'amount'                  => $amount,
        'currency_id'             => $order->currency_id,
        'status'                  => BankTransactionStatusEnum::BOOKED,
        'counterparty_name'       => $name,
        'reference'               => $reference,
        'booked_at'               => now()->subDay(),
    ], $extra));

    $daily    = $line('co1', 'CHECKOUT SAS', '00000003YGCF', 95.80);
    $spaced   = $line('co2', 'CHECKOUT SAS 44 RUE DE CHATEAUDUN P ARIS 75009 FR', '00000003YLN X', 50);
    $named    = $line('co3', 'CHECKOUT SAS', $order->reference.'XYZ1', 100);
    $notOurs  = $line('co4', 'Checkout Sasha Ltd', $order->reference, 100);
    $oldLabel = $line('co5', 'CHECKOUT SAS 20 BIS RUE LA FAYETTE PARIS 75009 FR', '00000003ABC 1', 20, [
        'match_state' => BankTransactionMatchStateEnum::DISMISSED,
        'match_data'  => ['reason' => 'no order number in the reference', 'dismissed_note' => 'checkout payment grouped'],
    ]);

    MatchBankTransactions::run($bankConnection);

    expect($daily->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::CARD_PROCESSOR_PAYOUT)
        ->and($daily->match_data['payout_id'])->toBe('00000003YGCF')
        ->and($daily->order_id)->toBeNull()
        ->and($spaced->refresh()->match_data['payout_id'])->toBe('00000003YLNX')
        ->and($named->refresh()->match_state)->not->toBe(BankTransactionMatchStateEnum::CARD_PROCESSOR_PAYOUT)
        ->and($notOurs->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::MATCHED)
        ->and($oldLabel->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::DISMISSED);

    expect(fn () => PayMatchedBankTransactions::make()->payToOrder($oldLabel, $order, false, 'test'))
        ->toThrow(ValidationException::class, 'This is a card processor payout of payments already on their orders');

    $checkoutAccount = StoreOrgPaymentServiceProviderAccount::make()->action(
        $this->organisation,
        PaymentServiceProvider::where('code', 'checkout')->firstOrFail(),
        ['code' => 'cko'.random_int(1000, 9999), 'name' => 'Checkout payouts test']
    );
    $cardPayment = \App\Actions\Accounting\Payment\StorePayment::make()->action($order->customer, $checkoutAccount, [
        'amount' => 100,
        'status' => \App\Enums\Accounting\Payment\PaymentStatusEnum::SUCCESS,
        'state'  => \App\Enums\Accounting\Payment\PaymentStateEnum::COMPLETED,
    ]);
    $paymentReference = 'pay_'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(26));
    $cardPayment->updateQuietly(['reference' => $paymentReference]);

    $csv = implode("\n", [
        'Action Type,Action ID,Payment ID,Payout ID,Breakdown Type,Processing Currency Amount,Holding Currency,Holding Currency Amount',
        "Capture,act_1,$paymentReference,00000003YGCF,Capture,100.00,CZK,100.00",
        "Capture,act_1,$paymentReference,00000003YGCF,Interchange Fixed Fee,-0.30,CZK,-0.30",
        "Capture,act_1,$paymentReference,00000003YGCF,Scheme Variable Fee,-0.20,CZK,-0.20",
        'Capture,act_2,pay_notinaiku000000000000000000,00000003YGCF,Capture,5.00,CZK,5.00',
        'Partial Refund,act_3,pay_refunded00000000000000000000,00000003YGCF,Refund,-8.00,CZK,-8.00',
        'Refund,act_3,pay_refunded00000000000000000000,00000003YGCF,Refund Fixed Fee,-0.50,CZK,-0.50',
    ]);
    expect(\App\Actions\Accounting\BankTransaction\ReconcileCheckoutPayouts::make()->payoutIdTag(['tags' => ['payout_id:00000003yvnv']]))->toBe('00000003YVNV')
        ->and(\App\Actions\Accounting\BankTransaction\ReconcileCheckoutPayouts::make()->payoutIdTag(['tags' => []]))->toBeNull();

    $reconciler = \App\Actions\Accounting\BankTransaction\ReconcileCheckoutPayouts::partialMock();
    $reconciler->shouldReceive('payoutReportFiles')->andReturn([
        '00000003YGCF' => ['report_id' => 'rpt_1', 'file_id' => 'file_1'],
        '00000003YLNX' => ['report_id' => 'rpt_2', 'file_id' => 'file_2'],
    ]);
    $reconciler->shouldReceive('payoutReportCsv')->with(\Mockery::any(), \Mockery::any(), 'rpt_1', 'file_1')->andReturn($csv);
    $reconciler->shouldReceive('payoutReportCsv')->with(\Mockery::any(), \Mockery::any(), 'rpt_2', 'file_2')->andReturn(implode("\n", [
        'Action Type,Payment ID,Breakdown Type,Processing Currency Amount,Holding Currency,Holding Currency Amount',
        'Capture,pay_short000000000000000000000,Capture,10.00,CZK,10.00',
    ]));
    Config::set('app.sandbox.checkout_com.secret_key', 'sk_test');

    $dryRun = $reconciler->handle(false, true);
    expect($dryRun['payout lines labelled'] ?? 0)->toBeGreaterThanOrEqual(1)
        ->and($oldLabel->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::DISMISSED)
        ->and($daily->refresh()->match_data)->not->toHaveKey('reconciled_at');

    $reconciler->handle(true, true);

    $daily->refresh();
    expect($oldLabel->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::CARD_PROCESSOR_PAYOUT)
        ->and($oldLabel->match_data['payout_id'])->toBe('00000003ABC1')
        ->and($daily->match_data['reconciled_at'])->not->toBeNull()
        ->and($daily->match_data['payout_total'])->toEqual(96)
        ->and($daily->match_data['gross'])->toEqual(97)
        ->and($daily->match_data['processor_fees'])->toEqual(1)
        ->and($daily->match_data['bank_fee'])->toEqual(0.2)
        ->and($daily->match_data['payment_ids'])->toBe([$cardPayment->id])
        ->and($daily->match_data['payments_in_payout'])->toBe(2)
        ->and($daily->match_data['refunds_in_payout'])->toBe(1)
        ->and($daily->match_data['payments_missing'])->toBe(['pay_notinaiku000000000000000000'])
        ->and($daily->match_data['payment_account_id'])->toBe($checkoutAccount->id)
        ->and($daily->payment_id)->toBeNull()
        ->and($spaced->refresh()->match_data)->not->toHaveKey('reconciled_at')
        ->and(collect($reconciler->samples)->pluck('payout')->unique()->sort()->values()->all())->toBe(['00000003YGCF', '00000003YLNX'])
        ->and(collect($reconciler->samples)->firstWhere('payout', '00000003YLNX')['understood'])->toBeFalse();

    actingAs(createAdminGuest($this->organisation->group)->getUser());
    get(route('grp.org.accounting.bank_incoming.payment_accounts', [$this->organisation->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('section', 'payment_accounts')
            ->where('sectionTotals.payment_accounts.count', fn ($count) => $count >= 1)
            ->where('providers', fn ($providers) => collect($providers)->pluck('value')->contains('checkout'))
            ->where('data.data', fn ($rows) => collect($rows)->contains(fn ($row) => $row['id'] === $daily->id
                && $row['payout']['reconciled']
                && $row['payout']['payments'] === 2
                && $row['payout']['processor_fees'] == 1
                && $row['payout']['payments_missing'] === 1)));
});

test('a pastpay payout settles the one pastpay order whose total less a pastpay fee arrived, and pays nothing', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1.0);
    $bankConnection = bankMatchingConnection($this->organisation);
    $pastpayAccount = StoreOrgPaymentServiceProviderAccount::make()->action(
        $this->organisation,
        PaymentServiceProvider::where('code', 'pastpay')->firstOrFail(),
        ['code' => 'pp'.random_int(1000, 9999), 'name' => 'PastPay payouts test']
    );
    $pastpayOrder = function (float $total) use ($pastpayAccount) {
        $order   = bankMatchingOrder(createCustomer($this->shop), $total);
        $payment = \App\Actions\Accounting\Payment\StorePayment::make()->action($order->customer, $pastpayAccount, [
            'amount' => $total,
            'status' => \App\Enums\Accounting\Payment\PaymentStatusEnum::SUCCESS,
            'state'  => \App\Enums\Accounting\Payment\PaymentStateEnum::COMPLETED,
        ]);
        $payment->updateQuietly(['date' => now()->subDays(5)]);
        \App\Actions\Ordering\Order\AttachPaymentToOrder::make()->action($order, $payment, ['amount' => $total]);

        return [$order, $payment];
    };
    $line = fn (string $id, float $amount) => BankTransaction::create([
        'group_id'                => $bankConnection->group_id,
        'organisation_id'         => $bankConnection->organisation_id,
        'bank_connection_id'      => $bankConnection->id,
        'provider_transaction_id' => $id,
        'provider_type'           => 'TRANSFER',
        'direction'               => BankTransactionDirectionEnum::IN,
        'amount'                  => $amount,
        'currency_id'             => $this->shop->currency_id,
        'status'                  => BankTransactionStatusEnum::BOOKED,
        'counterparty_name'       => 'PastPay Europe SP.ZO.O',
        'reference'               => 'SK2120525440-20261106-01/FXB133011X0ENLEP',
        'booked_at'               => now(),
    ]);

    [$order, $payment] = $pastpayOrder(681.82);
    $refund            = \App\Actions\Accounting\Payment\StorePayment::make()->action($order->customer, $pastpayAccount, [
        'amount' => -12.35,
        'status' => \App\Enums\Accounting\Payment\PaymentStatusEnum::SUCCESS,
        'state'  => \App\Enums\Accounting\Payment\PaymentStateEnum::COMPLETED,
    ]);
    $refund->updateQuietly(['date' => now()->subDays(2)]);
    \App\Actions\Ordering\Order\AttachPaymentToOrder::make()->action($order, $refund, ['amount' => -12.35]);
    [$spanishOrder] = $pastpayOrder(230.06);
    $pastpayOrder(2000.00);
    $pastpayOrder(2000.00);

    $spanish = $line('pp5', 221.87);
    $payout  = $line('pp1', 652.84);
    $again  = $line('pp2', 652.84);
    $twins  = $line('pp3', 1915.00);
    $none   = $line('pp4', 12.34);

    MatchBankTransactions::run($bankConnection);

    expect($payout->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::CARD_PROCESSOR_PAYOUT)
        ->and($payout->order_id)->toBe($order->id)
        ->and($payout->payment_id)->toBeNull()
        ->and($payout->match_data['provider'])->toBe('pastpay')
        ->and($payout->match_data['payment_ids'])->toBe([$payment->id])
        ->and($payout->match_data['fee_rate'])->toEqual(0.0425)
        ->and($payout->match_data['processor_fees'])->toEqual(28.98)
        ->and($spanish->refresh()->order_id)->toBe($spanishOrder->id)
        ->and($spanish->match_data['fee_rate'])->toEqual(0.0356)
        ->and($again->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::UNMATCHED)
        ->and($again->match_data['reason'])->toBe('PastPay payout, no PastPay order of this amount')
        ->and($twins->refresh()->match_data['reason'])->toBe('PastPay payout, several PastPay orders of this amount')
        ->and($none->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::UNMATCHED)
        ->and($order->refresh()->payment_amount)->toEqual(669.47);

    actingAs(createAdminGuest($this->organisation->group)->getUser());
    get(route('grp.org.accounting.bank_incoming.payment_accounts', [$this->organisation->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('data.data', fn ($rows) => collect($rows)->contains(fn ($row) => $row['id'] === $payout->id
                && $row['payout']['provider'] === 'pastpay'
                && $row['payout']['processor_fees'] == 28.98
                && $row['order_reference'] === $order->reference)));
});

test('a pastpay payout in forints or lei follows that currency fees and may settle several orders in one transfer', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1.0);
    $bankConnection = bankMatchingConnection($this->organisation);
    $pastpayAccount = StoreOrgPaymentServiceProviderAccount::make()->action(
        $this->organisation,
        PaymentServiceProvider::where('code', 'pastpay')->firstOrFail(),
        ['code' => 'pp'.random_int(1000, 9999), 'name' => 'PastPay HUF RON payouts test']
    );
    $huf          = \App\Models\Helpers\Currency::where('code', 'HUF')->firstOrFail();
    $ron          = \App\Models\Helpers\Currency::where('code', 'RON')->firstOrFail();
    $pastpayOrder = function (float $total, \App\Models\Helpers\Currency $currency) use ($pastpayAccount) {
        $order   = bankMatchingOrder(createCustomer($this->shop), $total);
        $payment = \App\Actions\Accounting\Payment\StorePayment::make()->action($order->customer, $pastpayAccount, [
            'amount' => $total,
            'status' => \App\Enums\Accounting\Payment\PaymentStatusEnum::SUCCESS,
            'state'  => \App\Enums\Accounting\Payment\PaymentStateEnum::COMPLETED,
        ]);
        $payment->updateQuietly(['date' => now()->subDays(5), 'currency_id' => $currency->id]);
        \App\Actions\Ordering\Order\AttachPaymentToOrder::make()->action($order, $payment, ['amount' => $total]);

        return [$order, $payment];
    };
    $line = fn (string $id, float $amount, \App\Models\Helpers\Currency $currency) => BankTransaction::create([
        'group_id'                => $bankConnection->group_id,
        'organisation_id'         => $bankConnection->organisation_id,
        'bank_connection_id'      => $bankConnection->id,
        'provider_transaction_id' => $id,
        'provider_type'           => 'TRANSFER',
        'direction'               => BankTransactionDirectionEnum::IN,
        'amount'                  => $amount,
        'currency_id'             => $currency->id,
        'status'                  => BankTransactionStatusEnum::BOOKED,
        'counterparty_name'       => 'PastPay Europe SP.ZO.O',
        'reference'               => 'SK2120525440-20261014-01',
        'booked_at'               => now(),
    ]);

    [$single, $singlePayment] = $pastpayOrder(130725.03, $huf);
    [$first, $firstPayment]   = $pastpayOrder(21461.50, $huf);
    [$second, $secondPayment] = $pastpayOrder(9843.99, $huf);
    [$lei]                    = $pastpayOrder(7678.69, $ron);
    $shopCurrency             = \App\Models\Helpers\Currency::findOrFail($this->shop->currency_id);
    $pastpayOrder(300.00, $shopCurrency);
    $pastpayOrder(100.00, $shopCurrency);

    $forints  = $line('pph1', 126673, $huf);
    $together = $line('pph2', 30336, $huf);
    $withFee  = $line('ppr1', 7256.76, $ron);
    $sumOfTwo = $line('ppe1', 385.05, $shopCurrency);

    MatchBankTransactions::run($bankConnection);

    expect($forints->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::CARD_PROCESSOR_PAYOUT)
        ->and($forints->order_id)->toBe($single->id)
        ->and($forints->match_data['fee_rate'])->toEqual(0.031)
        ->and($forints->match_data['payment_ids'])->toBe([$singlePayment->id])
        ->and($together->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::CARD_PROCESSOR_PAYOUT)
        ->and($together->order_id)->toBeNull()
        ->and($together->payment_id)->toBeNull()
        ->and($together->match_data['payment_ids'])->toEqualCanonicalizing([$firstPayment->id, $secondPayment->id])
        ->and($together->match_data['order_references'])->toEqualCanonicalizing([$first->reference, $second->reference])
        ->and($together->match_data['payments_in_payout'])->toBe(2)
        ->and($withFee->refresh()->order_id)->toBe($lei->id)
        ->and($withFee->match_data['fee_rate'])->toEqual(0.0525)
        ->and($withFee->match_data['fixed_fee'])->toEqual(18.80)
        ->and($withFee->match_data['processor_fees'])->toEqual(421.93)
        ->and($sumOfTwo->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::UNMATCHED);

    actingAs(createAdminGuest($this->organisation->group)->getUser());
    get(route('grp.org.accounting.bank_incoming.payment_accounts', [$this->organisation->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('data.data', fn ($rows) => collect($rows)->contains(fn ($row) => $row['id'] === $together->id
                && $row['payout']['provider'] === 'pastpay'
                && count($row['payout']['orders']) === 2)));
});

function bankMatchingFulfilmentShop(\App\Models\SysAdmin\Organisation $organisation, int $currencyId): \App\Models\Catalogue\Shop
{
    return \App\Models\Catalogue\Shop::where('organisation_id', $organisation->id)->where('type', \App\Enums\Catalogue\Shop\ShopTypeEnum::FULFILMENT)->first()
        ?? \App\Actions\Catalogue\Shop\StoreShop::run($organisation, array_merge(\App\Models\Catalogue\Shop::factory()->definition(), [
            'type'        => \App\Enums\Catalogue\Shop\ShopTypeEnum::FULFILMENT->value,
            'warehouses'  => [createWarehouse()->id],
            'currency_id' => $currencyId,
        ]));
}

function bankMatchingFulfilmentCustomer(\App\Models\Catalogue\Shop $shop): \App\Models\CRM\Customer
{
    return \App\Actions\CRM\Customer\StoreCustomer::make()->action($shop, \App\Models\CRM\Customer::factory()->definition());
}

function bankMatchingFulfilmentInvoice(\App\Models\CRM\Customer $customer, float $total, ?string $date = null): \App\Models\Accounting\Invoice
{
    $invoice = \App\Actions\Accounting\Invoice\StoreInvoice::make()->action($customer, \App\Models\Accounting\Invoice::factory()->definition());
    $invoice->updateQuietly(['reference' => 'EUFi'.random_int(100000, 999999), 'total_amount' => $total, 'payment_amount' => 0, 'currency_id' => $customer->shop->currency_id, 'date' => $date ?? now()->toDateString()]);

    return $invoice->refresh();
}

function bankMatchingPayByHand(\App\Models\Accounting\Invoice $invoice, \App\Models\Accounting\PaymentAccount $bankAccount, float $amount): \App\Models\Accounting\Payment
{
    $payment = \App\Actions\Accounting\Payment\StorePayment::make()->action($invoice->customer, $bankAccount, [
        'amount' => $amount,
        'status' => \App\Enums\Accounting\Payment\PaymentStatusEnum::SUCCESS,
        'state'  => \App\Enums\Accounting\Payment\PaymentStateEnum::COMPLETED,
    ]);
    \App\Actions\Accounting\Invoice\AttachPaymentToInvoice::make()->action($invoice, $payment, []);

    return $payment;
}

test('a fulfilment customer\'s transfer goes to their credit and pays the invoice named, then their oldest open invoices', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1.0);

    $fulfilmentShop = bankMatchingFulfilmentShop($this->organisation, $this->shop->currency_id);
    $bankConnection = bankMatchingConnection($this->organisation);
    $bankAccount    = StoreOrgPaymentServiceProviderAccount::make()->action(
        $this->organisation,
        PaymentServiceProvider::where('code', 'bank')->firstOrFail(),
        ['code' => 'inv'.random_int(1000, 9999), 'name' => 'Invoice paid test']
    );
    SetBankConnectionAutoPay::run($bankConnection, [$fulfilmentShop->currency->code => $bankAccount->code]);
    $bankConnection->refresh();

    $line = fn (string $id, string $reference, float $amount) => BankTransaction::create([
        'group_id'                => $bankConnection->group_id,
        'organisation_id'         => $bankConnection->organisation_id,
        'bank_connection_id'      => $bankConnection->id,
        'provider_transaction_id' => $id,
        'provider_type'           => 'TRANSFER',
        'direction'               => BankTransactionDirectionEnum::IN,
        'amount'                  => $amount,
        'currency_id'             => $fulfilmentShop->currency_id,
        'status'                  => BankTransactionStatusEnum::BOOKED,
        'reference'               => $reference,
        'booked_at'               => now()->addMinute(),
    ]);

    $customer   = bankMatchingFulfilmentCustomer($fulfilmentShop);
    $old        = bankMatchingFulfilmentInvoice($customer, 184.80, now()->subYears(2)->toDateString());
    bankMatchingPayByHand($old, $bankAccount, 184.80);
    $july       = bankMatchingFulfilmentInvoice($customer, 300, now()->subMonths(4)->toDateString());
    $august     = bankMatchingFulfilmentInvoice($customer, 200, now()->subMonths(3)->toDateString());
    $september  = bankMatchingFulfilmentInvoice($customer, 150, now()->subMonths(2)->toDateString());
    $creditNote = \App\Actions\Accounting\Invoice\StoreInvoice::make()->action($customer, \App\Models\Accounting\Invoice::factory()->definition());
    $creditNote->updateQuietly(['type' => \App\Enums\Accounting\Invoice\InvoiceTypeEnum::REFUND, 'original_invoice_id' => $august->id, 'total_amount' => -200, 'payment_amount' => 0, 'in_process' => false, 'currency_id' => $fulfilmentShop->currency_id]);

    $settled   = bankMatchingFulfilmentCustomer($fulfilmentShop);
    $settledIn = bankMatchingFulfilmentInvoice($settled, 50);
    bankMatchingPayByHand($settledIn, $bankAccount, 50)->updateQuietly(['date' => now()->subDays(60)]);

    $lines = [
        'byHand'  => $line('fi1', $old->reference.'/'.$old->reference, 184.80),
        'oldName' => $line('fi2', strtoupper($old->reference), 100),
        'named'   => $line('fi3', $september->reference, 400),
        'nothing' => $line('fi4', $settledIn->reference, 30),
    ];
    MatchBankTransactions::run($bankConnection);

    expect($lines['byHand']->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::ALREADY_PAID)
        ->and($lines['byHand']->match_data['invoice_ids'])->toBe([$old->id])
        ->and($lines['oldName']->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::MATCHED)
        ->and($lines['oldName']->match_data['customer_id'])->toBe($customer->id)
        ->and($lines['oldName']->match_data['owed'])->toEqual(450)
        ->and($lines['named']->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::MATCHED)
        ->and($lines['nothing']->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::REVIEW)
        ->and($lines['nothing']->match_data['reason'])->toBe('the customer owes nothing');

    expect(PayMatchedBankTransactions::run($bankConnection))->toBe(['paid' => 2])
        ->and($lines['oldName']->refresh()->match_data['paid_invoices'])->toEqual([$july->reference => 100])
        ->and((float)$lines['oldName']->payment->amount)->toBe(100.0)
        ->and($lines['oldName']->payment->payment_account_id)->toBe($bankAccount->id)
        ->and($lines['named']->refresh()->match_data['paid_invoices'])->toEqual([$september->reference => 150, $july->reference => 200])
        ->and((float)$july->refresh()->payment_amount)->toBe(300.0)
        ->and((float)$september->refresh()->payment_amount)->toBe(150.0)
        ->and((float)$august->refresh()->payment_amount)->toBe(0.0)
        ->and((float)$customer->refresh()->balance)->toBe(50.0);

    CancelPayment::make()->handle($lines['named']->payment);
    expect((float)$september->refresh()->payment_amount)->toBe(0.0)
        ->and((float)$july->refresh()->payment_amount)->toBe(100.0)
        ->and((float)$customer->refresh()->balance)->toBe(0.0)
        ->and($lines['named']->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::REVIEW);

    $lines['named']->update(['match_state' => null]);
    MatchBankTransactions::run($bankConnection);
    expect($lines['named']->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::REVIEW)
        ->and($lines['named']->match_data['reason'])->toBe('a payment from this line was cancelled');

    PayMatchedBankTransactions::make()->payToCustomerCredit($lines['named'], $customer, 'Tomas');
    expect($lines['named']->refresh()->match_data['paid_invoices'])->toEqual([$september->reference => 150, $july->reference => 200])
        ->and((float)$customer->refresh()->balance)->toBe(50.0);
});

test('bank-transactions:match --unmatched decides again only the lines left unmatched', function () {
    $order          = bankMatchingOrder(createCustomer($this->shop), 100);
    $bankConnection = bankMatchingConnection($this->organisation);
    $line           = fn (string $id, BankTransactionMatchStateEnum $state) => BankTransaction::create([
        'group_id'                => $bankConnection->group_id,
        'organisation_id'         => $bankConnection->organisation_id,
        'bank_connection_id'      => $bankConnection->id,
        'provider_transaction_id' => $id,
        'provider_type'           => 'TRANSFER',
        'direction'               => BankTransactionDirectionEnum::IN,
        'amount'                  => 100,
        'currency_id'             => $order->currency_id,
        'status'                  => BankTransactionStatusEnum::BOOKED,
        'reference'               => $order->reference,
        'booked_at'               => now(),
        'match_state'             => $state,
        'match_data'              => ['reason' => 'kept'],
    ]);
    $unmatched = $line('um1', BankTransactionMatchStateEnum::UNMATCHED);
    $byHand    = $line('um2', BankTransactionMatchStateEnum::ALREADY_PAID);

    $this->artisan('bank-transactions:match', ['bankConnection' => $bankConnection->id, '--unmatched' => true])->assertSuccessful();

    expect($unmatched->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::MATCHED)
        ->and($byHand->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::ALREADY_PAID)
        ->and($byHand->match_data['reason'])->toBe('kept');
});

test('a fulfilment customer paying without an invoice number is matched by payer name; split payments recorded by hand are already paid; customers whose invoices miss payments go to review', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1.0);

    $fulfilmentShop = bankMatchingFulfilmentShop($this->organisation, $this->shop->currency_id);
    $bankConnection = bankMatchingConnection($this->organisation);
    $bankAccount    = StoreOrgPaymentServiceProviderAccount::make()->action(
        $this->organisation,
        PaymentServiceProvider::where('code', 'bank')->firstOrFail(),
        ['code' => 'pay'.random_int(1000, 9999), 'name' => 'Payer name test']
    );
    SetBankConnectionAutoPay::run($bankConnection, [$fulfilmentShop->currency->code => $bankAccount->code]);
    $bankConnection->refresh();

    $suffix   = random_int(1000, 9999);
    $customer = bankMatchingFulfilmentCustomer($fulfilmentShop);
    $customer->updateQuietly(['company_name' => "Quokka Storage $suffix s.r.o."]);
    $messy = bankMatchingFulfilmentCustomer($fulfilmentShop);
    $messy->updateQuietly(['company_name' => "Wombat Haulage $suffix Ltd"]);
    $alsoShopper = bankMatchingFulfilmentCustomer($fulfilmentShop);
    $alsoShopper->updateQuietly(['company_name' => "Koala Freight $suffix Ltd"]);
    bankMatchingFulfilmentInvoice($alsoShopper, 90);
    \App\Actions\CRM\Customer\StoreCustomer::make()->action($this->shop, \App\Models\CRM\Customer::factory()->definition())->updateQuietly(['company_name' => "KOALA FREIGHT $suffix LTD."]);

    $line = fn (string $id, string $payer, float $amount, string $reference = 'storage fee') => BankTransaction::create([
        'group_id'                => $bankConnection->group_id,
        'organisation_id'         => $bankConnection->organisation_id,
        'bank_connection_id'      => $bankConnection->id,
        'provider_transaction_id' => $id,
        'provider_type'           => 'TRANSFER',
        'direction'               => BankTransactionDirectionEnum::IN,
        'amount'                  => $amount,
        'currency_id'             => $fulfilmentShop->currency_id,
        'status'                  => BankTransactionStatusEnum::BOOKED,
        'counterparty_name'       => $payer,
        'reference'               => $reference,
        'booked_at'               => now()->addMinute(),
    ]);

    $september = bankMatchingFulfilmentInvoice($customer, 250, now()->subDays(60)->toDateString());
    $october   = bankMatchingFulfilmentInvoice($customer, 250, now()->subDays(30)->toDateString());
    $split     = bankMatchingFulfilmentInvoice($customer, 84.49, now()->subDays(30)->toDateString());
    bankMatchingPayByHand($split, $bankAccount, 70.42);
    bankMatchingPayByHand($split, $bankAccount, 14.07);

    bankMatchingFulfilmentInvoice($messy, 500, now()->subDays(30)->toDateString());
    \App\Actions\Accounting\Payment\StorePayment::make()->action($messy, $bankAccount, [
        'amount' => 120,
        'status' => \App\Enums\Accounting\Payment\PaymentStatusEnum::SUCCESS,
        'state'  => \App\Enums\Accounting\Payment\PaymentStateEnum::COMPLETED,
    ])->updateQuietly(['date' => now()->subDays(60)]);

    $numbat = bankMatchingFulfilmentCustomer($fulfilmentShop);
    $numbatInvoice = bankMatchingFulfilmentInvoice($numbat, 400);
    bankMatchingPayByHand($numbatInvoice, $bankAccount, 100);

    $lines = [
        'splitOne'  => $line('pn2', "Quokka Storage $suffix s.r.o.", 70.42, $split->reference),
        'splitTwo'  => $line('pn3', "Quokka Storage $suffix s.r.o.", 14.07, $split->reference),
        'byName'    => $line('pn1', "QUOKKA STORAGE $suffix S. R. O.", 300),
        'unexplained' => $line('pn7', 'Numbat', 400, $numbatInvoice->reference),
        'messy'     => $line('pn4', "Wombat Haulage $suffix Ltd", 500),
        'stranger'  => $line('pn5', 'Someone Else Ltd', 250),
        'shopper'   => $line('pn6', "Koala Freight $suffix Ltd", 90),
    ];
    MatchBankTransactions::run($bankConnection);

    expect($lines['byName']->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::MATCHED)
        ->and($lines['byName']->match_data['matched_by'])->toBe('payer')
        ->and($lines['splitOne']->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::ALREADY_PAID)
        ->and($lines['splitTwo']->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::ALREADY_PAID)
        ->and($lines['splitOne']->match_data['hand_payment_ids'])->toHaveCount(1)
        ->and($lines['splitOne']->match_data['hand_payment_ids'])->not->toBe($lines['splitTwo']->match_data['hand_payment_ids'])
        ->and($lines['messy']->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::REVIEW)
        ->and($lines['messy']->match_data['reason'])->toBe('payments of this customer are on no invoice: put them on their invoices first')
        ->and($lines['stranger']->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::UNMATCHED)
        ->and($lines['stranger']->match_data['reason'])->toBe('no order number in the reference')
        ->and($lines['shopper']->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::REVIEW)
        ->and($lines['shopper']->match_data['reason'])->toBe('the payer is also a customer of another shop')
        ->and($lines['unexplained']->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::REVIEW)
        ->and($lines['unexplained']->match_data['reason'])->toBe('staff recorded other bank payments for this customer around this date');

    expect(PayMatchedBankTransactions::run($bankConnection))->toBe(['paid' => 1])
        ->and($lines['byName']->refresh()->match_data['paid_invoices'])->toEqual([$september->reference => 250, $october->reference => 50])
        ->and((float)$october->refresh()->payment_amount)->toBe(50.0)
        ->and((float)$customer->refresh()->balance)->toBe(0.0);

    PayMatchedBankTransactions::make()->payToCustomerCredit($lines['messy'], $messy, 'Tomas');
    expect($lines['messy']->refresh()->match_data)->not->toHaveKey('paid_invoices')
        ->and((float)$messy->refresh()->balance)->toBe(500.0);
});

test('a carrier cash on delivery payout is read from its emailed list: payments staff typed are linked, missing ones recorded, differences reported', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1.0);
    $bankConnection = bankMatchingConnection($this->organisation);
    $bankConnection->update(['settings' => ['auto_pay_from' => now()->subDay()->toIso8601String()]]);
    $codAccount = \App\Actions\Accounting\PaymentAccount\StorePaymentAccount::make()->action(
        \App\Actions\Accounting\OrgPaymentServiceProvider\StoreOrgPaymentServiceProvider::make()->action(
            paymentServiceProvider: PaymentServiceProvider::where('code', 'cond')->firstOrFail(),
            organisation: $this->organisation,
            modelData: ['code' => 'cod'.random_int(1000, 9999)]
        ),
        ['code' => 'cod'.random_int(1000, 9999), 'name' => 'COD payouts test', 'type' => PaymentAccountTypeEnum::CASH_ON_DELIVERY->value]
    );
    \App\Actions\Accounting\PaymentAccountShop\StorePaymentAccountShop::make()->action($codAccount, $this->shop, [
        'currency_id' => $this->shop->currency_id,
        'state'       => \App\Enums\Accounting\PaymentAccountShop\PaymentAccountShopStateEnum::ACTIVE,
    ]);
    $warehouse = createWarehouse();
    $shipper   = \App\Actions\Dispatching\Shipper\StoreShipper::make()->action($this->organisation, ['code' => 'DPD'.random_int(100, 999), 'name' => 'DPD', 'trade_as' => 'dpd']);
    $parcel    = function (float $total, string $tracking) use ($warehouse, $shipper) {
        $order = bankMatchingOrder(createCustomer($this->shop), $total);
        $order->updateQuietly(['to_be_paid_by' => \App\Enums\Ordering\Order\OrderToBePaidByEnum::CASH_ON_DELIVERY]);
        $deliveryNote = \App\Actions\Dispatching\DeliveryNote\StoreDeliveryNote::make()->action($order, [
            'reference'        => 'DN'.random_int(100000, 999999),
            'state'            => \App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum::UNASSIGNED,
            'email'            => 'test@email.com',
            'date'             => date('Y-m-d'),
            'delivery_address' => new Address(Address::factory()->definition()),
            'warehouse_id'     => $warehouse->id,
        ]);
        \App\Actions\Dispatching\Shipment\StoreShipment::make()->action($deliveryNote, $shipper, ['tracking' => $tracking]);

        return $order->refresh();
    };
    $digits = fn (Order $order) => preg_replace('/\D/', '', $order->reference);
    $prefix = (string)random_int(10000000, 99999999);

    $typed      = $parcel(50757.55, $prefix.'000001');
    $typedWrong = $parcel(40896.00, $prefix.'000002');
    $untyped    = $parcel(23431.40, $prefix.'000003');
    \App\Actions\Dispatching\Shipment\StoreShipment::make()->action($untyped->deliveryNotes()->first(), $shipper, ['tracking' => $prefix.'000004']);
    foreach ([[$typed, 50757.55], [$typedWrong, 31446]] as [$order, $amount]) {
        \App\Actions\Ordering\Order\PayOrder::make()->action($order, $codAccount, ['amount' => $amount, 'status' => \App\Enums\Accounting\Payment\PaymentStatusEnum::SUCCESS, 'state' => PaymentStateEnum::COMPLETED]);
    }

    $text = "Átutalás melléklet lista\nFeladó:Ancient Wisdom s.r.o.\tDátum:".now()->toDateString()."\nCsomagszám\tCímzett\tFeladás d.ÖsszegRef. szám\n"
        .$prefix."000001\t0001.01.01WEBfield-Design Kft. Richard J, 8,\n5700, Gyula\n50758.0".$digits($typed)."\n"
        .$prefix."000002\t0001.01.01Szarvas Agnes, D, 4200,\n40896.0".$digits($typedWrong)."\n"
        .$prefix."000003\t0001.01.01Nilsson Thomas,\n4243, Teglas\n20000.0".$digits($untyped)." / HU".$digits($untyped)."\n"
        .$prefix."000004\t0001.01.01Nilsson Thomas,\n4243, Teglas\n3431.0".$digits($untyped)." / HU".$digits($untyped)."-1\n"
        .$prefix."000009\t0001.01.01Somebody Else\n10000.0123\n\nÖsszesen: 125,085";
    $reconciler = \App\Actions\Accounting\BankTransaction\ReconcileCodPayouts::make();
    $list       = $reconciler->parseDpdHungaryList($text);

    expect($list['total'])->toBe(125085)
        ->and($list['rows'])->toHaveCount(5)
        ->and($list['rows'][3])->toBe(['tracking' => $prefix.'000004', 'reference' => $digits($untyped), 'amount' => 3431.0])
        ->and($reconciler->parseDpdHungaryList(str_replace('125,085', '125,086', $text)))->toBeNull();

    $line = BankTransaction::create([
        'group_id'                => $bankConnection->group_id,
        'organisation_id'         => $bankConnection->organisation_id,
        'bank_connection_id'      => $bankConnection->id,
        'provider_transaction_id' => 'cod'.random_int(1000, 9999),
        'provider_type'           => 'TRANSFER',
        'direction'               => BankTransactionDirectionEnum::IN,
        'amount'                  => 125085,
        'currency_id'             => $this->shop->currency_id,
        'status'                  => BankTransactionStatusEnum::BOOKED,
        'counterparty_name'       => 'DPD HUNGARY KFT.',
        'reference'               => $prefix.'000001, B02, 093 közvetített áru ellenértéke',
        'booked_at'               => now(),
    ]);
    MatchBankTransactions::run($bankConnection);

    expect($line->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::CARD_PROCESSOR_PAYOUT)
        ->and($line->match_data)->toEqual(['provider' => 'cod', 'carrier' => 'dpd_hu']);

    $dryRun = $reconciler->reconcile($line->load('bankConnection'), $list, false);
    expect($dryRun['payments_created'])->toBe(0)
        ->and($dryRun['payments_not_recorded'])->toBe([$untyped->reference])
        ->and($untyped->refresh()->payment_amount)->toEqual(0);

    $matchData = $reconciler->reconcile($line, $list, true);
    $recorded  = $untyped->refresh()->payments()->first();

    expect($matchData['payments_created'])->toBe(1)
        ->and($recorded->amount)->toEqual(23431.40)
        ->and($recorded->reference)->toBe($prefix.'000003')
        ->and($untyped->payment_amount)->toEqual(23431.40)
        ->and($matchData['payment_ids'])->toHaveCount(3)
        ->and($matchData['payment_ids'])->toContain($recorded->id)
        ->and($matchData['payments_missing'])->toBe([$prefix.'000009'])
        ->and($matchData['differences'])->toBe([['order' => $typedWrong->reference, 'list' => 40896.0, 'aiku' => 31446.0]])
        ->and($typedWrong->refresh()->payment_amount)->toEqual(31446)
        ->and($matchData['reconciled_at'])->toBeNull()
        ->and($reconciler->reconcile($line, $list, true)['payments_created'])->toBe(0)
        ->and($reconciler->reconcile($line, ['date' => $list['date'], 'total' => 50758, 'rows' => [$list['rows'][0]]], true)['reconciled_at'])->not->toBeNull();

    $line->update(['match_data' => $matchData]);
    actingAs(createAdminGuest($this->organisation->group)->getUser());
    post(route('grp.models.order.payment.store', [$untyped->id, $codAccount->id]), ['amount' => 23431.40, 'status' => 'success', 'state' => 'completed'])
        ->assertSessionHasErrors('amount');
    expect($untyped->payments()->count())->toBe(1);
    get(route('grp.org.accounting.bank_incoming.payment_accounts', [$this->organisation->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('data.data', fn ($rows) => collect($rows)->contains(fn ($row) => $row['id'] === $line->id
                && $row['payout']['provider'] === 'cod'
                && $row['payout']['carrier'] === 'DPD Hungary'
                && $row['payout']['reconciled'] === false
                && $row['payout']['payments'] === 5
                && $row['payout']['payments_missing'] === 1
                && count($row['payout']['differences']) === 1)));
});

test('carrier lines decided before carriers were recognised become cash on delivery payouts', function () {
    $bankConnection = bankMatchingConnection($this->organisation);
    $line           = fn (string $payer, $state) => BankTransaction::create([
        'group_id'                => $bankConnection->group_id,
        'organisation_id'         => $bankConnection->organisation_id,
        'bank_connection_id'      => $bankConnection->id,
        'provider_transaction_id' => 'gls'.random_int(10000, 99999),
        'provider_type'           => 'TRANSFER',
        'direction'               => BankTransactionDirectionEnum::IN,
        'amount'                  => 532.69,
        'currency_id'             => $this->shop->currency_id,
        'status'                  => BankTransactionStatusEnum::BOOKED,
        'counterparty_name'       => $payer,
        'reference'               => '/ROC/TRANSFER RAMBURS',
        'booked_at'               => now(),
        'match_state'             => $state,
        'match_data'              => ['reason' => 'no order named'],
    ]);
    $romania  = $line('GLS GENERAL LOGISTICS SYSTEMS ROMAN', BankTransactionMatchStateEnum::UNMATCHED);
    $reviewed = $line('GLS GENERAL LOG.SYSTEMS HUNG.CSOMAG', BankTransactionMatchStateEnum::REVIEW);
    $other    = $line('GLS Spain customer service', BankTransactionMatchStateEnum::UNMATCHED);

    \App\Actions\Accounting\BankTransaction\ReconcileCodPayouts::make()->handle(false);
    expect($romania->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::UNMATCHED);

    $amount = random_int(100000, 999999);
    $dpd    = fn (Carbon $bookedAt) => BankTransaction::create([
        'group_id'                => $bankConnection->group_id,
        'organisation_id'         => $bankConnection->organisation_id,
        'bank_connection_id'      => $bankConnection->id,
        'provider_transaction_id' => 'dpd'.random_int(10000, 99999),
        'provider_type'           => 'TRANSFER',
        'direction'               => BankTransactionDirectionEnum::IN,
        'amount'                  => $amount,
        'currency_id'             => $this->shop->currency_id,
        'status'                  => BankTransactionStatusEnum::BOOKED,
        'counterparty_name'       => 'DPD HUNGARY KFT.',
        'reference'               => '06585701046349 közvetített áru ellenértéke',
        'booked_at'               => $bookedAt,
    ]);
    $paid = $dpd(now()->subDay());
    $twin = $dpd(now()->subDay());
    $late = $dpd(now()->subDays(10));
    $list = ['date' => now()->toDateString(), 'total' => $amount, 'rows' => [['tracking' => '99999999999999', 'reference' => '1', 'amount' => (float)$amount]], 'source' => 'hu:test'];
    \App\Actions\Accounting\BankTransaction\ReconcileCodPayouts::partialMock()->shouldReceive('listsInMailboxes')->andReturn([$list['date'].'|'.$amount => $list]);

    \App\Actions\Accounting\BankTransaction\ReconcileCodPayouts::make()->handle(true);
    expect($romania->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::CARD_PROCESSOR_PAYOUT)
        ->and($romania->match_data)->toEqual(['provider' => 'cod', 'carrier' => 'gls_ro'])
        ->and($reviewed->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::REVIEW)
        ->and($other->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::UNMATCHED)
        ->and($paid->refresh()->match_data['reconciled_at'])->toBeNull()
        ->and($paid->match_data['list'])->toEqual(['date' => $list['date'], 'total' => $amount, 'source' => 'hu:test'])
        ->and($paid->match_data['payments_missing'])->toBe(['99999999999999'])
        ->and($twin->refresh()->match_data)->toEqual(['provider' => 'cod', 'carrier' => 'dpd_hu'])
        ->and($late->refresh()->match_data)->toEqual(['provider' => 'cod', 'carrier' => 'dpd_hu']);
});

test('GLS and DPD Poland cash on delivery sheets are read, and a parcel is followed through a shipment of several parcels or a reused number', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1.0);
    $bankConnection = bankMatchingConnection($this->organisation);
    $bankConnection->update(['settings' => ['auto_pay_from' => now()->subDay()->toIso8601String()]]);
    $codAccount = \App\Actions\Accounting\PaymentAccount\StorePaymentAccount::make()->action(
        \App\Actions\Accounting\OrgPaymentServiceProvider\StoreOrgPaymentServiceProvider::make()->action(
            paymentServiceProvider: PaymentServiceProvider::where('code', 'cond')->firstOrFail(),
            organisation: $this->organisation,
            modelData: ['code' => 'cod'.random_int(1000, 9999)]
        ),
        ['code' => 'cod'.random_int(1000, 9999), 'name' => 'GLS COD test', 'type' => PaymentAccountTypeEnum::CASH_ON_DELIVERY->value]
    );
    \App\Actions\Accounting\PaymentAccountShop\StorePaymentAccountShop::make()->action($codAccount, $this->shop, [
        'currency_id' => $this->shop->currency_id,
        'state'       => \App\Enums\Accounting\PaymentAccountShop\PaymentAccountShopStateEnum::ACTIVE,
    ]);
    $warehouse = createWarehouse();
    $shipper   = \App\Actions\Dispatching\Shipper\StoreShipper::make()->action($this->organisation, ['code' => 'GLS'.random_int(100, 999), 'name' => 'GLS', 'trade_as' => 'gls']);
    $parcel    = function (float $total, string $tracking) use ($warehouse, $shipper) {
        $order = bankMatchingOrder(createCustomer($this->shop), $total);
        $order->updateQuietly(['to_be_paid_by' => \App\Enums\Ordering\Order\OrderToBePaidByEnum::CASH_ON_DELIVERY]);
        $deliveryNote = \App\Actions\Dispatching\DeliveryNote\StoreDeliveryNote::make()->action($order, [
            'reference'        => 'DN'.random_int(100000, 999999),
            'state'            => \App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum::UNASSIGNED,
            'email'            => 'test@email.com',
            'date'             => date('Y-m-d'),
            'delivery_address' => new Address(Address::factory()->definition()),
            'warehouse_id'     => $warehouse->id,
        ]);
        \App\Actions\Dispatching\Shipment\StoreShipment::make()->action($deliveryNote, $shipper, ['tracking' => $tracking]);

        return $order->refresh();
    };
    $digits = fn (Order $order) => preg_replace('/\D/', '', $order->reference);
    $prefix = (string)random_int(1000000, 9999999);

    $twoParcels = $parcel(1264.47, $prefix.'0001, '.$prefix.'0002');
    $reused     = $parcel(254.83, $prefix.'0003');
    $other      = $parcel(254.83, $prefix.'0003');
    $differs    = $parcel(300.00, $prefix.'0004');

    $reconciler = \App\Actions\Accounting\BankTransaction\ReconcileCodPayouts::make();
    $sheet      = [
        ['GLS General Logistics Systems Romania SRL', '', '', '', '', '', ''],
        [null, null, null, null, null, null, null],
        ['Data tranferarii banilor: '.now()->format('d.m.Y'), null, null, null, null, null, null],
        ['Număr referinta', 'Număr colet', 'Referire la ramb.', 'Livrat la data', 'Sumă ramburs', '', 'Postal Address'],
        [135375480, (int)($prefix.'0001'), $digits($twoParcels).'/1', 46337, 1164.47, 'RON', 'Prodprosper 2003 Srl'],
        [135375480, (int)($prefix.'0002'), $digits($twoParcels).'/2', 46337, 100, 'RON', 'Prodprosper 2003 Srl'],
        [135379125, (int)($prefix.'0003'), $digits($reused), 46337, 254.83, 'RON', 'Sc Avelana Srl'],
        [135379126, (int)($prefix.'0004'), $digits($differs), 46337, 299.6, 'RON', 'Somebody'],
        ['', '', '', '', 1818.9, 'RON', ''],
    ];
    $list = $reconciler->parseGlsList($sheet);
    expect($list['date'])->toBe(now()->toDateString())
        ->and($list['total'])->toBe(1818.9)
        ->and($list['rows'][1])->toBe(['tracking' => $prefix.'0002', 'reference' => $digits($twoParcels), 'amount' => 100.0])
        ->and($reconciler->parseGlsList(array_replace($sheet, [8 => ['', '', '', '', 1818.91, 'RON', '']])))->toBeNull()
        ->and($reconciler->parseGlsList(array_replace($sheet, [2 => ['Utalás dátuma: 2026. 11. 10.', null]]))['date'])->toBe('2026-11-10');

    $dpdPoland = [
        ['Customer number', 'Customer name', 'Parcel label number', 'Shiping date', 'COD amount in PLN', 'Delivery date', 'Recipient name', 'Recipient adres (postal code)', 'Recipient adres (city)', 'Recipient adres (street)', 'Customer number2', 'Total transfer amount in PLN', 'Bank transfer date', 'Settlement number'],
        [230935, 'Ancient Wisdom s. r. o.', '06585701029623', 46318, 1654.92, 46321, 'NaturMedica', '42700', 'Lubliniec', 'Ul 22c', '31273', 318820.92, 46331, 'A25334'],
        [230935, 'Ancient Wisdom s. r. o.', '06585701029685', 46318, 2065.69, 46322, 'Lidia', '09200', 'Sierpc', 'ul. Płocka 47', '31210', 318820.92, 46331, 'A25334'],
    ];
    expect($reconciler->parseDpdPolandList($dpdPoland))->toBe(['date' => '2026-11-05', 'total' => 3720.61, 'rows' => [
        ['tracking' => '06585701029623', 'reference' => '31273', 'amount' => 1654.92],
        ['tracking' => '06585701029685', 'reference' => '31210', 'amount' => 2065.69],
    ]])
        ->and($reconciler->parseDpdPolandList(array_replace($dpdPoland, [2 => array_replace($dpdPoland[2], [12 => 46332])])))->toBeNull();

    $line = fn () => BankTransaction::create([
        'group_id'                => $bankConnection->group_id,
        'organisation_id'         => $bankConnection->organisation_id,
        'bank_connection_id'      => $bankConnection->id,
        'provider_transaction_id' => 'gls'.random_int(100000, 999999),
        'provider_type'           => 'TRANSFER',
        'direction'               => BankTransactionDirectionEnum::IN,
        'amount'                  => 1818.90,
        'currency_id'             => $this->shop->currency_id,
        'status'                  => BankTransactionStatusEnum::BOOKED,
        'counterparty_name'       => 'GLS GENERAL LOGISTICS SYSTEMS ROMAN',
        'reference'               => '/ROC/TRANSFER RAMBURS',
        'booked_at'               => now(),
    ]);
    $first = $line();
    $twin  = $line();
    MatchBankTransactions::run($bankConnection);
    \App\Actions\Accounting\BankTransaction\ReconcileCodPayouts::partialMock()->shouldReceive('listsInMailboxes')->andReturn(['gls' => $list + ['source' => 'ro:test']]);
    \App\Actions\Accounting\BankTransaction\ReconcileCodPayouts::make()->handle(true);

    $claimed = collect([$first->refresh(), $twin->refresh()])->filter(fn (BankTransaction $bankTransaction) => isset($bankTransaction->match_data['list']));
    expect($first->match_data['carrier'])->toBe('gls_ro')
        ->and($claimed)->toHaveCount(1)
        ->and($claimed->first()->match_data['payments_created'])->toBe(2)
        ->and($claimed->first()->match_data['payments_missing'])->toBe([])
        ->and($claimed->first()->match_data['differences'])->toEqual([['order' => $differs->reference, 'list' => 299.6, 'aiku' => 300, 'not_recorded' => true]])
        ->and((float)$twoParcels->refresh()->payment_amount)->toBe(1264.47)
        ->and((float)$reused->refresh()->payment_amount)->toBe(254.83)
        ->and((float)$other->refresh()->payment_amount)->toBe(0.0)
        ->and((float)$differs->refresh()->payment_amount)->toBe(0.0);
});

test('bank incoming puts every line received in exactly one section, each with its own page, and a dashboard that adds up', function () {
    $bankConnection = bankMatchingConnection($this->organisation);
    $currencyId     = $this->organisation->currency_id;
    $line           = fn (string $id, ?BankTransactionMatchStateEnum $state, array $matchData = [], string $type = 'TRANSFER') => BankTransaction::create([
        'group_id'                => $bankConnection->group_id,
        'organisation_id'         => $bankConnection->organisation_id,
        'bank_connection_id'      => $bankConnection->id,
        'provider_transaction_id' => $id,
        'provider_type'           => $type,
        'direction'               => BankTransactionDirectionEnum::IN,
        'amount'                  => 10,
        'currency_id'             => $currencyId,
        'status'                  => BankTransactionStatusEnum::BOOKED,
        'reference'               => 'Section test',
        'booked_at'               => now()->subDay(),
        'match_state'             => $state,
        'match_data'              => $matchData,
    ]);

    $expected = [
        'customers'        => [$line('s1', BankTransactionMatchStateEnum::ALREADY_PAID, ['reason' => 'order already settled'])],
        'marketplaces'     => [
            $line('s2', BankTransactionMatchStateEnum::PAID_BY_MARKETPLACE),
            $line('s3', BankTransactionMatchStateEnum::UNMATCHED, ['reason' => 'marketplace order not found']),
        ],
        'payment_accounts' => [
            $line('s4', BankTransactionMatchStateEnum::CARD_PROCESSOR_PAYOUT, ['provider' => 'cod', 'carrier' => 'gls_hu']),
            $line('s5', BankTransactionMatchStateEnum::UNMATCHED, ['reason' => 'PastPay payout, no PastPay order of this amount']),
        ],
        'other'            => [
            $line('s6', BankTransactionMatchStateEnum::DISMISSED, ['dismissed_note' => 'Own transfer', 'dismissed_by' => 'staff']),
            $line('s7', null, [], 'CARD_CASHBACK'),
        ],
        'needs_person'     => [
            $line('s8', BankTransactionMatchStateEnum::REVIEW, ['reason' => 'pays less than owed']),
            $line('s9', BankTransactionMatchStateEnum::UNMATCHED, ['reason' => 'no order number in the reference']),
            $line('s10', BankTransactionMatchStateEnum::MATCHED),
        ],
    ];

    actingAs(createAdminGuest($this->organisation->group)->getUser());
    Config::set('inertia.pages.paths', [resource_path('js/Pages/Grp')]);

    $shown = [];
    foreach ($expected as $section => $lines) {
        $props = get(route("grp.org.accounting.bank_incoming.$section", [$this->organisation->slug, 'filter' => ['global' => 'Section test']]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Org/Accounting/BankIncoming')->where('section', $section))
            ->viewData('page')['props'];
        $shown[$section] = collect($props['data']['data'])->keyBy('id');
        expect($shown[$section]->keys()->sort()->values()->all())->toBe(collect($lines)->pluck('id')->sort()->values()->all());
    }
    expect($shown['needs_person']->every(fn ($row) => $row['actionable']))->toBeTrue()
        ->and($shown['marketplaces'][$expected['marketplaces'][1]->id]['actionable'])->toBeTrue()
        ->and($shown['marketplaces'][$expected['marketplaces'][0]->id]['actionable'])->toBeFalse()
        ->and($shown['customers']->first()['actionable'])->toBeFalse()
        ->and($shown['other']->where('is_dismissed', true)->count())->toBe(1);

    $providers = get(route('grp.org.accounting.bank_incoming.payment_accounts', [$this->organisation->slug, 'provider' => 'gls_hu', 'filter' => ['global' => 'Section test']]))->viewData('page')['props'];
    expect(collect($providers['data']['data'])->pluck('id')->all())->toBe([$expected['payment_accounts'][0]->id])
        ->and($providers['provider'])->toBe('gls_hu')
        ->and(collect($providers['providers'])->pluck('label', 'value')->all())->toMatchArray(['gls_hu' => 'GLS Hungary', 'pastpay' => 'PastPay']);

    $dashboard = get(route('grp.org.accounting.bank_incoming.index', [$this->organisation->slug]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Org/Accounting/BankIncomingDashboard'))
        ->viewData('page')['props']['dashboard'];
    $received = BankTransaction::where('organisation_id', $this->organisation->id)
        ->where('direction', BankTransactionDirectionEnum::IN)
        ->where('status', BankTransactionStatusEnum::BOOKED)
        ->where('booked_at', '>=', now()->subDays(90))
        ->where(fn ($query) => $query->whereNotNull('match_state')->orWhere('provider_type', 'TRANSFER'))
        ->count();
    expect(collect($dashboard['sections'])->sum('lines'))->toBe($received)
        ->and($dashboard['total']['lines'])->toBe($received)
        ->and(collect($dashboard['placement'])->sum('lines'))->toBe($received)
        ->and($dashboard['backlog']['lines'])->toBe(collect($dashboard['sections'])->firstWhere('key', 'needs_person')['lines'])
        ->and($dashboard['backlog']['lines'])->toBeGreaterThanOrEqual(3)
        ->and($dashboard['placement']['hand']['lines'])->toBeGreaterThanOrEqual(1)
        ->and($dashboard['own']['lines'])->toBeGreaterThanOrEqual(1)
        ->and(collect($dashboard['sections'])->firstWhere('key', 'other')['lines'])->toBeGreaterThanOrEqual(1)
        ->and($dashboard['backlog']['oldest'])->not->toBeNull()
        ->and(collect($dashboard['providers'])->pluck('label')->all())->toContain('GLS Hungary', 'PastPay')
        ->and(collect($dashboard['banks'])->pluck('label')->all())->toContain($bankConnection->name)
        ->and(count($dashboard['trend']['starts']))->toBe(13)
        ->and(array_sum(array_map('array_sum', $dashboard['trend']['series'])))->toEqualWithDelta($dashboard['total']['amount'], 0.01);

    get("/org/{$this->organisation->slug}/accounting/bank-review")
        ->assertRedirect(route('grp.org.accounting.bank_incoming.index', [$this->organisation->slug]));
});

test('group financials shows bank incoming of every organisation the user may see, in the group currency, read only, only to holders of the financials permission', function () {
    $group                 = $this->organisation->group;
    $otherOrganisationData = Organisation::factory()->definition();
    data_set($otherOrganisationData, 'code', 'fin'.random_int(1000, 9999));
    data_set($otherOrganisationData, 'type', OrganisationTypeEnum::SHOP);
    $otherOrganisation = StoreOrganisation::make()->action($group, $otherOrganisationData);

    $line = fn (Organisation $organisation, string $id) => BankTransaction::create([
        'group_id'                => $group->id,
        'organisation_id'         => $organisation->id,
        'bank_connection_id'      => bankMatchingConnection($organisation)->id,
        'provider_transaction_id' => $id,
        'provider_type'           => 'TRANSFER',
        'direction'               => BankTransactionDirectionEnum::IN,
        'amount'                  => 10,
        'currency_id'             => $group->currency_id,
        'status'                  => BankTransactionStatusEnum::BOOKED,
        'reference'               => 'Financials test',
        'booked_at'               => now()->subDay(),
        'match_state'             => BankTransactionMatchStateEnum::REVIEW,
        'match_data'              => ['reason' => 'pays less than owed'],
    ]);
    $mine   = $line($this->organisation, 'g1');
    $theirs = $line($otherOrganisation, 'g2');

    $admin = createAdminGuest($group)->getUser();
    setPermissionsTeamId($group->id);
    expect(\App\Actions\UI\Grp\Layout\GetGroupNavigation::make()->handle($admin))->not->toHaveKey('financials');
    $admin->givePermissionTo('financials');
    $admin->refresh();
    actingAs($admin);
    Config::set('inertia.pages.paths', [resource_path('js/Pages/Grp')]);

    $rows = fn (array $query = []) => collect(get(route('grp.financials.bank_incoming.needs_person', ['filter' => ['global' => 'Financials test'], ...$query]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Org/Accounting/BankIncoming')
            ->where('canEdit', false)
            ->where('orgCurrency', $group->currency->code)
            ->where('queryBuilderProps.default.columns', fn ($columns) => collect($columns)->pluck('key')->contains('organisation_code'))
            ->where('pageHead.subNavigation.0.route.name', 'grp.financials.bank_incoming.index'))
        ->viewData('page')['props']['data']['data']);

    expect($rows()->pluck('id')->all())->toContain($mine->id, $theirs->id)
        ->and($rows()->every(fn ($row) => $row['actionable'] === false && $row['reason'] === 'pays less than owed'))->toBeTrue()
        ->and($rows()->firstWhere('id', $theirs->id)['organisation_slug'])->toBe($otherOrganisation->slug)
        ->and($rows(['org' => $otherOrganisation->slug])->pluck('id')->all())->toBe([$theirs->id]);

    foreach (['customers', 'marketplaces', 'payment_accounts', 'other'] as $section) {
        get(route("grp.financials.bank_incoming.$section"))->assertOk();
    }
    $dashboard = get(route('grp.financials.bank_incoming.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Org/Accounting/BankIncomingDashboard')->where('orgCurrency', $group->currency->code))
        ->viewData('page')['props']['dashboard'];
    expect(collect($dashboard['organisations'])->pluck('label')->all())->toContain($this->organisation->name, $otherOrganisation->name);
    get(route('grp.financials.dashboard'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('Org/Accounting/FinancialsDashboard'));
    expect(\App\Actions\UI\Grp\Layout\GetGroupNavigation::make()->handle($admin))->toHaveKey('financials');

    setPermissionsTeamId($group->id);
    $restricted = \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($group, array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => []]))->getUser();
    expect(\App\Actions\UI\Grp\Layout\GetGroupNavigation::make()->handle($restricted))->not->toHaveKey('financials');
    actingAs($restricted);
    get(route('grp.financials.bank_incoming.index'))->assertForbidden();
    get(route('grp.financials.bank_incoming.needs_person'))->assertForbidden();

    $restricted->givePermissionTo("accounting.{$otherOrganisation->id}.view");
    $restricted->refresh();
    actingAs($restricted);
    expect(\App\Actions\UI\Grp\Layout\GetGroupNavigation::make()->handle($restricted))->not->toHaveKey('financials');
    get(route('grp.financials.bank_incoming.needs_person'))->assertForbidden();

    $restricted->givePermissionTo('financials');
    $restricted->refresh();
    actingAs($restricted);
    expect($rows()->pluck('id')->all())->toBe([$theirs->id])
        ->and(\App\Actions\UI\Grp\Layout\GetGroupNavigation::make()->handle($restricted))->toHaveKey('financials');
    $dashboard = get(route('grp.financials.bank_incoming.index'))->assertOk()->viewData('page')['props']['dashboard'];
    expect(collect($dashboard['organisations'])->pluck('label')->all())->toBe([$otherOrganisation->name]);
});

test('bank outgoing follows the payee: guessed from a name in aiku, else classified once by a person for past and future lines', function () {
    $group          = $this->organisation->group;
    $bankConnection = bankMatchingConnection($this->organisation);
    $supplier       = \App\Actions\SupplyChain\Supplier\StoreSupplier::make()->action($group, array_merge(\App\Models\SupplyChain\Supplier::factory()->definition(), ['company_name' => 'Zebra Żółw Candles Limited']));
    $line           = fn (string $id, string $type, ?string $payee, float $amount = -10, array $extra = []) => BankTransaction::create(array_merge([
        'group_id'                => $group->id,
        'organisation_id'         => $this->organisation->id,
        'bank_connection_id'      => $bankConnection->id,
        'provider_transaction_id' => $id,
        'provider_type'           => $type,
        'direction'               => BankTransactionDirectionEnum::OUT,
        'amount'                  => $amount,
        'currency_id'             => $this->organisation->currency_id,
        'status'                  => BankTransactionStatusEnum::BOOKED,
        'counterparty_name'       => $payee,
        'reference'               => 'Outgoing test',
        'booked_at'               => now()->subDay(),
    ], $extra));

    $toSupplier = $line('o1', 'TRANSFER', 'ZEBRA  ŻÓŁW candles, Ltd.');
    $toOurs     = $line('o2', 'TRANSFER', strtoupper($this->organisation->name));
    $unknown    = $line('o3', 'TRANSFER', 'Quokka Freight s.r.o.', -40);
    $unknownToo = $line('o4', 'TRANSFER', 'quokka freight S.R.O', -60);
    $card       = $line('o5', 'CARD_PAYMENT', 'Paypal *Quokkaprints');
    $fee        = $line('o6', 'BALANCE_ASSET_FEE', 'Assets fee');
    $conversion = $line('o7', 'INTERBALANCE', 'To GBP', -500);
    $cardCheck  = $line('o8', 'CARD_CHECK', 'Some shop', 0);
    $incoming   = $line('o9', 'TRANSFER', 'Quokka Freight s.r.o.', 25, ['direction' => BankTransactionDirectionEnum::IN]);

    expect(\App\Models\Accounting\BankPayee::normalise('  AW  Aromatics, LIMITED. '))->toBe('aw aromatics ltd')
        ->and(\App\Models\Accounting\BankPayee::normalise('Żółw-Świat'))->toBe('żółw świat')
        ->and(\App\Models\Accounting\BankPayee::normalise(' .. '))->toBeNull();

    $this->artisan('bank-transactions:classify-outgoing')->assertExitCode(0);

    $category = fn (BankTransaction $bankTransaction) => $bankTransaction->refresh()->out_category;
    expect($category($toSupplier))->toBe('suppliers')
        ->and($category($toOurs))->toBe('own_companies')
        ->and($category($unknown))->toBe('needs_person')
        ->and($unknown->payee_key)->toBe($unknownToo->refresh()->payee_key)
        ->and($category($card))->toBe('services')
        ->and($category($fee))->toBe('bank_fees')
        ->and($category($conversion))->toBe('other')
        ->and($category($incoming))->toBeNull()
        ->and($incoming->match_state)->toBeNull();
    $guessed = \App\Models\Accounting\BankPayee::where('group_id', $group->id)->where('key', $toSupplier->payee_key)->firstOrFail();
    expect($guessed->source)->toBe('auto')
        ->and($guessed->model_type)->toBe('Supplier')
        ->and($guessed->model_id)->toBe($supplier->id);

    $admin = createAdminGuest($group)->getUser();
    actingAs($admin);
    Config::set('inertia.pages.paths', [resource_path('js/Pages/Grp')]);
    $props = fn (string $section, array $query = []) => get(route("grp.org.accounting.bank_outgoing.$section", [$this->organisation->slug, 'filter' => ['global' => 'Outgoing test'], ...$query]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Org/Accounting/BankOutgoing')->where('section', $section)->where('canEdit', true))
        ->viewData('page')['props'];

    $queue = collect($props('needs_person')['data']['data']);
    expect($queue->pluck('payee_key')->all())->toBe([$unknown->payee_key])
        ->and($queue->first()['lines'])->toBe(2)
        ->and($queue->first()['org_amount'])->toEqual(100)
        ->and(collect($props('suppliers')['data']['data'])->pluck('id')->all())->toBe([$toSupplier->id])
        ->and(collect($props('suppliers')['data']['data'])->first()['amount'])->toEqual(10)
        ->and(collect($props('other')['data']['data'])->pluck('id')->all())->toBe([$conversion->id])
        ->and(collect($props('services')['data']['data'])->pluck('id')->all())->toBe([$card->id])
        ->and(collect($props('bank_fees')['data']['data'])->pluck('id')->all())->toBe([$fee->id])
        ->and(collect(['own_companies', 'staff', 'tax'])->every(fn (string $section) => is_array($props($section)['data']['data'])))->toBeTrue()
        ->and(BankTransaction::find($cardCheck->id)->out_category)->toBe('other');

    $dashboard = get(route('grp.org.accounting.bank_outgoing.index', [$this->organisation->slug]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Org/Accounting/BankIncomingDashboard')->where('direction', 'out')->where('routeBase', 'grp.org.accounting.bank_outgoing.'))
        ->viewData('page')['props']['dashboard'];
    $paid = BankTransaction::where('organisation_id', $this->organisation->id)
        ->where('direction', BankTransactionDirectionEnum::OUT)
        ->where('status', BankTransactionStatusEnum::BOOKED)
        ->where('amount', '<', 0)
        ->where('provider_type', '!=', 'INTERBALANCE')
        ->where('booked_at', '>=', now()->subDays(90));
    expect($dashboard['total']['lines'])->toBe($paid->count())
        ->and($dashboard['total']['amount'])->toBeGreaterThanOrEqual(130)
        ->and(collect($dashboard['sections'])->sum('amount'))->toEqualWithDelta($dashboard['total']['amount'], 0.01)
        ->and($dashboard['own']['lines'])->toBeGreaterThanOrEqual(1)
        ->and(collect($dashboard['sections'])->pluck('key')->all())->toBe(\App\Actions\Accounting\BankTransaction\UI\IndexBankOutgoing::SECTIONS)
        ->and(collect($dashboard['backlog']['reasons'])->pluck('lines')->sum())->toBeGreaterThanOrEqual(2);

    post(route('grp.models.org.bank_payee.set', [$this->organisation->id]), ['payee_key' => 'never paid by anyone', 'category' => 'tax'])->assertSessionHasErrors('payee_key');
    post(route('grp.models.org.bank_payee.set', [$this->organisation->id]), ['payee_key' => $unknown->payee_key, 'category' => 'staff', 'model_type' => 'Supplier', 'model_id' => $supplier->id])->assertSessionHasErrors('model_id');
    post(route('grp.models.org.bank_payee.set', [$this->organisation->id]), ['payee_key' => $unknown->payee_key, 'category' => 'suppliers', 'model_type' => 'Supplier', 'model_id' => $supplier->id])->assertSessionHasNoErrors();
    expect($category($unknown))->toBe('suppliers')
        ->and($category($unknownToo))->toBe('suppliers');

    $later = $line('o10', 'TRANSFER', 'QUOKKA FREIGHT S.R.O.');
    $this->artisan('bank-transactions:classify-outgoing')->assertExitCode(0);
    expect($category($later))->toBe('suppliers');

    post(route('grp.models.org.bank_payee.set', [$this->organisation->id]), ['payee_key' => $card->refresh()->payee_key, 'category' => 'tax'])->assertSessionHasNoErrors();
    post(route('grp.models.org.bank_payee.set', [$this->organisation->id]), ['payee_key' => $toSupplier->payee_key, 'category' => 'other'])->assertSessionHasNoErrors();
    $this->artisan('bank-transactions:classify-outgoing')->assertExitCode(0);
    expect($category($card))->toBe('tax')
        ->and($category($toSupplier))->toBe('other')
        ->and(\App\Models\Accounting\BankPayee::where('group_id', $group->id)->where('key', $toSupplier->payee_key)->value('source'))->toBe('person')
        ->and(collect(get(route('grp.json.bank_payee.link_options', [$this->organisation->id]))->assertOk()->json())->where('type', 'Supplier')->pluck('id')->all())->toContain($supplier->id);

    setPermissionsTeamId($group->id);
    $viewer = \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($group, array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => []]))->getUser();
    actingAs($viewer);
    get(route('grp.org.accounting.bank_outgoing.index', [$this->organisation->slug]))->assertForbidden();
    $viewer->givePermissionTo("accounting.{$this->organisation->id}.view");
    $viewer->refresh();
    actingAs($viewer);
    get(route('grp.org.accounting.bank_outgoing.suppliers', [$this->organisation->slug]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('canEdit', false));
    post(route('grp.models.org.bank_payee.set', [$this->organisation->id]), ['payee_key' => $unknown->payee_key, 'category' => 'tax'])->assertForbidden();
    get(route('grp.json.bank_payee.link_options', [$this->organisation->id]))->assertForbidden();
    get(route('grp.financials.bank_outgoing.index'))->assertForbidden();
    expect($category($unknown))->toBe('suppliers');
});

test('group financials shows bank outgoing of the organisations the user may see, in the group currency, read only', function () {
    $group                 = $this->organisation->group;
    $otherOrganisationData = Organisation::factory()->definition();
    data_set($otherOrganisationData, 'code', 'out'.random_int(1000, 9999));
    data_set($otherOrganisationData, 'type', OrganisationTypeEnum::SHOP);
    $otherOrganisation = StoreOrganisation::make()->action($group, $otherOrganisationData);

    $line = fn (Organisation $organisation, string $id) => BankTransaction::create([
        'group_id'                => $group->id,
        'organisation_id'         => $organisation->id,
        'bank_connection_id'      => bankMatchingConnection($organisation)->id,
        'provider_transaction_id' => $id,
        'provider_type'           => 'TRANSFER',
        'direction'               => BankTransactionDirectionEnum::OUT,
        'amount'                  => -10,
        'currency_id'             => $group->currency_id,
        'status'                  => BankTransactionStatusEnum::BOOKED,
        'counterparty_name'       => 'Numbat Group Payee',
        'reference'               => 'Group outgoing test',
        'booked_at'               => now()->subDay(),
    ]);
    $mine   = $line($this->organisation, 'go1');
    $theirs = $line($otherOrganisation, 'go2');
    $this->artisan('bank-transactions:classify-outgoing')->assertExitCode(0);

    $admin = createAdminGuest($group)->getUser();
    $admin->givePermissionTo('financials');
    $admin->refresh();
    actingAs($admin);
    Config::set('inertia.pages.paths', [resource_path('js/Pages/Grp')]);

    $queue = fn (array $query = []) => collect(get(route('grp.financials.bank_outgoing.needs_person', ['filter' => ['global' => 'Group outgoing test'], ...$query]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Org/Accounting/BankOutgoing')
            ->where('canEdit', false)
            ->where('organisationId', null)
            ->where('orgCurrency', $group->currency->code)
            ->where('pageHead.subNavigation.0.route.name', 'grp.financials.bank_outgoing.index'))
        ->viewData('page')['props']['data']['data']);
    expect($queue()->count())->toBe(1)
        ->and($queue()->first()['lines'])->toBe(2)
        ->and($queue(['org' => $otherOrganisation->slug])->first()['lines'])->toBe(1);

    foreach (\App\Actions\Accounting\BankTransaction\UI\IndexBankOutgoing::SECTIONS as $section) {
        get(route("grp.financials.bank_outgoing.$section"))->assertOk();
    }
    $dashboard = get(route('grp.financials.bank_outgoing.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('direction', 'out')->where('orgCurrency', $group->currency->code))
        ->viewData('page')['props']['dashboard'];
    expect(collect($dashboard['organisations'])->pluck('label')->all())->toContain($this->organisation->name, $otherOrganisation->name);

    setPermissionsTeamId($group->id);
    $restricted = \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($group, array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => []]))->getUser();
    $restricted->givePermissionTo(["accounting.{$otherOrganisation->id}.view", "accounting.{$otherOrganisation->id}.edit"]);
    $restricted->refresh();
    actingAs($restricted);
    get(route('grp.financials.bank_outgoing.needs_person'))->assertForbidden();
    post(route('grp.models.org.bank_payee.set', [$this->organisation->id]), ['payee_key' => $mine->refresh()->payee_key, 'category' => 'tax'])->assertForbidden();

    $restricted->givePermissionTo('financials');
    $restricted->refresh();
    actingAs($restricted);
    expect($queue()->first()['lines'])->toBe(1)
        ->and($queue()->first()['organisation_code'])->toBe($otherOrganisation->code);
    $dashboard = get(route('grp.financials.bank_outgoing.index'))->assertOk()->viewData('page')['props']['dashboard'];
    expect(collect($dashboard['organisations'])->pluck('label')->all())->toBe([$otherOrganisation->name])
        ->and($theirs->refresh()->out_category)->toBe('needs_person');
});

test('each wise sync keeps the balances as one snapshot a day per currency, and balances that cannot be read never fail the sync', function () {
    Http::fake([
        'api.wise.com/v2/profiles'                 => Http::response([['id' => 555, 'type' => 'BUSINESS']]),
        'api.wise.com/v1/profiles/555/activities*' => Http::response(['cursor' => null, 'activities' => []]),
    ]);
    $balance = fn (string $currency, float $amount, float $reserved = 0) => [
        'id'             => random_int(1, 999999),
        'currency'       => $currency,
        'type'           => 'STANDARD',
        'amount'         => ['value' => $amount, 'currency' => $currency],
        'reservedAmount' => ['value' => $reserved, 'currency' => $currency],
    ];
    $bankConnection = StoreBankConnection::run($this->organisation, [
        'provider'    => BankConnectionProviderEnum::WISE,
        'name'        => 'Wise cash',
        'credentials' => 'cash-token',
    ]);
    $snapshot = fn () => \App\Models\Accounting\BankBalance::where('bank_connection_id', $bankConnection->id)->get()->keyBy(fn ($row) => $row->currency->code);

    $this->wiseBalances = [$balance('EUR', 100.5, 5), $balance('PLN', 0), $balance('PLN', 250), $balance('GBP', 10)];
    expect(SyncBankConnection::run($bankConnection, true))->toBe(['synced' => 0, 'skipped' => 0, 'skipped_examples' => []])
        ->and($snapshot()->keys()->sort()->values()->all())->toBe(['EUR', 'GBP', 'PLN'])
        ->and((float) $snapshot()['PLN']->amount)->toBe(250.0)
        ->and((float) $snapshot()['EUR']->amount)->toBe(100.5)
        ->and((float) $snapshot()['EUR']->reserved_amount)->toBe(5.0)
        ->and($snapshot()['EUR']->date->toDateString())->toBe(now()->toDateString())
        ->and($snapshot()['EUR']->organisation_id)->toBe($this->organisation->id);
    Http::assertSent(fn ($request) => str_contains($request->url(), '/v4/profiles/555/balances') && $request->hasHeader('Authorization', 'Bearer cash-token'));

    $this->wiseBalances = [$balance('EUR', 200), $balance('PLN', 250)];
    $this->artisan('bank-connections:balances', ['bankConnection' => $bankConnection->id])->assertSuccessful();
    expect($snapshot()->keys()->sort()->values()->all())->toBe(['EUR', 'PLN'])
        ->and((float) $snapshot()['EUR']->amount)->toBe(200.0)
        ->and((float) $snapshot()['EUR']->reserved_amount)->toBe(0.0);

    $this->wiseBalancesStatus = 401;
    expect(SyncBankConnection::run($bankConnection->refresh()))->toBe(['synced' => 0, 'skipped' => 0, 'skipped_examples' => []])
        ->and($bankConnection->refresh()->state)->toBe(BankConnectionStateEnum::ACTIVE)
        ->and($bankConnection->last_error)->toBeNull()
        ->and($bankConnection->sync_data['balances_error'])->not->toBeEmpty()
        ->and($bankConnection->sync_data['last_result']['synced'])->toBe(0)
        ->and((float) $snapshot()['EUR']->amount)->toBe(200.0);

    $this->wiseBalancesStatus = 200;
    $this->wiseBalances       = [$balance('EUR', 300), $balance('XYZ', 1)];
    $this->artisan('bank-connections:balances', ['bankConnection' => $bankConnection->id])->assertFailed();
    expect($bankConnection->refresh()->sync_data['balances_error'])->toContain('XYZ')
        ->and((float) $snapshot()['EUR']->amount)->toBe(200.0);

    $this->wiseBalances = [$balance('EUR', 300)];
    $this->artisan('bank-connections:balances', ['bankConnection' => $bankConnection->id])->assertSuccessful();
    expect($bankConnection->refresh()->sync_data)->not->toHaveKey('balances_error')
        ->and($snapshot()->keys()->all())->toBe(['EUR']);
});

test('cash shows what the connected banks hold: the group in its currency for the organisations the user may see, an organisation in its own, stale and failed balances flagged', function () {
    $group                 = $this->organisation->group;
    $otherOrganisationData = Organisation::factory()->definition();
    data_set($otherOrganisationData, 'code', 'csh'.random_int(1000, 9999));
    data_set($otherOrganisationData, 'type', OrganisationTypeEnum::SHOP);
    $otherOrganisation = StoreOrganisation::make()->action($group, $otherOrganisationData);

    $pivot    = config('app.currency_exchange.pivot');
    $foreign  = Currency::whereNotIn('code', [$group->currency->code, $pivot])->whereIn('code', ['PLN', 'HUF', 'EUR'])->firstOrFail();
    $unrated  = Currency::whereNotIn('id', CurrencyExchange::select('currency_id'))->whereNotIn('id', [$group->currency_id, $foreign->id])->where('code', '!=', $pivot)->firstOrFail();
    $today    = now()->toDateString();
    $groupRate = 1;
    if ($group->currency->code !== $pivot) {
        $groupRate = 2;
        CurrencyExchange::updateOrCreate(['currency_id' => $group->currency_id, 'date' => $today], ['exchange' => 2, 'source' => 'test']);
    }
    CurrencyExchange::updateOrCreate(['currency_id' => $foreign->id, 'date' => $today], ['exchange' => 4, 'source' => 'test']);

    $mine   = bankMatchingConnection($this->organisation);
    $theirs = bankMatchingConnection($otherOrganisation);
    $held   = fn (BankConnection $bankConnection, int $currencyId, float $amount, \Illuminate\Support\Carbon $takenAt, float $reserved = 0) => \App\Models\Accounting\BankBalance::factory()->of($bankConnection)->create([
        'currency_id'     => $currencyId,
        'amount'          => $amount,
        'reserved_amount' => $reserved,
        'date'            => $takenAt->toDateString(),
        'taken_at'        => $takenAt,
    ]);
    $held($mine, $group->currency_id, 70, now()->subDays(8));
    $held($mine, $group->currency_id, 100, now()->subMinutes(5), 10);
    $held($mine, $foreign->id, 80, now()->subMinutes(5));
    $held($mine, $unrated->id, 999, now()->subMinutes(5));
    $held($theirs, $group->currency_id, 40, now()->subHours(\App\Actions\Accounting\BankBalance\UI\ShowCash::STALE_HOURS + 1));

    $admin = createAdminGuest($group)->getUser();
    setPermissionsTeamId($group->id);
    $admin->givePermissionTo('financials');
    $admin->refresh();
    actingAs($admin);
    Config::set('inertia.pages.paths', [resource_path('js/Pages/Grp')]);

    $cash = fn (array $query = []) => get(route('grp.financials.cash.index', $query))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Org/Accounting/Cash')->where('currency', $group->currency->code)->where('isGroup', true))
        ->viewData('page')['props']['cash'];

    $groupCash   = $cash();
    $connections = collect($groupCash['connections'])->keyBy('id');
    $foreignHeld = round(80 * $groupRate / 4, 2);
    expect($connections)->toHaveKeys([$mine->id, $theirs->id])
        ->and($connections[$mine->id]['status'])->toBe('ok')
        ->and($connections[$mine->id]['amount'])->toEqual(100 + $foreignHeld)
        ->and($connections[$mine->id]['reserved'])->toEqual(10)
        ->and(collect($connections[$mine->id]['balances'])->firstWhere('currency', $foreign->code)['converted'])->toEqual($foreignHeld)
        ->and(collect($connections[$mine->id]['balances'])->firstWhere('currency', $unrated->code)['converted'])->toBeNull()
        ->and($connections[$theirs->id]['status'])->toBe('stale')
        ->and($connections[$theirs->id]['amount'])->toEqual(40)
        ->and($groupCash['total']['amount'])->toEqual(round($connections->sum('amount'), 2))
        ->and($groupCash['total']['amount'])->toEqual(end($groupCash['trend']['totals']))
        ->and($groupCash['total']['stale_amount'])->toEqual(round($connections->where('status', '!=', 'ok')->sum('amount'), 2))
        ->and($groupCash['total']['ago_7'])->toEqual(70)
        ->and($groupCash['total']['ago_30'])->toBeNull()
        ->and($groupCash['trend']['dates'][0])->toBe(now()->subDays(8)->toDateString())
        ->and(collect($groupCash['unconverted'])->firstWhere('currency', $unrated->code)['amount'])->toEqual(999)
        ->and(collect($groupCash['currencies'])->pluck('key')->all())->toContain($foreign->code)->not->toContain($unrated->code)
        ->and(collect($groupCash['organisations'])->pluck('label')->all())->toContain($this->organisation->name, $otherOrganisation->name)
        ->and($groupCash['not_connected'])->toBe(\App\Actions\Accounting\BankBalance\UI\ShowCash::notConnected(collect([$this->organisation, $otherOrganisation])))
        ->and(\App\Actions\Accounting\BankBalance\UI\ShowCash::notConnected(collect([new Organisation(['slug' => 'sk']), new Organisation(['slug' => 'es'])])))->toBe(['Tatra banka', 'Santander', 'Sabadell'])
        ->and(collect($cash(['org' => $otherOrganisation->slug])['connections'])->pluck('id')->all())->toBe([$theirs->id])
        ->and(collect(\App\Actions\UI\Grp\Layout\GetGroupNavigation::make()->handle($admin)['financials']['topMenu']['subSections'])->pluck('route.name')->all())->toContain('grp.financials.cash.index');

    $theirs->update(['sync_data' => ['balances_error' => 'HTTP request returned status code 401', 'balances_error_at' => now()->toIso8601String()]]);
    expect(collect($cash()['connections'])->firstWhere('id', $theirs->id)['status'])->toBe('failed');

    $organisationCash = get(route('grp.org.accounting.cash.index', [$this->organisation->slug]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Org/Accounting/Cash')->where('currency', $this->organisation->currency->code)->where('isGroup', false))
        ->viewData('page')['props']['cash'];
    expect(collect($organisationCash['connections'])->pluck('id')->all())->toContain($mine->id)->not->toContain($theirs->id)
        ->and($organisationCash['organisations'])->toBe([]);

    setPermissionsTeamId($group->id);
    $restricted = \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($group, array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => []]))->getUser();
    actingAs($restricted);
    get(route('grp.financials.cash.index'))->assertForbidden();
    get(route('grp.org.accounting.cash.index', [$otherOrganisation->slug]))->assertForbidden();

    $restricted->givePermissionTo("accounting.{$otherOrganisation->id}.view");
    $restricted->refresh();
    actingAs($restricted);
    get(route('grp.financials.cash.index'))->assertForbidden();
    get(route('grp.org.accounting.cash.index', [$otherOrganisation->slug]))->assertOk();

    $restricted->givePermissionTo('financials');
    $restricted->refresh();
    actingAs($restricted);
    expect(collect($cash()['connections'])->pluck('id')->all())->toBe([$theirs->id]);
});

test('bank outgoing: a transfer naming one of our orders is a customer refund, our companies are left out of group totals, employees are offered only to those who edit their accounting', function () {
    $group          = $this->organisation->group;
    $bankConnection = bankMatchingConnection($this->organisation);
    $order          = bankMatchingOrder(createCustomer($this->shop), 50);
    $line           = fn (string $id, string $payee, ?string $reference, float $amount = -10, ?float $fee = null) => BankTransaction::create([
        'group_id'                => $group->id,
        'organisation_id'         => $this->organisation->id,
        'bank_connection_id'      => $bankConnection->id,
        'provider_transaction_id' => $id,
        'provider_type'           => 'TRANSFER',
        'direction'               => BankTransactionDirectionEnum::OUT,
        'amount'                  => $amount,
        'fee_amount'              => $fee,
        'currency_id'             => $group->currency_id,
        'status'                  => BankTransactionStatusEnum::BOOKED,
        'counterparty_name'       => $payee,
        'reference'               => $reference,
        'booked_at'               => now()->subDay(),
    ]);

    $spaced     = preg_replace('/^([A-Za-z]+)(\d+)$/', '$1 $2', $order->reference);
    $refund     = $line('r1', 'Wombat Refund Customer', "refund for order $spaced");
    $toOurs     = $line('r2', $this->organisation->name.' ', "invoices, also $order->reference", -30);
    $nameOnly   = $line('r3', 'Wombat Refund Customer', 'refund, sorry');
    $ownCompany = $line('r4', $this->organisation->name, 'Wise Transfer', -70, 0.7);
    $limited    = $line('r5', 'Wombat Panels Limited', 'Invoice 1', -20);
    $ltd        = $line('r6', 'WOMBAT PANELS LTD', 'Invoice 2', -20);

    $this->artisan('bank-transactions:classify-outgoing')->assertExitCode(0);

    expect($refund->refresh()->out_category)->toBe('customer_refunds')
        ->and($refund->order_id)->toBe($order->id)
        ->and($toOurs->refresh()->out_category)->toBe('customer_refunds')
        ->and($nameOnly->refresh()->out_category)->toBe('needs_person')
        ->and($nameOnly->order_id)->toBeNull()
        ->and($ownCompany->refresh()->out_category)->toBe('own_companies')
        ->and(\App\Models\Accounting\BankPayee::where('group_id', $group->id)->where('key', $refund->payee_key)->exists())->toBeFalse();

    $admin = createAdminGuest($group)->getUser();
    $admin->givePermissionTo('financials');
    $admin->refresh();
    actingAs($admin);
    Config::set('inertia.pages.paths', [resource_path('js/Pages/Grp')]);

    $refunds = collect(get(route('grp.org.accounting.bank_outgoing.customer_refunds', [$this->organisation->slug, 'filter' => ['global' => $order->reference]]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('queryBuilderProps.default.columns', fn ($columns) => collect($columns)->pluck('key')->contains('order_reference')))
        ->viewData('page')['props']['data']['data']);
    expect($refunds->pluck('id')->all())->toContain($toOurs->id)
        ->and($refunds->firstWhere('id', $toOurs->id)['order_reference'])->toBe($order->reference)
        ->and($refunds->firstWhere('id', $toOurs->id)['order_slug'])->toBe($order->slug);

    $own = fn (array $dashboard) => collect($dashboard['sections'])->firstWhere('key', 'own_companies');
    $inOrganisation = get(route('grp.org.accounting.bank_outgoing.index', [$this->organisation->slug]))->assertOk()->viewData('page')['props'];
    $inGroup        = get(route('grp.financials.bank_outgoing.index', ['org' => $this->organisation->slug]))->assertOk()->viewData('page')['props'];
    expect($own($inOrganisation['dashboard'])['lines'])->toBeGreaterThanOrEqual(1)
        ->and($own($inGroup['dashboard'])['lines'])->toBe(0)
        ->and($inGroup['ownCompaniesApart'])->toBeTrue()
        ->and($inGroup['dashboard']['own']['by_section']['own_companies'])->toBe($own($inOrganisation['dashboard'])['lines'])
        ->and($inGroup['dashboard']['total']['lines'])->toBe($inOrganisation['dashboard']['total']['lines'] - $own($inOrganisation['dashboard'])['lines'])
        ->and(collect($inGroup['pageHead']['subNavigation'])->firstWhere('route.name', 'grp.financials.bank_outgoing.own_companies')['number'])->toBe($own($inOrganisation['dashboard'])['lines'])
        ->and($inOrganisation['dashboard']['transfer_fees'])->toBeGreaterThanOrEqual(0.7)
        ->and(collect($inOrganisation['dashboard']['backlog']['reasons'])->where('key', $limited->refresh()->payee_key)->pluck('lines')->all())->toBe([2])
        ->and($ltd->refresh()->payee_key)->toBe($limited->payee_key);

    $otherOrganisationData = Organisation::factory()->definition();
    data_set($otherOrganisationData, 'code', 'emp'.random_int(1000, 9999));
    data_set($otherOrganisationData, 'type', OrganisationTypeEnum::SHOP);
    $otherOrganisation = StoreOrganisation::make()->action($group, $otherOrganisationData);
    $employeeOf        = fn (Organisation $organisation, string $name) => \App\Actions\HumanResources\Employee\StoreEmployee::make()->action(
        $organisation,
        array_merge(\App\Models\HumanResources\Employee::factory()->definition(), ['contact_name' => $name])
    );
    $mine   = $employeeOf($this->organisation, 'Wombat Mine Employee');
    $theirs = $employeeOf($otherOrganisation, 'Wombat Their Employee');

    setPermissionsTeamId($group->id);
    $editor = \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($group, array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => []]))->getUser();
    $editor->givePermissionTo(["accounting.{$this->organisation->id}.view", "accounting.{$this->organisation->id}.edit"]);
    $editor->refresh();
    actingAs($editor);
    $employees = collect(get(route('grp.json.bank_payee.link_options', [$this->organisation->id]))->assertOk()->json())->where('type', 'Employee')->pluck('id');
    expect($employees->all())->toContain($mine->id)->not->toContain($theirs->id);
    post(route('grp.models.org.bank_payee.set', [$this->organisation->id]), ['payee_key' => $nameOnly->payee_key, 'category' => 'staff', 'model_type' => 'Employee', 'model_id' => $theirs->id])->assertSessionHasErrors('model_id');
    post(route('grp.models.org.bank_payee.set', [$this->organisation->id]), ['payee_key' => $nameOnly->payee_key, 'category' => 'customer_refunds'])->assertSessionHasNoErrors();
    expect($nameOnly->refresh()->out_category)->toBe('customer_refunds');
});

function fioMovement(int $id, float $amount, ?string $counterAccount, ?string $message, string $currency = 'CZK'): array
{
    return [
        'column22' => ['value' => $id, 'name' => 'ID pohybu', 'id' => 22],
        'column0'  => ['value' => '2026-11-12+0100', 'name' => 'Datum', 'id' => 0],
        'column1'  => ['value' => $amount, 'name' => 'Objem', 'id' => 1],
        'column14' => ['value' => $currency, 'name' => 'Měna', 'id' => 14],
        'column2'  => $counterAccount ? ['value' => $counterAccount, 'name' => 'Protiúčet', 'id' => 2] : null,
        'column3'  => $counterAccount ? ['value' => '0800', 'name' => 'Kód banky', 'id' => 3] : null,
        'column10' => $counterAccount ? ['value' => 'Jan Novák', 'name' => 'Název protiúčtu', 'id' => 10] : null,
        'column16' => $message ? ['value' => $message, 'name' => 'Zpráva pro příjemce', 'id' => 16] : null,
        'column8'  => ['value' => $counterAccount ? 'Bezhotovostní příjem' : 'Poplatek', 'name' => 'Typ', 'id' => 8],
    ];
}

test('a fio account statement is mirrored once, transfers carry the message as reference and the closing balance is kept', function () {
    $statement = fn (array $movements) => ['accountStatement' => [
        'info'            => ['accountId' => '2900123456', 'currency' => 'CZK', 'closingBalance' => 15234.5],
        'transactionList' => ['transaction' => $movements],
    ]];
    Http::fake([
        'fioapi.fio.cz/*' => Http::sequence()
            ->push($statement([fioMovement(1001, 2450.0, '123456789', 'CZ10234'), fioMovement(1002, -35.0, null, null)]))
            ->push($statement([fioMovement(1002, -35.0, null, null), fioMovement(1003, 99.0, '987654321', null, 'XYZ')])),
    ]);

    $bankConnection = StoreBankConnection::run($this->organisation, [
        'provider'    => BankConnectionProviderEnum::FIO,
        'name'        => 'Fio CZK',
        'credentials' => 'fio-test-token',
    ]);

    expect(SyncBankConnection::run($bankConnection, true))->toBe(['synced' => 2, 'skipped' => 0, 'skipped_examples' => []]);

    $lines = BankTransaction::where('bank_connection_id', $bankConnection->id)->get()->keyBy('provider_transaction_id');
    expect($bankConnection->refresh()->external_id)->toBe('2900123456')
        ->and($lines)->toHaveCount(2)
        ->and($lines['1001']->provider_type)->toBe('TRANSFER')
        ->and($lines['1001']->direction)->toBe(BankTransactionDirectionEnum::IN)
        ->and((float)$lines['1001']->amount)->toBe(2450.0)
        ->and($lines['1001']->currency->code)->toBe('CZK')
        ->and($lines['1001']->reference)->toBe('CZ10234')
        ->and($lines['1001']->counterparty_name)->toBe('Jan Novák')
        ->and($lines['1001']->counterparty_account)->toBe('123456789/0800')
        ->and($lines['1002']->counterparty_account)->toBeNull()
        ->and($lines['1001']->booked_at->toDateString())->toBe('2026-11-12')
        ->and($lines['1002']->provider_type)->toBe('OTHER')
        ->and((float)$lines['1002']->amount)->toBe(-35.0)
        ->and((float)\App\Models\Accounting\BankBalance::where('bank_connection_id', $bankConnection->id)->value('amount'))->toBe(15234.5);

    expect(SyncBankConnection::run($bankConnection->refresh()))->toBe(['synced' => 0, 'skipped' => 1, 'skipped_examples' => ['1003: unknown currency: XYZ']])
        ->and(BankTransaction::where('bank_connection_id', $bankConnection->id)->count())->toBe(2);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'fioapi.fio.cz/v1/rest/periods/fio-test-token/'));
    expect(\App\Helpers\SentryScrubber::redact('GET https://fioapi.fio.cz/v1/rest/periods/fio-test-token/2026-08-19/2026-11-16/transactions.json'))
        ->toBe('GET https://fioapi.fio.cz/v1/rest/periods/***/2026-08-19/2026-11-16/transactions.json');
});

test('a fio payment whose variable symbol is the order number without its prefix is matched to that order', function () {
    $order          = bankMatchingOrder(createCustomer($this->shop), 4697.25);
    $digits         = preg_replace('/^[A-Z]+/', '', $order->reference);
    $bankConnection = bankMatchingConnection($this->organisation);

    $line = fn (string $id, ?string $variableSymbol, ?int $currencyId = null) => BankTransaction::create([
        'group_id'                => $bankConnection->group_id,
        'organisation_id'         => $bankConnection->organisation_id,
        'bank_connection_id'      => $bankConnection->id,
        'provider_transaction_id' => $id,
        'provider_type'           => 'TRANSFER',
        'direction'               => BankTransactionDirectionEnum::IN,
        'amount'                  => 4697.25,
        'currency_id'             => $currencyId ?? $order->currency_id,
        'status'                  => BankTransactionStatusEnum::BOOKED,
        'reference'               => '06585700897356',
        'booked_at'               => now(),
        'data'                    => ['movement' => ['column5' => ['value' => $variableSymbol]]],
    ]);

    $matched       = $line('vs1', $digits);
    $otherCurrency = $line('vs2', $digits, Currency::where('id', '!=', $order->currency_id)->value('id'));
    $noSymbol      = $line('vs3', null);

    MatchBankTransactions::run($bankConnection);

    expect($matched->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::MATCHED)
        ->and($matched->order_id)->toBe($order->id)
        ->and($otherCurrency->refresh()->order_id)->toBeNull()
        ->and($noSymbol->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::UNMATCHED);
});

function tatraTransaction(string $id, float $amount, ?string $debtorIban, ?string $creditorIban, ?string $message, ?string $variableSymbol = null, string $currency = 'EUR'): array
{
    return array_filter([
        'transactionId'                     => $id,
        'bookingDate'                       => '2026-11-12',
        'valueDate'                         => '2026-11-12',
        'transactionAmount'                 => ['currency' => $currency, 'amount' => $amount],
        'debtorName'                        => $debtorIban ? 'Ján Kováč' : null,
        'debtorAccount'                     => $debtorIban ? ['iban' => $debtorIban] : null,
        'creditorName'                      => $creditorIban ? 'Supplier s.r.o.' : null,
        'creditorAccount'                   => $creditorIban ? ['iban' => $creditorIban] : null,
        'remittanceInformationUnstructured' => $message,
        'variableSymbol'                    => $variableSymbol,
        'transactionState'                  => 'BOOKED',
    ]);
}

test('a tatra account is mirrored once through business banking consent, direction from the sign or from our IBAN, every page read', function () {
    $ours     = 'SK3111000000002612345678';
    $accounts = ['accounts' => [[
        'accountId'             => 'tatra-acc-1',
        'accountReference'      => ['iban' => $ours, 'currency' => 'EUR'],
        'consentExpirationDate' => '2027-05-16T10:00:00+02:00',
        'balances'              => [
            ['balanceAmount' => ['currency' => 'EUR', 'amount' => 999.0], 'balanceType' => 'interimAvailable'],
            ['balanceAmount' => ['currency' => 'EUR', 'amount' => 8000.0], 'balanceType' => 'closingBooked'],
            ['balanceAmount' => ['currency' => 'EUR', 'amount' => 8123.4], 'balanceType' => 'interimBooked'],
        ],
    ]]];
    Http::fake([
        'api.tatrabanka.sk/premium/production/auth/oauth/v2/token' => Http::response(['access_token' => 'tatra-token', 'token_type' => 'Bearer', 'expires_in' => 86400]),
        'api.tatrabanka.sk/premium/production/v3/accounts*'        => Http::response($accounts),
        'api.tatrabanka.sk/premium/production/v5/accounts/*'       => Http::sequence()
            ->push([
                'transactions' => [
                    tatraTransaction('t1', 120.5, 'SK8975000000000012345671', $ours, 'Objednavka SK10234'),
                    tatraTransaction('t2', -45.0, $ours, 'CZ6508000000192000145399', 'Invoice 77'),
                ],
                '_links' => ['next' => ['href' => 'https://api.tatrabanka.sk/premium/production/v5/accounts/tatra-acc-1/transactions?dateFrom=2025-08-24&bookingStatus=booked&entryReferenceFrom=abc']],
            ])
            ->push(str_replace('LINEBREAK', "\n", json_encode([
                'transactions' => [
                    tatraTransaction('t8', 15.0, 'SK8975000000000012345671', $ours, "Objednavka LINEBREAKSK10235"),
                    tatraTransaction('t3', 60.0, $ours, 'DE89370400440532013000', null),
                    tatraTransaction('t4', 10.0, 'AT611904300234573201', 'AT483200000012345864', null),
                    array_merge(tatraTransaction('t5', 75.0, 'SK8975000000000012345671', $ours, null), ['isReversal' => true]),
                    tatraTransaction('t6', 5.0, $ours, $ours, null),
                    array_merge(tatraTransaction('t7', 30.0, 'SK8975000000000012345671', $ours, null), ['transactionState' => 'REJECTED']),
                ],
            ], JSON_UNESCAPED_UNICODE)))
            ->push(['transactions' => [tatraTransaction('t1', 120.5, 'SK8975000000000012345671', $ours, 'Objednavka SK10234')]]),
    ]);

    $bankConnection = StoreBankConnection::run($this->organisation, [
        'provider'    => BankConnectionProviderEnum::TATRA,
        'name'        => 'Tatra EUR',
        'credentials' => 'tatra-client:tatra-secret',
    ]);

    expect(SyncBankConnection::run($bankConnection, true))->toBe(['synced' => 4, 'skipped' => 4, 'skipped_examples' => [
        't4: direction not understood',
        't5: reversal with an unsigned amount, direction not certain',
        't6: direction not understood',
        't7: not booked: REJECTED',
    ]]);

    $lines = BankTransaction::where('bank_connection_id', $bankConnection->id)->get()->keyBy('provider_transaction_id');
    expect($bankConnection->refresh()->external_id)->toBe('tatra-acc-1')
        ->and($bankConnection->sync_data['tatra_iban'])->toBe($ours)
        ->and($lines)->toHaveCount(4)
        ->and($lines['t8']->reference)->toBe('Objednavka  SK10235')
        ->and($lines['t1']->direction)->toBe(BankTransactionDirectionEnum::IN)
        ->and((float)$lines['t1']->amount)->toBe(120.5)
        ->and($lines['t1']->provider_type)->toBe('TRANSFER')
        ->and($lines['t1']->reference)->toBe('Objednavka SK10234')
        ->and($lines['t1']->counterparty_name)->toBe('Ján Kováč')
        ->and($lines['t1']->counterparty_account)->toBe('SK8975000000000012345671')
        ->and($lines['t1']->booked_at->toDateString())->toBe('2026-11-12')
        ->and($lines['t2']->direction)->toBe(BankTransactionDirectionEnum::OUT)
        ->and((float)$lines['t2']->amount)->toBe(-45.0)
        ->and($lines['t2']->counterparty_name)->toBe('Supplier s.r.o.')
        ->and($lines['t3']->direction)->toBe(BankTransactionDirectionEnum::OUT)
        ->and((float)$lines['t3']->amount)->toBe(-60.0)
        ->and((float)\App\Models\Accounting\BankBalance::where('bank_connection_id', $bankConnection->id)->value('amount'))->toBe(8123.4);

    expect(SyncBankConnection::run($bankConnection->refresh()))->toBe(['synced' => 0, 'skipped' => 0, 'skipped_examples' => []]);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/auth/oauth/v2/token')
        && $request['grant_type'] === 'client_credentials' && $request['scope'] === 'PREMIUM_AIS'
        && $request['client_id'] === 'tatra-client' && $request['client_secret'] === 'tatra-secret');
    Http::assertSent(fn ($request) => str_contains($request->url(), 'entryReferenceFrom=abc') && str_contains($request->url(), 'bookingStatus=booked'));
    Http::assertSent(fn ($request) => str_contains($request->url(), '/v5/accounts/tatra-acc-1/transactions?')
        && str_contains($request->url(), 'bookingStatus=booked')
        && $request->hasHeader('Authorization', 'Bearer tatra-token')
        && $request->hasHeader('X-Request-ID'));
});

test('a tatra connection whose consent reaches several accounts asks for the account instead of guessing', function () {
    Http::fake([
        'api.tatrabanka.sk/premium/production/auth/oauth/v2/token' => Http::response(['access_token' => 'tatra-token']),
        'api.tatrabanka.sk/premium/production/v3/accounts*'        => Http::response(['accounts' => [
            ['accountId' => 'eur-acc', 'accountReference' => ['iban' => 'SK1', 'currency' => 'EUR']],
            ['accountId' => 'usd-acc', 'accountReference' => ['iban' => 'SK2', 'currency' => 'USD']],
        ]]),
    ]);

    $bankConnection = StoreBankConnection::run($this->organisation, [
        'provider'    => BankConnectionProviderEnum::TATRA,
        'name'        => 'Tatra two accounts',
        'credentials' => 'tatra-client:tatra-secret',
    ]);

    expect(SyncBankConnection::run($bankConnection))->toBeNull()
        ->and($bankConnection->refresh()->state)->toBe(\App\Enums\Accounting\BankConnection\BankConnectionStateEnum::ERROR)
        ->and($bankConnection->last_error)->toContain('eur-acc SK1 EUR')->toContain('usd-acc SK2 USD');
});

test('a tatra sync fails loudly instead of losing lines when a full page comes without a next link or the next link leaves the api', function () {
    $accounts = ['accounts' => [['accountId' => 'tatra-full', 'accountReference' => ['iban' => 'SK3111000000002612345678', 'currency' => 'EUR']]]];
    $fullPage = array_map(fn (int $i) => tatraTransaction("f$i", -1.0, 'SK3111000000002612345678', 'SK8975000000000012345671', null), range(1, \App\Actions\Accounting\BankConnection\Tatra\SyncTatraBankConnection::PAGE_SIZE));
    Http::fake([
        'api.tatrabanka.sk/premium/production/auth/oauth/v2/token' => Http::response(['access_token' => 'tatra-token']),
        'api.tatrabanka.sk/premium/production/v3/accounts*'        => Http::response($accounts),
        'api.tatrabanka.sk/premium/production/v5/accounts/*'       => Http::sequence()
            ->push(['transactions' => $fullPage])
            ->push(['transactions' => [tatraTransaction('n1', -1.0, 'SK3111000000002612345678', 'SK8975000000000012345671', null)], '_links' => ['next' => ['href' => 'https://evil.example/v5/accounts/tatra-full/transactions?page=2']]]),
    ]);

    $bankConnection = StoreBankConnection::run($this->organisation, [
        'provider'    => BankConnectionProviderEnum::TATRA,
        'name'        => 'Tatra full page',
        'credentials' => 'tatra-client:tatra-secret',
    ]);

    expect(SyncBankConnection::run($bankConnection, true))->toBeNull()
        ->and($bankConnection->refresh()->last_error)->toContain('without a next link')
        ->and($bankConnection->last_synced_at)->toBeNull()
        ->and(SyncBankConnection::run($bankConnection, true))->toBeNull()
        ->and($bankConnection->refresh()->last_error)->toContain('leaves the API')
        ->and(BankTransaction::where('bank_connection_id', $bankConnection->id)->count())->toBe(0);
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'evil.example'));
});

test('a tatra payment whose variable symbol is the order number without its prefix is matched to that order, the one owing the amount when other shops have that number', function () {
    $order          = bankMatchingOrder(createCustomer($this->shop), 212.4);
    $bankConnection = bankMatchingConnection($this->organisation);
    bankMatchingOrder(createCustomer($this->shop), 71.46)->update(['reference' => 'AWS'.preg_replace('/^[A-Z]+/', '', $order->reference)]);

    $line = BankTransaction::create([
        'group_id'                => $bankConnection->group_id,
        'organisation_id'         => $bankConnection->organisation_id,
        'bank_connection_id'      => $bankConnection->id,
        'provider_transaction_id' => 'tatra-vs1',
        'provider_type'           => 'TRANSFER',
        'direction'               => BankTransactionDirectionEnum::IN,
        'amount'                  => 212.4,
        'currency_id'             => $order->currency_id,
        'status'                  => BankTransactionStatusEnum::BOOKED,
        'reference'               => 'Platba',
        'booked_at'               => now(),
        'data'                    => ['transaction' => ['variableSymbol' => preg_replace('/^[A-Z]+/', '', $order->reference)]],
    ]);

    MatchBankTransactions::run($bankConnection);

    expect($line->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::MATCHED)
        ->and($line->order_id)->toBe($order->id);
});

test('a payment quoting the invoice number instead of the order number is matched to the invoiced order', function () {
    $order   = bankMatchingOrder(createCustomer($this->shop), 839.59);
    $invoice = \App\Actions\Accounting\Invoice\StoreInvoice::make()->action($order->customer, \App\Models\Accounting\Invoice::factory()->definition());
    $invoiceReference = 'TSi'.random_int(1000000, 9999999);
    $invoice->updateQuietly(['reference' => $invoiceReference, 'order_id' => $order->id]);
    $bankConnection = bankMatchingConnection($this->organisation);

    $line = fn (string $id, string $reference) => BankTransaction::create([
        'group_id'                => $bankConnection->group_id,
        'organisation_id'         => $bankConnection->organisation_id,
        'bank_connection_id'      => $bankConnection->id,
        'provider_transaction_id' => $id,
        'provider_type'           => 'TRANSFER',
        'direction'               => BankTransactionDirectionEnum::IN,
        'amount'                  => 839.59,
        'currency_id'             => $order->currency_id,
        'status'                  => BankTransactionStatusEnum::BOOKED,
        'reference'               => $reference,
        'booked_at'               => now(),
    ]);

    $asWritten  = $line('inv1', $invoiceReference);
    $upperCased = $line('inv2', 'Invoice '.strtoupper($invoiceReference));
    $unknown    = $line('inv3', 'TSi'.random_int(1000000, 9999999).'0');

    MatchBankTransactions::run($bankConnection);

    expect($asWritten->refresh()->order_id)->toBe($order->id)
        ->and($asWritten->match_state)->toBe(BankTransactionMatchStateEnum::MATCHED)
        ->and($upperCased->refresh()->order_id)->toBe($order->id)
        ->and($upperCased->match_state)->toBe(BankTransactionMatchStateEnum::REVIEW)
        ->and($unknown->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::UNMATCHED);
});

test('auto-pay records a mapped payer, such as DPD CZ cash on delivery, on its own account and everyone else on the bank account', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1.0);

    $codOrder       = bankMatchingOrder(createCustomer($this->shop), 3354);
    $transferOrder  = bankMatchingOrder(createCustomer($this->shop), 500);
    $bankConnection = bankMatchingConnection($this->organisation);
    $bankAccount    = StoreOrgPaymentServiceProviderAccount::make()->action(
        $this->organisation,
        PaymentServiceProvider::where('code', 'bank')->firstOrFail(),
        ['code' => 'fio'.random_int(1000, 9999), 'name' => 'Fio test']
    );
    $codAccount = \App\Actions\Accounting\PaymentAccount\StorePaymentAccount::make()->action(
        \App\Actions\Accounting\OrgPaymentServiceProvider\StoreOrgPaymentServiceProvider::make()->action(
            paymentServiceProvider: PaymentServiceProvider::where('code', 'cond')->firstOrFail(),
            organisation: $this->organisation,
            modelData: ['code' => 'cod'.random_int(1000, 9999)]
        ),
        ['code' => 'cod'.random_int(1000, 9999), 'name' => 'COD CZK test', 'type' => PaymentAccountTypeEnum::CASH_ON_DELIVERY->value]
    );

    expect(fn () => SetBankConnectionAutoPay::run($bankConnection, [$codOrder->currency->code => $bankAccount->code], payerPaymentAccountCodes: ['Direct Parcel' => 'no-such-account']))
        ->toThrow(ValidationException::class);

    SetBankConnectionAutoPay::run($bankConnection->refresh(), [$codOrder->currency->code => $bankAccount->code], payerPaymentAccountCodes: ['Direct Parcel' => $codAccount->code]);
    $bankConnection->refresh();

    $line = fn (string $id, Order $order, string $payer) => BankTransaction::create([
        'group_id'                => $bankConnection->group_id,
        'organisation_id'         => $bankConnection->organisation_id,
        'bank_connection_id'      => $bankConnection->id,
        'provider_transaction_id' => $id,
        'provider_type'           => 'TRANSFER',
        'direction'               => BankTransactionDirectionEnum::IN,
        'amount'                  => (float)$order->total_amount,
        'currency_id'             => $order->currency_id,
        'status'                  => BankTransactionStatusEnum::BOOKED,
        'counterparty_name'       => $payer,
        'reference'               => $order->reference,
        'booked_at'               => now()->addMinute(),
    ]);

    $codLine      = $line('dpd1', $codOrder, 'Direct Parcel Distri');
    $transferLine = $line('cus1', $transferOrder, 'NATURAL CENTRUM S.R.');

    MatchBankTransactions::run($bankConnection);
    expect(PayMatchedBankTransactions::run($bankConnection))->toBe(['paid' => 2])
        ->and($codLine->refresh()->payment->payment_account_id)->toBe($codAccount->id)
        ->and($transferLine->refresh()->payment->payment_account_id)->toBe($bankAccount->id)
        ->and($codOrder->refresh()->pay_status)->toBe(OrderPayStatusEnum::PAID);
});

test('a line naming no order suggests orders of a customer named like the payer, by amount and date, and staff confirm it with one click', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1.0);

    $bankConnection = bankMatchingConnection($this->organisation);
    $bankAccount    = StoreOrgPaymentServiceProviderAccount::make()->action(
        $this->organisation,
        PaymentServiceProvider::where('code', 'bank')->firstOrFail(),
        ['code' => 'sugg'.random_int(1000, 9999), 'name' => 'Suggestion test']
    );
    $name        = 'Zorvak'.random_int(100000, 999999);
    $newCustomer = fn (string $customerName) => \App\Actions\CRM\Customer\StoreCustomer::make()->action($this->shop, array_merge(\App\Models\CRM\Customer::factory()->definition(), ['contact_name' => $customerName, 'company_name' => $customerName]));
    $customer    = $newCustomer($name);

    $paidByHand = bankMatchingOrder($customer, 2454);
    $paidByHand->updateQuietly(['submitted_at' => now()->addHour()]);
    $payment = \App\Actions\Accounting\Payment\StorePayment::make()->action($customer, $bankAccount, [
        'amount' => 2454,
        'status' => \App\Enums\Accounting\Payment\PaymentStatusEnum::SUCCESS,
        'state'  => \App\Enums\Accounting\Payment\PaymentStateEnum::COMPLETED,
    ]);
    \App\Actions\Ordering\Order\AttachPaymentToOrder::make()->action($paidByHand, $payment, []);
    $paidByHand->updateQuietly(['total_amount' => 1472.40]);

    $owing = bankMatchingOrder($customer, 310.50);
    $owing->updateQuietly(['submitted_at' => now()->subDays(3)]);

    $line = fn (string $id, float $amount, string $payer) => BankTransaction::create([
        'group_id'                => $bankConnection->group_id,
        'organisation_id'         => $bankConnection->organisation_id,
        'bank_connection_id'      => $bankConnection->id,
        'provider_transaction_id' => $id,
        'provider_type'           => 'TRANSFER',
        'direction'               => BankTransactionDirectionEnum::IN,
        'amount'                  => $amount,
        'currency_id'             => $paidByHand->currency_id,
        'status'                  => BankTransactionStatusEnum::BOOKED,
        'reference'               => null,
        'counterparty_name'       => $payer,
        'booked_at'               => now(),
    ]);

    bankMatchingOrder($newCustomer('Jo'.$name), 55.55)->updateQuietly(['submitted_at' => now()]);

    $twinName = 'Quillon'.random_int(100000, 999999);
    bankMatchingOrder($newCustomer($twinName.' Twin'), 77.77)->updateQuietly(['submitted_at' => now()]);
    bankMatchingOrder($newCustomer($twinName.' Twin Studio'), 77.77)->updateQuietly(['submitted_at' => now()]);

    $sharedWord = 'Brannoch'.random_int(100000, 999999);
    bankMatchingOrder($newCustomer($sharedWord), 66.66)->updateQuietly(['submitted_at' => now()]);
    $newCustomer($sharedWord.' Garden Shop');

    $partPaid = bankMatchingOrder($newCustomer('Tessaly'.random_int(100000, 999999)), 200);
    $partPaid->updateQuietly(['submitted_at' => now()]);
    $firstHalf = \App\Actions\Accounting\Payment\StorePayment::make()->action($partPaid->customer, $bankAccount, [
        'amount' => 100,
        'status' => \App\Enums\Accounting\Payment\PaymentStatusEnum::SUCCESS,
        'state'  => \App\Enums\Accounting\Payment\PaymentStateEnum::COMPLETED,
    ]);
    \App\Actions\Ordering\Order\AttachPaymentToOrder::make()->action($partPaid, $firstHalf, []);
    $firstHalf->updateQuietly(['date' => now()->subDays(20)]);

    $smith = 'Smithwick'.random_int(100000, 999999);
    $newCustomer($smith.' Ltd');
    bankMatchingOrder($newCustomer('J. '.$smith), 120)->updateQuietly(['submitted_at' => now()]);

    $jong = 'Jongbloed'.random_int(100000, 999999);
    $newCustomer('De Vries '.$jong);
    bankMatchingOrder($newCustomer('De '.$jong), 85)->updateQuietly(['submitted_at' => now()]);

    bankMatchingOrder($customer, 99.99)->updateQuietly(['submitted_at' => now()->subDays(45)]);

    $alreadyPaid  = $line('s1', 2454, $name.' Health GmbH');
    $pays         = $line('s2', 310.50, strtoupper($name).' Health Ltd');
    $otherPayer   = $line('s3', 310.50, 'Somebody Else Ltd');
    $feeShort     = $line('s4', 310.00, $name.' Health GmbH');
    $substring    = $line('s5', 55.55, $name.' Ltd');
    $twoCustomers = $line('s6', 77.77, $twinName.' Twin Ltd');
    $firstWordOnly = $line('s9', 66.66, $sharedWord.' Health');
    $secondHalf    = $line('s10', 100, $partPaid->customer->company_name.' Ltd');
    $tooOld       = $line('s7', 99.99, $name);
    $smithPayer   = $line('s11', 120, $smith.' Ltd');
    $particle     = $line('s12', 85, 'De '.$jong.' Bloemen BV');
    MatchBankTransactions::run($bankConnection);

    $matcher = MatchBankTransactions::make();
    expect($alreadyPaid->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::UNMATCHED)
        ->and($alreadyPaid->order_id)->toBeNull()
        ->and($matcher->suggestedOrders($alreadyPaid))->sequence(fn ($suggestion) => $suggestion->toMatchArray(['id' => $paidByHand->id, 'kind' => 'already_paid']))
        ->and($matcher->suggestedOrders($pays->refresh()))->sequence(fn ($suggestion) => $suggestion->toMatchArray(['id' => $owing->id, 'kind' => 'pay']))
        ->and($matcher->suggestedOrders($otherPayer->refresh()))->toBe([])
        ->and($matcher->suggestedOrders($feeShort->refresh()))->toBe([])
        ->and($matcher->suggestedOrders($substring->refresh()))->toBe([])
        ->and($matcher->suggestedOrders($twoCustomers->refresh()))->toBe([])
        ->and($matcher->suggestedOrders($firstWordOnly->refresh()))->toBe([])
        ->and($matcher->suggestedOrders($secondHalf->refresh()))->toBe([])
        ->and($matcher->suggestedOrders($tooOld->refresh()))->toBe([])
        ->and($matcher->suggestedOrders($smithPayer->refresh()))->toBe([])
        ->and($matcher->suggestedOrders($particle->refresh()))->toBe([]);

    $dialogCandidates = \App\Actions\Accounting\BankTransaction\Json\GetBankTransactionOrderCandidates::make()->handle($pays, '', byPayer: true);
    expect($dialogCandidates[0])->toMatchArray(['id' => $owing->id, 'amount_matches' => true])
        ->and(collect($dialogCandidates)->where('amount_matches', true)->count())->toBe(1);

    $paymentsBefore = \App\Models\Accounting\Payment::count();
    expect(fn () => \App\Actions\Accounting\BankTransaction\PayBankTransactionToOrder::make()->handle($otherPayer, ['order_id' => $owing->id, 'from_suggestion' => true]))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
    expect(fn () => \App\Actions\Accounting\BankTransaction\DismissBankTransaction::make()->handle($pays, ['note' => 'Already recorded on the order by hand', 'already_paid' => true, 'order_id' => $owing->id, 'from_suggestion' => true]))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
    \App\Actions\Accounting\BankTransaction\DismissBankTransaction::make()->handle($alreadyPaid, ['note' => 'Already recorded on the order by hand', 'already_paid' => true, 'order_id' => $paidByHand->id, 'from_suggestion' => true]);

    $secondTransfer = $line('s8', 2454, $name.' Health GmbH');
    $namingIt       = $line('s13', 2454, $name.' Health GmbH');
    $namingIt->update(['reference' => $paidByHand->reference]);
    MatchBankTransactions::run($bankConnection);
    expect($secondTransfer->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::UNMATCHED)
        ->and($namingIt->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::REVIEW)
        ->and($namingIt->match_data['reason'])->toBe('another bank line already accounts for the payment of this amount on the order');
    expect($alreadyPaid->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::ALREADY_PAID)
        ->and($alreadyPaid->order_id)->toBe($paidByHand->id)
        ->and(\App\Models\Accounting\Payment::count())->toBe($paymentsBefore)
        ->and($matcher->suggestedOrders($secondTransfer))->toBe([])
        ->and(fn () => \App\Actions\Accounting\BankTransaction\DismissBankTransaction::make()->handle($secondTransfer, ['note' => 'Already recorded on the order by hand', 'already_paid' => true, 'order_id' => $paidByHand->id]))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    \App\Actions\Accounting\BankTransaction\RestoreBankTransaction::make()->handle($alreadyPaid);
    expect($alreadyPaid->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::UNMATCHED)
        ->and($alreadyPaid->order_id)->toBeNull();
    $paidCandidates = \App\Actions\Accounting\BankTransaction\Json\GetBankTransactionOrderCandidates::make()->handle($alreadyPaid, $name, paid: true);
    expect(collect($paidCandidates)->pluck('id'))->toContain($paidByHand->id)->not->toContain($owing->id)
        ->and(collect($paidCandidates)->firstWhere('id', $paidByHand->id))->toMatchArray(['paid' => 2454.0, 'amount_matches' => true]);

    $handPick = fn (BankTransaction $bankTransaction, int $orderId) => \App\Actions\Accounting\BankTransaction\DismissBankTransaction::make()->handle($bankTransaction, ['note' => 'Already recorded on the order by hand', 'already_paid' => true, 'order_id' => $orderId]);
    expect(fn () => $handPick($otherPayer->refresh(), $owing->id))->toThrow(\Illuminate\Validation\ValidationException::class);
    $handPick($alreadyPaid, $paidByHand->id);
    expect($alreadyPaid->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::ALREADY_PAID)
        ->and($alreadyPaid->order_id)->toBe($paidByHand->id)
        ->and(fn () => $handPick($secondTransfer->refresh(), $paidByHand->id))->toThrow(\Illuminate\Validation\ValidationException::class, 'Another bank payment of this amount');

    \App\Actions\Accounting\BankTransaction\RestoreBankTransaction::make()->handle($alreadyPaid);
    $alreadyPaid->refresh()->update(['match_state' => BankTransactionMatchStateEnum::ALREADY_PAID, 'match_data' => ['order_ids' => [$paidByHand->id]]]);
    expect(fn () => $handPick($secondTransfer->refresh(), $paidByHand->id))->toThrow(\Illuminate\Validation\ValidationException::class, 'Another bank payment of this amount');

    $alreadyPaid->update(['match_state' => BankTransactionMatchStateEnum::UNMATCHED, 'match_data' => []]);
    $typoReference = $line('s14', 2454, $name.' Health GmbH');
    $typoReference->update(['match_state' => BankTransactionMatchStateEnum::REVIEW, 'order_id' => $owing->id, 'match_data' => ['reason' => 'order still in the basket', 'order_ids' => [$owing->id]]]);
    $handPick($typoReference, $paidByHand->id);
    expect($typoReference->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::ALREADY_PAID)
        ->and($typoReference->order_id)->toBe($paidByHand->id)
        ->and($typoReference->match_data)->not->toHaveKey('order_ids');
    \App\Actions\Accounting\BankTransaction\RestoreBankTransaction::make()->handle($typoReference);
    expect($typoReference->refresh()->match_state)->not->toBe(BankTransactionMatchStateEnum::ALREADY_PAID);

    $typoReference->update(['match_state' => BankTransactionMatchStateEnum::MATCHED, 'order_id' => $owing->id, 'match_data' => []]);
    expect(fn () => $handPick($typoReference, 0))->toThrow(\Illuminate\Validation\ValidationException::class, 'This order cannot be used');
    $handPick($typoReference, $paidByHand->id);
    expect($typoReference->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::ALREADY_PAID)
        ->and($typoReference->order_id)->toBe($paidByHand->id);
});

test('an orderchamp payout is linked only to the one hand-paid order of its country and amount, everything else stays for staff', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1.0);

    $bankConnection = bankMatchingConnection($this->organisation);
    $bankAccount    = StoreOrgPaymentServiceProviderAccount::make()->action(
        $this->organisation,
        PaymentServiceProvider::where('code', 'bank')->firstOrFail(),
        ['code' => 'ochand'.random_int(1000, 9999), 'name' => 'Orderchamp hand paid test']
    );

    $newCustomer = fn (string $customerName) => \App\Actions\CRM\Customer\StoreCustomer::make()->action($this->shop, array_merge(\App\Models\CRM\Customer::factory()->definition(), ['contact_name' => $customerName, 'company_name' => $customerName]));
    $netherlands = $newCustomer('Orderchamp B.V. (NL)');
    $germany     = $newCustomer('Orderchamp GmbH (DE)');

    $order = function (\App\Models\CRM\Customer $customer, float $total, bool $paid = true, int $daysAgo = 20) use ($bankAccount) {
        $order = bankMatchingOrder($customer, $total);
        $order->updateQuietly(['date' => now()->subDays($daysAgo)]);
        if ($paid) {
            $payment = \App\Actions\Accounting\Payment\StorePayment::make()->action($order->customer, $bankAccount, [
                'amount' => $total,
                'status' => \App\Enums\Accounting\Payment\PaymentStatusEnum::SUCCESS,
                'state'  => \App\Enums\Accounting\Payment\PaymentStateEnum::COMPLETED,
            ]);
            \App\Actions\Ordering\Order\AttachPaymentToOrder::make()->action($order, $payment, []);
        }

        return $order->refresh();
    };

    $line = fn (string $id, string $reference, float $amount, array $extra = []) => BankTransaction::create(array_merge([
        'group_id'                => $bankConnection->group_id,
        'organisation_id'         => $bankConnection->organisation_id,
        'bank_connection_id'      => $bankConnection->id,
        'provider_transaction_id' => $id,
        'provider_type'           => 'TRANSFER',
        'direction'               => BankTransactionDirectionEnum::IN,
        'amount'                  => $amount,
        'currency_id'             => $this->shop->currency_id,
        'status'                  => BankTransactionStatusEnum::BOOKED,
        'counterparty_name'       => 'ORDERCHAMP BV',
        'reference'               => $reference,
        'booked_at'               => now(),
    ], $extra));

    $single = $order($netherlands, 116.08);
    $order($netherlands, 99.60);
    $order($netherlands, 101.76);
    $order($netherlands, 201.40);
    $order($netherlands, 80, paid: false);
    $old     = $order($netherlands, 141.70, daysAgo: 100);
    $claimed = $order($netherlands, 150);
    $german  = $order($germany, 121.46);

    $line('oc0', 'earlier line', 150, ['order_id' => $claimed->id, 'match_state' => BankTransactionMatchStateEnum::ALREADY_PAID]);

    $lines = [
        'single'  => $line('oc1', 'Orderchamp NL payment: STL228059-1/STL228059-1', 115.92),
        'several' => $line('oc2', 'Orderchamp NL payment: STL228060-1/STL228060-1', 201.38),
        'unpaid'  => $line('oc3', 'Orderchamp NL payment: STL228061-1/STL228061-1', 80),
        'country' => $line('oc4', 'Orderchamp DE payment: STL228062-1/STL228062-1', 116.00),
        'german'  => $line('oc5', 'Orderchamp DE payment: STL230552-1/STL230552-1', 121.81, ['counterparty_name' => 'Orderchamp GmbH']),
        'claimed' => $line('oc6', 'Orderchamp NL payment: STL228063-1/STL228063-1', 150),
        'old'     => $line('oc7', 'Orderchamp NL payment: STL228064-1/STL228064-1', 141.70),
    ];
    MatchBankTransactions::run($bankConnection);

    foreach ($lines as $bankTransaction) {
        $bankTransaction->refresh();
    }

    expect($lines['single']->match_state)->toBe(BankTransactionMatchStateEnum::ALREADY_PAID)
        ->and($lines['single']->order_id)->toBe($single->id)
        ->and($lines['single']->match_data['difference'])->toBe(-0.16)
        ->and($lines['german']->match_state)->toBe(BankTransactionMatchStateEnum::ALREADY_PAID)
        ->and($lines['german']->order_id)->toBe($german->id)
        ->and($lines['several']->match_state)->toBe(BankTransactionMatchStateEnum::UNMATCHED)
        ->and($lines['several']->match_data['reason'])->toBe('several Orderchamp orders could make this amount')
        ->and($lines['several']->match_data['order_ids'])->toHaveCount(3)
        ->and($lines['unpaid']->match_state)->toBe(BankTransactionMatchStateEnum::UNMATCHED)
        ->and($lines['country']->match_state)->toBe(BankTransactionMatchStateEnum::UNMATCHED)
        ->and($lines['claimed']->match_state)->toBe(BankTransactionMatchStateEnum::UNMATCHED)
        ->and($lines['old']->match_state)->toBe(BankTransactionMatchStateEnum::UNMATCHED)
        ->and($lines['old']->order_id)->toBeNull()
        ->and($old->refresh()->payment_amount)->toEqual(141.70);
});
