<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 08 May 2023 09:03:42 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\SysAdmin\Organisation;
use App\Actions\Procurement\OrgSupplier\StoreOrgSupplier;
use App\Actions\Helpers\Redirects\RedirectSupplierLink;
use App\Models\SysAdmin\User;
use App\Actions\Goods\TradeUnit\StoreTradeUnit;
use App\Actions\SupplyChain\SupplierProduct\UI\GetSupplierProductShowcase;
use App\Actions\Procurement\GetOrganisationStockCoverBuckets;
use App\Actions\Procurement\GetStockOutsHistory;
use App\Actions\Procurement\GetStockOutsPipeline;
use App\Actions\Procurement\OrgAgent\StoreOrgAgent;
use App\Actions\Procurement\OrgAgent\UpdateOrgAgent;
use App\Actions\Procurement\OrgSupplier\UpdateOrgSupplier;
use App\Actions\SupplyChain\Agent\DeleteAgent;
use App\Actions\SupplyChain\Agent\StoreAgent;
use App\Actions\SupplyChain\Agent\UpdateAgent;
use App\Actions\Procurement\PurchaseOrder\StorePurchaseOrder;
use App\Models\Procurement\PurchaseOrder;
use App\Actions\SupplyChain\Supplier\DeleteSupplier;
use App\Actions\SupplyChain\Supplier\StoreSupplier;
use App\Actions\SupplyChain\Supplier\UpdateSupplier;
use App\Actions\Procurement\OrgSupplierProducts\UI\GetOrgSupplierProductShowcase;
use App\Actions\SupplyChain\SupplierProduct\StoreSupplierProduct;
use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\HumanResources\Employee\StoreEmployee;
use App\Actions\SysAdmin\GetSectionRoute;
use App\Actions\SysAdmin\User\StoreUser;
use App\Actions\UI\Grp\Layout\GetOrganisationsLayout;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Enums\HumanResources\Employee\EmployeeTypeEnum;
use App\Enums\HumanResources\Employee\EmploymentTypeEnum;
use App\Enums\SysAdmin\User\UserAuthTypeEnum;
use App\Models\HumanResources\JobPosition;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Actions\UI\Grp\Layout\GetGroupNavigation;
use App\Enums\Analytics\AikuSection\AikuSectionEnum;
use App\Models\Analytics\AikuScopedSection;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Upload;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgAgentStats;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\OrgSupplierStats;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use App\Models\Helpers\Currency;
use Illuminate\Validation\ValidationException;
use App\Models\SupplyChain\SupplierProduct;
use Inertia\Testing\AssertableInertia;
use App\Enums\SupplyChain\SupplierProduct\SupplierProductStateEnum;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\patch;

beforeAll(function () {
    loadDB();
});


beforeEach(function () {
    $this->organisation = createOrganisation();
    $this->group        = group();
    $this->stocks       = createStocks($this->group);

    $this->adminGuest = createAdminGuest($this->organisation->group);

    Config::set(
        'inertia.testing.page_paths',
        [resource_path('js/Pages/Grp')]
    );
    actingAs($this->adminGuest->getUser());
});

test('create agent', function () {
    $modelData = Agent::factory()->definition();
    $agent     = StoreAgent::make()->action(
        group: $this->group,
        modelData: $modelData
    );

    expect($agent)->toBeInstanceOf(Agent::class)
        ->and($this->group->supplyChainStats->number_agents)->toBe(1)
        ->and($this->group->supplyChainStats->number_archived_agents)->toBe(0);


    return $agent;
});

test('agent org admin can log in with aurora legacy password', function (Agent $agent) {
    $organisation = $agent->organisation;
    $orgAdmin     = JobPosition::where('organisation_id', $organisation->id)->where('code', 'org-admin')->firstOrFail();

    $employee = StoreEmployee::make()->action($organisation, [
        'worker_number'   => 'coco',
        'alias'           => 'coco',
        'contact_name'    => 'Coco',
        'state'           => EmployeeStateEnum::WORKING,
        'type'            => EmployeeTypeEnum::EMPLOYEE,
        'employment_type' => EmploymentTypeEnum::FULL_TIME,
        'positions'       => [['slug' => $orgAdmin->slug, 'scopes' => []]],
    ]);

    $user = StoreUser::make()->action($employee, [
        'username'        => 'coco',
        'password'        => Str::random(64),
        'status'          => true,
        'reset_password'  => false,
        'auth_type'       => UserAuthTypeEnum::AURORA,
        'legacy_password' => hash('sha256', 'aurora-password'),
    ], strict: false);

    expect($user->roles()->pluck('name')->all())->toBe(["org-admin-{$organisation->id}"])
        ->and($user->authorisedOrganisations()->pluck('organisations.id')->all())->toBe([$organisation->id]);

    Auth::logout();
    Config::set('app.with_user_legacy_passwords', true);
    $response = $this->post(route('grp.login.store'), [
        'username' => 'coco',
        'password' => 'aurora-password',
    ]);

    $response->assertRedirect(route('grp.org.dashboard.show', [$organisation->slug]));
    $this->assertAuthenticatedAs($user);

    $user->refresh();
    expect($user->auth_type)->toBe(UserAuthTypeEnum::DEFAULT)
        ->and($user->legacy_password)->toBeNull()
        ->and(Hash::check('aurora-password', $user->password))->toBeTrue()
        ->and(array_keys(GetOrganisationsLayout::run($user)[$organisation->slug]))->toBe(['agent_suppliers', 'agent_products', 'agent_purchase_orders', 'agent_containers', 'agent_accounting', 'hr', 'agent_settings'])
        ->and($user->hasGroupAccess())->toBeFalse()
        ->and(array_keys(GetGroupNavigation::run($user)))->toBe(['tickets']);

    $this->get(route('grp.dashboard.show'))->assertRedirect(route('grp.org.dashboard.show', $organisation->slug));
    $this->get(route('grp.devops.dashboard'))->assertForbidden();
    $this->get(route('grp.chat.reports'))->assertForbidden();
    $this->get(route('grp.org.dashboard.show', $organisation->slug))->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('dashboard.super_blocks', [])
            ->has('cleanHandover.quarters')
            ->missing('cleanHandover.hygiene'));

    $this->get(route('grp.org.agent.org_suppliers.index', $organisation->slug))->assertOk();
    $this->get(route('grp.org.agent.purchase_orders.index', $organisation->slug))->assertOk();
    $this->get(route('grp.org.agent.stock_deliveries.index', $organisation->slug))->assertOk();
    $this->get(route('grp.org.procurement.org_suppliers.index', $organisation->slug).'?sort=code')
        ->assertRedirect(route('grp.org.agent.org_suppliers.index', $organisation->slug).'?sort=code');
    $this->get(route('grp.org.procurement.dashboard', $organisation->slug))->assertRedirect(route('grp.org.dashboard.show', $organisation->slug));
    $this->get(route('grp.org.procurement.org_agents.index', $organisation->slug))->assertNotFound();
    $this->get(route('grp.org.procurement.org_partners.index', $organisation->slug))->assertNotFound();
    $this->get(route('grp.org.warehouses.index', $organisation->slug))->assertForbidden();
    $this->get(route('grp.org.agent.org_suppliers.index', Organisation::where('type', OrganisationTypeEnum::SHOP)->firstOrFail()->slug))->assertForbidden();
})->depends('create agent');

test('update agent', function (Agent $agent) {
    $modelData    = [
        'name'          => 'UpdatedName',
        'delivery_type' => 'parcel',
        'delivery_time' => 45,
        'payment_terms' => '50% upfront',
        'image'         => \Illuminate\Http\UploadedFile::fake()->image('agent.jpg', 200, 200),
        'journey_days_production' => 40,
    ];
    $updatedAgent = UpdateAgent::make()->action(
        agent: $agent,
        modelData: $modelData
    );

    expect($updatedAgent)->toBeInstanceOf(Agent::class)
        ->and($updatedAgent->name)->toBe('UpdatedName')
        ->and(Arr::get($updatedAgent->data, 'delivery_type'))->toBe('parcel')
        ->and(Arr::get($updatedAgent->data, 'delivery_time'))->toBe(45)
        ->and(Arr::get($updatedAgent->settings, 'payment_terms'))->toBe('50% upfront')
        ->and(Arr::get($updatedAgent->settings, 'journey_stage_days.production'))->toBe(40)
        ->and($updatedAgent->image_id)->not->toBeNull();

    return $updatedAgent;
})->depends('create agent');


test('create another agent', function () {
    $modelData = Agent::factory()->definition();
    $agent     = StoreAgent::make()->action(
        group: $this->group,
        modelData: $modelData
    );

    expect($agent)->toBeInstanceOf(Agent::class)
        ->and($this->group->supplyChainStats->number_agents)->toBe(2)
        ->and($this->group->supplyChainStats->number_archived_agents)->toBe(0);

    return $agent;
});


test('create independent supplier', function () {
    $supplier = StoreSupplier::make()->action(
        parent: $this->group,
        modelData: Supplier::factory()->definition()
    );
    expect($supplier)->toBeInstanceOf(Supplier::class)
        ->and($supplier->agent_id)->toBeNull()
        ->and($this->group->supplyChainStats->number_suppliers)->toBe(1);


    return $supplier;
});

test('update supplier', function (Supplier $supplier) {
    $modelData       = [
        'contact_name'  => 'UpdatedName',
        'delivery_type' => 'parcel',
        'delivery_time' => 45,
        'payment_terms' => '50% upfront',
        'image'         => \Illuminate\Http\UploadedFile::fake()->image('supplier.jpg', 200, 200),
    ];
    $updatedSupplier = UpdateSupplier::make()->action(
        supplier: $supplier,
        modelData: $modelData
    );

    expect($updatedSupplier)->toBeInstanceOf(Supplier::class)
        ->and($updatedSupplier->contact_name)->toBe('UpdatedName')
        ->and(Arr::get($updatedSupplier->data, 'delivery_type'))->toBe('parcel')
        ->and(Arr::get($updatedSupplier->data, 'delivery_time'))->toBe(45)
        ->and(Arr::get($updatedSupplier->settings, 'payment_terms'))->toBe('50% upfront')
        ->and($updatedSupplier->image_id)->not->toBeNull();

    return $updatedSupplier;
})->depends('create independent supplier');

test('create independent supplier 2', function () {
    $supplier = StoreSupplier::make()->action(
        parent: $this->group,
        modelData: Supplier::factory()->definition(),
    );
    expect($supplier)->toBeInstanceOf(Supplier::class)
        ->and($supplier->agent_id)->toBeNull()
        ->and($this->group->supplyChainStats->number_suppliers)->toBe(2);


    return $supplier;
});

test('number independent supplier should be two', function () {
    $this->assertEquals(2, $this->group->supplyChainStats->number_suppliers);
});

test('create supplier in agent', function ($agent) {
    expect($agent->stats->number_suppliers)->toBe(0);

    $supplier = StoreSupplier::make()->action(
        parent: $agent,
        modelData: Supplier::factory()->definition()
    );
    $agent->refresh();
    expect($supplier)->toBeInstanceOf(Supplier::class)
        ->and($agent->stats->number_suppliers)->toBe(1);

    return $supplier;
})->depends('create agent');

test('create supplier product independent supplier', function ($supplier) {
    $supplierProductData = SupplierProduct::factory()->definition();
    data_set($supplierProductData, 'stock_id', $this->stocks[0]->id);
    $supplierProduct = StoreSupplierProduct::make()->action($supplier, $supplierProductData);
    $this->assertModelExists($supplierProduct);

    return $supplierProduct;
})->depends('create independent supplier');

test('create supplier product independent supplier 2', function ($supplier) {
    $supplierProductData = SupplierProduct::factory()->definition();
    data_set($supplierProductData, 'stock_id', $this->stocks[1]->id);
    $supplierProduct = StoreSupplierProduct::make()->action($supplier, $supplierProductData);
    $this->assertModelExists($supplierProduct);

    return $supplierProduct;
})->depends('create independent supplier');

test('create supplier product in agent supplier', function ($supplier) {
    $supplierProductData = SupplierProduct::factory()->definition();
    data_set($supplierProductData, 'stock_id', $this->stocks[2]->id);
    $supplierProduct = StoreSupplierProduct::make()->action($supplier, $supplierProductData);
    $this->group->refresh();
    expect($supplierProduct)->toBeInstanceOf(SupplierProduct::class)
        ->and($this->group->supplyChainStats->number_supplier_products)->toBe(3)
        ->and($this->group->supplyChainStats->number_independent_supplier_products)->toBe(2)
        ->and($this->group->supplyChainStats->number_supplier_products_in_agents)->toBe(1);
})->depends('create supplier in agent');

