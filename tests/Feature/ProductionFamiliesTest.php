<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 25 Nov 2024 11:48:10 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Feature;

use App\Actions\Production\Artefact\StoreArtefact;
use App\Actions\Production\Artefact\MoveArtefactsToDepartment;
use App\Actions\Production\ArtefactDepartment\StoreArtefactDepartment;
use App\Actions\Production\Artefact\MoveArtefactsToFamily;
use App\Actions\Production\Artefact\SetArtefactsState;
use App\Actions\Production\Artefact\SetArtefactState;
use App\Actions\Production\Artefact\SetArtefactsBatchSize;
use App\Actions\Production\Artefact\SetArtefactsRecipe;
use App\Actions\Production\ArtefactFamily\Hydrators\ArtefactFamilyHydrateArtefacts;
use App\Actions\Production\Artefact\UI\IndexArtefacts;
use App\Actions\Production\ArtefactFamily\AssignArtefactsToFamiliesFromOrgStockFamilies;
use App\Actions\Production\ArtefactFamily\DeleteArtefactFamily;
use App\Actions\Production\ArtefactFamily\MoveArtefactFamiliesToDepartment;
use App\Actions\Production\ArtefactFamily\StoreArtefactFamily;
use App\Models\Production\ArtefactFamily;
use App\Models\Production\ArtefactDepartment;
use App\Actions\Production\JobOrder\ConfirmJobOrder;
use App\Actions\Production\JobOrder\StoreJobOrder;
use App\Actions\Production\ManufactureTask\StoreManufactureTask;
use App\Actions\Production\Artefact\AttachManufactureTaskToArtefact;
use App\Actions\Production\Artefact\AttachRawMaterialToRecipeStep;
use App\Models\Production\ArtefactManufactureTask;
use App\Models\Production\RecipeStepRawMaterial;
use App\Actions\Production\JobOrderItem\StoreJobOrderItem;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum;
use App\Actions\Production\Production\StoreProduction;
use App\Actions\Production\RawMaterial\StoreRawMaterial;
use App\Enums\Production\ManufactureTask\ManufactureTaskOperativeRewardAllowanceTypeEnum;
use App\Enums\Production\ManufactureTask\ManufactureTaskOperativeRewardTermsEnum;
use App\Enums\Production\RawMaterial\RawMaterialStateEnum;
use App\Enums\Production\RawMaterial\RawMaterialTypeEnum;
use App\Enums\Production\RawMaterial\RawMaterialUnitEnum;
use App\Enums\Production\Artefact\ArtefactStateEnum;
use App\Models\Production\Artefact;
use App\Models\Production\JobOrderItemTask;
use App\Models\Production\ManufactureTaskSession;
use App\Models\Production\ManufactureTask;
use App\Models\Production\Production;
use App\Models\Production\RawMaterial;
use Config;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\delete;

require_once __DIR__.'/../Support/ProductionHelpers.php';

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    $this->organisation = createOrganisation();
    $this->group        = group();
    setPermissionsTeamId($this->group->id);
    $this->guest        = createAdminGuest($this->group);
    ManufactureTaskSession::where('user_id', $this->guest->getUser()->id)
        ->where('state', ManufactureTaskSessionStateEnum::OPEN)
        ->update(['state' => ManufactureTaskSessionStateEnum::VOIDED]);

    $production = Production::orderBy('id')->first();
    if (!$production) {
        data_set($storeData, 'code', 'CODE');
        data_set($storeData, 'name', 'NAME');

        $production = StoreProduction::make()->action(
            $this->organisation,
            $storeData
        );
    }
    $this->production = $production;

    $artefact = Artefact::orderBy('id')->first();
    if (!$artefact) {
        data_set($storeData, 'code', 'CODE');
        data_set($storeData, 'name', 'NAME');
        data_set($storeData, 'state', \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value);

        $artefact = StoreArtefact::make()->action(
            $this->production,
            $storeData
        );
    }
    $this->artefact = $artefact;

    $rawMaterial = RawMaterial::orderBy('id')->first();
    if (!$rawMaterial) {
        data_set($storeData, 'type', RawMaterialTypeEnum::CONSUMABLE->value);
        data_set($storeData, 'state', RawMaterialStateEnum::ORPHAN->value);
        data_set($storeData, 'code', 'CODE');
        data_set($storeData, 'description', 'desc');
        data_set($storeData, 'unit', RawMaterialUnitEnum::KILOGRAM->value);
        data_set($storeData, 'unit_cost', 10);

        $rawMaterial = StoreRawMaterial::make()->action(
            $this->production,
            $storeData
        );
    }
    $this->rawMaterial = $rawMaterial;

    $manufactureTask = ManufactureTask::where('code', '!=', 'PROD')->orderBy('id')->first();
    if (!$manufactureTask) {
        data_set($storeData, 'code', 'CODE');
        data_set($storeData, 'name', 'name');
        data_set($storeData, 'task_materials_cost', 10);
        data_set($storeData, 'task_energy_cost', 10);
        data_set($storeData, 'task_other_cost', 10);
        data_set($storeData, 'task_work_cost', 10);
        data_set($storeData, 'task_lower_target', 10);
        data_set($storeData, 'task_upper_target', 10);
        data_set($storeData, 'operative_reward_terms', ManufactureTaskOperativeRewardTermsEnum::ABOVE_LOWER_LIMIT->value);
        data_set($storeData, 'operative_reward_allowance_type', ManufactureTaskOperativeRewardAllowanceTypeEnum::OFFSET_SALARY->value);
        data_set($storeData, 'operative_reward_amount', 10);

        $manufactureTask = StoreManufactureTask::make()->action(
            $this->production,
            $storeData
        );
    }
    $this->manufactureTask = $manufactureTask;
    $this->artisan('group:seed_aiku_scoped_sections')->assertExitCode(0);

    Config::set(
        'inertia.testing.page_paths',
        [resource_path('js/Pages/Grp')]
    );
    actingAs($this->guest->getUser());

});

