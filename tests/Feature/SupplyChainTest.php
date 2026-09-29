<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 08 May 2023 09:03:42 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Helpers\Redirects\RedirectSupplierLink;
use App\Models\SysAdmin\User;
use App\Actions\Goods\TradeUnit\StoreTradeUnit;
use App\Actions\SupplyChain\SupplierProduct\UI\GetSupplierProductShowcase;
use App\Actions\Procurement\OrgAgent\StoreOrgAgent;
use App\Actions\Procurement\OrgAgent\UpdateOrgAgent;
use App\Actions\Procurement\OrgSupplier\UpdateOrgSupplier;
use App\Actions\SupplyChain\Agent\DeleteAgent;
use App\Actions\SupplyChain\Agent\StoreAgent;
use App\Actions\SupplyChain\Agent\UpdateAgent;
use App\Actions\Procurement\PurchaseOrder\StorePurchaseOrder;
use App\Models\Procurement\PurchaseOrder;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Actions\SupplyChain\Supplier\DeleteSupplier;
use App\Actions\SupplyChain\Supplier\StoreSupplier;
use App\Actions\SupplyChain\Supplier\UpdateSupplier;
use App\Actions\SupplyChain\SupplierProduct\StoreSupplierProduct;
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
use App\Enums\Helpers\Import\UploadRecordStatusEnum;
use App\Actions\Goods\Stock\StoreStock;
use App\Actions\Goods\StockFamily\StoreStockFamily;
use App\Imports\SupplyChain\SupplierProductImport;
use App\Models\Analytics\AikuScopedSection;
use App\Models\Goods\StockFamily;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Upload;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgAgentStats;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\OrgSupplierStats;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;

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
        ->and(array_keys(GetOrganisationsLayout::run($user)[$organisation->slug]))->toContain('procurement', 'hr')
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

test('import supplier product row creates trade unit and stock family', function ($supplier) {
    $upload = Upload::create([
        'group_id'          => $this->group->id,
        'organisation_id'   => $this->organisation->id,
        'model'             => 'SupplierProduct',
        'parent_type'       => $supplier->getMorphClass(),
        'parent_id'         => $supplier->id,
        'original_filename' => 'supplier_products.xlsx',
        'filename'          => 'supplier_products.xlsx',
        'filesize'          => 0,
    ]);

    $import = new SupplierProductImport($supplier, $upload);

    $row = collect([
        'id_supplier_part_key'                => 'new',
        'suppliers_product_code'               => 'IMP-SUP-001',
        'suppliers_unit_description'           => 'Imported unit',
        'family'                               => 'IMP-FAM',
        'part_reference'                       => 'IMP-TU-001',
        'unit_label'                           => 'Imported trade unit',
        'units_per_sko'                        => 12,
        'skos_per_carton'                      => 4,
        'minimum_order_cartons'                => 1,
        'average_delivery_time_days'           => 21,
        'carton_cbm'                           => 0.08,
        'unit_cost'                            => 1.25,
        'unit_extra_costs'                     => 0,
        'unit_recommended_description_website' => 'Imported product description',
        'unit_barcode_ean_13_for_website'      => '5000000000001',
        'unit_weight_kg'                       => 0.5,
        'unit_dimensions_l_x_w_x_h_in_cm'      => '10 x 5 x 3',
        'country_of_origin'                    => 'GBR',
    ]);

    $uploadRecord = $upload->records()->create(['values' => $row->all(), 'row_number' => 2]);
    $import->storeModel($row, $uploadRecord);

    $tradeUnit = TradeUnit::where('group_id', $this->group->id)->where('code', 'IMP-TU-001')->first();
    expect($tradeUnit)->not->toBeNull()
        ->and($tradeUnit->name)->toBe('Imported trade unit')
        ->and($tradeUnit->description)->toBe('Imported product description');

    $stockFamily = StockFamily::where('group_id', $this->group->id)->where('code', 'IMP-FAM')->first();
    expect($stockFamily)->not->toBeNull();

    $supplierProduct = SupplierProduct::where('supplier_id', $supplier->id)->where('code', 'IMP-SUP-001')->first();
    expect($supplierProduct)->not->toBeNull()
        ->and((int)$supplierProduct->tradeUnits()->first()->pivot->quantity)->toBe(12);

    $uploadRecord->refresh();
    expect($uploadRecord->status)->toBe(UploadRecordStatusEnum::COMPLETE->value);

    $secondRow          = clone $row;
    $secondUploadRecord = $upload->records()->create(['values' => $secondRow->all(), 'row_number' => 3]);
    $import->storeModel($secondRow, $secondUploadRecord);

    expect(TradeUnit::where('group_id', $this->group->id)->where('code', 'IMP-TU-001')->count())->toBe(1)
        ->and(StockFamily::where('group_id', $this->group->id)->where('code', 'IMP-FAM')->count())->toBe(1)
        ->and(SupplierProduct::where('supplier_id', $supplier->id)->where('code', 'IMP-SUP-001')->count())->toBe(1)
        ->and($tradeUnit->barcode)->toBe('5000000000001');

    $autoBarcodeRow = $row->merge(['suppliers_product_code' => 'IMP-SUP-002', 'part_reference' => 'IMP-TU-002', 'unit_barcode_ean_13_for_website' => 'auto']);
    $import->storeModel($autoBarcodeRow, $upload->records()->create(['values' => $autoBarcodeRow->all(), 'row_number' => 4]));

    expect(TradeUnit::where('group_id', $this->group->id)->where('code', 'IMP-TU-002')->value('barcode'))->toBeNull();
})->depends('create supplier in agent');