function supplierProductUploadSheet(array $rows, array $extraHeadings = [], array $extraSheets = []): string
{
    $headings = [...App\Exports\SupplyChain\SupplierProductTemplateExport::headings(), ...$extraHeadings];

    $spreadsheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();
    $spreadsheet->getActiveSheet()->setTitle('Product data');
    $spreadsheet->getActiveSheet()->fromArray([
        ['AW new supplier parts - upload template'],
        [],
        [],
        array_map(fn (string $heading) => App\Enums\SupplyChain\SupplierProductUpload\SupplierProductSheetColumnEnum::fromHeading($heading)?->isRequired() ? 'Required' : 'Opt', $headings),
        $headings,
        ...array_map(fn (array $row) => array_map(fn (string $heading) => $row[$heading] ?? null, $headings), $rows),
    ]);
    foreach ($extraSheets as $title => $sheetRows) {
        $spreadsheet->createSheet()->setTitle($title)->fromArray($sheetRows);
    }
    $path = sys_get_temp_dir().'/supplier_products_'.uniqid().'.xlsx';
    (new PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($path);

    return $path;
}

function supplierProductUploadRow(array $overrides = []): array
{
    return array_merge([
        'Family'                                 => 'UPL-BAG',
        'Part reference'                         => 'UPLB-01',
        'Unit recommended description (website)' => 'Hemp Forest Bag',
        "Supplier's product code"                => 'UPLB-01',
        'Unit label'                             => 'bag',
        'Units per SKO'                          => 2,
        'Recommended SKOs per selling outer'     => 1,
        'SKOs per carton'                        => 40,
        'Minimum order (cartons)'                => 2,
        'Unit cost (Sup Cur)'                    => 1.5,
        'Unit expense (Sup Cur)'                 => 0.1,
        'Unit Est True Extra costs %'            => 0.4,
        'Unit recommended price (£)'             => 8.5,
        'Unit recommended RRP (£)'               => 20,
        'Unit recommended price (€)'             => 10.2,
        'Unit recommended RRP (€)'               => 24,
        'Unit barcode (EAN-13, for website)'     => '4006381333931',
        'Average delivery time (days)'           => 30,
        'Unit weight (kg)'                       => 0.25,
        'Unit dimensions (l x w x h) in cm'      => '28x17x8',
        'SKO weight (kg)'                        => 0.55,
        'Carton Weight'                          => 23,
        'SKO dimensions (l x w x h) in cm'       => '30x20x10',
        'Carton CBM'                             => 0.3,
        'Materials'                              => 'Hemp, cotton',
        'Tariff code'                            => '4202.12.9990',
    ], $overrides);
}

function uploadSupplierProductSheet(Supplier $supplier, string $path): Upload
{
    $upload = Upload::create([
        'group_id'          => $supplier->group_id,
        'model'             => 'SupplierProduct',
        'parent_type'       => $supplier->getMorphClass(),
        'parent_id'         => $supplier->id,
        'original_filename' => basename($path),
        'filename'          => basename($path),
        'path'              => 'excel-uploads/tests',
        'filesize'          => filesize($path),
    ]);
    Illuminate\Support\Facades\Storage::disk('excel-uploads')->put('excel-uploads/tests/'.basename($path), file_get_contents($path));

    return App\Actions\SupplyChain\SupplierProduct\Upload\PrepareSupplierProductUpload::run($supplier, $upload)->refresh();
}

function acceptSupplierProductUploadFindings(Upload $upload): void
{
    foreach ($upload->records as $record) {
        $decisions = collect($record->data['findings'])
            ->whereIn('level', ['block', 'link'])
            ->mapWithKeys(fn (array $finding) => [$finding['code'] => ['accepted' => true]])
            ->all();
        $values = $record->values;
        $values['sko_name'] ??= 'Pack of '.$values['units_per_sko'].' '.$values['unit_name'];
        $record->update(['values' => $values, 'data' => array_merge($record->data, ['decisions' => $decisions])]);
    }
}

test('supplier product upload refuses a sheet missing a required column', function () {
    $supplier = StoreSupplier::make()->action(parent: $this->group, modelData: Supplier::factory()->definition());

    $spreadsheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();
    $spreadsheet->getActiveSheet()->fromArray([['Family', 'Part reference', 'Unit cost (Sup Cur)'], ['UPL-BAG', 'UPLB-01', 1]]);
    $path = sys_get_temp_dir().'/supplier_products_'.uniqid().'.xlsx';
    (new PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($path);

    $upload = uploadSupplierProductSheet($supplier, $path);

    expect($upload->state)->toBe(App\Enums\Helpers\Import\UploadStateEnum::REFUSED)
        ->and($upload->data['errors'])->toContain('Missing column: Unit recommended RRP (£)')
        ->and($upload->records()->count())->toBe(0);
});

test('supplier product upload previews rows with their findings and creates nothing until imported', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1.0);
    $supplier = StoreSupplier::make()->action(parent: $this->group, modelData: Supplier::factory()->definition());

    $upload = uploadSupplierProductSheet($supplier, supplierProductUploadSheet([
        supplierProductUploadRow(),
        supplierProductUploadRow([
            'Part reference'                         => 'UPLB-01',
            "Supplier's product code"                => 'UPLB-01B',
        ]),
        supplierProductUploadRow([
            'Part reference'                         => 'UPLB-02',
            "Supplier's product code"                => null,
            'Unit recommended description (website)' => 'Pack of 50 Craft Roses',
            'Unit label'                             => '20x',
            'Units per SKO'                          => 1.5,
            'Unit cost (Sup Cur)'                    => 'Rs',
            'Unit recommended price (€)'             => '£10.20',
            'Unit barcode (EAN-13, for website)'     => null,
            'SKOs per carton'                        => 30,
            'Recommended SKOs per selling outer'     => 4,
        ]),
    ]));

    $findings = $upload->records()->orderBy('row_number')->get()->mapWithKeys(fn ($record) => [$record->row_number => collect($record->data['findings'])->pluck('level', 'code')->all()]);

    expect($upload->state)->toBe(App\Enums\Helpers\Import\UploadStateEnum::WAITING_CONFIRMATION)
        ->and($upload->number_rows)->toBe(3)
        ->and($findings->keys()->all())->toBe([6, 7, 8])
        ->and($findings[6])->toMatchArray(['family_new' => 'warning'])
        ->and($findings[6])->not->toHaveKey('unit_name_pack')
        ->and($findings[6])->not->toHaveKey('duplicate_part_reference')
        ->and($findings[7])->toMatchArray(['duplicate_part_reference' => 'error'])
        ->and($findings[8])->toMatchArray([
            'supplier_code_from_part_reference' => 'warning',
            'unit_name_pack'                    => 'block',
            'unit_label_odd'                    => 'block',
            'invalid_units_per_sko'             => 'error',
            'invalid_unit_cost'                 => 'error',
            'currency_recommended_price_eur'    => 'error',
            'no_barcode'                        => 'block',
            'carton_not_split_into_outers'      => 'block',
        ])
        ->and($upload->records()->where('row_number', 8)->first()->values['supplier_code'])->toBe('UPLB-02')
        ->and(TradeUnit::where('group_id', $this->group->id)->where('code', 'like', 'UPLB-%')->exists())->toBeFalse()
        ->and(fn () => App\Actions\SupplyChain\SupplierProduct\Upload\ImportSupplierProductUpload::run($upload))->toThrow(ValidationException::class);
});

test('confirmed supplier product upload creates families, trade unit, SKO, supplier product and the draft purchase order', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1.0);
    $supplier = StoreSupplier::make()->action(parent: $this->group, modelData: Supplier::factory()->definition());
    $orderColumn = 'Order Cartons '.$this->organisation->code;

    $upload = uploadSupplierProductSheet($supplier, supplierProductUploadSheet([
        supplierProductUploadRow([$orderColumn => 3]),
    ], [$orderColumn]));

    acceptSupplierProductUploadFindings($upload);
    $upload = App\Actions\SupplyChain\SupplierProduct\Upload\ImportSupplierProductUpload::run($upload->refresh())->refresh();

    $tradeUnit       = TradeUnit::where('group_id', $this->group->id)->where('code', 'UPLB-01')->firstOrFail();
    $stock           = $tradeUnit->stocks()->firstOrFail();
    $supplierProduct = SupplierProduct::where('supplier_id', $supplier->id)->where('code', 'UPLB-01')->firstOrFail();
    $purchaseOrder   = PurchaseOrder::where('organisation_id', $this->organisation->id)
        ->where('parent_type', 'OrgSupplier')
        ->where('parent_id', $supplier->orgSuppliers()->where('organisation_id', $this->organisation->id)->value('id'))
        ->firstOrFail();

    expect($upload->state)->toBe(App\Enums\Helpers\Import\UploadStateEnum::IMPORTED)
        ->and($upload->number_success)->toBe(1)
        ->and($upload->number_fails)->toBe(0)
        ->and($tradeUnit->name)->toBe('Hemp Forest Bag')
        ->and($tradeUnit->type)->toBe('bag')
        ->and($tradeUnit->gross_weight)->toBe(250)
        ->and($tradeUnit->tariff_code)->toBe('4202129990')
        ->and($tradeUnit->marketing_ingredients)->toBe('Hemp, cotton')
        ->and($tradeUnit->barcode)->toBe('4006381333931')
        ->and($tradeUnit->tradeUnitFamily?->code)->toBe('UPL-BAG')
        ->and($stock->name)->toBe('Pack of 2 Hemp Forest Bag')
        ->and($stock->stockFamily?->code)->toBe('UPL-BAG')
        ->and((int)$stock->packed_in)->toBe(2)
        ->and($stock->gross_weight)->toBe(550)
        ->and($stock->dimensions)->toEqual(['l' => 30, 'w' => 20, 'h' => 10])
        ->and($stock->orgStocks()->pluck('unit_barcode')->unique()->all())->toBe(['4006381333931'])
        ->and((float)$supplierProduct->cost)->toBe(1.5)
        ->and($supplierProduct->units_per_carton)->toBe(80)
        ->and($supplierProduct->carton_weight)->toBe(23000)
        ->and($supplierProduct->data['seed'])->toEqual([
            'recommended_price'          => 8.5,
            'recommended_rrp'            => 20,
            'recommended_price_eur'      => 10.2,
            'recommended_rrp_eur'        => 24,
            'recommended_skos_per_outer' => 1,
        ])
        ->and($supplierProduct->data['unit_expense'])->toEqual(0.1)
        ->and((float)$purchaseOrder->purchaseOrderTransactions()->where('supplier_product_id', $supplierProduct->id)->value('quantity_ordered'))->toBe(240.0)
        ->and($upload->data['purchase_orders'][strtoupper($this->organisation->code)]['lines'])->toBe(1);

    expect(App\Actions\Procurement\PurchaseOrder\UI\ShowPurchaseOrder::make()->estimatedExpenses($purchaseOrder))->toEqual(24.0);

    $orderColumn = strtoupper($this->organisation->code);
    $this->get(route('grp.supply-chain.suppliers.supplier_products.uploads.show', [$supplier->slug, $upload->id]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where("upload.purchase_orders.$orderColumn.purchase_order", $purchaseOrder->reference)
            ->where("upload.purchase_orders.$orderColumn.organisation", $this->organisation->name)
            ->where("upload.purchase_orders.$orderColumn.route.name", 'grp.org.procurement.purchase_orders.show')
            ->where("upload.purchase_orders.$orderColumn.route.parameters.purchaseOrder", $purchaseOrder->slug)
            ->etc());

    $this->get(route('grp.supply-chain.suppliers.supplier_products.index', [$supplier->slug, 'tab' => 'uploads']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('SupplyChain/SupplierProducts')
            ->where('tabs.current', 'uploads')
            ->where('uploads.data.0.id', $upload->id)
            ->where('uploads.data.0.preview_route.name', 'grp.supply-chain.suppliers.supplier_products.uploads.show')
            ->etc());

    $again = uploadSupplierProductSheet($supplier, supplierProductUploadSheet([supplierProductUploadRow(['Unit cost (Sup Cur)' => 3])]));
    $againFindings = collect($again->records()->first()->data['findings'])->pluck('level', 'code');

    expect($againFindings->all())->toMatchArray(['link_trade_unit' => 'link', 'update_supplier_product' => 'block', 'cost_change' => 'block']);
});


