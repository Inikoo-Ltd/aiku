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
