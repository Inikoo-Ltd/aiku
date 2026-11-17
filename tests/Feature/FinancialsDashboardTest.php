<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 16 Nov 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Accounting\Invoice\StoreInvoice;
use App\Actions\Accounting\UI\ShowFinancialsDashboard;
use App\Actions\CRM\Customer\StoreCustomer;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\SysAdmin\Guest\StoreGuest;
use App\Actions\SysAdmin\Organisation\StoreOrganisation;
use App\Actions\UI\Grp\Layout\GetGroupNavigation;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\Accounting\Invoice;
use App\Models\CRM\Customer;
use App\Models\Helpers\Address;
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

test('the financials dashboard forecasts cash from receivables on their due date, unpaid orders and payables, counting a cash on delivery invoice once', function () {
    $group            = $this->organisation->group;
    $organisationData = Organisation::factory()->definition();
    data_set($organisationData, 'code', 'fin'.random_int(1000, 9999));
    data_set($organisationData, 'type', OrganisationTypeEnum::SHOP);
    $organisation = StoreOrganisation::make()->action($group, $organisationData);
    $today        = Carbon::parse('2026-11-15 12:00:00');

    $customer = StoreCustomer::make()->action($this->shop, Customer::factory()->definition());
    DB::table('customers')->where('id', $customer->id)->update(['payment_terms_days' => 45]);
    $invoice = function (int $daysAgo, float $total, array $more = []) use ($customer, $organisation, $today) {
        $invoice = StoreInvoice::make()->action($customer, array_merge(Invoice::factory()->definition(), ['in_process' => false]));
        DB::table('invoices')->where('id', $invoice->id)->update([
            'organisation_id' => $organisation->id,
            'date'            => $today->copy()->subDays($daysAgo),
            'total_amount'    => $total,
            'payment_amount'  => 0,
            'pay_status'      => 'unpaid',
            'org_exchange'    => 1,
            'grp_exchange'    => 2,
            ...$more,
        ]);
    };
    $invoice(30, 100);
    $invoice(0, 10);
    $invoice(10, 25, ['is_cash_on_delivery' => true]);
    $invoice(60, 1000);

    $order = StoreOrder::make()->action($customer, [
        'date'             => date('Y-m-d'),
        'delivery_address' => new Address(Address::factory()->definition()),
        'billing_address'  => new Address(Address::factory()->definition()),
    ]);
    DB::table('orders')->where('id', $order->id)->update(['organisation_id' => $organisation->id, 'state' => 'in_warehouse', 'total_amount' => 80, 'payment_amount' => 30, 'grp_exchange' => 2]);

    setPermissionsTeamId($group->id);
    $user = StoreGuest::make()->action($group, array_merge(Guest::factory()->definition(), ['positions' => []]))->getUser();
    $user->givePermissionTo(['financials', "accounting.$organisation->id.view"]);
    $user->refresh();

    $forecast = ShowFinancialsDashboard::make()->handle($group, collect([$organisation]), $user, $organisation->slug, $today)['forecast'];

    expect($forecast['overdue'])->toBe(2000.0)
        ->and(collect($forecast['horizons'])->pluck('receivables', 'days')->all())->toBe([30 => 200.0, 60 => 270.0, 90 => 270.0])
        ->and(collect($forecast['horizons'])->pluck('pipeline', 'days')->all())->toBe([30 => 0.0, 60 => 100.0, 90 => 100.0])
        ->and($forecast['pipeline_orders'])->toBe(1)
        ->and($forecast['horizons'][2]['balance'])->toBe(round($forecast['start'] + 270 + 100 + $forecast['horizons'][2]['providers'] - $forecast['horizons'][2]['payables'], 2));

    actingAs($user);
    get(route('grp.financials.dashboard', ['org' => $organisation->slug]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Org/Accounting/FinancialsDashboard')
            ->where('organisation', $organisation->slug)
            ->where('routes.receivables.name', 'grp.financials.receivables.index')
            ->where('dashboard.receivables.overdue', fn ($overdue) => $overdue >= 2000));
    expect(GetGroupNavigation::make()->handle($user)['financials']['route']['name'])->toBe('grp.financials.dashboard');

    $restricted = StoreGuest::make()->action($group, array_merge(Guest::factory()->definition(), ['positions' => []]))->getUser();
    $restricted->givePermissionTo("accounting.$organisation->id.view");
    actingAs($restricted->refresh());
    get(route('grp.financials.dashboard'))->assertForbidden();
});

test('cash flow of an organisation: cash at the start and end of each week worked back from the last balance, money in and out by section, conversions apart', function () {
    $this->travelTo(Carbon::parse('2026-11-18 12:00:00'));
    $group            = $this->organisation->group;
    $pivot            = \App\Models\Helpers\Currency::where('code', config('app.currency_exchange.pivot'))->firstOrFail();
    $foreign          = \App\Models\Helpers\Currency::whereNotIn('id', \App\Models\Helpers\CurrencyExchange::select('currency_id'))->where('id', '!=', $pivot->id)->where('store_historic_data', false)->firstOrFail();
    $organisationData = Organisation::factory()->definition();
    data_set($organisationData, 'code', 'cfl'.random_int(1000, 9999));
    data_set($organisationData, 'type', OrganisationTypeEnum::SHOP);
    data_set($organisationData, 'currency_id', $pivot->id);
    data_set($organisationData, 'timezone_id', \App\Models\Helpers\Timezone::where('name', 'Europe/Bratislava')->value('id'));
    $organisation = StoreOrganisation::make()->action($group, $organisationData);
    \App\Models\Helpers\CurrencyExchange::create(['currency_id' => $foreign->id, 'date' => '2026-10-01', 'exchange' => 4, 'source' => 'test']);

    $bankConnection = \App\Models\Accounting\BankConnection::create([
        'group_id'        => $group->id,
        'organisation_id' => $organisation->id,
        'provider'        => \App\Enums\Accounting\BankConnection\BankConnectionProviderEnum::WISE,
        'name'            => 'Wise cash flow',
        'external_id'     => (string) random_int(1000, 9999),
    ]);
    $line = fn (float $amount, string $bookedAt, array $more = []) => \App\Models\Accounting\BankTransaction::create(array_merge([
        'group_id'                => $group->id,
        'organisation_id'         => $organisation->id,
        'bank_connection_id'      => $bankConnection->id,
        'provider_transaction_id' => uniqid('cf'),
        'provider_type'           => 'TRANSFER',
        'direction'               => $amount > 0 ? 'in' : 'out',
        'amount'                  => $amount,
        'currency_id'             => $pivot->id,
        'status'                  => \App\Enums\Accounting\BankTransaction\BankTransactionStatusEnum::BOOKED,
        'booked_at'               => $bookedAt,
    ], $more));
    $line(100, '2026-11-10 09:00:00', ['match_state' => 'already_paid']);
    $line(-30, '2026-11-11 09:00:00', ['out_category' => 'suppliers']);
    $line(50, '2026-11-16 09:00:00', ['match_state' => null, 'provider_type' => 'DIRECT_DEBIT']);
    $line(-20, '2026-11-17 09:00:00');
    $line(-10, '2026-11-17 10:00:00', ['provider_type' => 'INTERBALANCE', 'out_category' => 'other']);
    $line(8, '2026-11-17 10:00:00', ['provider_type' => 'INTERBALANCE', 'currency_id' => $foreign->id]);
    $line(7, '2026-11-18 11:30:00', ['match_state' => 'already_paid']);
    $line(5, '2026-11-15 23:30:00', ['match_state' => null, 'provider_type' => 'DIRECT_DEBIT']);
    foreach ([[$pivot->id, 1000], [$foreign->id, 40]] as [$currencyId, $amount]) {
        \App\Models\Accounting\BankBalance::factory()->of($bankConnection)->create(['currency_id' => $currencyId, 'amount' => $amount, 'date' => '2026-11-18', 'taken_at' => '2026-11-18 11:00:00']);
    }

    $closedBank = \App\Models\Accounting\BankConnection::create([
        'group_id'        => $group->id,
        'organisation_id' => $organisation->id,
        'provider'        => \App\Enums\Accounting\BankConnection\BankConnectionProviderEnum::WISE,
        'name'            => 'Wise closed currency',
        'external_id'     => (string) random_int(1000, 9999),
    ]);
    $line(40, '2022-05-23 09:00:00', ['bank_connection_id' => $closedBank->id, 'currency_id' => $foreign->id]);
    \App\Models\Accounting\BankBalance::factory()->of($closedBank)->create(['currency_id' => $pivot->id, 'amount' => 0, 'date' => '2026-11-18', 'taken_at' => '2026-11-18 11:00:00']);

    \App\Actions\Accounting\BankConnection\SyncBankConnection::keepExchangeRates($bankConnection);
    expect($foreign->refresh()->store_historic_data)->toBeTrue()
        ->and($foreign->historic_data_since->toDateString())->toBe('2026-11-17');

    $cashFlow = \App\Actions\Accounting\BankBalance\UI\ShowCashFlow::make()->handle($organisation);
    $weeks    = collect($cashFlow['periods'])->keyBy('start');

    expect($weeks)->toHaveCount(12)
        ->and($weeks->keys()->last())->toBe('2026-11-16')
        ->and($weeks['2026-11-16']['closing'])->toEqual(1007 + 10)
        ->and($weeks['2026-11-16']['in']['other'])->toEqual(50 + 5)
        ->and($weeks['2026-11-16']['in']['customers'])->toEqual(7)
        ->and($weeks['2026-11-16']['out']['needs_person'])->toEqual(20)
        ->and($weeks['2026-11-16']['total_out'])->toEqual(20)
        ->and($weeks['2026-11-16']['conversions'])->toEqual(-10 + 2)
        ->and($weeks['2026-11-16']['opening'])->toEqual($weeks['2026-11-09']['closing'])
        ->and($weeks['2026-11-09']['in']['customers'])->toEqual(100)
        ->and($weeks['2026-11-09']['out']['suppliers'])->toEqual(30)
        ->and($weeks['2026-11-09']['conversions'])->toEqual(0)
        ->and($weeks['2026-11-09']['opening'])->toEqual(905 + 8)
        ->and($weeks['2026-11-02']['closing'])->toEqual(905 + 8)
        ->and($cashFlow['banks'][0]['partial'])->toBeTrue()
        ->and($cashFlow['not_connected'])->toBe([])
        ->and(collect(\App\Actions\Accounting\BankBalance\UI\ShowCashFlow::make()->handle($organisation, 'month')['periods'])->last()['total_in'])->toEqual(162);

    setPermissionsTeamId($group->id);
    $user = StoreGuest::make()->action($group, array_merge(Guest::factory()->definition(), ['positions' => []]))->getUser();
    actingAs($user);
    get(route('grp.org.accounting.cash_flow.index', [$organisation->slug]))->assertForbidden();

    $user->givePermissionTo("accounting.$organisation->id.view");
    actingAs($user->refresh());
    get(route('grp.org.accounting.cash_flow.index', [$organisation->slug, 'interval' => 'month']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Org/Accounting/CashFlow')
            ->where('cashFlow.interval', 'month')
            ->where('cashFlow.currency', $pivot->code)
            ->has('cashFlow.periods', 12));
});