test('supplier product upload for a supplier in an agent puts the lines on the org supplier draft, sent through the agent', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1.0);
    $agent    = StoreAgent::make()->action(group: $this->group, modelData: Agent::factory()->definition());
    $orgAgent = StoreOrgAgent::make()->action($this->organisation, $agent, []);
    $supplier = StoreSupplier::make()->action(parent: $agent, modelData: Supplier::factory()->definition());
    $orderColumn = 'Order Cartons '.$this->organisation->code;

    $upload = uploadSupplierProductSheet($supplier, supplierProductUploadSheet([
        supplierProductUploadRow([
            $orderColumn                         => 2,
            'Family'                             => 'UPL-AGT',
            'Part reference'                     => 'UPLA-01',
            "Supplier's product code"            => 'UPLA-01',
            'Unit barcode (EAN-13, for website)' => '5901234123457',
        ]),
    ], [$orderColumn]));

    acceptSupplierProductUploadFindings($upload);
    App\Actions\SupplyChain\SupplierProduct\Upload\ImportSupplierProductUpload::run($upload->refresh());

    $orgSupplier   = App\Models\Procurement\OrgSupplier::where('organisation_id', $this->organisation->id)->where('supplier_id', $supplier->id)->firstOrFail();
    $purchaseOrder = PurchaseOrder::where('parent_type', 'OrgSupplier')->where('parent_id', $orgSupplier->id)->firstOrFail();

    expect($orgSupplier->org_agent_id)->toBe($orgAgent->id)
        ->and($purchaseOrder->state)->toBe(App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum::IN_PROCESS)
        ->and($purchaseOrder->isAgentOrder())->toBeTrue()
        ->and($purchaseOrder->agent_id)->toBe($agent->id)
        ->and($purchaseOrder->purchaseOrderTransactions()->count())->toBe(1)
        ->and(PurchaseOrder::where('parent_type', 'OrgAgent')->where('parent_id', $orgAgent->id)->exists())->toBeFalse();

    $this->patch(route('grp.models.purchase-order.submit', ['purchaseOrder' => $purchaseOrder->id]))->assertRedirect();

    expect($purchaseOrder->refresh()->state)->toBe(App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum::SUBMITTED);
});

test('supplier product upload AI checks add Jev findings and the final review, and stop when the monthly budget is spent', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1.0);
    config(['services.openrouter.api_key' => 'test-key']);
    App\Actions\Helpers\AI\AskJev::shouldRun()->andReturn([
        'unit_name_is_pack' => ['noul' => 0.95],
        'spelling'          => ['noul' => 0.75],
        'materials_misfit'  => ['noul' => 0.1],
        'numbers_odd'       => ['noul' => 0.2],
        'family_misfit'     => ['noul' => 0.1],
        'rows_shifted'      => ['noul' => 0.1],
        'tariff_misfit'     => ['noul' => 0.1],
    ]);
    Illuminate\Support\Facades\Http::fake(['openrouter.ai/api/v1/chat/completions' => Illuminate\Support\Facades\Http::response([
        'model'   => 'anthropic/claude-fable-5.1',
        'choices' => [['message' => ['content' => '{"summary": ["Row 6 names a pack."], "rows": {"6": "Name it Hemp Coaster."}}']]],
        'usage'   => ['prompt_tokens' => 1000, 'completion_tokens' => 200, 'cost' => 0.02],
    ])]);
    $supplier = StoreSupplier::make()->action(parent: $this->group, modelData: Supplier::factory()->definition());

    $upload = uploadSupplierProductSheet($supplier, supplierProductUploadSheet([supplierProductUploadRow(['Unit recommended description (website)' => 'Hemp Coasters'])]));

    $findings = collect($upload->records()->first()->data['findings'])->where('source', 'jev')->pluck('level', 'code');
    expect($upload->data['ai'])->toBe('done')
        ->and($findings->all())->toBe(['jev_unit_name_pack' => 'block', 'jev_spelling' => 'warning'])
        ->and($upload->data['review'])->toMatchArray(['status' => 'done', 'summary' => 'Row 6 names a pack.', 'rows' => ['6' => 'Name it Hemp Coaster.']])
        ->and((float)DB::table('ai_usages')->where('feature', 'ReviewSupplierProductUpload')->sum('cost'))->toBe(0.02);

    DB::table('ai_usages')->insert(['created_at' => now(), 'feature' => 'ReviewSupplierProductUpload', 'provider' => 'openrouter', 'model' => 'anthropic/claude-fable-5.1', 'prompt_tokens' => 0, 'completion_tokens' => 0, 'cost' => 40]);
    $upload = uploadSupplierProductSheet($supplier, supplierProductUploadSheet([supplierProductUploadRow(['Part reference' => 'UPLB-09', "Supplier's product code" => 'UPLB-09'])]));

    expect($upload->data['review']['status'])->toBe('off');
});

test('supplier product upload is not left waiting when the AI checks crash', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1.0);
    App\Actions\Helpers\AI\AskJev::shouldRun()->andThrow(new RuntimeException('AI gateway down'));
    $supplier = StoreSupplier::make()->action(parent: $this->group, modelData: Supplier::factory()->definition());

    $upload = uploadSupplierProductSheet($supplier, supplierProductUploadSheet([supplierProductUploadRow()]));

    expect($upload->data['ai'])->toBe('failed')
        ->and(collect($upload->records()->first()->data['findings'])->pluck('level', 'code')->all())->toMatchArray(['ai_checks_not_run' => 'block'])
        ->and(App\Actions\SupplyChain\SupplierProduct\Upload\ImportSupplierProductUpload::make()->problems($upload))->not->toContain('The AI checks are still running.');
});

test('supplier product upload from a supplier in China compares new rows with sourcing websites, caches by name and stops at the AI budget', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1.0);
    config(['services.openrouter.api_key' => 'test-key']);
    DB::table('ai_usages')->whereIn('feature', ['ReviewSupplierProductUpload', 'CheckSupplierProductUploadSourcingPrices'])->where('created_at', '>=', now()->startOfMonth())->delete();
    App\Actions\Helpers\AI\AskJev::shouldRun()->andReturn([]);
    App\Actions\SupplyChain\SupplierProduct\Upload\CheckSupplierProductUploadSourcingPrices::partialMock()->shouldReceive('sourcingDomains')->andReturn(['wholesale.example']);
    Illuminate\Support\Facades\Http::fake(fn (Illuminate\Http\Client\Request $request) => isset($request->data()['tools'])
        ? Illuminate\Support\Facades\Http::response([
            'choices' => [['message' => [
                'content'     => json_encode(['low' => 0.8, 'high' => 1.1, 'note' => 'Same tray, 100 to 1000 pieces.', 'links' => [
                    ['url' => 'https://www.wholesale.example/item/1', 'title' => 'Bamboo tray', 'price' => 0.9],
                    ['url' => 'https://www.wholesale.example/item/made-up', 'title' => 'Not in the search results', 'price' => 0.5],
                    ['url' => 'https://elsewhere.example/item/2', 'title' => 'Another website', 'price' => 0.7],
                ]]),
                'annotations' => [['type' => 'url_citation', 'url_citation' => ['url' => 'https://www.wholesale.example/item/1']], ['type' => 'url_citation', 'url_citation' => ['url' => 'https://elsewhere.example/item/2']]],
            ]]],
            'usage'   => ['prompt_tokens' => 4000, 'completion_tokens' => 300, 'cost' => 0.1],
        ])
        : Illuminate\Support\Facades\Http::response([
            'choices' => [['message' => ['content' => '{"summary": "Fine.", "rows": {}}']]],
            'usage'   => ['prompt_tokens' => 1000, 'completion_tokens' => 100, 'cost' => 0.02],
        ]));
    $supplier = StoreSupplier::make()->action(parent: $this->group, modelData: array_merge(Supplier::factory()->definition(), [
        'address' => array_merge(App\Models\Helpers\Address::factory()->definition(), ['country_id' => App\Models\Helpers\Country::where('code', 'CN')->value('id'), 'country_code' => 'CN']),
    ]));
    $name = 'Bamboo Serving Tray '.Str::random(6);

    $upload = uploadSupplierProductSheet($supplier, supplierProductUploadSheet([supplierProductUploadRow(['Unit recommended description (website)' => $name, 'Unit barcode (EAN-13, for website)' => 'auto'])]));
    $sourcing = $upload->records()->first()->data['sourcing'];

    expect($upload->data['sourcing'])->toBe('done')
        ->and($upload->data['review']['cost'])->toBe(0.02)
        ->and($sourcing)->toMatchArray(['status' => 'overpaying', 'low' => 0.8, 'high' => 1.1, 'currency' => 'USD', 'note' => 'Same tray, 100 to 1000 pieces.'])
        ->and(collect($sourcing['links'])->pluck('url')->all())->toBe(['https://www.wholesale.example/item/1'])
        ->and(App\Actions\SupplyChain\SupplierProduct\Upload\ImportSupplierProductUpload::make()->problems($upload))->not->toContain('The AI checks are still running.');

    $upload = uploadSupplierProductSheet($supplier, supplierProductUploadSheet([supplierProductUploadRow(['Part reference' => 'UPLB-03', "Supplier's product code" => 'UPLB-03', 'Unit recommended description (website)' => $name, 'Unit cost (Sup Cur)' => 0.95, 'Unit barcode (EAN-13, for website)' => 'auto'])]));

    expect($upload->records()->first()->data['sourcing']['status'])->toBe('ok')
        ->and(Illuminate\Support\Facades\Http::recorded(fn ($request) => isset($request->data()['tools']))->count())->toBe(1);

    DB::table('ai_usages')->insert(['created_at' => now(), 'feature' => 'CheckSupplierProductUploadSourcingPrices', 'provider' => 'openrouter', 'model' => 'anthropic/claude-fable-5.1', 'prompt_tokens' => 0, 'completion_tokens' => 0, 'cost' => 40]);
    $upload = uploadSupplierProductSheet($supplier, supplierProductUploadSheet([supplierProductUploadRow(['Part reference' => 'UPLB-04', "Supplier's product code" => 'UPLB-04', 'Unit recommended description (website)' => $name.' XL', 'Unit barcode (EAN-13, for website)' => 'auto'])]));

    expect($upload->data['review']['status'])->toBe('off')
        ->and($upload->data['sourcing'])->toBe('budget')
        ->and($upload->records()->first()->data)->not->toHaveKey('sourcing');

    DB::table('ai_usages')->whereIn('feature', ['ReviewSupplierProductUpload', 'CheckSupplierProductUploadSourcingPrices'])->where('created_at', '>=', now()->startOfMonth())->delete();
});

test('sourcing price verdict: within range, more than 30% above, well below, nothing found', function () {
    $verdict = fn (?float $low, ?float $high, float $cost) => App\Actions\SupplyChain\SupplierProduct\Upload\CheckSupplierProductUploadSourcingPrices::verdict(['low' => $low, 'high' => $high, 'links' => [], 'note' => null], $cost, 'USD')['status'];

    expect($verdict(1.0, 2.0, 2.5))->toBe('ok')
        ->and($verdict(1.0, 2.0, 2.7))->toBe('overpaying')
        ->and($verdict(1.0, 2.0, 0.4))->toBe('cheap')
        ->and($verdict(null, null, 1.0))->toBe('unknown');
});

test('supplier product upload import errors shown to staff never carry urls or keys', function () {
    $errorText = fn (Throwable $e) => (fn () => $this->errorText($e))->call(App\Actions\SupplyChain\SupplierProduct\Upload\ImportSupplierProductUpload::make());

    expect($errorText(new RuntimeException('cURL error 28: timed out for https://rates.example.com/v1/historical?api_key=SECRET123&date=2026-10-07')))
        ->toBe('An outside service did not answer in time, please try again.')
        ->and($errorText(new RuntimeException('Bad answer from https://rates.example.com/x?api_key=SECRET123')))
        ->not->toContain('SECRET123')
        ->and($errorText(Illuminate\Validation\ValidationException::withMessages(['code' => 'Code taken.'])))
        ->toBe('Code taken.');
});