test('supplier product sheet with mistakes creates nothing and lists every mistake by row', function ($supplier) {
    $dressFamily = StoreStockFamily::make()->action($this->group, ['code' => 'CHK-DRESS', 'name' => 'Dresses'], strict: false);
    StoreStock::make()->action($dressFamily, ['code' => 'CHKD-01', 'name' => 'Dress'], strict: false);

    $headings = ['Id: Supplier Part Key', 'Family', 'Part reference', "Supplier's product code", "Supplier's unit description", 'Unit cost', 'Unit label', 'Unit barcode (EAN-13, for website)', 'Units per SKO', 'SKOs per carton'];
    $sheetFile = function (array $rows) use ($headings) {
        $spreadsheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray([$headings, ...$rows]);
        $spreadsheet->getActiveSheet()->setCellValue('A20', null);
        $spreadsheet->createSheet()->setTitle('Country codes')->fromArray([['Nepal', 'NPL'], ['Spain', 'ESP']]);
        $path = sys_get_temp_dir().'/supplier_products_'.uniqid().'.xlsx';
        (new PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($path);

        return $path;
    };
    $import = function (string $path) use ($supplier) {
        $upload = Upload::create([
            'group_id'          => $this->group->id,
            'organisation_id'   => $this->organisation->id,
            'model'             => 'SupplierProduct',
            'parent_type'       => $supplier->getMorphClass(),
            'parent_id'         => $supplier->id,
            'original_filename' => 'trousers.xlsx',
            'filename'          => 'trousers.xlsx',
            'filesize'          => 0,
        ]);
        Maatwebsite\Excel\Facades\Excel::import(new SupplierProductImport($supplier, $upload), $path);

        return $upload->refresh();
    };

    $upload = $import($sheetFile([
        ['new', 'CHK-TROUSER', 'CHKT-01', 'CHKT-01', 'Trousers S/M', 825, 'Trousers', 'auto', 1, 30],
        ['new', 'CHK-DRESS', 'CHKT-02', 'CHKT-02', 'Trousers L/XL', 825, 'Trousers', 'auto', 1, 30],
        ['new', 'CHK-DRESS', 'CHKT-03', 'CHKT-02', 'Trousers S/M', 'Rs', 'Trousers', '12345', 1, 30],
    ]));

    $errors = $upload->records()->whereNotNull('row_number')->orderBy('row_number')->get()->mapWithKeys(fn ($record) => [$record->row_number => $record->errors]);
    expect($upload->number_rows)->toBe(3)
        ->and($upload->number_fails)->toBe(3)
        ->and($upload->number_success)->toBe(0)
        ->and($errors->keys()->all())->toBe([2, 3, 4])
        ->and($errors[2])->toBe(['CHKT products have different families in this sheet: CHK-TROUSER (rows 2), CHK-DRESS (rows 3-4). Use one family.'])
        ->and($errors[4])->toContain('Unit cost must be a number above zero, found "Rs".')
        ->and($errors[4])->toContain('Unit barcode "12345" is not a barcode (8 to 14 digits).')
        ->and($errors[4])->toContain("Supplier's product code CHKT-02 appears in rows 3-4.")
        ->and(TradeUnit::where('group_id', $this->group->id)->where('code', 'like', 'CHKT-%')->exists())->toBeFalse()
        ->and(SupplierProduct::where('supplier_id', $supplier->id)->where('code', 'like', 'CHKT-%')->exists())->toBeFalse();

    $upload = $import($sheetFile([
        ['new', 'CHK-DRESS', 'CHKT-01', 'CHKT-01', 'Trousers S/M', 825, 'Trousers', 'auto', 1, 30],
    ]));
    expect($upload->records()->first()->errors)->toBe(['Family CHK-DRESS holds CHKD products, CHKT does not look like it belongs there.'])
        ->and(TradeUnit::where('group_id', $this->group->id)->where('code', 'CHKT-01')->exists())->toBeFalse();

    $upload = $import($sheetFile([
        ['new', 'CHK-TROUSER', 'CHKT-01', 'CHKT-01', 'Trousers S/M', 825, 'Trousers', 'auto', 1, 30],
        ['new', 'CHK-TROUSER', 'CHKT-02', 'CHKT-02', 'Trousers L/XL', 825, 'Trousers', 'auto', 1, 30],
    ]));
    expect($upload->number_success)->toBe(2)
        ->and($upload->number_fails)->toBe(0)
        ->and(SupplierProduct::where('supplier_id', $supplier->id)->where('code', 'like', 'CHKT-%')->count())->toBe(2)
        ->and(TradeUnit::where('group_id', $this->group->id)->where('code', 'CHKT-01')->value('barcode'))->toBeNull();

    $upload = $import($sheetFile([
        ['new', 'CHK-TROUSER', 'CHKT-01', 'CHKT-01', 'Trousers S/M', 825, 'Trousers', 'auto', 1, 30],
    ]));
    expect($upload->records()->first()->errors)->toBe(['CHKT-01 already exists for this supplier, use its Id instead of "new".']);
})->depends('create supplier in agent');


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
    $response = $this->get(route('grp.supply-chain.suppliers.create'));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CreateModel')
            ->has('title')
            ->has('pageHead')
            ->has('formData')
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
            ->has('dashboardCards', 6)
            ->where('dashboardCards.0.route.name', 'grp.supply-chain.agents.index')
            ->where('dashboardCards.1.route.name', 'grp.supply-chain.suppliers.index')
            ->where('dashboardCards.1.metrics.0.route.name', 'grp.supply-chain.agent_suppliers.index')
            ->missing('dashboardCards.1.route.parameters._query.elements[type]')
            ->missing('dashboardCards.1.metrics.0.route.parameters._query.elements[type]')
            ->where('dashboardCards.2.route.name', 'grp.supply-chain.supplier_products.index')
            ->where('dashboardCards.3.route.name', 'grp.supply-chain.agent_supplier_purchase_orders.index')
            ->where('dashboardCards.4.route.name', 'grp.supply-chain.control.dashboard')
            ->where('dashboardCards.5.route.name', 'grp.supply-chain.shopping_list.board')
            ->missing('staleOrders')
            ->missing('search_demand')
            ->missing('poJourney')
            ->has('breadcrumbs', 3)
            ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                ->where('poJourney.route.name', 'grp.supply-chain.dashboard')
                ->has('poJourney.summary.open')
                ->has('poJourney.summary.overdue')
                ->has('poJourney.blockages'));
    });
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
            ->has('stalled_aspos')
            ->has('deposits_at_risk')
            ->has('pos_without_action')
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
            ->has('filters.buyer')
            ->has('blockages')
            ->has('quickStats')
            ->where('active.journey', 'supplier')
            ->where('ribbons', fn ($ribbons) => collect($ribbons)->contains(
                fn ($ribbon) => $ribbon['reference'] === $purchaseOrder->reference && $ribbon['current_stage'] === 'po_created'
            )));

    $this->patch(route('grp.models.purchase-order.journey_stage', ['purchaseOrder' => $purchaseOrder->id]), [
        'stage' => 'production',
        'date'  => now()->toDateString(),
    ])->assertRedirect();

    expect($purchaseOrder->fresh()->produced_at)->not->toBeNull();

    $this->get(route('grp.supply-chain.dashboard', ['search' => $purchaseOrder->reference]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('ribbons.0.reference', $purchaseOrder->reference)
            ->where('ribbons.0.segments', fn ($segments) => collect($segments)->firstWhere('key', 'production')['state'] === 'done'));
})->depends('create independent supplier 2');

