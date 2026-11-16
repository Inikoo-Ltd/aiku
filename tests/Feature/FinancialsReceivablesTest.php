<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 15 Nov 2026 19:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Accounting\Invoice\StoreInvoice;
use App\Actions\Accounting\ProfitAndLoss\GetProfitAndLoss;
use App\Actions\Accounting\Receivables\UI\ShowReceivablesDashboard;
use App\Actions\CRM\Customer\StoreCustomer;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\SysAdmin\Guest\StoreGuest;
use App\Actions\SysAdmin\Organisation\StoreOrganisation;
use App\Actions\UI\Grp\Layout\GetGroupNavigation;
use App\Enums\Accounting\BankConnection\BankConnectionProviderEnum;
use App\Enums\Accounting\BankTransaction\BankTransactionDirectionEnum;
use App\Enums\Accounting\BankTransaction\BankTransactionMatchStateEnum;
use App\Enums\Accounting\BankTransaction\BankTransactionStatusEnum;
use App\Models\Accounting\BankConnection;
use App\Models\Accounting\BankTransaction;
use App\Models\Helpers\Address;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\Accounting\Invoice;
use App\Models\CRM\Customer;
use App\Models\SysAdmin\Guest;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses()->group('base');

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    list($this->organisation, , $this->shop) = createShop();
    Config::set('inertia.pages.paths', [resource_path('js/Pages/Grp')]);
});

function receivableInvoice(Customer $customer, int $daysAgo, float $total, float $paid = 0, array $more = []): Invoice
{
    $invoice = StoreInvoice::make()->action($customer, array_merge(Invoice::factory()->definition(), ['in_process' => false]));
    DB::table('invoices')->where('id', $invoice->id)->update([
        'date'           => Carbon::parse('2026-11-15 12:00:00')->subDays($daysAgo),
        'total_amount'   => $total,
        'payment_amount' => $paid,
        'pay_status'     => 'unpaid',
        'org_exchange'   => 1,
        'grp_exchange'   => 2,
        ...$more,
    ]);

    return $invoice->refresh();
}

function receivableRefund(Invoice $invoice, float $total): void
{
    $refund = StoreInvoice::make()->action($invoice->customer, array_merge(Invoice::factory()->definition(), ['in_process' => false]));
    DB::table('invoices')->where('id', $refund->id)->update(['type' => 'refund', 'original_invoice_id' => $invoice->id, 'total_amount' => $total, 'payment_amount' => 0, 'pay_status' => 'unpaid']);
}