test('UI supplier product upload preview shows the rows and saves decisions', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1.0);
    $supplier = StoreSupplier::make()->action(parent: $this->group, modelData: Supplier::factory()->definition());
    $upload   = uploadSupplierProductSheet($supplier, supplierProductUploadSheet([supplierProductUploadRow(['Unit barcode (EAN-13, for website)' => null])]));
    $record   = $upload->records()->first();

    $this->withoutVite()
        ->get(route('grp.supply-chain.suppliers.supplier_products.uploads.show', ['supplier' => $supplier->slug, 'upload' => $upload->id]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('SupplyChain/SupplierProductUploadPreview')
            ->where('upload.state', 'waiting_confirmation')
            ->has('rows', 1)
            ->where('rows.0.values.part_reference', 'UPLB-01'));

    $this->patch(route('grp.models.supplier_product_upload.record.update', ['upload' => $upload->id, 'record' => $record->id]), [
        'decisions' => ['no_barcode' => true],
        'sko_name'  => 'Pair of Hemp Forest Bags',
    ])->assertRedirect();

    $record->refresh();
    expect($record->data['decisions']['no_barcode']['accepted'])->toBeTrue()
        ->and($record->data['decisions']['no_barcode']['user_id'])->toBe($this->adminGuest->getUser()->id)
        ->and($record->values['sko_name'])->toBe('Pair of Hemp Forest Bags');

    $this->post(route('grp.models.supplier_product_upload.cancel', ['upload' => $upload->id]))->assertRedirect();
    expect($upload->refresh()->state)->toBe(App\Enums\Helpers\Import\UploadStateEnum::CANCELLED);
});

function supplierProductFormInput(array $overrides = []): array
{
    return collect(supplierProductUploadRow())
        ->mapWithKeys(fn ($value, string $heading) => [App\Enums\SupplyChain\SupplierProductUpload\SupplierProductSheetColumnEnum::fromHeading($heading)->value => (string)$value])
        ->merge($overrides)
        ->all();
}

test('UI new supplier product form checks like an upload row and creates the trade unit, barcode, SKO and supplier product', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1.0);
    $supplier = StoreSupplier::make()->action(parent: $this->group, modelData: Supplier::factory()->definition());
    $input    = supplierProductFormInput([
        'family'         => 'UPL-FORM',
        'part_reference' => 'UPLF-01',
        'supplier_code'  => '',
        'unit_label'     => '20x',
        'unit_barcode'   => '5901234123464',
    ]);

    $this->withoutVite()
        ->get(route('grp.supply-chain.suppliers.supplier_products.create', ['supplier' => $supplier->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('SupplyChain/SupplierProductCreate')
            ->has('sections', 4)
            ->where('sections.0.fields.0.key', 'family'));

    $checkRoute = route('grp.models.supplier.supplier-product.check_form', ['supplier' => $supplier->id]);
    $storeRoute = route('grp.models.supplier.supplier-product.store_from_form', ['supplier' => $supplier->id]);

    $check    = $this->postJson($checkRoute, $input)->assertOk();
    $findings = collect($check->json('findings'))->pluck('level', 'code');

    expect($findings->all())->toMatchArray([
        'family_new'                        => 'warning',
        'supplier_code_from_part_reference' => 'warning',
        'unit_label_odd'                    => 'block',
        'sko_name_suggested'                => 'warning',
    ])
        ->and($check->json('values.sko_name'))->toBe('Pack of 2 Hemp Forest Bag')
        ->and($check->json('review'))->toBeNull();

    $symbols = collect($this->postJson($checkRoute, ['recommended_price_eur' => '€10.20', 'recommended_rrp_eur' => '£24'] + $input)->json());
    expect($symbols['values']['recommended_price_eur'])->toEqual(10.2)
        ->and(collect($symbols['findings'])->pluck('level', 'code')->all())->toMatchArray(['currency_recommended_rrp_eur' => 'error'])
        ->and(collect($symbols['findings'])->pluck('code'))->not->toContain('currency_recommended_price_eur');

    $this->post($storeRoute, $input + ['accepted' => ['unit_label_odd']])->assertSessionHasErrors('review');

    App\Actions\Helpers\AI\AskJev::shouldRun()->once()->andReturn(['unit_name_is_pack' => ['noul' => 0.9]]);
    $reviewed = $this->postJson($checkRoute, $input + ['start_review' => true])->assertOk();
    $reviewId = $reviewed->json('review.id');
    $findings = collect($reviewed->json('findings'))->pluck('level', 'code');

    expect($reviewed->json('review.status'))->toBe('done')
        ->and($findings->all())->toMatchArray(['jev_unit_name_pack' => 'block', 'unit_label_odd' => 'block']);

    $renamed = collect($this->postJson($checkRoute, ['unit_name' => 'Hemp Forest Tote', 'review' => $reviewId] + $input)->json('findings'))->keyBy('code');
    expect($renamed['jev_unit_name_pack']['message'])->toStartWith('About the earlier value:')
        ->and($renamed)->toHaveKey('unit_label_odd');

    $this->post($storeRoute, ['part_reference' => 'UPLF-99', 'supplier_code' => 'UPLF-99', 'review' => $reviewId, 'accepted' => ['unit_label_odd', 'jev_unit_name_pack']] + $input)
        ->assertSessionHasErrors('review');

    $this->post($storeRoute, $input + ['review' => $reviewId, 'accepted' => ['unit_label_odd']])->assertSessionHasErrors('findings');
    expect(TradeUnit::where('group_id', $this->group->id)->where('code', 'UPLF-01')->exists())->toBeFalse();

    $this->post($storeRoute, $input + [
        'review'   => $reviewId,
        'sko_name' => 'Pair of Hemp Forest Bags',
        'accepted' => $findings->filter(fn (string $level) => in_array($level, ['block', 'link']))->keys()->all(),
    ])->assertSessionHasNoErrors()->assertRedirect();

    $this->post($storeRoute, $input + ['review' => $reviewId])->assertSessionHasErrors('review');

    $tradeUnit       = TradeUnit::where('group_id', $this->group->id)->where('code', 'UPLF-01')->firstOrFail();
    $stock           = $tradeUnit->stocks()->firstOrFail();
    $supplierProduct = SupplierProduct::where('supplier_id', $supplier->id)->where('code', 'UPLF-01')->firstOrFail();

    $orgSupplierProductIds = $supplierProduct->orgSupplierProducts()->pluck('id');

    expect($supplierProduct->data['decisions']['jev_unit_name_pack']['user_id'])->toBe($this->adminGuest->getUser()->id)
        ->and($supplierProduct->data['decisions'])->toHaveKeys(['unit_label_odd', 'jev_unit_name_pack'])
        ->and($stock->orgStocks()->count())->toBe($orgSupplierProductIds->count())
        ->and(App\Models\Inventory\OrgStockHasOrgSupplierProduct::whereIn('org_supplier_product_id', $orgSupplierProductIds)->whereIn('org_stock_id', $stock->orgStocks()->pluck('id'))->where('status', true)->count())->toBe($orgSupplierProductIds->count());

    expect($tradeUnit->barcode)->toBe('5901234123464')
        ->and($tradeUnit->type)->toBe('20x')
        ->and($tradeUnit->tradeUnitFamily?->code)->toBe('UPL-FORM')
        ->and($stock->name)->toBe('Pair of Hemp Forest Bags')
        ->and($stock->stockFamily?->code)->toBe('UPL-FORM')
        ->and((int)$stock->packed_in)->toBe(2)
        ->and($supplierProduct->units_per_carton)->toBe(80)
        ->and($supplierProduct->data['seed']['recommended_price'])->toEqual(8.5)
        ->and($supplierProduct->tradeUnits()->pluck('trade_units.id')->all())->toBe([$tradeUnit->id])
        ->and($supplierProduct->orgSupplierProducts()->count())->toBe($supplier->orgSuppliers()->count());

    $again = collect($this->postJson($checkRoute, $input)->json('findings'))->pluck('level', 'code');
    expect($again->all())->toMatchArray(['link_trade_unit' => 'link', 'update_supplier_product' => 'block']);
});

test('UI show suppliers product in supplier', function (SupplierProduct $supplierProduct) {
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.supply-chain.suppliers.supplier_products.show', [
        $supplierProduct->supplier->slug,
        $supplierProduct->slug
    ]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('SupplyChain/SupplierProduct')
            ->has('title')
            ->has('pageHead')
            ->where('pageHead.actions.0.route.name', 'grp.supply-chain.suppliers.supplier_products.edit')
            ->has('tabs')
            ->has('breadcrumbs', 4);
    });
})->depends('create supplier product independent supplier');

test('UI show supplier product in supply chain', function (SupplierProduct $supplierProduct) {
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.supply-chain.supplier_products.show', [
        $supplierProduct->slug
    ]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page->component('SupplyChain/SupplierProduct');
    });

    $showcase = GetSupplierProductShowcase::run($supplierProduct);
    expect($showcase['composition'])->toBeArray();
})->depends('create supplier product independent supplier');


test('a supply chain worker edits a supplier product but only a manager discontinues it', function (SupplierProduct $supplierProduct) {
    $user          = $this->adminGuest->getUser();
    $originalRoles = $user->roles()->pluck('name')->all();
    setPermissionsTeamId($user->group_id);
    $user->syncRoles([RolesEnum::getRoleName(RolesEnum::SUPPLY_CHAIN_WORKER->value, $this->group)]);
    Cache::tags('auth-user:'.$user->id)->flush();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    actingAs($user->refresh());

    patch(route('grp.models.supplier-product.update', $supplierProduct->id), ['name' => 'Worker renamed'])->assertRedirect();
    patch(route('grp.models.supplier-product.update', $supplierProduct->id), ['state' => SupplierProductStateEnum::DISCONTINUED->value])->assertForbidden();
    expect($supplierProduct->refresh()->state)->not->toBe(SupplierProductStateEnum::DISCONTINUED);

    $user->syncRoles($originalRoles);
    Cache::tags('auth-user:'.$user->id)->flush();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
})->depends('create supplier product independent supplier');

test('create trade unit', function () {
    $tradeUnit = StoreTradeUnit::make()->action(
        $this->group,
        TradeUnit::factory()->definition()
    );
    $this->assertModelExists($tradeUnit);

    return $tradeUnit;
});

test('create org-agent', function ($agent) {
    $orgAgent = StoreOrgAgent::make()->action(
        $this->organisation,
        $agent,
        []
    );

    expect($orgAgent)->toBeInstanceOf(OrgAgent::class)
        ->and($orgAgent->stats)->toBeInstanceOf(OrgAgentStats::class);

    return $orgAgent;
})->depends('create agent');

test('update org-agent', function ($orgAgent) {
    $updatedOrgAgent = UpdateOrgAgent::make()->action(
        $orgAgent,
        [
            'status' => false
        ]
    );

    expect($updatedOrgAgent)->toBeInstanceOf(OrgAgent::class)
        ->and($updatedOrgAgent->status)->toBeFalse();

    return $updatedOrgAgent;
})->depends('create org-agent');

test('the independent supplier is propagated as an org-supplier', function ($supplier) {
    $orgSupplier = $supplier->orgSuppliers()->where('organisation_id', $this->organisation->id)->first();

    expect($orgSupplier)->toBeInstanceOf(OrgSupplier::class)
        ->and($orgSupplier->stats)->toBeInstanceOf(OrgSupplierStats::class);

    return $orgSupplier;
})->depends('create independent supplier');

test('update org-supplier', function ($orgSupplier) {
    $updatedOrgSupplier = UpdateOrgSupplier::make()->action(
        $orgSupplier,
        [
            'status' => false
        ]
    );

    expect($updatedOrgSupplier)->toBeInstanceOf(OrgSupplier::class)
        ->and($updatedOrgSupplier->status)->toBeFalse();

    return $updatedOrgSupplier;
})->depends('the independent supplier is propagated as an org-supplier');

test('delete agent', function () {
    /** @var Agent $agent */
    $agent = Agent::first();

    $deletedAgent = DeleteAgent::make()->action($agent);

    expect(Agent::find($agent->id))->toBeNull();

    return $deletedAgent;
});

test('delete supplier', function () {
    /** @var Supplier $supplier */
    $supplier = Supplier::first();

    $deletedSupplier = DeleteSupplier::make()->action($supplier);

    expect(Supplier::find($supplier->id))->toBeNull();

    return $deletedSupplier;
});

test('UI Index suppliers', function () {
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.supply-chain.suppliers.index'));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('SupplyChain/Suppliers')
            ->where('title', 'Free Suppliers')
            ->has('pageHead')
            ->has('pageHead.actions', 1)
            ->where('pageHead.actions.0.route.name', 'grp.supply-chain.suppliers.create')
            ->has('data')
            ->where('queryBuilderProps.default.elementGroups', [])
            ->has('breadcrumbs', 3);
    });
});

test('UI Index agent suppliers', function () {
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.supply-chain.agent_suppliers.index'));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('SupplyChain/AgentSuppliers')
            ->where('title', 'Agent Suppliers')
            ->has('data')
            ->where('queryBuilderProps.default.elementGroups', [])
            ->has('breadcrumbs', 3);
    });
});