test('UI create suppliers product in supplier', function () {
    $this->withoutExceptionHandling();
    $supplier = Supplier::first();
    $response = $this->get(route('grp.supply-chain.suppliers.supplier_products.create', [
        $supplier->slug
    ]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CreateModel')
            ->has('title')
            ->has('pageHead')
            ->has('formData')
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

test('UI show agent supplier has agent supplier purchase order navigation', function () {
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
            ->where('pageHead.subNavigation.2.route.name', 'grp.supply-chain.suppliers.agent_supplier_purchase_orders.index')
            ->where('showcase.stats.1.route.name', 'grp.supply-chain.suppliers.agent_supplier_purchase_orders.index')
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

test('UI index agent supplier purchase orders in supplier', function () {
    $supplier = Supplier::first();
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.supply-chain.suppliers.agent_supplier_purchase_orders.index', [$supplier->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('SupplyChain/AgentSupplierPurchaseOrders')
            ->has('title')
            ->has('breadcrumbs')
            ->has('data');
    });
});

test('UI index agent supplier purchase orders in agent', function () {
    $agent = Agent::first();
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.supply-chain.agents.show.agent_supplier_purchase_orders.index', [$agent->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('SupplyChain/AgentSupplierPurchaseOrders')
            ->has('title')
            ->has('breadcrumbs')
            ->has('data')
            ->has('pageHead.subNavigation');
    });
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

    $expectedLosing = \App\Models\Procurement\OrgSupplier::query()
        ->where('supplier_id', $free->id)
        ->where('status', true)
        ->whereNotIn('organisation_id', $agent->orgAgents()->pluck('organisation_id'))
        ->with('organisation')
        ->get()
        ->pluck('organisation.name')
        ->sort()
        ->implode(', ');

    expect($rows['ASSIGNFREE']['organisations_losing_supplier'])->toBe($expectedLosing ?: null);

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

test('supplier product sheet with an ORDER tab creates the products and a draft purchase order, or nothing when the order has mistakes', function () {
    $supplier     = StoreSupplier::make()->action(parent: $this->group, modelData: Supplier::factory()->definition());
    $organisation = $this->organisation->code;

    $sheetFile = function (array $products, array $order) {
        $spreadsheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray([['Id: Supplier Part Key', 'Family', 'Part reference', "Supplier's product code", "Supplier's unit description", 'Unit cost', 'Unit label', 'Units per SKO', 'SKOs per carton'], ...$products]);
        $spreadsheet->createSheet()->setTitle('ORDER')->fromArray($order);
        $path = sys_get_temp_dir().'/opening_order_'.uniqid().'.xlsx';
        (new PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($path);

        return $path;
    };
    $import = function (string $path) use ($supplier) {
        $upload = Upload::create([
            'group_id'          => $this->group->id,
            'organisation_id'   => $this->organisation->id,
            'model'             => 'SupplierProduct',
            'parent_type'       => $supplier->getMorphClass(),
            'parent_id'         => $supplier->id,
            'original_filename' => 'opening_order.xlsx',
            'filename'          => 'opening_order.xlsx',
            'filesize'          => 0,
        ]);
        Maatwebsite\Excel\Facades\Excel::import(new SupplierProductImport($supplier, $upload), $path);

        return $upload->refresh();
    };

    $upload = $import($sheetFile(
        [
            ['new', 'OPN-FAM', 'OPN-01', 'OPN-01', 'Trousers S/M', 825, 'Trousers', 1, 30],
            ['new', 'OPN-FAM', 'OPN-02', 'OPN-02', 'Trousers L/XL', 825, 'Trousers', 1, 30],
        ],
        [
            ['Product Code', 'Description', 'Unit Cost', 'Carton', $organisation, 'ZZ', 'Total value', $organisation],
            ['=Worksheet!D2', 'Trousers S/M', 825, 30, 5, 1, '=C2*D2*E2', 999999],
            ['=Worksheet!D3', 'Trousers L/XL', 825, 30, 2, null, '=C3*D3*E3', 999999],
            [],
            [null, 'Total', null, null, '=SUM(E2:E3)'],
        ]
    ));

    $purchaseOrder = PurchaseOrder::where('organisation_id', $this->organisation->id)
        ->where('parent_type', 'OrgSupplier')
        ->where('parent_id', $supplier->orgSuppliers()->where('organisation_id', $this->organisation->id)->value('id'))
        ->firstOrFail();
    $quantities = $purchaseOrder->purchaseOrderTransactions()->with('supplierProduct')->get()->mapWithKeys(fn ($transaction) => [$transaction->supplierProduct->code => (float)$transaction->quantity_ordered]);

    expect($upload->number_fails)->toBe(0)
        ->and($upload->number_success)->toBe(3)
        ->and($purchaseOrder->state)->toBe(PurchaseOrderStateEnum::IN_PROCESS)
        ->and($purchaseOrder->currency_id)->toBe($supplier->currency_id)
        ->and($quantities->all())->toBe(['OPN-01' => 150.0, 'OPN-02' => 60.0])
        ->and($upload->records()->whereNull('row_number')->first()->values)->toMatchArray(['purchase_order' => $purchaseOrder->reference, 'lines' => 2]);

    $upload = $import($sheetFile(
        [
            ['new', 'OPN-FAM', 'OPN-03', 'OPN-03', 'Trousers XXL', 825, 'Trousers', 1, 30],
        ],
        [
            ['Product Code', 'Unit Cost', 'Carton', $organisation],
            ['OPN-03', 900, 24, 4],
            ['OPN-99', 825, 30, 'five'],
            [null, 'Total', null, 30],
        ]
    ));

    expect($upload->number_success)->toBe(0)
        ->and($upload->records()->whereNull('row_number')->pluck('errors')->flatten()->all())->toBe([
            'ORDER tab row 2: unit cost is 900, but products tab row 2 says 825.',
            'ORDER tab row 2: 24 pieces per carton, but products tab row 2 says 30.',
            'ORDER tab row 3: OPN-99 is not in the products tab and is not a product of this supplier.',
            'ORDER tab row 4: the '.$organisation.' total is 30 cartons, but the lines add up to 4.',
        ])
        ->and(SupplierProduct::where('supplier_id', $supplier->id)->where('code', 'OPN-03')->exists())->toBeFalse()
        ->and($purchaseOrder->purchaseOrderTransactions()->count())->toBe(2);
});