test('artefacts with nothing sold in three years go dormant and wake up when they sell again', function () {
    $repair = \App\Actions\Maintenance\Production\RepairDormantArtefacts::make();
    $since  = now()->subMonths(36)->toDateTimeString();

    $artefact = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'DORM-01', 'name' => 'Dormant candidate']);
    $artefact->update(['state' => ArtefactStateEnum::ACTIVE, 'created_at' => now()->subYears(4)]);
    expect($repair->toPark($this->production, $since)->pluck('id')->all())->toContain($artefact->id);

    $repair->handle($artefact, ArtefactStateEnum::DORMANT);
    expect($artefact->refresh()->state)->toBe(ArtefactStateEnum::DORMANT)
        ->and($repair->toWake($this->production, $since)->count())->toBe(0);

    $made = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'DORM-02', 'name' => 'Made but never sold']);
    $made->update(['state' => ArtefactStateEnum::ACTIVE]);
    StoreJobOrderItem::make()->action(StoreJobOrder::make()->action($this->production, []), ['artefact_id' => $made->id, 'quantity' => 1]);
    expect($repair->toPark($this->production, $since)->pluck('id')->all())->not->toContain($made->id);

    list($organisation, $user, $shop) = createShop();
    [$orgStocks, $product] = createProduct($shop);
    $orgStock = $product->orgStocks()->first();
    if (!$orgStock) {
        $orgStock = $orgStocks[0];
        $product->orgStocks()->attach($orgStock->id, ['quantity' => 1]);
    }
    $artefact->update(['org_stock_id' => $orgStock->id]);

    $invoice = \App\Actions\Accounting\Invoice\StoreInvoice::make()->action(createCustomer($shop), \App\Models\Accounting\Invoice::factory()->definition());
    \App\Actions\Accounting\InvoiceTransaction\StoreInvoiceTransaction::make()->action($invoice, $product->historicAsset, [
        'date'            => now(),
        'tax_category_id' => $invoice->tax_category_id,
        'quantity'        => 1,
        'gross_amount'    => 10,
        'net_amount'      => 10,
    ]);

    expect($repair->toWake($this->production, $since)->pluck('id')->all())->toBe([$artefact->id])
        ->and($repair->toPark($this->production, $since)->pluck('id')->all())->not->toContain($artefact->id);

    $this->artisan('repair:dormant_artefacts', ['production' => $this->production->slug, '--fix' => true])->assertExitCode(0);
    expect($artefact->refresh()->state)->toBe(ArtefactStateEnum::ACTIVE);
});