test('free and agent supplier routes return separate datasets', function () {
    $freeCode = fake()->unique()->numerify('SPLIT-FREE-#####');
    $freeSupplier = StoreSupplier::make()->action(
        parent: $this->group,
        modelData: array_merge(Supplier::factory()->definition(), ['code' => $freeCode, 'status' => true]),
    );

    $agent = StoreAgent::make()->action(
        group: $this->group,
        modelData: Agent::factory()->definition(),
    );
    $agentCode = fake()->unique()->numerify('SPLIT-AGENT-#####');
    $agentSupplier = StoreSupplier::make()->action(
        parent: $agent,
        modelData: array_merge(Supplier::factory()->definition(), ['code' => $agentCode, 'status' => true]),
    );

    $this->get(route('grp.supply-chain.suppliers.index', ['filter[global]' => $freeCode]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('data.data', fn ($suppliers) => collect($suppliers)->pluck('id')->contains($freeSupplier->id))
            ->etc());
    $this->get(route('grp.supply-chain.suppliers.index', ['filter[global]' => $agentCode]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('data.data', fn ($suppliers) => !collect($suppliers)->pluck('id')->contains($agentSupplier->id))
            ->etc());
    $this->get(route('grp.supply-chain.agent_suppliers.index', ['filter[global]' => $agentCode]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('data.data', fn ($suppliers) => collect($suppliers)->pluck('id')->contains($agentSupplier->id))
            ->etc());
    $this->get(route('grp.supply-chain.agent_suppliers.index', ['filter[global]' => $freeCode]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('data.data', fn ($suppliers) => !collect($suppliers)->pluck('id')->contains($freeSupplier->id))
            ->etc());
});

test('legacy through agent supplier filter redirects to agent suppliers', function () {
    $this->get(route('grp.supply-chain.suppliers.index', ['elements[type]' => 'through_agent']))
        ->assertRedirect(route('grp.supply-chain.agent_suppliers.index', ['sort' => 'code']));
});

test('majordomo redirect supplier link', function () {
    $freeSupplier = StoreSupplier::make()->action(
        parent: $this->group,
        modelData: Supplier::factory()->definition()
    );
    $agent        = StoreAgent::make()->action(
        group: $this->group,
        modelData: Agent::factory()->definition()
    );
    $agentSupplier = StoreSupplier::make()->action(
        parent: $agent,
        modelData: Supplier::factory()->definition()
    );

    $this->get(route('grp.majordomo.redirect_supplier', [$freeSupplier->id]))
        ->assertRedirect(route('grp.supply-chain.suppliers.show', [$freeSupplier->slug]));

    $this->get(route('grp.majordomo.redirect_supplier', [$agentSupplier->id]))
        ->assertRedirect(route('grp.supply-chain.agents.show.suppliers.show', [$agent->slug, $agentSupplier->slug]));
});

test('majordomo redirect supplier link sends users without supply chain access to their organisation procurement', function () {
    $supplier = StoreSupplier::make()->action(
        parent: $this->group,
        modelData: Supplier::factory()->definition()
    );
    $orgSupplier = $supplier->orgSuppliers()->where('organisation_id', $this->organisation->id)->firstOrFail();

    $procurementUser = Mockery::mock(User::class)->makePartial();
    $procurementUser->shouldReceive('authTo')->with('supply-chain.view')->andReturnFalse();
    $procurementUser->shouldReceive('authTo')->andReturnUsing(fn (string $permission) => $permission === "procurement.{$this->organisation->id}.view");

    expect(RedirectSupplierLink::run($supplier, $procurementUser)->getTargetUrl())
        ->toBe(route('grp.org.procurement.org_suppliers.show', [$this->organisation->slug, $orgSupplier->slug]));
});

test('majordomo redirect supplier product link', function () {
    $freeSupplier = StoreSupplier::make()->action(
        parent: $this->group,
        modelData: Supplier::factory()->definition()
    );
    $freeProductData = SupplierProduct::factory()->definition();
    data_set($freeProductData, 'stock_id', $this->stocks[0]->id);
    $freeProduct = StoreSupplierProduct::make()->action($freeSupplier, $freeProductData);

    $agent = StoreAgent::make()->action(
        group: $this->group,
        modelData: Agent::factory()->definition()
    );
    $agentSupplier = StoreSupplier::make()->action(
        parent: $agent,
        modelData: Supplier::factory()->definition()
    );
    $agentProductData = SupplierProduct::factory()->definition();
    data_set($agentProductData, 'stock_id', $this->stocks[1]->id);
    $agentProduct = StoreSupplierProduct::make()->action($agentSupplier, $agentProductData);

    $this->get(route('grp.majordomo.redirect_supplier_product', [$freeProduct->id]))
        ->assertRedirect(route('grp.supply-chain.supplier_products.show', [$freeProduct->slug]));

    $this->get(route('grp.majordomo.redirect_supplier_product', [$agentProduct->id]))
        ->assertRedirect(route('grp.supply-chain.agents.show.supplier_products.show', [$agent->slug, $agentProduct->slug]));
});

test('UI create supplier', function () {
    $this->withoutExceptionHandling();
    $supplier = Supplier::first();
    $response = $this->get(route('grp.supply-chain.suppliers.create'));

    $response->assertInertia(function (AssertableInertia $page) use ($supplier) {
        $page
            ->component('CreateModel')
            ->has('title')
            ->has('pageHead')
            ->has('formData')
            ->where('formData.blueprint.2.fields.code.takenValues.'.strtolower($supplier->code), $supplier->name ?: $supplier->code)
            ->has('breadcrumbs', 4);
    });
});

test('UI Index suppliers product in supplier', function () {
    $this->withoutExceptionHandling();
    $supplier = Supplier::first();
    $response = $this->get(route('grp.supply-chain.suppliers.supplier_products.index', [
        $supplier->slug
    ]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('SupplyChain/SupplierProducts')
            ->has('title')
            ->has('pageHead')
            ->has('data')
            ->has('upload_spreadsheet')
            ->has('breadcrumbs', 4);
    });
});

test('UI Index free supplier products', function () {
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.supply-chain.supplier_products.free'));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('SupplyChain/SupplierProducts')
            ->where('title', 'Free Supplier Products')
            ->has('pageHead.subNavigation', 3)
            ->has('data')
            ->has('breadcrumbs', 3);
    });
});

test('UI Index supplier products in agents', function () {
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.supply-chain.supplier_products.in_agents'));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('SupplyChain/SupplierProducts')
            ->where('title', 'Agents Supplier Products')
            ->has('pageHead.subNavigation', 3)
            ->has('data')
            ->has('breadcrumbs', 3);
    });
});

test('UI supply chain overview', function () {
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.supply-chain.overview'));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('SupplyChain/SupplyChainDashboard')
            ->has('title')
            ->has('pageHead')
            ->has('dashboardCards', 5)
            ->where('dashboardCards.0.route.name', 'grp.supply-chain.agents.index')
            ->where('dashboardCards.1.route.name', 'grp.supply-chain.suppliers.index')
            ->where('dashboardCards.1.metrics.0.route.name', 'grp.supply-chain.agent_suppliers.index')
            ->missing('dashboardCards.1.route.parameters._query.elements[type]')
            ->missing('dashboardCards.1.metrics.0.route.parameters._query.elements[type]')
            ->where('dashboardCards.2.route.name', 'grp.supply-chain.supplier_products.index')
            ->where('dashboardCards.3.route.name', 'grp.supply-chain.control.dashboard')
            ->where('dashboardCards.4.route.name', 'grp.supply-chain.shopping_list.board')
            ->missing('staleOrders')
            ->missing('search_demand')
            ->missing('poJourney')
            ->missing('stockOuts')
            ->has('breadcrumbs', 3)
            ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                ->where('stockOuts.period', '1y')
                ->has('stockOuts.series')
                ->has('stockOuts.organisations')
                ->has('stockLevelsByOrganisation.0.levels', 8)
                ->where('stockLevelsByOrganisation.0.levels.0.bucket', 'out')
                ->where('stockLevelsByOrganisation.0.levels.0.route.name', 'grp.org.procurement.stock_cover.index')
                ->where('poJourney.route.name', 'grp.supply-chain.dashboard')
                ->has('poJourney.summary.open')
                ->has('poJourney.summary.overdue')
                ->has('poJourney.blockages'));
    });
});

test('UI supply chain overview stock levels match the stock cover buckets and pipeline for every source', function () {
    $this->withoutExceptionHandling();
    $organisations = GetStockOutsHistory::make()->organisations(group());

    foreach ([null, ...array_keys(GetOrganisationStockCoverBuckets::SOURCES)] as $source) {
        $this->get(route('grp.supply-chain.overview', ['source' => $source]))
            ->assertInertia(fn (AssertableInertia $page) => $page->loadDeferredProps(function (AssertableInertia $reload) use ($organisations, $source) {
                $reload->has('stockLevelsByOrganisation', $organisations->count());

                foreach ($organisations->values() as $index => $organisation) {
                    $pipeline = GetStockOutsPipeline::run($organisation, $source);
                    $reload->where("stockLevelsByOrganisation.$index.slug", $organisation->slug);

                    foreach (GetOrganisationStockCoverBuckets::run($organisation, null, $source) as $bucketIndex => $bucket) {
                        $reload->where("stockLevelsByOrganisation.$index.levels.$bucketIndex.count", $bucket['count'])
                            ->where("stockLevelsByOrganisation.$index.levels.$bucketIndex.in_transit", $pipeline[$bucket['bucket']]['in_transit'] ?? 0);
                    }
                }
            }));
    }
});

test('supply chain navigation separates agent suppliers from free suppliers', function () {
    $navigation = GetGroupNavigation::run($this->adminGuest->getUser());

    expect(data_get($navigation, 'supply-chain.route.name'))->toBe('grp.supply-chain.overview')
        ->and(data_get($navigation, 'supply-chain.topMenu.subSections.0.route.name'))->toBe('grp.supply-chain.overview')
        ->and(data_get($navigation, 'supply-chain.topMenu.subSections.1.route.name'))->toBe('grp.supply-chain.dashboard')
        ->and(data_get($navigation, 'supply-chain.topMenu.subSections.3.route'))->toBe([
        'name' => 'grp.supply-chain.agent_suppliers.index',
    ])->and(data_get($navigation, 'supply-chain.topMenu.subSections.4.route'))->toBe([
        'name'       => 'grp.supply-chain.suppliers.index',
        'parameters' => [
            '_query' => [
                'sort' => 'code',
            ],
        ],
    ]);
});

test('UI supply chain control', function () {
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.supply-chain.control.dashboard'));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('SupplyChain/SupplyChainControl')
            ->has('title')
            ->has('pageHead')
            ->has('breadcrumbs', 3)
            ->has('stalled_purchase_orders')
            ->has('deposits_at_risk')
            ->missing('stalled_aspos')
            ->missing('pos_without_action')
            ->has('agent_scorecard');
    });
});

