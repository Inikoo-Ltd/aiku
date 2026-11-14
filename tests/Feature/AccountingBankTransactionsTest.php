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

    get(route('grp.org.accounting.bank_review.index', [$this->organisation->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Org/Accounting/BankTransactionsToReview')
            ->where('canEdit', true)
            ->where('period', '90d')
            ->where('scopeCounts.review', fn ($count) => $count >= 1)
            ->where('scopeCounts', fn ($counts) => collect($counts)->keys()->all() === ['review', 'unmatched', 'dismissed', 'payouts'])
            ->where('queryBuilderProps.default.columns', fn ($columns) => collect($columns)->pluck('key')->contains('actions') && !collect($columns)->pluck('key')->contains('reason'))
            ->where('data.data', fn ($rows) => collect($rows)->pluck('id')->contains($toReview->id)
                && !collect($rows)->pluck('id')->contains($old->id)
                && !collect($rows)->pluck('id')->contains($paid->id)
                && !collect($rows)->pluck('id')->contains($payout->id)));

    get(route('grp.org.accounting.bank_review.index', [$this->organisation->slug, 'period' => 'all']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('period', 'all')
            ->where('data.data', fn ($rows) => collect($rows)->pluck('id')->contains($old->id)));

    $toReview->update(['reference' => 'Faire EWACREAMZ3']);
    $ids = fn (array $query) => collect(get(route('grp.org.accounting.bank_review.index', [$this->organisation->slug, ...$query]))->viewData('page')['props']['data']['data'])->pluck('id');

    expect($ids(['filter' => ['global' => 'ewacreamz3']]))->toContain($toReview->id)->not->toContain($old->id)
        ->and($ids(['filter' => ['global' => '12,00']]))->toContain($toReview->id)->not->toContain($old->id)
        ->and($ids(['period' => 'all', 'filter' => ['global' => $bankConnection->name]]))->toContain($toReview->id, $old->id)
        ->and($ids(['currency' => $order->currency->code]))->toContain($toReview->id)
        ->and($ids(['currency' => $otherCurrencyLine->currency->code]))->not->toContain($toReview->id)
        ->and($ids(['from' => now()->subYears(3)->subDay()->toDateString(), 'to' => now()->subYears(2)->toDateString()]))->toContain($old->id)->not->toContain($toReview->id);
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
        $line = fn (string $id, Order $order, float $amount) => BankTransaction::create([
            'group_id'                => $bankConnection->group_id,
            'organisation_id'         => $bankConnection->organisation_id,
            'bank_connection_id'      => $bankConnection->id,
            'provider_transaction_id' => $id,
            'provider_type'           => 'TRANSFER',
            'direction'               => BankTransactionDirectionEnum::IN,
            'amount'                  => $amount,
            'currency_id'             => $order->currency_id,
            'status'                  => BankTransactionStatusEnum::BOOKED,
            'counterparty_name'       => 'Faire Wholesale B. V.',
            'reference'               => 'Faire '.$order->reference,
            'booked_at'               => now(),
        ]);

        $exactLine = $line('f1', $exact, 168.35);
        $shortLine = $line('f2', $short, 270.00);

        expect(MatchBankTransactions::run($bankConnection))->toBe(['paid_by_marketplace' => 2])
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
        "Capture,act_1,$paymentReference,00000003YGCF,Gross,100.00,CZK,100.00",
        "Capture,act_1,$paymentReference,00000003YGCF,Interchange Fee,-0.30,CZK,-0.30",
        "Capture,act_1,$paymentReference,00000003YGCF,Scheme Fee,-0.20,CZK,-0.20",
        'Capture,act_2,pay_notinaiku000000000000000000,00000003YGCF,Gross,5.00,CZK,5.00',
        'Refund,act_3,pay_refunded00000000000000000000,00000003YGCF,Gross,-8.00,CZK,-8.00',
        'Refund,act_3,pay_refunded00000000000000000000,00000003YGCF,Gateway Fee,-0.50,CZK,-0.50',
    ]);
    $reconciler = \App\Actions\Accounting\BankTransaction\ReconcileCheckoutPayouts::partialMock();
    $reconciler->shouldReceive('payoutReportFiles')->andReturn([
        '00000003YGCF' => ['report_id' => 'rpt_1', 'file_id' => 'file_1'],
        '00000003YLNX' => ['report_id' => 'rpt_2', 'file_id' => 'file_2'],
    ]);
    $reconciler->shouldReceive('payoutReportCsv')->with(\Mockery::any(), \Mockery::any(), 'rpt_1', 'file_1')->andReturn($csv);
    $reconciler->shouldReceive('payoutReportCsv')->with(\Mockery::any(), \Mockery::any(), 'rpt_2', 'file_2')->andReturn(implode("\n", [
        'Action Type,Payment ID,Breakdown Type,Processing Currency Amount,Holding Currency,Holding Currency Amount',
        'Capture,pay_short000000000000000000000,Gross,10.00,CZK,10.00',
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
    get(route('grp.org.accounting.bank_review.index', [$this->organisation->slug, 'scope' => 'payouts']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('scope', 'payouts')
            ->where('scopeCounts.payouts', fn ($count) => $count >= 1)
            ->where('data.data', fn ($rows) => collect($rows)->contains(fn ($row) => $row['id'] === $daily->id
                && $row['payout']['reconciled']
                && $row['payout']['payments'] === 2
                && $row['payout']['processor_fees'] == 1
                && $row['payout']['payments_missing'] === 1)));
});