test('repair assigns artefacts to families mirroring their org stock family', function () {
    $department = StoreArtefactDepartment::make()->action($this->production, ['code' => 'FAMDEP', 'name' => 'Family department']);

    $stockFamily = \App\Actions\Goods\StockFamily\StoreStockFamily::make()->action(
        $this->group,
        \App\Models\Goods\StockFamily::factory()->definition()
    );
    $stock = \App\Actions\Goods\Stock\StoreStock::make()->action(
        $stockFamily,
        array_merge(\App\Models\Goods\Stock::factory()->definition(), [
            'state' => \App\Enums\Goods\Stock\StockStateEnum::ACTIVE
        ])
    );
    $orgStockFamily = \App\Actions\Inventory\OrgStockFamily\StoreOrgStockFamily::make()->action($this->organisation, $stockFamily, []);
    $orgStock       = \App\Actions\Inventory\OrgStock\StoreOrgStock::make()->action($orgStockFamily, $stock);

    $artefact = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value,
        'code'                   => 'FAMART1',
        'name'                   => 'Artefact with org stock family',
        'org_stock_id'           => $orgStock->id,
        'artefact_department_id' => $department->id,
    ]);

    $orphan = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value,
        'code'                   => 'FAMART2',
        'name'                   => 'Artefact without org stock',
        'artefact_department_id' => $department->id,
    ]);

    $dryRun = AssignArtefactsToFamiliesFromOrgStockFamilies::make()->handle();
    expect($dryRun['families_created'])->toBe(1)
        ->and($artefact->refresh()->artefact_family_id)->toBeNull();

    $result = AssignArtefactsToFamiliesFromOrgStockFamilies::make()->handle(true);
    expect($result['families_created'])->toBe(1)
        ->and($result['artefacts_assigned'])->toBe(1);

    $family = ArtefactFamily::where('org_stock_family_id', $orgStockFamily->id)->first();
    expect($family)->not->toBeNull()
        ->and($family->artefact_department_id)->toBe($department->id)
        ->and($family->production_id)->toBe($this->production->id)
        ->and($family->code)->toBe($orgStockFamily->code)
        ->and($family->number_artefacts)->toBe(1)
        ->and($artefact->refresh()->artefact_family_id)->toBe($family->id)
        ->and($orphan->refresh()->artefact_family_id)->toBeNull();

    $rerun = AssignArtefactsToFamiliesFromOrgStockFamilies::make()->handle(true);
    expect($rerun['families_created'])->toBe(0)
        ->and($rerun['artefacts_assigned'])->toBe(0);
});

test('artefact family UI pages render', function () {
    $department = StoreArtefactDepartment::make()->action($this->production, ['code' => 'UIDEP', 'name' => 'UI department']);
    $family     = StoreArtefactFamily::make()->action($department, ['code' => 'UIFAM', 'name' => 'UI family']);
    StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value,
        'code'                   => 'UIFAMART',
        'name'                   => 'UI family artefact',
        'artefact_department_id' => $department->id,
        'artefact_family_id'     => $family->id,
    ]);

    $routeParameters = [$this->organisation->slug, $this->production->slug];

    get(route('grp.org.productions.show.crafts.artefact_families.index', $routeParameters))->assertOk();
    get(route('grp.org.productions.show.crafts.artefact_families.create', $routeParameters))->assertOk();
    get(route('grp.org.productions.show.crafts.artefact_families.show', array_merge($routeParameters, [$family->slug])))->assertOk();
    get(route('grp.org.productions.show.crafts.artefact_families.edit', array_merge($routeParameters, [$family->slug])))->assertOk();
    get(route('grp.org.productions.show.crafts.artefact_departments.show', array_merge($routeParameters, [$department->slug])).'?tab=families')->assertOk();
});

test('moving a family to another department takes its artefacts along', function () {
    $from = StoreArtefactDepartment::make()->action($this->production, ['code' => 'MVFROM', 'name' => 'From department']);
    $to   = StoreArtefactDepartment::make()->action($this->production, ['code' => 'MVTO', 'name' => 'To department']);

    $family   = StoreArtefactFamily::make()->action($from, ['code' => 'MVFAM', 'name' => 'Moving family']);
    $artefact = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value,
        'code'                   => 'MVART',
        'name'                   => 'Moving artefact',
        'artefact_department_id' => $from->id,
        'artefact_family_id'     => $family->id,
    ]);

    $moved = MoveArtefactFamiliesToDepartment::make()->action($this->production, [
        'families'               => [$family->id],
        'artefact_department_id' => $to->id,
    ]);

    expect($moved)->toBe(1)
        ->and($family->refresh()->artefact_department_id)->toBe($to->id)
        ->and($artefact->refresh()->artefact_department_id)->toBe($to->id)
        ->and($artefact->artefact_family_id)->toBe($family->id);

    /* Moving the artefact to a third department drops a family that does not live there. */
    $other = StoreArtefactDepartment::make()->action($this->production, ['code' => 'MVOTHER', 'name' => 'Other department']);
    MoveArtefactsToDepartment::make()->action($this->production, [
        'artefacts'              => [$artefact->id],
        'artefact_department_id' => $other->id,
    ]);

    expect($artefact->refresh()->artefact_family_id)->toBeNull()
        ->and($family->refresh()->number_artefacts)->toBe(0);
});