test('UI supply chain PO journey', function (Supplier $supplier) {
    $this->withoutExceptionHandling();
    StoreSupplierProduct::make()->action($supplier, array_merge(SupplierProduct::factory()->definition(), ['stock_id' => $this->stocks[1]->id]));
    $orgSupplier   = $supplier->orgSuppliers()->where('organisation_id', $this->organisation->id)->first();
    $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());

    expect($purchaseOrder->buyer_id)->toBe($this->adminGuest->getUser()->id);

    $this->get(route('grp.supply-chain.dashboard', ['journey' => 'supplier']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('SupplyChain/SupplyChainPurchaseOrderJourney')
            ->loadDeferredProps('journey', fn (AssertableInertia $reload) => $reload
                ->has('filters.buyer')
                ->has('blockages')
                ->has('quickStats')
                ->where('active.journey', 'supplier')
                ->where('ribbons', fn ($ribbons) => collect($ribbons)->contains(
                    fn ($ribbon) => $ribbon['reference'] === $purchaseOrder->reference && $ribbon['current_stage'] === 'po_created'
                ))));

    $this->patch(route('grp.models.purchase-order.journey_stage', ['purchaseOrder' => $purchaseOrder->id]), [
        'stage' => 'production',
        'date'  => now()->toDateString(),
    ])->assertRedirect();

    expect($purchaseOrder->fresh()->produced_at)->not->toBeNull();

    $this->get(route('grp.supply-chain.dashboard', ['search' => $purchaseOrder->reference]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->loadDeferredProps('journey', fn (AssertableInertia $reload) => $reload
                ->where('ribbons.0.reference', $purchaseOrder->reference)
                ->where('ribbons.0.segments', fn ($segments) => collect($segments)->firstWhere('key', 'production')['state'] === 'done')));

    $partial = $this->withHeaders([
        'X-Inertia'                   => 'true',
        'X-Inertia-Version'           => \Illuminate\Support\Facades\Vite::manifestHash('grp'),
        'X-Inertia-Partial-Component' => 'SupplyChain/SupplyChainPurchaseOrderJourney',
        'X-Inertia-Partial-Data'      => 'filters,active,summary,blockages,quickStats,ribbons,pagination',
    ])->get(route('grp.supply-chain.dashboard', ['search' => $purchaseOrder->reference, 'page' => 1]));

    $partial->assertOk();
    expect($partial->json('props'))->toHaveKeys(['filters', 'active', 'summary', 'blockages', 'quickStats', 'ribbons', 'pagination'])
        ->and($partial->json('deferredProps'))->toBeNull();
})->depends('create independent supplier 2');

test('UI create suppliers product in supplier', function () {
    $this->withoutExceptionHandling();
    $supplier = Supplier::first();
    $response = $this->get(route('grp.supply-chain.suppliers.supplier_products.create', [
        $supplier->slug
    ]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('SupplyChain/SupplierProductCreate')
            ->has('title')
            ->has('pageHead')
            ->has('sections')
            ->has('breadcrumbs', 5);
    });
});

test('UI Index suppliers product in agent', function () {
    $this->withoutExceptionHandling();
    $supplier = Supplier::first();
    $supplier->update(['agent_id' => Agent::first()->id]);
    $response = $this->get(route('grp.supply-chain.agents.show.supplier_products.index', [
        $supplier->agent->slug,
        $supplier->slug
    ]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('SupplyChain/SupplierProducts')
            ->has('title')
            ->has('pageHead')
            ->has('data')
            ->has('upload_spreadsheet')
            ->has('breadcrumbs', 4);
    });
});

test('UI show free supplier has direct procurement navigation', function () {
    $supplier = StoreSupplier::make()->action(
        parent: $this->group,
        modelData: Supplier::factory()->definition(),
    );
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.supply-chain.suppliers.show', [$supplier->slug]));
    $response->assertInertia(function (AssertableInertia $page) use ($supplier) {
        $page
            ->component('SupplyChain/Supplier')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $supplier->name)
                    ->where('subNavigation.2.route.name', 'grp.supply-chain.suppliers.purchase_orders.index')
                    ->where('subNavigation.3.route.name', 'grp.supply-chain.suppliers.stock_deliveries.index')
                    ->etc()
            )
            ->where('showcase.stats.1.route.name', 'grp.supply-chain.suppliers.purchase_orders.index')
            ->has('tabs');
    });
});

test('UI show agent supplier lists its purchase orders', function () {
    $agent = StoreAgent::make()->action(
        group: $this->group,
        modelData: Agent::factory()->definition(),
    );
    $supplier = StoreSupplier::make()->action(
        parent: $agent,
        modelData: Supplier::factory()->definition(),
    );

    $this->get(route('grp.supply-chain.suppliers.show', [$supplier->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('pageHead.subNavigation.2.route.name', 'grp.supply-chain.suppliers.purchase_orders.index')
            ->missing('pageHead.subNavigation.4')
            ->where('showcase.stats.1.route.name', 'grp.supply-chain.suppliers.purchase_orders.index')
            ->etc());

    StoreOrgSupplier::make()->action($this->organisation, $supplier);

    $this->get(route('grp.supply-chain.suppliers.purchase_orders.index', [$supplier->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Procurement/PurchaseOrders')
            ->has('pageHead.actions', 1)
            ->where('pageHead.actions.0.route.name', 'grp.models.org-supplier.purchase-order.store')
            ->etc());
});

test('UI index purchase orders in free supplier', function () {
    $supplier = StoreSupplier::make()->action(
        parent: $this->group,
        modelData: Supplier::factory()->definition(),
    );
    $this->withoutExceptionHandling();

    $this->get(route('grp.supply-chain.suppliers.purchase_orders.index', [$supplier->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Procurement/PurchaseOrders')
            ->where('pageHead.subNavigation.2.route.name', 'grp.supply-chain.suppliers.purchase_orders.index')
            ->has('title')
            ->has('breadcrumbs')
            ->has('data'));
});

test('UI index stock deliveries in agent', function () {
    $agent = Agent::first();
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.supply-chain.agents.show.stock_deliveries.index', [$agent->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Procurement/StockDeliveries')
            ->has('title')
            ->has('breadcrumbs')
            ->has('data');
    });
});

test('UI index stock deliveries in supplier', function () {
    $supplier = Supplier::first();
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.supply-chain.suppliers.stock_deliveries.index', [$supplier->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Procurement/StockDeliveries')
            ->has('title')
            ->has('breadcrumbs')
            ->has('data');
    });
});

test('UI show supplier navigation follows the free bucket', function () {
    $this->withoutExceptionHandling();

    $makeSupplier = function (string $code, ?Agent $agent = null) {
        $data = array_merge(Supplier::factory()->definition(), ['code' => $code, 'name' => $code.' name']);

        return StoreSupplier::make()->action(parent: $agent ?? $this->group, modelData: $data);
    };

    $agent = Agent::first();

    $first    = $makeSupplier('NAVSUPA');
    $inAgent  = $agent ? $makeSupplier('NAVSUPB', $agent) : null;
    $middle   = $makeSupplier('NAVSUPC');
    $last     = $makeSupplier('NAVSUPD');

    $this->get(route('grp.supply-chain.suppliers.show', [$middle->slug]).'?bucket=free')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('navigation.previous.label', $first->code)
            ->where('navigation.next.label', $last->code)
            ->etc()
    );

    if ($inAgent) {
        expect($inAgent->agent_id)->not->toBeNull();
    }
});

test('UI edit supplier', function () {
    $agent    = Agent::first();
    $supplier = $agent->suppliers()->first();
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.supply-chain.agents.show.suppliers.edit', [$agent->slug, $supplier->slug]));
    $response->assertInertia(function (AssertableInertia $page) use ($supplier) {
        $page
            ->component('EditModel')
            ->has('title')
            ->has('breadcrumbs', 4)
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $supplier->code)
                    ->etc()
            )
            ->has('formData');
    });
});

test('UI assignable suppliers list free and other agents suppliers, not the agent own', function () {
    $this->withoutExceptionHandling();

    $agent      = StoreAgent::make()->action(group: $this->group, modelData: Agent::factory()->definition());
    $otherAgent = StoreAgent::make()->action(group: $this->group, modelData: Agent::factory()->definition());

    $makeSupplier = fn (string $code, Agent|\App\Models\SysAdmin\Group $parent) => StoreSupplier::make()->action(
        parent: $parent,
        modelData: array_merge(Supplier::factory()->definition(), ['code' => $code, 'name' => $code.' name'])
    );

    $free    = $makeSupplier('ASSIGNFREE', $this->group);
    $stolen  = $makeSupplier('ASSIGNSTEAL', $otherAgent);
    $ownOne  = $makeSupplier('ASSIGNOWN', $agent);

    $url = route('grp.supply-chain.agents.show.suppliers.assignable', [$agent->slug]);

    $response = $this->get($url);
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('SupplyChain/AssignableSuppliers')
        ->where('agent.id', $agent->id)
        ->has('data'));

    $rows = collect($response->viewData('page')['props']['data']['data'])->keyBy('code');

    expect($rows->keys())->toContain('ASSIGNFREE', 'ASSIGNSTEAL')
        ->and($rows->keys())->not->toContain('ASSIGNOWN')
        ->and($rows['ASSIGNFREE']['agent_code'])->toBeNull()
        ->and($rows['ASSIGNSTEAL']['agent_code'])->toBe($otherAgent->code);

    $expectedJoining = \App\Models\Procurement\OrgSupplier::query()
        ->where('supplier_id', $free->id)
        ->where('status', true)
        ->whereNotIn('organisation_id', $agent->orgAgents()->pluck('organisation_id'))
        ->with('organisation')
        ->get()
        ->pluck('organisation.name')
        ->sort()
        ->implode(', ');

    expect($rows['ASSIGNFREE']['organisations_joining_agent'])->toBe($expectedJoining ?: null);

    $country       = $free->refresh()->location[0];
    $filteredCodes = collect(
        $this->get($url.'?filter[country]='.$country)->viewData('page')['props']['data']['data']
    )->pluck('code');

    expect($filteredCodes)->toContain('ASSIGNFREE')
        ->and($filteredCodes->every(fn ($code) => Supplier::where('code', $code)->first()->location[0] === $country))->toBeTrue();

    expect($ownOne->refresh()->agent_id)->toBe($agent->id)
        ->and($stolen->refresh()->agent_id)->toBe($otherAgent->id);
});

test('UI add supplier moves free and other agents suppliers to the agent', function () {
    $this->withoutExceptionHandling();

    $agent      = StoreAgent::make()->action(group: $this->group, modelData: Agent::factory()->definition());
    $otherAgent = StoreAgent::make()->action(group: $this->group, modelData: Agent::factory()->definition());

    $free   = StoreSupplier::make()->action(parent: $this->group, modelData: array_merge(Supplier::factory()->definition(), ['code' => 'ATTACHFREE']));
    $stolen = StoreSupplier::make()->action(parent: $otherAgent, modelData: array_merge(Supplier::factory()->definition(), ['code' => 'ATTACHSTEAL']));

    foreach ([$free, $stolen] as $supplier) {
        $this->patch(route('grp.models.supplier.update', $supplier->id), ['agent_id' => $agent->id])
            ->assertSessionHasNoErrors();

        expect($supplier->refresh()->agent_id)->toBe($agent->id)
            ->and($supplier->supplierProducts()->where('agent_id', '!=', $agent->id)->count())->toBe(0);
    }

    $this->get(route('grp.supply-chain.agents.show.suppliers.index', [$agent->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('pageHead.actions.0.route.name', 'grp.supply-chain.agents.show.suppliers.assignable'));
});

test('UI edit supplier product', function () {
    $supplierProduct = SupplierProduct::first();
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.supply-chain.supplier_products.edit', [$supplierProduct->slug]));
    $response->assertInertia(function (AssertableInertia $page) use ($supplierProduct) {
        $page
            ->component('EditModel')
            ->has('title')
            ->has('breadcrumbs')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $supplierProduct->code)
                    ->etc()
            )
            ->has('formData.args.updateRoute')
            ->has('formData.blueprint.0.fields.code');
    });
});

test('UI Index agents', function () {
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.supply-chain.agents.index'));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('SupplyChain/Agents')
            ->has('title')
            ->has('pageHead')
            ->has('data')
            ->has('breadcrumbs', 3);
    });
});

test('UI show agent', function () {
    $agent = Agent::first() ?? StoreAgent::make()->action($this->group, Agent::factory()->definition());
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.supply-chain.agents.show', [$agent->slug]));
    $response->assertInertia(function (AssertableInertia $page) use ($agent) {
        $page
            ->component('SupplyChain/Agent')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $agent->organisation->name)
                    ->etc()
            )
            ->has('pageHead.actions', 1)
            ->where('pageHead.actions.0.style', 'edit')
            ->where('pageHead.actions.0.route.name', 'grp.supply-chain.agents.edit')
            ->where('pageHead.actions.0.route.parameters.0', $agent->slug)
            ->has('tabs');
    });
});

test('UI edit agent', function () {
    $agent = Agent::first() ?? StoreAgent::make()->action($this->group, Agent::factory()->definition());
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.supply-chain.agents.edit', [$agent->slug]));

    $response->assertInertia(function (AssertableInertia $page) use ($agent) {
        $page
            ->component('EditModel')
            ->where('pageHead.actions.0.style', 'exitEdit')
            ->where('pageHead.actions.0.route.name', 'grp.supply-chain.agents.show')
            ->where('formData.args.updateRoute.name', 'grp.models.agent.update')
            ->where('formData.args.updateRoute.parameters', $agent->id)
            ->has('formData.blueprint.0.fields.code')
            ->has('formData.blueprint.1.fields.currency_id');
    });
});

test('UI create agent', function () {
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.supply-chain.agents.create'));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CreateModel')
            ->has('title')
            ->has('breadcrumbs', 4)
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', 'New agent')
                    ->etc()
            )
            ->has('formData');
    });
});