test('receivables age what is left of unpaid invoices, net of refunds, and leave out what is not owed', function () {
    $today     = Carbon::parse('2026-11-15 12:00:00');
    $dashboard = fn () => ShowReceivablesDashboard::make()->handle($this->organisation, collect([$this->organisation]), $today);
    $before    = $dashboard();

    $customer  = StoreCustomer::make()->action($this->shop, Customer::factory()->definition());
    $withTerms = StoreCustomer::make()->action($this->shop, Customer::factory()->definition());
    DB::table('customers')->where('id', $withTerms->id)->update(['payment_terms_days' => 30]);

    receivableInvoice($customer, 10, 100);
    receivableInvoice($customer, 45, 200, 50);
    receivableRefund(receivableInvoice($customer, 100, 300), -100);
    receivableRefund(receivableInvoice($customer, 70, 80), -80);
    receivableInvoice($withTerms, 20, 40);
    foreach ([0, 30, 31, 60, 61, 90, 91] as $daysAgo) {
        receivableInvoice($customer, $daysAgo, 1);
    }
    receivableInvoice($customer, 10, 1000, 0, ['deleted_at' => now()]);
    receivableInvoice($customer, 10, 1000, 0, ['in_process' => true]);
    receivableInvoice($customer, 10, 1000, 1000, ['pay_status' => 'paid']);
    receivableInvoice($customer, 10, 1000, 0, ['pay_status' => 'unknown']);
    receivableInvoice($customer, 10, 1000, 0, ['type' => 'refund']);
    receivableInvoice($customer, 400, 333, 0, ['source_id' => 'aurora:'.uniqid()]);
    DB::table('customers')->where('id', $withTerms->id)->update(['balance' => -10]);
    DB::table('customers')->where('id', $customer->id)->update(['balance' => 25]);

    $after = $dashboard();
    $held  = fn (array $receivables, string $side) => collect($receivables['balances'][$side]['currencies'])->sum('native');
    $delta = fn (string $age) => round(collect($after['ageing'])->firstWhere('key', $age)['amount'] - collect($before['ageing'])->firstWhere('key', $age)['amount'], 2);

    expect($delta('not_due'))->toBe(41.0)
        ->and($delta('1_30'))->toBe(101.0)
        ->and($delta('31_60'))->toBe(152.0)
        ->and($delta('61_90'))->toBe(2.0)
        ->and($delta('over_90'))->toBe(201.0)
        ->and(round($after['total']['amount'] - $before['total']['amount'], 2))->toBe(497.0)
        ->and($after['total']['invoices'] - $before['total']['invoices'])->toBe(11)
        ->and($after['total']['debtors'] - $before['total']['debtors'])->toBe(2)
        ->and(round($after['total']['overdue'] - $before['total']['overdue'], 2))->toBe(456.0)
        ->and(last($after['trend']['totals']))->toBe($after['total']['amount'])
        ->and(round($after['legacy']['amount'] - $before['legacy']['amount'], 2))->toBe(333.0)
        ->and($after['legacy']['invoices'] - $before['legacy']['invoices'])->toBe(1)
        ->and(collect($after['debtors'])->pluck('id'))->toContain($customer->id)
        ->and(round($held($after, 'credit') - $held($before, 'credit'), 2))->toBe(25.0)
        ->and(round($held($after, 'debt') - $held($before, 'debt'), 2))->toBe(10.0)
        ->and($after['balances']['debt']['customers'] - $before['balances']['debt']['customers'])->toBe(1);

    $paidLater = receivableInvoice($customer, 40, 500, 500, ['pay_status' => 'paid', 'paid_at' => $today->copy()->subDays(10)]);
    $trend     = $dashboard()['trend'];
    $at        = fn (int $weeksAgo) => $trend['totals'][count($trend['totals']) - 1 - $weeksAgo];
    $was       = fn (int $weeksAgo) => $after['trend']['totals'][count($trend['totals']) - 1 - $weeksAgo];
    expect($paidLater->pay_status->value)->toBe('paid')
        ->and(round($at(0) - $was(0), 2))->toBe(0.0)
        ->and(round($at(1) - $was(1), 2))->toBe(0.0)
        ->and(round($at(2) - $was(2), 2))->toBe(500.0)
        ->and(round($at(5) - $was(5), 2))->toBe(500.0)
        ->and(round($at(6) - $was(6), 2))->toBe(0.0);
});

test('a marketplace invoice is owed by the marketplace until its payout is in the bank', function () {
    $today     = Carbon::parse('2026-11-15 12:00:00');
    $dashboard = fn () => ShowReceivablesDashboard::make()->handle($this->organisation, collect([$this->organisation]), $today);
    $customer  = StoreCustomer::make()->action($this->shop, Customer::factory()->definition());
    $before    = $dashboard();
    $invoice   = receivableInvoice($customer, 5, 60);
    $shopType  = $this->shop->type;
    DB::table('shops')->where('id', $this->shop->id)->update(['type' => 'external']);

    try {
        $owed = $dashboard();
        expect(round($owed['marketplaces']['amount'] - $before['marketplaces']['amount'], 2))->toBeGreaterThanOrEqual(60.0)
            ->and($owed['total']['amount'])->toBeLessThanOrEqual($before['total']['amount']);

        $order = StoreOrder::make()->action($customer, [
            'date'             => date('Y-m-d'),
            'delivery_address' => new Address(Address::factory()->definition()),
            'billing_address'  => new Address(Address::factory()->definition()),
        ]);
        DB::table('invoices')->where('id', $invoice->id)->update(['order_id' => $order->id]);
        $bankConnection = BankConnection::create([
            'group_id'        => $this->organisation->group_id,
            'organisation_id' => $this->organisation->id,
            'provider'        => BankConnectionProviderEnum::WISE,
            'name'            => 'Wise receivables',
            'external_id'     => (string) random_int(1000, 9999),
        ]);
        BankTransaction::create([
            'group_id'                => $bankConnection->group_id,
            'organisation_id'         => $bankConnection->organisation_id,
            'bank_connection_id'      => $bankConnection->id,
            'provider_transaction_id' => 'rec'.uniqid(),
            'provider_type'           => 'TRANSFER',
            'direction'               => BankTransactionDirectionEnum::IN,
            'amount'                  => 60,
            'currency_id'             => $invoice->currency_id,
            'status'                  => BankTransactionStatusEnum::BOOKED,
            'booked_at'               => now(),
            'order_id'                => $order->id,
            'match_state'             => BankTransactionMatchStateEnum::PAID_BY_MARKETPLACE,
        ]);

        expect(round($owed['marketplaces']['amount'] - $dashboard()['marketplaces']['amount'], 2))->toBe(60.0);
    } finally {
        DB::table('shops')->where('id', $this->shop->id)->update(['type' => $shopType->value]);
    }
});

