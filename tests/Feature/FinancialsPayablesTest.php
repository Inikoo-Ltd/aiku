<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 15 Nov 2026 20:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Accounting\Payables\GetPayables;
use App\Actions\GoodsIn\StockDelivery\StoreStockDelivery;
use App\Actions\SupplyChain\Agent\StoreAgent;
use App\Actions\SupplyChain\Supplier\StoreSupplier;
use App\Actions\SysAdmin\Guest\StoreGuest;
use App\Actions\SysAdmin\Organisation\StoreOrganisation;
use App\Actions\UI\Grp\Layout\GetGroupNavigation;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\SupplyChain\AgentPayment\AgentPaymentTypeEnum;
use App\Enums\SupplyChain\AspoDeposit\DepositRequestStateEnum;
use App\Enums\SupplyChain\StockDeliveryInvoice\StockDeliveryInvoiceSourceEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryServiceInvoice;
use App\Models\Helpers\Currency;
use App\Models\Procurement\PurchaseOrderDepositPayment;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\AgentInvoice;
use App\Models\SupplyChain\AgentPayment;
use App\Models\SupplyChain\DepositRequest;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierInvoice;
use App\Models\SupplyChain\SupplierPayment;
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
    $this->organisation = createOrganisation();
    $this->group        = $this->organisation->group;
    $this->admin        = createAdminGuest($this->group)->getUser();
    Config::set('inertia.pages.paths', [resource_path('js/Pages/Grp')]);
});

function payablesDelivery(Organisation $organisation, string $reference): StockDelivery
{
    $supplier = StoreSupplier::make()->action(parent: $organisation->group, modelData: Supplier::factory()->definition());

    return StoreStockDelivery::make()->action($supplier->orgSuppliers()->where('organisation_id', $organisation->id)->firstOrFail(), [
        'reference' => $reference,
        'date'      => date('Y-m-d'),
    ]);
}

function payablesSupplierInvoice(StockDelivery $stockDelivery, string $reference, float $total, int $currencyId, StockDeliveryInvoiceSourceEnum $source = StockDeliveryInvoiceSourceEnum::ACTUAL): SupplierInvoice
{
    return SupplierInvoice::create([
        'group_id'          => $stockDelivery->group_id,
        'organisation_id'   => $stockDelivery->organisation_id,
        'supplier_id'       => $stockDelivery->supplier_id,
        'stock_delivery_id' => $stockDelivery->id,
        'source'            => $source,
        'reference'         => $reference,
        'date'              => now()->subDays(40)->toDateString(),
        'currency_id'       => $currencyId,
        'goods_amount'      => $total,
        'total_amount'      => $total,
        'lines'             => [],
    ]);
}

function payablesSupplierPayment(StockDelivery $stockDelivery, float $amount, int $currencyId): SupplierPayment
{
    return SupplierPayment::create([
        'group_id'          => $stockDelivery->group_id,
        'organisation_id'   => $stockDelivery->organisation_id,
        'stock_delivery_id' => $stockDelivery->id,
        'date'              => now()->toDateString(),
        'amount'            => $amount,
        'currency_id'       => $currencyId,
        'method'            => 'bank_transfer',
    ]);
}

test('a due date falls in the bucket of how overdue or how soon it is, and a document with none is in no due date', function () {
    $today = Carbon::parse('2026-11-15');
    $due   = fn (int $days) => GetPayables::dueBucket($today->copy()->addDays($days)->toDateString(), $today);

    expect(GetPayables::dueBucket(null, $today))->toBe('none')
        ->and($due(-91))->toBe('overdue_90')
        ->and($due(-90))->toBe('overdue_61_90')
        ->and($due(-61))->toBe('overdue_61_90')
        ->and($due(-60))->toBe('overdue_31_60')
        ->and($due(-31))->toBe('overdue_31_60')
        ->and($due(-30))->toBe('overdue_1_30')
        ->and($due(-1))->toBe('overdue_1_30')
        ->and($due(0))->toBe('due_7')
        ->and($due(7))->toBe('due_7')
        ->and($due(8))->toBe('due_30')
        ->and($due(30))->toBe('due_30')
        ->and($due(31))->toBe('later')
        ->and(GetPayables::ageBucket('2026-11-15', $today))->toBe('age_0_30')
        ->and(GetPayables::ageBucket('2026-10-16', $today))->toBe('age_0_30')
        ->and(GetPayables::ageBucket('2026-10-15', $today))->toBe('age_31_60')
        ->and(GetPayables::ageBucket('2026-09-15', $today))->toBe('age_61_90')
        ->and(GetPayables::ageBucket('2026-08-17', $today))->toBe('age_61_90')
        ->and(GetPayables::ageBucket('2026-08-16', $today))->toBe('age_90')
        ->and(GetPayables::DUE_BUCKETS)->toContain('overdue_90', 'overdue_61_90', 'overdue_31_60', 'overdue_1_30', 'due_7', 'due_30', 'later', 'none');
});