test('moving artefacts to a family moves them into the family department', function () {
    $home    = StoreArtefactDepartment::make()->action($this->production, ['code' => 'HOMEDEP', 'name' => 'Home department']);
    $elsewhere = StoreArtefactDepartment::make()->action($this->production, ['code' => 'AWAYDEP', 'name' => 'Away department']);

    $family   = StoreArtefactFamily::make()->action($home, ['code' => 'HOMEFAM', 'name' => 'Home family']);
    $artefact = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value,
        'code'                   => 'STRAYART',
        'name'                   => 'Stray artefact',
        'artefact_department_id' => $elsewhere->id,
    ]);

    MoveArtefactsToFamily::make()->action($this->production, [
        'artefacts'          => [$artefact->id],
        'artefact_family_id' => $family->id,
    ]);

    expect($artefact->refresh()->artefact_family_id)->toBe($family->id)
        ->and($artefact->artefact_department_id)->toBe($home->id)
        ->and($family->refresh()->number_artefacts)->toBe(1);
});

test('deleting a family orphans its artefacts but keeps them in the department', function () {
    $department = StoreArtefactDepartment::make()->action($this->production, ['code' => 'DELDEP', 'name' => 'Delete department']);
    $family     = StoreArtefactFamily::make()->action($department, ['code' => 'DELFAM', 'name' => 'Doomed family']);

    $artefact = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value,
        'code'                   => 'DELART',
        'name'                   => 'Orphan to be',
        'artefact_department_id' => $department->id,
        'artefact_family_id'     => $family->id,
    ]);

    $orphaned = DeleteArtefactFamily::make()->action($family);

    expect($orphaned)->toBe(1)
        ->and(ArtefactFamily::find($family->id))->toBeNull()
        ->and($artefact->refresh()->artefact_family_id)->toBeNull()
        ->and($artefact->artefact_department_id)->toBe($department->id);
});

test('UI delete artefact family', function () {
    $department = StoreArtefactDepartment::make()->action($this->production, ['code' => 'UIDELDEP', 'name' => 'UI delete department']);
    $family     = StoreArtefactFamily::make()->action($department, ['code' => 'UIDELFAM', 'name' => 'UI doomed family']);

    $artefact = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value,
        'code'                   => 'UIDELART',
        'name'                   => 'UI orphan to be',
        'artefact_department_id' => $department->id,
        'artefact_family_id'     => $family->id,
    ]);

    $response = get(route('grp.org.productions.show.crafts.artefact_families.show', [$this->organisation->slug, $this->production->slug, $family->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page->component('Org/Production/ArtefactFamily')
            ->where('number_artefacts', 1)
            ->missing('delete_route');
    });

    /* Deleting is a thing you go into the edit screen for, not something to graze on the family page. */
    get(route('grp.org.productions.show.crafts.artefact_families.edit', [$this->organisation->slug, $this->production->slug, $family->slug]))
        ->assertInertia(function (AssertableInertia $page) {
            $page->component('Org/Production/EditArtefactFamily')
                ->where('number_artefacts', 1)
                ->has('delete_route');
        });

    delete(route('grp.models.artefact_family.delete', [$family->id]))
        ->assertRedirect(route('grp.org.productions.show.crafts.artefact_families.index', [$this->organisation->slug, $this->production->slug]));

    expect(ArtefactFamily::find($family->id))->toBeNull()
        ->and($artefact->refresh()->artefact_family_id)->toBeNull()
        ->and($artefact->artefact_department_id)->toBe($department->id);
});

test('UI crafts artefacts as org admin', function () {
    $this->withoutExceptionHandling();
    $user = $this->guest->getUser();
    $user->syncRoles(['org-admin-'.$this->organisation->id, 'production-orchestrator-'.$this->production->id]);
    actingAs($user->fresh());
    foreach (['dashboard', 'artefacts.index', 'raw_materials.index'] as $page) {
        get(route('grp.org.productions.show.crafts.'.$page, [$this->organisation->slug, $this->production->slug]))->assertOk();
    }
});

test('artefact family state follows the liveliest of its artefacts', function () {
    $department = StoreArtefactDepartment::make()->action($this->production, ['code' => 'STATEDEP', 'name' => 'State department']);
    $family     = StoreArtefactFamily::make()->action($department, ['code' => 'STATEFAM', 'name' => 'State family']);

    $one = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value,
        'code'               => 'STATE-01',
        'name'               => 'First',
        'artefact_family_id' => $family->id,
    ]);
    $two = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value,
        'code'               => 'STATE-02',
        'name'               => 'Second',
        'artefact_family_id' => $family->id,
    ]);

    SetArtefactState::make()->action($one, ArtefactStateEnum::ACTIVE);
    SetArtefactState::make()->action($two, ArtefactStateEnum::DORMANT);

    expect($family->refresh()->state)->toBe(ArtefactStateEnum::ACTIVE)
        ->and($family->number_artefacts)->toBe(2);

    SetArtefactState::make()->action($one, ArtefactStateEnum::DORMANT);
    expect($family->refresh()->state)->toBe(ArtefactStateEnum::DORMANT);

    SetArtefactState::make()->action($one, ArtefactStateEnum::DISCONTINUED);
    SetArtefactState::make()->action($two, ArtefactStateEnum::DISCONTINUED);
    expect($family->refresh()->state)->toBe(ArtefactStateEnum::DISCONTINUED);
});