test('UI get section route group supply chain index', function () {
    $sectionScope = GetSectionRoute::make()->handle('grp.supply-chain.suppliers.index', []);
    expect($sectionScope)->toBeInstanceOf(AikuScopedSection::class)
        ->and($sectionScope->code)->toBe(AikuSectionEnum::GROUP_SUPPLY_CHAIN->value);
});

test('housekeep purchase orders flags legacy open orders and undo removes the flag', function () {
    $flagged = \App\Actions\Procurement\PurchaseOrder\HousekeepPurchaseOrders::run(0);
    expect($flagged)->toBeGreaterThanOrEqual(0);
    $response = $this->get(route('grp.supply-chain.dashboard'));
    $response->assertInertia(fn (AssertableInertia $page) => $page->component('SupplyChain/SupplyChainPurchaseOrderJourney'));
    expect(\App\Actions\Procurement\PurchaseOrder\HousekeepPurchaseOrders::run(0, true))->toBe($flagged);
});

test('agents and suppliers keep documents in an attachments tab', function () {
    $agent    = StoreAgent::make()->action(group: $this->group, modelData: Agent::factory()->definition());
    $supplier = StoreSupplier::make()->action(parent: $this->group, modelData: Supplier::factory()->definition());

    $this->post(route('grp.models.agent.attachment.attach', ['agent' => $agent->id]), [
        'attachments' => [\Illuminate\Http\UploadedFile::fake()->create('agent-contract.pdf', 10, 'application/pdf')],
        'scope'       => 'Contract',
    ])->assertSessionHasNoErrors();

    $this->post(route('grp.models.supplier.attachment.attach', ['supplier' => $supplier->id]), [
        'attachments' => [\Illuminate\Http\UploadedFile::fake()->create('scan-0042.pdf', 12, 'application/pdf')],
        'scope'       => 'Other',
        'caption'     => 'Factory audit 2026',
    ])->assertSessionHasNoErrors();

    expect($agent->attachments()->wherePivot('scope', 'Contract')->first()->pivot->caption)->toBe('agent-contract')
        ->and($supplier->attachments()->wherePivot('scope', 'Other')->first()->pivot->caption)->toBe('Factory audit 2026');

    $this->get(route('grp.supply-chain.agents.show', [$agent->slug, 'tab' => 'attachments']))
        ->assertInertia(fn (AssertableInertia $page) => $page->component('SupplyChain/Agent')
            ->has('attachments.data', 1)
            ->where('attachmentRoutes.attachRoute.name', 'grp.models.agent.attachment.attach')
            ->has('attachmentScopes', 8));

    $this->get(route('grp.supply-chain.suppliers.show', [$supplier->slug, 'tab' => 'attachments']))
        ->assertInertia(fn (AssertableInertia $page) => $page->component('SupplyChain/Supplier')
            ->has('attachments.data', 1));

    $orgAgent    = StoreOrgAgent::make()->action($this->organisation, $agent, []);
    $orgSupplier = $supplier->orgSuppliers()->where('organisation_id', $this->organisation->id)->firstOrFail();

    $this->get(route('grp.org.procurement.org_agents.show', [$this->organisation->slug, $orgAgent->slug, 'tab' => 'attachments']))
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Procurement/OrgAgent')
            ->has('attachments.data', 1)
            ->where('attachmentRoutes.detachRoute.parameters.agent', $agent->id));

    $this->get(route('grp.org.procurement.org_suppliers.show', [$this->organisation->slug, $orgSupplier->slug, 'tab' => 'attachments']))
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Procurement/OrgSupplier')
            ->has('attachments.data', 1));

    $this->delete(route('grp.models.agent.attachment.detach', ['agent' => $agent->id, 'attachment' => $agent->attachments()->first()->id]));

    expect($agent->attachments()->count())->toBe(0);
});

test('UI edit independent supplier shows the agent field', function () {
    $supplier = Supplier::whereNull('agent_id')->first();
    $this->withoutExceptionHandling();
    $this->get(route('grp.supply-chain.suppliers.show', $supplier->slug))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('pageHead.actions.0.route.name', 'grp.supply-chain.suppliers.edit'));
    $blueprint = $this->get(route('grp.supply-chain.suppliers.edit', $supplier->slug))->viewData('page')['props']['formData']['blueprint'];
    expect(collect($blueprint)->pluck('fields.agent_id.type')->filter()->values()->all())->toBe(['select']);
});

test('move independent supplier to an agent and free it again', function () {
    $agent    = StoreAgent::make()->action(group: $this->group, modelData: Agent::factory()->definition());
    $orgAgent = StoreOrgAgent::make()->action($this->organisation, $agent, []);

    $supplier = StoreSupplier::make()->action(parent: $this->group, modelData: Supplier::factory()->definition());
    $supplierProductData = SupplierProduct::factory()->definition();
    data_set($supplierProductData, 'stock_id', $this->stocks[0]->id);
    $supplierProduct = StoreSupplierProduct::make()->action($supplier, $supplierProductData);

    $supplier = UpdateSupplier::make()->action(supplier: $supplier, modelData: ['agent_id' => $agent->id]);

    $orgSupplier = $supplier->orgSuppliers()->where('organisation_id', $this->organisation->id)->first();

    expect($supplier->agent_id)->toBe($agent->id)
        ->and($supplierProduct->refresh()->agent_id)->toBe($agent->id)
        ->and($orgSupplier->agent_id)->toBe($agent->id)
        ->and($orgSupplier->org_agent_id)->toBe($orgAgent->id)
        ->and($orgSupplier->orgSupplierProducts()->whereNull('org_agent_id')->count())->toBe(0)
        ->and($supplier->orgSuppliers()->whereNull('org_agent_id')->where('status', true)->count())->toBe(0);

    $supplier    = UpdateSupplier::make()->action(supplier: $supplier, modelData: ['agent_id' => null]);
    $orgSupplier->refresh();

    expect($supplier->agent_id)->toBeNull()
        ->and($supplierProduct->refresh()->agent_id)->toBeNull()
        ->and($orgSupplier->agent_id)->toBeNull()
        ->and($orgSupplier->org_agent_id)->toBeNull()
        ->and($orgSupplier->status)->toBeTrue()
        ->and($orgSupplier->orgSupplierProducts()->whereNotNull('org_agent_id')->count())->toBe(0);
});

test('a supplier with products must say what happens to their costs when its currency changes', function () {
    $supplier            = StoreSupplier::make()->action(parent: $this->group, modelData: Supplier::factory()->definition());
    $supplierProductData = SupplierProduct::factory()->definition();
    data_set($supplierProductData, 'stock_id', $this->stocks[0]->id);
    $supplierProduct  = StoreSupplierProduct::make()->action($supplier, $supplierProductData);
    $originalCurrency = $supplier->currency;
    $otherCurrency    = Currency::where('id', '!=', $originalCurrency->id)->first();
    $originalCost     = (float) $supplierProduct->cost;

    expect(fn () => UpdateSupplier::make()->action($supplier, ['currency_id' => $otherCurrency->id]))
        ->toThrow(ValidationException::class);

    UpdateSupplier::make()->action($supplier->refresh(), ['currency_id' => $otherCurrency->id, 'products_currency' => 'relabel']);
    $supplierProduct->refresh();
    expect($supplierProduct->currency_id)->toBe($otherCurrency->id)
        ->and((float) $supplierProduct->cost)->toBe($originalCost);

    GetCurrencyExchange::shouldRun()
        ->once()
        ->with(Mockery::on(fn ($from) => $from->id === $otherCurrency->id), Mockery::on(fn ($to) => $to->id === $originalCurrency->id))
        ->andReturn(2.0);
    UpdateSupplier::make()->action($supplier->refresh(), ['currency_id' => $originalCurrency->id, 'products_currency' => 'convert']);
    $supplierProduct->refresh();
    expect($supplierProduct->currency_id)->toBe($originalCurrency->id)
        ->and((float) $supplierProduct->cost)->toBe(round($originalCost * 2, 4));
});

test('organisations buying from a supplier follow it onto an agent they did not trade with yet', function () {
    $agent    = StoreAgent::make()->action(group: $this->group, modelData: Agent::factory()->definition());
    $supplier = StoreSupplier::make()->action(parent: $this->group, modelData: Supplier::factory()->definition());

    $organisationIds = $supplier->orgSuppliers()->where('status', true)->pluck('organisation_id');
    expect($organisationIds)->not->toBeEmpty()
        ->and($agent->orgAgents()->count())->toBe(0);

    $supplier = UpdateSupplier::make()->action(supplier: $supplier, modelData: ['agent_id' => $agent->id]);

    expect($agent->orgAgents()->pluck('organisation_id')->sort()->values()->all())->toBe($organisationIds->sort()->values()->all())
        ->and($supplier->orgSuppliers()->whereIn('organisation_id', $organisationIds)->where('status', true)->whereNotNull('org_agent_id')->count())->toBe($organisationIds->count());
});

test('only supply chain editors attach and detach agent and supplier documents', function () {
    setPermissionsTeamId($this->group->id);
    $agent    = StoreAgent::make()->action(group: $this->group, modelData: Agent::factory()->definition());
    $supplier = StoreSupplier::make()->action(parent: $this->group, modelData: Supplier::factory()->definition());
    $newUser  = fn (array $permissions) => tap(
        \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($this->group, array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => []]))->getUser()
    )->givePermissionTo($permissions);
    $pdf = fn () => ['attachments' => [\Illuminate\Http\UploadedFile::fake()->create('contract.pdf', 10, 'application/pdf')], 'scope' => 'Contract'];

    actingAs($newUser(['supply-chain.view']));
    $this->post(route('grp.models.agent.attachment.attach', ['agent' => $agent->id]), $pdf())->assertForbidden();
    $this->post(route('grp.models.supplier.attachment.attach', ['supplier' => $supplier->id]), $pdf())->assertForbidden();

    actingAs($newUser(["procurement.{$supplier->orgSuppliers()->first()->organisation_id}.edit"]));
    $this->post(route('grp.models.agent.attachment.attach', ['agent' => $agent->id]), $pdf())->assertForbidden();
    $this->post(route('grp.models.supplier.attachment.attach', ['supplier' => $supplier->id]), $pdf())->assertSessionHasNoErrors();

    actingAs($newUser(['supply-chain.edit']));
    $this->post(route('grp.models.agent.attachment.attach', ['agent' => $agent->id]), $pdf())->assertSessionHasNoErrors();
    $this->post(route('grp.models.supplier.attachment.attach', ['supplier' => $supplier->id]), $pdf())->assertSessionHasNoErrors();
    $attachment = $agent->attachments()->first();

    actingAs($newUser(['supply-chain.view']));
    $this->delete(route('grp.models.agent.attachment.detach', [$agent->id, $attachment->id]))->assertForbidden();

    actingAs($newUser(['supply-chain.edit']));
    $this->delete(route('grp.models.agent.attachment.detach', [$agent->id, $attachment->id]))->assertSuccessful();

    expect($agent->attachments()->count())->toBe(0)
        ->and($supplier->attachments()->count())->toBeGreaterThan(0);
});

test('agent organisation procurement editors can edit internal pictures of their clients supplier products', function () {
    $agent = new Agent(['organisation_id' => 801]);
    $orgAgent = (new \App\Models\Procurement\OrgAgent())->setRelation('agent', $agent);
    $orgSupplierProduct = (new \App\Models\Procurement\OrgSupplierProduct(['organisation_id' => 802]))->setRelation('orgAgent', $orgAgent);
    $userWith = fn (array $permissions) => Mockery::mock(\App\Models\SysAdmin\User::class)->makePartial()
        ->shouldReceive('authTo')->andReturnUsing(fn (string $permission) => in_array($permission, $permissions))->getMock();
    $canEdit = fn (array $permissions) => \App\Actions\SupplyChain\SupplierProduct\UploadImagesToSupplierProduct::canEditOrgSupplierProductPictures($userWith($permissions), $orgSupplierProduct);

    expect($canEdit(['procurement.802.edit']))->toBeTrue()
        ->and($canEdit(['procurement.801.edit']))->toBeTrue()
        ->and($canEdit(['procurement.803.edit']))->toBeFalse()
        ->and($canEdit([]))->toBeFalse()
        ->and(\App\Actions\SupplyChain\SupplierProduct\UploadImagesToSupplierProduct::canEditOrgSupplierProductPictures(
            $userWith(['procurement.801.edit']),
            new \App\Models\Procurement\OrgSupplierProduct(['organisation_id' => 802])
        ))->toBeFalse();
});