test('the organisation sees its receivables in accounting: dashboard, unpaid invoices by age, debtors and customer balances', function () {
    $customer = StoreCustomer::make()->action($this->shop, Customer::factory()->definition());
    $overdue = receivableInvoice($customer, 2000, 70, 20);
    $deleted = receivableInvoice($customer, 2000, 70, 0, ['deleted_at' => now()]);
    $legacy  = receivableInvoice($customer, 2000, 90, 0, ['source_id' => 'aurora:'.uniqid()]);
    DB::table('customers')->where('id', $customer->id)->update(['balance' => 12.5]);

    actingAs(createAdminGuest($this->organisation->group)->getUser());

    get(route('grp.org.accounting.receivables.index', [$this->organisation->slug]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Org/Accounting/ReceivablesDashboard')
            ->where('isGroup', false)
            ->where('currency', $this->organisation->currency->code)
            ->where('pageHead.subNavigation.1.route.name', 'grp.org.accounting.receivables.invoices')
            ->has('receivables.ageing', 5));

    $rows = fn (string $section, array $query = []) => collect(get(route("grp.org.accounting.receivables.$section", [$this->organisation->slug, 'filter' => ['global' => $customer->reference], ...$query]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Org/Accounting/Receivables')->where('section', $section))
        ->viewData('page')['props']['data']['data']);

    $invoices = $rows('invoices');
    expect($invoices->pluck('id')->all())->toBe([$overdue->id])
        ->and($invoices->pluck('id')->all())->not->toContain($deleted->id)
        ->and($invoices->first()['native'])->toEqual(50)
        ->and($invoices->first()['invoice_route']['parameters'])->toBe([$this->organisation->slug, $overdue->slug])
        ->and($rows('invoices', ['age' => 'over_90'])->pluck('id')->all())->toBe([$overdue->id])
        ->and($rows('invoices', ['age' => '1_30']))->toBeEmpty()
        ->and($rows('invoices', ['owed_by' => 'marketplaces']))->toBeEmpty()
        ->and($rows('invoices', ['owed_by' => 'legacy'])->pluck('id')->all())->toBe([$legacy->id]);

    $debtor = $rows('debtors')->firstWhere('id', $customer->id);
    expect($debtor['amount'])->toEqual(50)
        ->and($debtor['company'])->toBeFalse()
        ->and($debtor['invoices'])->toBe(1)
        ->and($debtor['customer_route']['parameters'])->toBe([$this->organisation->slug, $this->shop->slug, $customer->slug]);

    expect($rows('balances')->firstWhere('id', $customer->id)['balance'])->toBe(12.5)
        ->and($rows('balances', ['side' => 'credit'])->pluck('id')->all())->toContain($customer->id)
        ->and($rows('balances', ['side' => 'debt'])->pluck('id')->all())->not->toContain($customer->id);
});

test('group financials shows receivables of the organisations the user may see, in the group currency, only to holders of the financials permission', function () {
    $group                 = $this->organisation->group;
    $otherOrganisationData = Organisation::factory()->definition();
    data_set($otherOrganisationData, 'code', 'rec'.random_int(1000, 9999));
    data_set($otherOrganisationData, 'type', OrganisationTypeEnum::SHOP);
    $otherOrganisation = StoreOrganisation::make()->action($group, $otherOrganisationData);

    $customer = StoreCustomer::make()->action($this->shop, Customer::factory()->definition());
    $mine     = receivableInvoice($customer, 15, 30);
    $theirs   = receivableInvoice($customer, 15, 45);
    DB::table('invoices')->where('id', $theirs->id)->update(['organisation_id' => $otherOrganisation->id]);
    receivableInvoice($customer, 15, 7, 0, ['organisation_id' => $otherOrganisation->id, 'as_organisation_id' => $this->organisation->id]);
    $inOrganisation = ShowReceivablesDashboard::make()->handle($otherOrganisation, collect([$otherOrganisation]));
    expect($inOrganisation['total']['amount'])->toBe(52.0)
        ->and($inOrganisation['companies']['amount'])->toBe(7.0);

    setPermissionsTeamId($group->id);
    $admin = StoreGuest::make()->action($group, array_merge(Guest::factory()->definition(), ['positions' => []]))->getUser();
    $admin->givePermissionTo(['financials', "accounting.{$this->organisation->id}.view", "accounting.{$otherOrganisation->id}.view"]);
    $admin->refresh();
    actingAs($admin);

    $receivables = fn (array $query = []) => get(route('grp.financials.receivables.index', $query))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Org/Accounting/ReceivablesDashboard')
            ->where('isGroup', true)
            ->where('currency', $group->currency->code)
            ->where('pageHead.subNavigation.0.route.name', 'grp.financials.receivables.index'))
        ->viewData('page')['props'];

    expect(collect($receivables()['organisations'])->pluck('value')->all())->toContain($this->organisation->slug, $otherOrganisation->slug)
        ->and(collect($receivables()['receivables']['organisations'])->pluck('label')->all())->toContain($this->organisation->name, $otherOrganisation->name)
        ->and($receivables(['org' => $otherOrganisation->slug])['receivables']['total']['amount'])->toEqual(90)
        ->and($receivables(['org' => $otherOrganisation->slug])['receivables']['companies']['amount'])->toEqual(14)
        ->and(collect(GetGroupNavigation::make()->handle($admin)['financials']['topMenu']['subSections'])->pluck('route.name')->all())->toContain('grp.financials.receivables.index');

    $ids = fn (array $query = []) => collect(get(route('grp.financials.receivables.invoices', ['filter' => ['global' => $customer->reference], ...$query]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('queryBuilderProps.default.columns', fn ($columns) => collect($columns)->pluck('key')->contains('organisation_code')))
        ->viewData('page')['props']['data']['data'])->pluck('id')->all();
    expect($ids())->toContain($mine->id, $theirs->id)
        ->and($ids(['org' => $otherOrganisation->slug]))->toBe([$theirs->id]);
    get(route('grp.financials.receivables.debtors'))->assertOk();
    get(route('grp.financials.receivables.balances'))->assertOk();

    setPermissionsTeamId($group->id);
    $restricted = StoreGuest::make()->action($group, array_merge(Guest::factory()->definition(), ['positions' => []]))->getUser();
    $restricted->givePermissionTo("accounting.{$otherOrganisation->id}.view");
    $restricted->refresh();
    actingAs($restricted);
    get(route('grp.financials.receivables.index'))->assertForbidden();
    get(route('grp.financials.receivables.invoices'))->assertForbidden();
    get(route('grp.org.accounting.receivables.index', [$this->organisation->slug]))->assertForbidden();

    $restricted->givePermissionTo('financials');
    $restricted->refresh();
    actingAs($restricted);
    expect(collect($receivables()['organisations'])->pluck('value')->all())->toBe([$otherOrganisation->slug])
        ->and($receivables()['receivables']['total']['amount'])->toEqual(90)
        ->and($ids())->toBe([$theirs->id]);
});

test('payouts show per provider and currency what customers paid, what was paid out with its fees, and what is still held, as not seen where payouts never reach a connected bank', function () {
    $currency    = fn (string $code) => \App\Models\Helpers\Currency::where('code', $code)->firstOrFail();
    $pastpayCode = $currency('CHF');
    $checkoutCur = $currency('JPY');
    $psp         = fn (string $code) => \App\Models\Accounting\PaymentServiceProvider::where('code', $code)->firstOrFail();
    $account     = fn (string $code) => \App\Actions\Accounting\OrgPaymentServiceProvider\StoreOrgPaymentServiceProviderAccount::make()->action($this->organisation, $psp($code), ['code' => $code.random_int(1000, 9999), 'name' => "$code payouts"]);
    $pastpay     = $account('pastpay');
    $checkout    = $account('checkout');
    $customer    = StoreCustomer::make()->action($this->shop, Customer::factory()->definition());
    $pay         = function ($paymentAccount, float $amount, $currencyModel, bool $invoiced) use ($customer) {
        $order   = StoreOrder::make()->action($customer, [
            'date'             => date('Y-m-d'),
            'delivery_address' => new Address(Address::factory()->definition()),
            'billing_address'  => new Address(Address::factory()->definition()),
        ]);
        $payment = \App\Actions\Accounting\Payment\StorePayment::make()->action($customer, $paymentAccount, [
            'amount' => $amount,
            'status' => \App\Enums\Accounting\Payment\PaymentStatusEnum::SUCCESS,
            'state'  => \App\Enums\Accounting\Payment\PaymentStateEnum::COMPLETED,
        ]);
        \App\Actions\Ordering\Order\AttachPaymentToOrder::make()->action($order, $payment, ['amount' => $amount]);
        DB::table('payments')->where('id', $payment->id)->update(['currency_id' => $currencyModel->id, 'date' => now()->subDays(10)]);
        if ($invoiced) {
            DB::table('invoices')->where('id', receivableInvoice($customer, 5, $amount, $amount, ['pay_status' => 'paid'])->id)->update(['order_id' => $order->id]);
        }

        return $payment;
    };
    $settled  = $pay($pastpay, 100, $pastpayCode, true);
    $held     = $pay($pastpay, 200, $pastpayCode, true);
    $reserved = $pay($pastpay, 50, $pastpayCode, false);
    DB::table('payments')->where('id', $held->id)->update(['total_refund' => 20]);
    $pay($checkout, 30, $checkoutCur, false);

    $bankConnection = BankConnection::create([
        'group_id'        => $this->organisation->group_id,
        'organisation_id' => $this->organisation->id,
        'provider'        => BankConnectionProviderEnum::WISE,
        'name'            => 'Wise payouts',
        'external_id'     => (string) random_int(1000, 9999),
    ]);
    $line = fn (float $amount, array $more) => BankTransaction::create([
        'group_id'                => $bankConnection->group_id,
        'organisation_id'         => $bankConnection->organisation_id,
        'bank_connection_id'      => $bankConnection->id,
        'provider_transaction_id' => 'pay'.uniqid(),
        'provider_type'           => 'TRANSFER',
        'direction'               => BankTransactionDirectionEnum::IN,
        'amount'                  => $amount,
        'currency_id'             => $pastpayCode->id,
        'status'                  => BankTransactionStatusEnum::BOOKED,
        'booked_at'               => now()->subDays(2),
        ...$more,
    ]);
    $line(95.75, ['match_state' => BankTransactionMatchStateEnum::CARD_PROCESSOR_PAYOUT, 'match_data' => ['provider' => 'pastpay', 'reconciled_at' => now()->toIso8601String(), 'processor_fees' => 4.25, 'bank_fee' => 0, 'payment_ids' => [$settled->id]]]);
    $line(12.34, ['match_state' => BankTransactionMatchStateEnum::UNMATCHED, 'match_data' => ['reason' => 'PastPay payout, no PastPay order of this amount']]);

    $codBefore = collect(\App\Actions\Accounting\BankTransaction\UI\ShowPayouts::make()->handle($this->organisation, collect([$this->organisation]))['rows'])->firstWhere('key', "cod|{$this->organisation->id}|{$this->shop->currency->code}");
    receivableInvoice($customer, 3, 70, 0, ['is_cash_on_delivery' => true]);
    receivableInvoice($customer, 200, 40, 0, ['is_cash_on_delivery' => true]);

    $payouts = \App\Actions\Accounting\BankTransaction\UI\ShowPayouts::make()->handle($this->organisation, collect([$this->organisation]));
    $row     = fn (string $key) => collect($payouts['rows'])->firstWhere('key', $key);
    $pastpayRow  = $row("pastpay|{$this->organisation->id}|CHF");
    $checkoutRow = $row("checkout|{$this->organisation->id}|JPY");
    $codRow      = $row("cod|{$this->organisation->id}|{$this->shop->currency->code}");

    expect($pastpayRow['paid']['native'])->toEqual(350)
        ->and($pastpayRow['paid']['count'])->toBe(3)
        ->and($pastpayRow['paid_out']['native'])->toEqual(108.09)
        ->and($pastpayRow['paid_out']['count'])->toBe(2)
        ->and($pastpayRow['fees']['native'])->toEqual(4.25)
        ->and($pastpayRow['held']['status'])->toBe('known')
        ->and($pastpayRow['held']['native'])->toEqual(180)
        ->and($pastpayRow['held']['count'])->toBe(1)
        ->and($pastpayRow['not_reconciled']['count'])->toBe(1)
        ->and($pastpayRow['route']['name'])->toBe('grp.org.accounting.bank_incoming.payment_accounts')
        ->and($pastpayRow['route']['parameters']['provider'])->toBe('pastpay')
        ->and($checkoutRow['paid']['native'])->toEqual(30)
        ->and($checkoutRow['held']['status'])->toBe('not_seen')
        ->and($checkoutRow['held']['amount'])->toBeNull()
        ->and(round($codRow['held']['native'] - ($codBefore['held']['native'] ?? 0), 2))->toBe(70.0)
        ->and(round($codRow['held']['stale']['native'] - ($codBefore['held']['stale']['native'] ?? 0), 2))->toBe(40.0)
        ->and($reserved->id)->not->toBe($held->id);

    actingAs(createAdminGuest($this->organisation->group)->getUser());
    get(route('grp.org.accounting.payouts.index', [$this->organisation->slug, 'period' => '1y']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Org/Accounting/Payouts')
            ->where('isGroup', false)
            ->where('payouts.period', '1y')
            ->where('payouts.rows', fn ($rows) => collect($rows)->contains('key', "pastpay|{$this->organisation->id}|CHF")));

    $group = $this->organisation->group;
    setPermissionsTeamId($group->id);
    $viewer = StoreGuest::make()->action($group, array_merge(Guest::factory()->definition(), ['positions' => []]))->getUser();
    $viewer->givePermissionTo(['financials', "accounting.{$this->organisation->id}.view"]);
    $viewer->refresh();
    actingAs($viewer);
    get(route('grp.financials.payouts.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Org/Accounting/Payouts')
            ->where('isGroup', true)
            ->where('currency', $group->currency->code)
            ->where('payouts.rows', fn ($rows) => collect($rows)->contains(fn ($row) => $row['key'] === "pastpay|{$this->organisation->id}|CHF" && $row['route']['name'] === 'grp.financials.bank_incoming.payment_accounts')));
    expect(collect(GetGroupNavigation::make()->handle($viewer)['financials']['topMenu']['subSections'])->pluck('route.name')->all())->toContain('grp.financials.payouts.index');
});

test('profit and loss by month: sales, cost of goods picked, fees and costs read from the banks, beside the same month a year before', function () {
    $group    = $this->organisation->group;
    $currency = $this->organisation->currency_id;
    $other    = StoreOrganisation::make()->action($group, array_merge(Organisation::factory()->definition(), ['code' => 'pl'.random_int(1000, 9999), 'type' => OrganisationTypeEnum::SHOP]));
    $customer = createCustomer($this->shop);
    $company  = createCustomer($this->shop);
    DB::table('customers')->where('id', $company->id)->update(['as_organisation_id' => $other->id]);

    $sale = fn (string $date, float $net, array $more = []) => receivableInvoice($customer, 0, $net, 0, ['date' => $date, 'org_net_amount' => $net, 'grp_net_amount' => $net * 2, ...$more]);
    $sale('2001-03-10', 1000);
    $sale('2001-03-11', -100, ['type' => 'refund']);
    $sale('2001-03-12', 500, ['as_organisation_id' => $other->id]);
    $sale('2001-03-13', 9999, ['deleted_at' => now()]);
    $sale('2001-03-14', 9999, ['in_process' => true]);
    $sale('2000-03-10', 800);

    $orgStock     = createOrgStocks($this->organisation, createStocks($group))[0];
    $deliveryNote = fn (Customer $for) => DB::table('delivery_notes')->insertGetId([
        'group_id' => $group->id, 'organisation_id' => $this->organisation->id, 'warehouse_id' => createWarehouse()->id, 'shop_id' => $this->shop->id,
        'customer_id' => $for->id, 'reference' => 'pl-'.random_int(100000, 999999), 'slug' => 'pl-'.random_int(100000, 999999), 'date' => '2001-03-12', 'data' => '{}',
    ]);
    $moved = fn (string $date, string $type, float $amount, ?int $deliveryNoteId = null) => DB::table('org_stock_movements')->insert([
        'group_id' => $group->id, 'organisation_id' => $this->organisation->id, 'warehouse_id' => createWarehouse()->id, 'org_stock_id' => $orgStock->id,
        'date' => $date, 'class' => 'movement', 'type' => $type, 'flow' => $amount < 0 ? 'out' : 'in', 'org_amount' => $amount, 'grp_amount' => $amount * 2, 'data' => '{}',
        'parent_type' => $deliveryNoteId ? 'DeliveryNote' : null, 'parent_id' => $deliveryNoteId,
    ]);
    $moved('2001-03-10', 'picked', -400);
    $moved('2001-03-11', 'cancel-picked', 50);
    $moved('2001-03-12', 'picked', -200, $deliveryNote($company));
    $moved('2001-03-12', 'audit', -999);
    $moved('2000-03-10', 'picked', -300);

    $bankConnection = BankConnection::create(['group_id' => $group->id, 'organisation_id' => $this->organisation->id, 'provider' => BankConnectionProviderEnum::WISE, 'name' => 'Wise P&L', 'external_id' => (string) random_int(1000, 9999)]);
    $line           = fn (float $amount, array $more = []) => BankTransaction::create(array_merge([
        'group_id' => $group->id, 'organisation_id' => $this->organisation->id, 'bank_connection_id' => $bankConnection->id,
        'provider_transaction_id' => 'pl-'.random_int(1, PHP_INT_MAX), 'provider_type' => 'TRANSFER', 'direction' => $amount < 0 ? BankTransactionDirectionEnum::OUT : BankTransactionDirectionEnum::IN,
        'amount' => $amount, 'currency_id' => $currency, 'status' => BankTransactionStatusEnum::BOOKED, 'booked_at' => '2001-03-15 10:00:00',
    ], $more));
    $line(-60, ['out_category' => 'services', 'fee_amount' => 2]);
    $line(-300, ['out_category' => 'staff']);
    $line(-25, ['out_category' => 'needs_person']);
    $line(-5, ['out_category' => 'bank_fees']);
    $line(-700, ['out_category' => 'suppliers', 'fee_amount' => 3]);
    $line(-90, ['out_category' => 'own_companies']);
    $line(-40, ['out_category' => 'customer_refunds']);
    $line(-120, ['out_category' => 'tax']);
    $line(-50, ['provider_type' => 'INTERBALANCE']);
    $line(970, ['match_state' => BankTransactionMatchStateEnum::CARD_PROCESSOR_PAYOUT, 'match_data' => ['reconciled_at' => '2001-03-16', 'processor_fees' => 30]]);
    $line(400, ['match_state' => BankTransactionMatchStateEnum::CARD_PROCESSOR_PAYOUT]);
    $line(80, ['match_state' => BankTransactionMatchStateEnum::PAID_BY_MARKETPLACE, 'match_data' => ['commission' => 20]]);
    $line(-1, ['out_category' => 'staff', 'status' => BankTransactionStatusEnum::PENDING]);

    $organisationView = GetProfitAndLoss::make()->handle($this->organisation, collect([$this->organisation]), Carbon::parse('2001-03-01'), Carbon::parse('2001-04-30'));
    [$march, $april]  = $organisationView['months'];

    expect($march)->toMatchArray([
        'month' => '2001-03', 'sales' => 1400.0, 'cost_of_goods' => 550.0, 'gross_margin' => 850.0,
        'bank_fees' => 10.0, 'provider_fees' => 30.0, 'provider_fees_unknown' => 1, 'marketplace_fees' => 20.0,
        'staff' => 300.0, 'services' => 58.0, 'other' => 0.0, 'unclassified' => 25.0, 'tax_paid' => 120.0, 'result' => 407.0,
    ])
        ->and($march['last_year'])->toMatchArray(['sales' => 800.0, 'cost_of_goods' => 300.0, 'gross_margin' => 500.0, 'bank_fees' => null, 'result' => null])
        ->and($april)->toMatchArray(['sales' => 0.0, 'staff' => null, 'result' => null])
        ->and($organisationView['total'])->toMatchArray(['sales' => 1400.0, 'staff' => 300.0, 'result' => null])
        ->and($organisationView['currency'])->toBe($this->organisation->currency->code);

    $groupMarch = GetProfitAndLoss::make()->handle($group, collect([$this->organisation]), Carbon::parse('2001-03-01'), Carbon::parse('2001-03-31'))['months'][0];
    expect($groupMarch)->toMatchArray(['sales' => 1800.0, 'cost_of_goods' => 700.0]);
});

test('profit and loss shows in the organisation accounting and in group financials, with its under construction banner data, only to those who may see it', function () {
    $group = $this->organisation->group;

    setPermissionsTeamId($group->id);
    $viewer = StoreGuest::make()->action($group, array_merge(Guest::factory()->definition(), ['positions' => []]))->getUser();
    $viewer->givePermissionTo(['financials', "accounting.{$this->organisation->id}.view"]);
    $viewer->refresh();
    actingAs($viewer);

    get(route('grp.financials.profit_and_loss.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Org/Accounting/ProfitAndLoss')
            ->where('isGroup', true)
            ->where('profitAndLoss.currency', $group->currency->code)
            ->where('profitAndLoss.months', fn ($months) => collect($months)->count() === 12 && collect($months)->last()["month"] === now()->format("Y-m"))
            ->where('notConnected', fn ($banks) => collect($banks)->contains('Tatra banka')));
    get(route('grp.org.accounting.profit_and_loss.index', [$this->organisation->slug]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('isGroup', false)->where('profitAndLoss.currency', $this->organisation->currency->code));
    expect(collect(GetGroupNavigation::make()->handle($viewer)['financials']['topMenu']['subSections'])->pluck('route.name')->all())->toContain('grp.financials.profit_and_loss.index');

    setPermissionsTeamId($group->id);
    $outsider = StoreGuest::make()->action($group, array_merge(Guest::factory()->definition(), ['positions' => []]))->getUser();
    actingAs($outsider);
    get(route('grp.financials.profit_and_loss.index'))->assertForbidden();
    get(route('grp.org.accounting.profit_and_loss.index', [$this->organisation->slug]))->assertForbidden();
});

test('payouts show a marketplace shop with its payouts, the commission kept and short payouts, and payouts naming an order not found apart', function () {
    $customer = StoreCustomer::make()->action($this->shop, Customer::factory()->definition());
    $shopType = $this->shop->type;
    DB::table('shops')->where('id', $this->shop->id)->update(['type' => 'external']);

    try {
        $order = fn () => StoreOrder::make()->action($customer, [
            'date'             => date('Y-m-d'),
            'delivery_address' => new Address(Address::factory()->definition()),
            'billing_address'  => new Address(Address::factory()->definition()),
        ]);
        $paid   = $order();
        $unpaid = $order();
        DB::table('invoices')->where('id', receivableInvoice($customer, 5, 100)->id)->update(['order_id' => $paid->id]);
        DB::table('invoices')->where('id', receivableInvoice($customer, 5, 60)->id)->update(['order_id' => $unpaid->id]);

        $payouts = fn () => collect(\App\Actions\Accounting\BankTransaction\UI\ShowPayouts::make()->handle($this->organisation, collect([$this->organisation]))['rows']);
        $before  = $payouts();

        $bankConnection = BankConnection::create([
            'group_id'        => $this->organisation->group_id,
            'organisation_id' => $this->organisation->id,
            'provider'        => BankConnectionProviderEnum::WISE,
            'name'            => 'Wise marketplace payouts',
            'external_id'     => (string) random_int(1000, 9999),
        ]);
        $line = fn (float $amount, array $more) => BankTransaction::create([
            'group_id'                => $bankConnection->group_id,
            'organisation_id'         => $bankConnection->organisation_id,
            'bank_connection_id'      => $bankConnection->id,
            'provider_transaction_id' => 'mkt'.uniqid(),
            'provider_type'           => 'TRANSFER',
            'direction'               => BankTransactionDirectionEnum::IN,
            'amount'                  => $amount,
            'currency_id'             => $this->shop->currency_id,
            'status'                  => BankTransactionStatusEnum::BOOKED,
            'booked_at'               => now()->subDay(),
            ...$more,
        ]);
        $line(83, ['order_id' => $paid->id, 'match_state' => BankTransactionMatchStateEnum::PAID_BY_MARKETPLACE, 'match_data' => ['owed' => 85, 'received' => 83, 'difference' => -2, 'commission' => 15]]);
        $line(40, ['match_state' => BankTransactionMatchStateEnum::UNMATCHED, 'match_data' => ['reason' => 'marketplace order not found', 'tokens' => ['NOTOURS1']]]);

        $after = $payouts();
        $shop  = fn ($rows) => $rows->firstWhere('key', "marketplace|{$this->shop->id}");
        $lost  = $after->firstWhere('key', "marketplace|unknown|{$this->organisation->id}|{$this->shop->currency->code}");

        expect($shop($after)['held']['status'])->toBe('known')
            ->and(round($shop($after)['paid_out']['native'] - ($shop($before)['paid_out']['native'] ?? 0), 2))->toBe(83.0)
            ->and(round($shop($after)['fees']['native'] - ($shop($before)['fees']['native'] ?? 0), 2))->toBe(17.0)
            ->and($shop($after)['not_reconciled']['count'] - ($shop($before)['not_reconciled']['count'] ?? 0))->toBe(1)
            ->and(round(($shop($before)['held']['native'] ?? 0) - $shop($after)['held']['native'], 2))->toBe(100.0)
            ->and($shop($after)['route']['name'])->toBe('grp.org.accounting.bank_incoming.marketplaces')
            ->and($lost['held']['status'])->toBe('not_seen')
            ->and($lost['held']['amount'])->toBeNull()
            ->and($lost['not_reconciled']['count'])->toBeGreaterThanOrEqual(1);
    } finally {
        DB::table('shops')->where('id', $this->shop->id)->update(['type' => $shopType->value]);
    }
});