test('artefact families index hides dormant and discontinued families by default', function () {
    $department = StoreArtefactDepartment::make()->action($this->production, ['code' => 'HIDEDEP', 'name' => 'Hide department']);
    $live       = StoreArtefactFamily::make()->action($department, ['code' => 'HIDELIVE', 'name' => 'Live family']);
    $parked     = StoreArtefactFamily::make()->action($department, ['code' => 'HIDEPARK', 'name' => 'Parked family']);

    $liveArtefact = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'HIDE-01', 'name' => 'Live', 'artefact_family_id' => $live->id]);
    $deadArtefact = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'HIDE-02', 'name' => 'Dead', 'artefact_family_id' => $parked->id]);

    SetArtefactState::make()->action($liveArtefact, ArtefactStateEnum::ACTIVE);
    SetArtefactState::make()->action($deadArtefact, ArtefactStateEnum::DISCONTINUED);

    expect($parked->refresh()->state)->toBe(ArtefactStateEnum::DISCONTINUED);

    $response = get(route('grp.org.productions.show.crafts.artefact_families.index', [$this->organisation->slug, $this->production->slug]));

    $response->assertOk();
    expect($response->content())->toContain('HIDELIVE')
        ->and($response->content())->not->toContain('HIDEPARK');
});

test('artefact family counts artefacts missing a recipe and a batch size', function () {
    $department = StoreArtefactDepartment::make()->action($this->production, ['code' => 'GAPDEP', 'name' => 'Gap department']);
    $family     = StoreArtefactFamily::make()->action($department, ['code' => 'GAPFAM', 'name' => 'Gap family']);

    $complete = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value,
        'code'                   => 'GAP-01',
        'name'                   => 'Complete',
        'artefact_family_id'     => $family->id,
        'recommended_batch_size' => 40,
    ]);
    StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value,
        'code'               => 'GAP-02',
        'name'               => 'No batch size, no recipe',
        'artefact_family_id' => $family->id,
    ]);

    expect($family->refresh()->number_artefacts_without_batch_size)->toBe(1)
        ->and($family->number_artefacts_without_recipe)->toBe(2);

    $task = StoreManufactureTask::make()->action($this->production, [
        'code'                            => 'GAPTASK',
        'name'                            => 'Gap task',
        'task_materials_cost'             => 1.0,
        'task_energy_cost'                => 1.0,
        'task_other_cost'                 => 1.0,
        'task_work_cost'                  => 1.0,
        'task_lower_target'               => 10,
        'task_upper_target'               => 20,
        'operative_reward_terms'          => ManufactureTaskOperativeRewardTermsEnum::ABOVE_LOWER_LIMIT,
        'operative_reward_allowance_type' => ManufactureTaskOperativeRewardAllowanceTypeEnum::OFFSET_SALARY,
        'operative_reward_amount'         => 1.0,
    ]);
    AttachManufactureTaskToArtefact::make()->action($complete, ['manufacture_task_id' => $task->id]);
    AttachRawMaterialToRecipeStep::make()->action(
        ArtefactManufactureTask::where('artefact_id', $complete->id)->where('manufacture_task_id', $task->id)->first(),
        ['raw_material_id' => $this->rawMaterial->id, 'quantity_per_unit' => 1]
    );

    ArtefactFamilyHydrateArtefacts::run($family);

    expect($family->refresh()->number_artefacts_without_recipe)->toBe(1)
        ->and($family->number_artefacts_without_batch_size)->toBe(1);
});

test('crafts dashboard families card carries the family state counts', function () {
    $department = StoreArtefactDepartment::make()->action($this->production, ['code' => 'CARDDEP', 'name' => 'Card department']);
    $family     = StoreArtefactFamily::make()->action($department, ['code' => 'CARDFAM', 'name' => 'Card family']);

    $artefact = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value,
        'code'               => 'CARD-01',
        'name'               => 'Card artefact',
        'artefact_family_id' => $family->id,
    ]);
    SetArtefactState::make()->action($artefact, ArtefactStateEnum::DISCONTINUED);

    $response = get(route('grp.org.productions.show.crafts.dashboard', [$this->organisation->slug, $this->production->slug]));

    $response->assertOk();
    $response->assertInertia(function (AssertableInertia $page) {
        $stats   = collect($page->toArray()['props']['stats']);
        $card    = $stats->firstWhere('label', 'Families');
        $tooltips = collect($card['metas'])->pluck('tooltip');

        expect($tooltips)->toContain('Active families')
            ->and($tooltips)->toContain('Discontinued')
            ->and(collect($card['metas'])->firstWhere('tooltip', 'Discontinued')['count'])->toBeGreaterThanOrEqual(1);
    });
});