test('organisation payables count only what is still owed: paid, cancelled, settled and deleted documents are left out; goods not invoiced, requests awaiting approval and invoices of wallet agents are shown apart', function () {
    actingAs($this->admin);
    $organisation = $this->organisation;
    $currencyId   = $organisation->currency_id;
    $token        = 'PAY'.random_int(100000, 999999);
    $dashboard    = fn () => get(route('grp.org.accounting.payables.index', [$organisation->slug]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Org/Accounting/PayablesDashboard')
            ->where('isGroup', false)
            ->where('currency', $organisation->currency->code)
            ->where('pageHead.subNavigation.0.route.name', 'grp.org.accounting.payables.index'))
        ->viewData('page')['props']['payables'];
    $kind     = fn (array $payables, string $key) => collect($payables['kinds'])->firstWhere('key', $key);
    $baseline = $dashboard();

    $partPaid = payablesDelivery($organisation, "$token-A");
    payablesSupplierInvoice($partPaid, "$token-A", 1000, $currencyId);
    payablesSupplierPayment($partPaid, 300, $currencyId);
    payablesSupplierPayment($partPaid, 100, $currencyId)->delete();

    $paid = payablesDelivery($organisation, "$token-B");
    payablesSupplierInvoice($paid, "$token-B", 500, $currencyId);
    payablesSupplierPayment($paid, 500, $currencyId);

    $cancelled = payablesDelivery($organisation, "$token-C");
    payablesSupplierInvoice($cancelled, "$token-C", 400, $currencyId);
    $cancelled->update(['state' => StockDeliveryStateEnum::CANCELLED]);

    $notInvoiced = payablesDelivery($organisation, "$token-D");
    payablesSupplierInvoice($notInvoiced, "$token-D", 250, $currencyId, StockDeliveryInvoiceSourceEnum::ESTIMATED);
    $notInvoiced->update(['state' => StockDeliveryStateEnum::RECEIVED]);
    payablesSupplierInvoice(payablesDelivery($organisation, "$token-D2"), "$token-D2", 90, $currencyId, StockDeliveryInvoiceSourceEnum::ESTIMATED);

    $deleted = payablesDelivery($organisation, "$token-E");
    payablesSupplierInvoice($deleted, "$token-E", 150, $currencyId);
    $deleted->delete();

    $firstDelivery  = payablesDelivery($organisation, "$token-F");
    $secondDelivery = payablesDelivery($organisation, "$token-G");
    payablesSupplierInvoice($firstDelivery, "$token-F", 1000, $currencyId);
    payablesSupplierInvoice($secondDelivery, "$token-G", 600, $currencyId);
    $purchaseOrderId = DB::table('purchase_orders')->insertGetId([
        'group_id'        => $organisation->group_id,
        'organisation_id' => $organisation->id,
        'slug'            => strtolower($token).'-po',
        'parent_type'     => $firstDelivery->parent_type,
        'parent_id'       => $firstDelivery->parent_id,
        'parent_code'     => $firstDelivery->parent_code,
        'parent_name'     => $firstDelivery->parent_name,
        'reference'       => "$token-PO",
        'date'            => now(),
        'currency_id'     => $currencyId,
        'cost_data'       => '{}',
        'data'            => '{}',
    ]);
    DB::table('purchase_order_stock_delivery')->insert([
        ['purchase_order_id' => $purchaseOrderId, 'stock_delivery_id' => $firstDelivery->id],
        ['purchase_order_id' => $purchaseOrderId, 'stock_delivery_id' => $secondDelivery->id],
    ]);
    PurchaseOrderDepositPayment::create([
        'group_id'          => $organisation->group_id,
        'organisation_id'   => $organisation->id,
        'purchase_order_id' => $purchaseOrderId,
        'date'              => now()->toDateString(),
        'amount'            => 200,
        'currency_id'       => $currencyId,
        'method'            => 'bank_transfer',
    ]);

    $serviceInvoice = fn (string $reference, float $total, array $extra = []) => StockDeliveryServiceInvoice::create([
        'group_id'         => $organisation->group_id,
        'organisation_id'  => $organisation->id,
        'type'             => 'freight',
        'issuer'           => "$token Forwarder",
        'reference'        => $reference,
        'date'             => now()->subDays(100)->toDateString(),
        'currency_id'      => $currencyId,
        'total_amount'     => $total,
        'org_total_amount' => $total,
        ...$extra,
    ]);
    $serviceInvoice("$token-S1", 120);
    $serviceInvoice("$token-S2", 80, ['paid_at' => now()]);
    $serviceInvoice("$token-S3", 60)->delete();

    $agent        = StoreAgent::make()->action(group: $this->group, modelData: Agent::factory()->definition());
    AgentPayment::create([
        'group_id'        => $organisation->group_id,
        'agent_id'        => $agent->id,
        'organisation_id' => $organisation->id,
        'type'            => AgentPaymentTypeEnum::AGENT_FEE,
        'date'            => now()->toDateString(),
        'amount'          => 60,
        'currency_id'     => $currencyId,
    ]);
    $moneyRequest = function (string $reference, DepositRequestStateEnum $state, float $asked, float $sent, bool $approved = true) use ($organisation, $agent, $currencyId) {
        $depositRequest = DepositRequest::create([
            'group_id'        => $organisation->group_id,
            'agent_id'        => $agent->id,
            'organisation_id' => $organisation->id,
            'currency_id'     => $currencyId,
            'state'           => $state,
            'reference'       => $reference,
            'requested_at'    => now()->subDays(5),
            'approved_at'     => $approved ? now() : null,
        ]);
        $depositRequest->items()->create(['organisation_id' => $organisation->id, 'amount' => $asked]);
        if ($sent) {
            AgentPayment::create([
                'group_id'           => $organisation->group_id,
                'agent_id'           => $agent->id,
                'organisation_id'    => $organisation->id,
                'deposit_request_id' => $depositRequest->id,
                'type'               => AgentPaymentTypeEnum::TRANSFER_RECEIVED,
                'date'               => now()->toDateString(),
                'amount'             => $sent,
                'currency_id'        => $currencyId,
            ]);
        }
    };
    $moneyRequest("$token-R1", DepositRequestStateEnum::REQUESTED, 900, 400);
    $moneyRequest("$token-R2", DepositRequestStateEnum::SETTLED, 700, 700);
    $moneyRequest("$token-R3", DepositRequestStateEnum::CANCELLED, 300, 0);
    $moneyRequest("$token-R4", DepositRequestStateEnum::REJECTED, 300, 0);
    $moneyRequest("$token-R5", DepositRequestStateEnum::REQUESTED, 250, 0, false);

    $container = payablesDelivery($organisation, "$token-H");
    AgentInvoice::create([
        'group_id'          => $organisation->group_id,
        'agent_id'          => $agent->id,
        'organisation_id'   => $organisation->id,
        'stock_delivery_id' => $container->id,
        'source'            => StockDeliveryInvoiceSourceEnum::AGENT,
        'reference'         => "$token-H",
        'date'              => now()->subDays(70)->toDateString(),
        'currency_id'       => $currencyId,
        'total_amount'      => 2000,
        'lines'             => [],
    ]);
    AgentPayment::create([
        'group_id'          => $organisation->group_id,
        'agent_id'          => $agent->id,
        'organisation_id'   => $organisation->id,
        'stock_delivery_id' => $container->id,
        'type'              => AgentPaymentTypeEnum::TRANSFER_RECEIVED,
        'date'              => now()->toDateString(),
        'amount'            => 500,
        'currency_id'       => $currencyId,
    ]);

    $walletAgent = StoreAgent::make()->action(group: $this->group, modelData: Agent::factory()->definition());
    $ledgerLine  = fn (AgentPaymentTypeEnum $type, float $amount) => AgentPayment::create([
        'group_id'        => $organisation->group_id,
        'agent_id'        => $walletAgent->id,
        'organisation_id' => $organisation->id,
        'type'            => $type,
        'date'            => now()->toDateString(),
        'amount'          => $amount,
        'currency_id'     => $currencyId,
    ]);
    $ledgerLine(AgentPaymentTypeEnum::SUPPLIER_PAYMENT, 700);
    $ledgerLine(AgentPaymentTypeEnum::AGENT_FEE, 100);
    $ledgerLine(AgentPaymentTypeEnum::TRANSFER_RECEIVED, 300);
    $ledgerLine(AgentPaymentTypeEnum::AGENT_FEE, 999)->delete();
    AgentInvoice::create([
        'group_id'          => $organisation->group_id,
        'agent_id'          => $walletAgent->id,
        'organisation_id'   => $organisation->id,
        'stock_delivery_id' => payablesDelivery($organisation, "$token-W")->id,
        'source'            => StockDeliveryInvoiceSourceEnum::AGENT,
        'reference'         => "$token-W",
        'date'              => now()->toDateString(),
        'currency_id'       => $currencyId,
        'total_amount'      => 1000,
        'lines'             => [],
    ]);

    $after = $dashboard();
    $apart = fn (string $key, string $field) => round($after['apart'][$key][$field] - $baseline['apart'][$key][$field], 2);
    $delta = fn (string $key) => round($kind($after, $key)['amount'] - $kind($baseline, $key)['amount'], 2);
    $none  = fn (array $payables) => collect($payables['due'])->firstWhere('key', 'none');
    $age   = fn (array $payables, string $key) => collect($payables['age'])->firstWhere('key', $key)['count'];

    expect($delta('supplier_invoices'))->toBe(2100.0)
        ->and($kind($after, 'supplier_invoices')['count'] - $kind($baseline, 'supplier_invoices')['count'])->toBe(3)
        ->and($delta('service_invoices'))->toBe(120.0)
        ->and($delta('money_requests'))->toBe(500.0)
        ->and($delta('agent_invoices'))->toBe(1500.0)
        ->and($delta('agent_wallets'))->toBe(500.0)
        ->and(round($after['total']['amount'] - $baseline['total']['amount'], 2))->toBe(4720.0)
        ->and($after['total']['count'] - $baseline['total']['count'])->toBe(7)
        ->and(round($none($after)['amount'] - $none($baseline)['amount'], 2))->toBe(4720.0)
        ->and($after['overdue']['count'])->toBe(0)
        ->and($apart('not_invoiced', 'amount'))->toBe(250.0)
        ->and($apart('not_invoiced', 'count'))->toBe(1.0)
        ->and($apart('awaiting_approval', 'amount'))->toBe(250.0)
        ->and($apart('awaiting_approval', 'count'))->toBe(1.0)
        ->and($apart('in_wallet', 'amount'))->toBe(1000.0)
        ->and($apart('in_wallet', 'count'))->toBe(1.0)
        ->and($age($after, 'age_0_30') - $age($baseline, 'age_0_30'))->toBe(2)
        ->and($age($after, 'age_31_60') - $age($baseline, 'age_31_60'))->toBe(3)
        ->and($age($after, 'age_61_90') - $age($baseline, 'age_61_90'))->toBe(1)
        ->and($age($after, 'age_90') - $age($baseline, 'age_90'))->toBe(1);

    $rows = fn (string $section) => collect(get(route("grp.org.accounting.payables.$section", [$organisation->slug, 'filter' => ['global' => $token]]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Org/Accounting/Payables')->where('section', $section))
        ->viewData('page')['props']['data']['data']);

    $supplierInvoices = $rows('supplier_invoices')->keyBy('reference');
    expect($supplierInvoices->keys()->sort()->values()->all())->toBe(["$token-A", "$token-F", "$token-G"])
        ->and((float) $supplierInvoices["$token-A"]['owed'])->toBe(700.0)
        ->and((float) $supplierInvoices["$token-F"]['paid'])->toBe(200.0)
        ->and((float) $supplierInvoices["$token-G"]['paid'])->toBe(0.0)
        ->and($supplierInvoices["$token-A"]['due_date'])->toBeNull()
        ->and($supplierInvoices["$token-A"]['stock_delivery_slug'])->toBe($partPaid->slug)
        ->and($rows('service_invoices')->pluck('reference')->all())->toBe(["$token-S1"])
        ->and($rows('not_invoiced')->pluck('reference')->all())->toBe(["$token-D"])
        ->and($rows('not_invoiced')->first()['counted'])->toBeFalse();

    $moneyRequests = $rows('money_requests')->keyBy('reference');
    $agentInvoices = $rows('agent_invoices')->keyBy('reference');
    $wallet        = collect(get(route('grp.org.accounting.payables.agent_wallets', [$organisation->slug, 'filter' => ['global' => $walletAgent->name]]))
        ->assertOk()->viewData('page')['props']['data']['data'])->firstWhere('creditor_id', $walletAgent->id);
    expect($moneyRequests->keys()->sort()->values()->all())->toBe(["$token-R1", "$token-R5"])
        ->and((float) $moneyRequests["$token-R1"]['owed'])->toBe(500.0)
        ->and($moneyRequests["$token-R1"]['counted'])->toBeTrue()
        ->and($moneyRequests["$token-R5"]['counted'])->toBeFalse()
        ->and($agentInvoices->keys()->sort()->values()->all())->toBe(["$token-H", "$token-W"])
        ->and((float) $agentInvoices["$token-H"]['owed'])->toBe(1500.0)
        ->and($agentInvoices["$token-H"]['counted'])->toBeTrue()
        ->and($agentInvoices["$token-W"]['counted'])->toBeFalse()
        ->and((float) $wallet['total'])->toBe(800.0)
        ->and((float) $wallet['paid'])->toBe(300.0)
        ->and((float) $wallet['owed'])->toBe(500.0);

    $walletCreditor = get(route('grp.org.accounting.payables.creditors', [$organisation->slug, 'filter' => ['global' => $walletAgent->name]]))
        ->assertOk()->viewData('page')['props']['data']['data'];
    expect(collect($walletCreditor)->where('creditor_name', $walletAgent->name)->sum(fn ($row) => (float) $row['amount']))->toBe(500.0);

    $creditor = $rows('creditors')->firstWhere('creditor_name', "$token Forwarder");
    expect($creditor['creditor_type'])->toBe('issuer')
        ->and((int) $creditor['documents'])->toBe(1)
        ->and((float) $creditor['amount'])->toBe(120.0);
});

test('group payables show the organisations the user may see, in the group currency, only to holders of the financials permission', function () {
    $group             = $this->group;
    $otherOrganisation = Organisation::where('code', 'pay2')->first()
        ?? StoreOrganisation::make()->action($group, array_merge(Organisation::factory()->definition(), ['code' => 'pay2', 'type' => OrganisationTypeEnum::SHOP]));
    $token    = 'GPAY'.random_int(100000, 999999);
    $noRate   = Currency::whereNotIn('id', [$group->currency_id])->where('code', '!=', config('app.currency_exchange.pivot'))
        ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('currency_exchanges')->whereColumn('currency_exchanges.currency_id', 'currencies.id'))
        ->firstOrFail();

    payablesSupplierInvoice(payablesDelivery($this->organisation, "$token-MINE"), "$token-MINE", 1000, $group->currency_id);
    payablesSupplierInvoice(payablesDelivery($otherOrganisation, "$token-THEIRS"), "$token-THEIRS", 400, $group->currency_id);
    payablesSupplierInvoice(payablesDelivery($otherOrganisation, "$token-NORATE"), "$token-NORATE", 77, $noRate->id);

    setPermissionsTeamId($group->id);
    $viewer = StoreGuest::make()->action($group, array_merge(Guest::factory()->definition(), ['positions' => []]))->getUser();
    $viewer->givePermissionTo(['financials', "accounting.{$this->organisation->id}.view", "accounting.{$otherOrganisation->id}.view"]);
    $viewer->refresh();
    actingAs($viewer);

    $rows = fn (array $query = []) => collect(get(route('grp.financials.payables.supplier_invoices', ['filter' => ['global' => $token], ...$query]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Org/Accounting/Payables')
            ->where('currency', $group->currency->code)
            ->where('queryBuilderProps.default.columns', fn ($columns) => collect($columns)->pluck('key')->contains('organisation_code'))
            ->where('pageHead.subNavigation.0.route.name', 'grp.financials.payables.index'))
        ->viewData('page')['props']['data']['data'])->keyBy('reference');

    expect($rows()->keys()->sort()->values()->all())->toBe(["$token-MINE", "$token-NORATE", "$token-THEIRS"])
        ->and((float) $rows()["$token-MINE"]['amount'])->toBe(1000.0)
        ->and($rows()["$token-NORATE"]['amount'])->toBeNull()
        ->and($rows()["$token-THEIRS"]['organisation_slug'])->toBe($otherOrganisation->slug)
        ->and($rows(['org' => $otherOrganisation->slug])->keys()->sort()->values()->all())->toBe(["$token-NORATE", "$token-THEIRS"]);

    foreach (['agent_wallets', 'agent_invoices', 'money_requests', 'service_invoices', 'not_invoiced', 'creditors'] as $section) {
        get(route("grp.financials.payables.$section"))->assertOk();
    }

    $dashboard = fn (array $query = []) => get(route('grp.financials.payables.index', $query))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Org/Accounting/PayablesDashboard')->where('isGroup', true)->where('currency', $group->currency->code))
        ->viewData('page')['props']['payables'];
    $theirs = $dashboard(['org' => $otherOrganisation->slug]);
    expect(collect($dashboard()['organisations'])->pluck('label')->all())->toContain($this->organisation->code, $otherOrganisation->code)
        ->and(collect($theirs['organisations'])->pluck('label')->all())->toBe([$otherOrganisation->code])
        ->and(collect($theirs['unconverted'])->firstWhere('currency', $noRate->code)['amount'])->toBeGreaterThanOrEqual(77.0)
        ->and(collect(GetGroupNavigation::make()->handle($viewer)['financials']['topMenu']['subSections'])->pluck('route.name')->all())->toContain('grp.financials.payables.index');

    setPermissionsTeamId($group->id);
    $restricted = StoreGuest::make()->action($group, array_merge(Guest::factory()->definition(), ['positions' => []]))->getUser();
    actingAs($restricted);
    get(route('grp.financials.payables.index'))->assertForbidden();
    get(route('grp.financials.payables.supplier_invoices'))->assertForbidden();
    get(route('grp.org.accounting.payables.index', [$otherOrganisation->slug]))->assertForbidden();

    $restricted->givePermissionTo("accounting.{$otherOrganisation->id}.view");
    $restricted->refresh();
    actingAs($restricted);
    get(route('grp.financials.payables.index'))->assertForbidden();
    get(route('grp.financials.payables.creditors'))->assertForbidden();
    get(route('grp.org.accounting.payables.index', [$otherOrganisation->slug]))->assertOk();

    $restricted->givePermissionTo('financials');
    $restricted->refresh();
    actingAs($restricted);
    expect($rows()->keys()->sort()->values()->all())->toBe(["$token-NORATE", "$token-THEIRS"])
        ->and(collect($dashboard()['organisations'])->pluck('label')->all())->toBe([$otherOrganisation->code])
        ->and($rows(['org' => $this->organisation->slug])->keys()->sort()->values()->all())->toBe(["$token-NORATE", "$token-THEIRS"]);
});