test('purchase order journey rows query runs', function () {
    $journey = \App\Actions\SupplyChain\UI\ShowSupplyChainPurchaseOrderJourney::make();
    $group   = $this->group;

    $rows = (function () use ($group) {
        $this->group = $group;

        return $this->rows();
    })->call($journey);

    expect($rows)->toBeArray();
});

test('procurement editors keep internal pictures on the supplier product, away from its trade units', function () {
    setPermissionsTeamId($this->group->id);
    $supplier        = StoreSupplier::make()->action(parent: $this->group, modelData: Supplier::factory()->definition());
    $supplierProduct = StoreSupplierProduct::make()->action($supplier, array_merge(SupplierProduct::factory()->definition(), ['stock_id' => $this->stocks[0]->id]));
    $orgSupplierProduct = $supplierProduct->orgSupplierProducts()->firstOrFail();
    $tradeUnitMediaCount = fn () => $supplierProduct->tradeUnits->sum(fn ($tradeUnit) => $tradeUnit->images()->count());
    $tradeUnitMediaBefore = $tradeUnitMediaCount();
    $newUser = fn (array $permissions) => tap(
        \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($this->group, array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => []]))->getUser()
    )->givePermissionTo($permissions);
    $picture = fn () => ['images' => [\Illuminate\Http\UploadedFile::fake()->image('packing.jpg', 50, 50)]];

    actingAs($newUser(["procurement.{$orgSupplierProduct->organisation_id}.view"]));
    $this->post(route('grp.models.org_supplier_product.upload_images', ['orgSupplierProduct' => $orgSupplierProduct->id]), $picture())->assertForbidden();

    actingAs($newUser(["procurement.{$orgSupplierProduct->organisation_id}.edit"]));
    $this->post(route('grp.models.org_supplier_product.upload_images', ['orgSupplierProduct' => $orgSupplierProduct->id]), $picture())->assertSessionHasNoErrors();
    $this->post(route('grp.models.supplier-product.upload_images', ['supplierProduct' => $supplierProduct->id]), $picture())->assertForbidden();

    $supplierProduct->refresh();
    $media = $supplierProduct->images()->firstOrFail();
    expect($supplierProduct->images()->count())->toBe(1)
        ->and($supplierProduct->image_id)->toBe($media->id)
        ->and($tradeUnitMediaCount())->toBe($tradeUnitMediaBefore)
        ->and(GetOrgSupplierProductShowcase::run($orgSupplierProduct)['internal_images']['images'])->toHaveCount(1);

    $this->delete(route('grp.models.org_supplier_product.detach_image', ['orgSupplierProduct' => $orgSupplierProduct->id, 'media' => $media->id]))->assertSuccessful();

    expect($supplierProduct->images()->count())->toBe(0)
        ->and($supplierProduct->refresh()->image_id)->toBeNull();
});

test('UI index purchase orders in supplier links each order to its organisation', function () {
    $supplier = StoreSupplier::make()->action(
        parent: $this->group,
        modelData: Supplier::factory()->definition(),
    );
    StoreSupplierProduct::make()->action($supplier, array_merge(SupplierProduct::factory()->definition(), ['stock_id' => $this->stocks[1]->id]));
    $orgSupplier   = $supplier->orgSuppliers()->where('organisation_id', $this->organisation->id)->first();
    $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());

    $this->get(route('grp.supply-chain.suppliers.purchase_orders.index', [$supplier->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Procurement/PurchaseOrders')
            ->where('data.data.0.slug', $purchaseOrder->slug)
            ->where('data.data.0.organisation_slug', $this->organisation->slug)
            ->etc());
});

test('the supplier product template has the v7 tabs and reads back without errors', function () {
    $path = sys_get_temp_dir().'/supplier_products_template_'.uniqid().'.xlsx';
    file_put_contents($path, Maatwebsite\Excel\Facades\Excel::raw(new App\Exports\SupplyChain\SupplierProductTemplateExport(), Maatwebsite\Excel\Excel::XLSX));

    $sheet = App\Actions\SupplyChain\SupplierProduct\Upload\ReadSupplierProductSheet::run($path);

    expect(PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getSheetNames())->toBe(['Product data', 'Packaging components', 'Supplier declarations'])
        ->and($sheet['errors'])->toBe([])
        ->and($sheet['columns'])->toContain('eudr_geolocation')
        ->and($sheet['packaging'])->toBe([])
        ->and($sheet['declaration'])->toBeNull();
});

test('EPR material mappings name each packaging material in every scheme, plastics by polymer where the scheme splits them', function () {
    $resolve = fn (string $scheme, string $category, ?string $polymer = null) => App\Models\Goods\EprMaterialMapping::resolve(
        App\Enums\Goods\Packaging\EprSchemeEnum::from($scheme),
        App\Enums\Goods\Packaging\PackagingMaterialCategoryEnum::from($category),
        $polymer ? App\Enums\Goods\Packaging\PackagingPolymerEnum::from($polymer) : null
    );

    foreach (App\Enums\Goods\Packaging\EprSchemeEnum::cases() as $scheme) {
        foreach (App\Enums\Goods\Packaging\PackagingMaterialCategoryEnum::cases() as $category) {
            expect($resolve($scheme->value, $category->value))->not->toBeNull();
        }
    }

    expect($resolve('uk', 'paper_cardboard')->scheme_material)->toBe('Paper or card')
        ->and($resolve('uk', 'plastic', 'eps')->scheme_material)->toBe('Plastic')
        ->and($resolve('sk', 'plastic', 'pet')->scheme_subcategory)->toBe('PET, HDPE, LDPE, PP, PS')
        ->and($resolve('sk', 'plastic', 'eps')->scheme_subcategory)->toBe('EPS')
        ->and($resolve('sk', 'plastic')->scheme_subcategory)->toBe('PET, HDPE, LDPE, PP, PS')
        ->and($resolve('fr', 'plastic', 'hdpe')->scheme_subcategory)->toBe('PEHD')
        ->and($resolve('de', 'steel')->scheme_material)->toBe('Eisenmetalle');
});

test('v7 supplier product upload keeps the GPSR and EUDR answers, shares one packaging family and stores the signed declaration', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1.0);
    $supplier = StoreSupplier::make()->action(parent: $this->group, modelData: Supplier::factory()->definition());

    $compliance = [
        'Manufacturer (name, postal address, email)' => 'Sandya Crafts, Thamel, Kathmandu, sandya@example.com',
        'Warnings and safety information'            => 'Not a toy. Decorative use only.',
        'Toy status'                                 => 'Not a toy - not designed or intended for play',
        'Material composition (% by weight)'         => 'Wool 90%, Cotton 5%',
        'EUDR status'                                => 'Yes - wood, HS ch.44',
        'EUDR species (scientific name)'             => 'Shorea robusta',
    ];
    $part = fn (string $code) => supplierProductUploadRow([
        'Part reference'                     => $code,
        "Supplier's product code"            => $code,
        'Family'                             => 'UPL-V7',
        'Unit barcode (EAN-13, for website)' => null,
        ...$compliance,
    ]);
    $packaging = fn (string $code) => [
        [$code, 'Primary - sales unit packaging', 'Polybag', 'PE-LD', 'PE-LD 4', 4, 1, '30%'],
        [$code, 'Secondary - grouped SKO packaging', 'Header card', 'Folding boxboard / paperboard', 'PAP 21', 10, 1, null],
        [$code, 'Tertiary - transport carton', 'Export carton', 'Corrugated board', 'PAP 20', 800, 1, null],
    ];

    $upload = uploadSupplierProductSheet($supplier, supplierProductUploadSheet([$part('UPLV-01'), $part('UPLV-02')], [], [
        'Packaging components'  => [
            ['Part reference', 'Packaging level', 'Component', 'Material', 'Material code', 'Weight (g)', 'Quantity at this level', 'Recycled content %'],
            ...$packaging('UPLV-01'),
            ...$packaging('UPLV-02'),
            ['UPLV-99', 'Primary - sales unit packaging', 'Polybag', 'PE-LD', 'PE-LD 4', 4, 1, null],
            [null, 'Primary - sales unit packaging', 'Sticker', 'Paper', 'PAP 22', 1, 0, null],
        ],
        'Supplier declarations' => [
            ['Company', 'Sandya Crafts'],
            ['Signed by', 'Sandya Rai'],
            ['Position', 'Owner'],
            ['Date', '01/10/2026'],
            [],
            ['Statement', 'Answer'],
            ['No packaging component contains lead, cadmium, mercury and hexavalent chromium above 100 mg/kg in total (PPWR Art 5)', 'Yes - confirmed, evidence attached'],
            ['The materials and weights on the Packaging components tab are correct', 'Not known / not yet tested'],
            ['We will tell AW before changing any material, packaging, factory or origin', null],
        ],
    ]));

    $findings = collect($upload->records()->orderBy('row_number')->first()->data['findings'])->pluck('level', 'code');

    expect($upload->data['packaging'])->toEqual(['rows' => 8, 'orphans' => [8, 9], 'unread' => false])
        ->and($upload->data['declaration']['signed_by'])->toBe('Sandya Rai')
        ->and($findings->get('eudr_incomplete'))->toBe('warning')
        ->and($findings->get('material_composition_total'))->toBe('warning');

    acceptSupplierProductUploadFindings($upload);
    App\Actions\SupplyChain\SupplierProduct\Upload\ImportSupplierProductUpload::run($upload->refresh());

    $first  = TradeUnit::where('group_id', $this->group->id)->where('code', 'UPLV-01')->firstOrFail();
    $second = TradeUnit::where('group_id', $this->group->id)->where('code', 'UPLV-02')->firstOrFail();
    $family = $first->packagingFamily()->with('components')->firstOrFail();
    $carton = $family->components->firstWhere('name', 'Export carton');
    $bag    = $family->components->firstWhere('name', 'Polybag');

    expect($first->gpsr_manufacturer)->toBe('Sandya Crafts, Thamel, Kathmandu, sandya@example.com')
        ->and($first->gpsr_warnings)->toBe('Not a toy. Decorative use only.')
        ->and($first->compliance['toy_status'])->toBe('Not a toy - not designed or intended for play')
        ->and($first->compliance['eudr'])->toBe(['status' => 'Yes - wood, HS ch.44', 'species' => 'Shorea robusta'])
        ->and($first->compliance['material_composition']['materials'])->toEqual([['material' => 'Wool', 'percentage' => 90.0], ['material' => 'Cotton', 'percentage' => 5.0]])
        ->and($second->packaging_family_id)->toBe($first->packaging_family_id)
        ->and($family->components)->toHaveCount(3)
        ->and((float)$family->components->firstWhere('name', 'Header card')->pivot->quantity_per_unit)->toBe(0.5)
        ->and((float)$carton->pivot->quantity_per_unit)->toBe(0.0125)
        ->and($carton->material_category)->toBe(App\Enums\Goods\Packaging\PackagingMaterialCategoryEnum::PAPER_CARDBOARD)
        ->and($bag->material_category)->toBe(App\Enums\Goods\Packaging\PackagingMaterialCategoryEnum::PLASTIC)
        ->and($bag->polymer)->toBe(App\Enums\Goods\Packaging\PackagingPolymerEnum::LDPE)
        ->and($carton->polymer)->toBeNull()
        ->and($family->source)->toBe(App\Enums\Goods\Packaging\PackagingFamilySourceEnum::SUPPLIER)
        ->and($family->brand_ownership)->toBe(App\Enums\Goods\Packaging\PackagingBrandOwnershipEnum::UNKNOWN)
        ->and((float)$bag->recycled_content_pct)->toBe(30.0);

    $declaration = $supplier->declarations()->sole();
    expect($declaration->signed_on->toDateString())->toBe('2026-10-01')
        ->and($declaration->company)->toBe('Sandya Crafts')
        ->and($declaration->answers)->toHaveCount(3)
        ->and($declaration->answers[2]['answer'])->toBe('');

    $this->get(route('grp.trade_units.units.show', [$first->slug, 'tab' => 'compliance']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('compliance.packaging.code', $family->code)
            ->where('compliance.packaging.shared_with', 1)
            ->where('compliance.packaging.weight_per_unit_g', fn ($grams) => (float)$grams === 19.0)
            ->where('compliance.eudr.2.value', 'Shorea robusta')
            ->etc());

    $this->get(route('grp.supply-chain.suppliers.show', [$supplier->slug, 'tab' => 'declarations']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('declarations.0.signed_by', 'Sandya Rai')
            ->where('declarations.0.answers.1.is_yes', false)
            ->etc());
});