test('set artefacts batch size in bulk and rehydrate the family', function () {
    $department = StoreArtefactDepartment::make()->action($this->production, ['code' => 'BULKDEP', 'name' => 'Bulk department']);
    $family     = StoreArtefactFamily::make()->action($department, ['code' => 'BULKFAM', 'name' => 'Bulk family']);

    $one = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'BULK-01', 'name' => 'One', 'artefact_family_id' => $family->id]);
    $two = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'BULK-02', 'name' => 'Two', 'artefact_family_id' => $family->id]);

    expect($family->refresh()->number_artefacts_without_batch_size)->toBe(2);

    $changed = SetArtefactsBatchSize::make()->action($this->production, [
        'artefacts'              => [$one->id, $two->id],
        'recommended_batch_size' => 200,
    ]);

    expect($changed)->toBe(2)
        ->and($one->refresh()->recommended_batch_size)->toBe(200)
        ->and($two->refresh()->recommended_batch_size)->toBe(200)
        ->and($family->refresh()->number_artefacts_without_batch_size)->toBe(0)
        ->and($one->audits()->where('event', 'updated')->latest('id')->first()->new_values)->toBe(['recommended_batch_size' => 200]);
});

test('bulk batch size leaves artefacts of another production alone', function () {
    $mine = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'SCOPE-01', 'name' => 'Mine']);

    $otherProduction = StoreProduction::make()->action($this->organisation, [
        'code' => 'SCOPEPROD',
        'name' => 'Scope production',
    ]);
    $theirs = StoreArtefact::make()->action($otherProduction, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'SCOPE-02', 'name' => 'Theirs']);

    $changed = SetArtefactsBatchSize::make()->action($this->production, [
        'artefacts'              => [$mine->id, $theirs->id],
        'recommended_batch_size' => 50,
    ]);

    expect($changed)->toBe(1)
        ->and($mine->refresh()->recommended_batch_size)->toBe(50)
        ->and($theirs->refresh()->recommended_batch_size)->toBeNull();
});

test('unified manufacture task replaces the steps of every selected artefact', function () {
    $pouring = stepTestManufactureTask($this->production, 'POUR', 'Pouring');
    $boxing  = stepTestManufactureTask($this->production, 'BOX', 'Boxing');
    $oil     = RawMaterial::where('code', 'UNI-OIL')->first() ?? StoreRawMaterial::make()->action($this->production, [
        'type'        => RawMaterialTypeEnum::CONSUMABLE->value,
        'state'       => RawMaterialStateEnum::ORPHAN->value,
        'code'        => 'UNI-OIL',
        'description' => 'Unified oil',
        'unit'        => RawMaterialUnitEnum::KILOGRAM->value,
        'unit_cost'   => 4,
    ]);

    $one = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'UNI-01', 'name' => 'One']);
    $two = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'UNI-02', 'name' => 'Two']);

    $removedStep = ArtefactManufactureTask::where('artefact_id', $one->id)->first();
    AttachRawMaterialToRecipeStep::make()->action($removedStep, ['raw_material_id' => $this->rawMaterial->id, 'quantity_per_unit' => 1]);

    AttachManufactureTaskToArtefact::make()->action($two, ['manufacture_task_id' => $boxing->id, 'position' => 5]);
    $keptStep = ArtefactManufactureTask::where('artefact_id', $two->id)->where('manufacture_task_id', $boxing->id)->first();
    AttachRawMaterialToRecipeStep::make()->action($keptStep, ['raw_material_id' => $this->rawMaterial->id, 'quantity_per_unit' => 0.3]);

    $changed = SetArtefactsRecipe::make()->action($this->production, [
        'artefacts' => [$one->id, $two->id],
        'steps'     => [
            ['manufacture_task_id' => $pouring->id, 'position' => 1, 'units_per_artefact' => 2, 'raw_materials' => [['raw_material_id' => $oil->id, 'quantity_per_unit' => 0.5]]],
            ['manufacture_task_id' => $boxing->id, 'position' => 2, 'units_per_artefact' => 1],
        ],
    ]);

    $expected = [
        ['manufacture_task_id' => $pouring->id, 'position' => 1, 'units_per_artefact' => 2.0, 'raw_materials' => [$oil->id => 0.5]],
        ['manufacture_task_id' => $boxing->id, 'position' => 2, 'units_per_artefact' => 1.0, 'raw_materials' => []],
    ];

    expect($changed)->toBe(2)
        ->and(unifiedRecipeSteps($one))->toBe($expected)
        ->and(unifiedRecipeSteps($two))->toBe($expected)
        ->and(ArtefactManufactureTask::find($removedStep->id))->toBeNull()
        ->and(RecipeStepRawMaterial::where('artefact_manufacture_task_id', $removedStep->id)->exists())->toBeFalse()
        ->and(ArtefactManufactureTask::find($keptStep->id))->not->toBeNull();
});

