<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 15 Nov 2026 19:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Accounting\Invoice\StoreInvoice;
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