test('unified manufacture task can give an artefact its own raw materials on a step', function () {
    $pouring = stepTestManufactureTask($this->production, 'POUR', 'Pouring');
    $oil     = RawMaterial::where('code', 'UNI-OIL')->first();

    $shared = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'UNI-07', 'name' => 'Shared']);
    $own    = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'UNI-08', 'name' => 'Own']);
    $none   = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'UNI-09', 'name' => 'None']);

    SetArtefactsRecipe::make()->action($this->production, [
        'artefacts' => [$shared->id, $own->id, $none->id],
        'steps'     => [[
            'manufacture_task_id'    => $pouring->id,
            'position'               => 1,
            'units_per_artefact'     => 1,
            'raw_materials'          => [['raw_material_id' => $oil->id, 'quantity_per_unit' => 0.5]],
            'artefact_raw_materials' => [
                ['artefact_id' => $own->id, 'raw_materials' => [['raw_material_id' => $this->rawMaterial->id, 'quantity_per_unit' => 2]]],
                ['artefact_id' => $none->id, 'raw_materials' => []],
            ],
        ]],
    ]);

    expect(unifiedRecipeSteps($shared)[0]['raw_materials'])->toBe([$oil->id => 0.5])
        ->and(unifiedRecipeSteps($own)[0]['raw_materials'])->toBe([$this->rawMaterial->id => 2.0])
        ->and(unifiedRecipeSteps($none)[0]['raw_materials'])->toBe([]);
});

test('unified manufacture task swaps the steps nobody has started on open job orders', function () {
    $pouring  = stepTestManufactureTask($this->production, 'POUR', 'Pouring');
    $artefact = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'UNI-03', 'name' => 'Three']);

    $jobOrder     = StoreJobOrder::make()->action($this->production, []);
    $jobOrderItem = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $artefact->id, 'quantity' => 4]);
    ConfirmJobOrder::make()->action($jobOrder);
    $defaultStep = $jobOrderItem->tasks()->first();

    SetArtefactsRecipe::make()->action($this->production, [
        'artefacts' => [$artefact->id],
        'steps'     => [['manufacture_task_id' => $pouring->id, 'position' => 1, 'units_per_artefact' => 3]],
    ]);

    $tasks = $jobOrderItem->refresh()->tasks()->get();

    expect(JobOrderItemTask::find($defaultStep->id))->toBeNull()
        ->and($tasks->pluck('manufacture_task_id')->all())->toBe([$pouring->id])
        ->and((float) $tasks->first()->quantity_required)->toBe(12.0);
});

test('unified manufacture task rejects a task used twice and leaves other productions alone', function () {
    $pouring  = stepTestManufactureTask($this->production, 'POUR', 'Pouring');
    $artefact = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'UNI-04', 'name' => 'Four']);

    expect(fn () => SetArtefactsRecipe::make()->action($this->production, [
        'artefacts' => [$artefact->id],
        'steps'     => [
            ['manufacture_task_id' => $pouring->id, 'position' => 1, 'units_per_artefact' => 1],
            ['manufacture_task_id' => $pouring->id, 'position' => 2, 'units_per_artefact' => 1],
        ],
    ]))->toThrow(ValidationException::class);

    $otherProduction = Production::where('code', 'UNIPROD')->first() ?? StoreProduction::make()->action($this->organisation, [
        'code' => 'UNIPROD',
        'name' => 'Unified scope production',
    ]);
    $theirs      = StoreArtefact::make()->action($otherProduction, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'UNI-05', 'name' => 'Theirs']);
    $theirRecipe = unifiedRecipeSteps($theirs);

    $changed = SetArtefactsRecipe::make()->action($this->production, [
        'artefacts' => [$artefact->id, $theirs->id],
        'steps'     => [['manufacture_task_id' => $pouring->id, 'position' => 1, 'units_per_artefact' => 1]],
    ]);

    expect($changed)->toBe(1)
        ->and(unifiedRecipeSteps($theirs))->toBe($theirRecipe);
});

test('unified manufacture task is offered on the artefacts page and saves through its route', function () {
    $pouring  = stepTestManufactureTask($this->production, 'POUR', 'Pouring');
    $artefact = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'UNI-06', 'name' => 'Six']);

    $this->get(route('grp.org.productions.show.crafts.artefacts.index', [$this->organisation->slug, $this->production->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('set_recipe.set_route.name', 'grp.models.production.artefacts.set_recipe')
            ->where('set_recipe.task_options.name', 'grp.json.production.manufacture_tasks.index'));

    $department = ArtefactDepartment::where('code', 'UNIDEP')->first()
        ?? StoreArtefactDepartment::make()->action($this->production, ['code' => 'UNIDEP', 'name' => 'Unified department']);
    $family = ArtefactFamily::where('code', 'UNIFAM')->first()
        ?? StoreArtefactFamily::make()->action($department, ['code' => 'UNIFAM', 'name' => 'Unified family']);

    $this->get(route('grp.org.productions.show.crafts.artefact_families.show', [$this->organisation->slug, $this->production->slug, $family->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('set_recipe.set_route.name', 'grp.models.production.artefacts.set_recipe'));
    $this->get(route('grp.org.productions.show.crafts.artefact_departments.show', [$this->organisation->slug, $this->production->slug, $department->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('set_recipe.set_route.name', 'grp.models.production.artefacts.set_recipe'));

    $this->post(route('grp.models.production.artefacts.set_recipe', [$this->production->id]), [
        'artefacts' => [$artefact->id],
        'steps'     => [['manufacture_task_id' => $pouring->id, 'position' => 1, 'units_per_artefact' => 1]],
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(collect(unifiedRecipeSteps($artefact))->pluck('manufacture_task_id')->all())->toBe([$pouring->id]);
});

test('discontinue artefacts in bulk and take the family down with them', function () {
    $department = StoreArtefactDepartment::make()->action($this->production, ['code' => 'DISCDEP', 'name' => 'Disc department']);
    $family     = StoreArtefactFamily::make()->action($department, ['code' => 'DISCFAM', 'name' => 'Disc family']);

    $one = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'DISC-01', 'name' => 'One', 'artefact_family_id' => $family->id]);
    $two = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'DISC-02', 'name' => 'Two', 'artefact_family_id' => $family->id]);
    SetArtefactState::make()->action($one, ArtefactStateEnum::ACTIVE);
    SetArtefactState::make()->action($two, ArtefactStateEnum::ACTIVE);

    expect($family->refresh()->state)->toBe(ArtefactStateEnum::ACTIVE);

    $changed = SetArtefactsState::make()->action($this->production, ['artefacts' => [$one->id, $two->id], 'state' => ArtefactStateEnum::DISCONTINUED->value]);

    expect($changed)->toBe(2)
        ->and($one->refresh()->state)->toBe(ArtefactStateEnum::DISCONTINUED)
        ->and($two->refresh()->state)->toBe(ArtefactStateEnum::DISCONTINUED)
        ->and($family->refresh()->state)->toBe(ArtefactStateEnum::DISCONTINUED);

    $again = SetArtefactsState::make()->action($this->production, ['artefacts' => [$one->id, $two->id], 'state' => ArtefactStateEnum::DISCONTINUED->value]);
    expect($again)->toBe(0);

    /* Discontinuing has to be undoable, otherwise one wrong click needs a developer. */
    $revived = SetArtefactsState::make()->action($this->production, ['artefacts' => [$one->id, $two->id], 'state' => ArtefactStateEnum::ACTIVE->value]);

    expect($revived)->toBe(2)
        ->and($one->refresh()->state)->toBe(ArtefactStateEnum::ACTIVE)
        ->and($family->refresh()->state)->toBe(ArtefactStateEnum::ACTIVE);
});

test('artefact index reports the batch size in SKOs', function () {
    $stock = \App\Actions\Goods\Stock\StoreStock::make()->action(
        $this->group,
        array_merge(\App\Models\Goods\Stock::factory()->definition(), [
            'state' => \App\Enums\Goods\Stock\StockStateEnum::ACTIVE
        ])
    );
    $orgStock = \App\Actions\Inventory\OrgStock\StoreOrgStock::make()->action($this->organisation, $stock);
    $orgStock->update(['packed_in' => 10]);

    $artefact = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value,
        'code'                   => 'SKOMISMATCH',
        'name'                   => 'Batch that does not fit the pack',
        'org_stock_id'           => $orgStock->id,
        'recommended_batch_size' => 16,
    ]);

    $this->get(route('grp.org.productions.show.crafts.artefacts.index', [$this->organisation->slug, $this->production->slug]));

    $row = IndexArtefacts::make()->handle($this->production)
        ->firstWhere('id', $artefact->id);

    expect($row)->not->toBeNull()
        ->and((int) $row->packed_in)->toBe(10);

    $resource = \App\Http\Resources\Production\ArtefactsResource::make($row)->resolve();
    expect($resource['batch_in_skos'])->toBe(1.6)
        ->and($resource['suggested_batch_size'])->toBe(20);

    $showcase = \App\Actions\Production\Artefact\UI\GetArtefactShowcase::run($artefact->refresh());
    expect($showcase['batch_pack'])->toBe([
        'packed_in'            => 10,
        'batch_in_skos'        => 1.6,
        'suggested_batch_size' => 20,
    ]);
});
