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
use App\Actions\Production\Artefact\UpdateArtefact;
use App\Actions\Production\Artefact\UI\GetArtefactShowcase;
use App\Actions\Production\ArtefactDepartment\StoreArtefactDepartment;
use App\Actions\Production\Artefact\MoveArtefactsToFamily;
use App\Actions\Production\Artefact\SetArtefactsState;
use App\Actions\Production\Artefact\SetArtefactState;
use App\Actions\Production\Artefact\SetArtefactsBatchSize;
use App\Actions\Production\ArtefactFamily\Hydrators\ArtefactFamilyHydrateArtefacts;
use App\Actions\Production\Artefact\UI\IndexArtefacts;
use App\Actions\Production\ArtefactFamily\AssignArtefactsToFamiliesFromOrgStockFamilies;
use App\Actions\Production\ArtefactFamily\DeleteArtefactFamily;
use App\Actions\Production\ArtefactFamily\MoveArtefactFamiliesToDepartment;
use App\Actions\Production\ArtefactFamily\StoreArtefactFamily;
use App\Models\Production\ArtefactFamily;
use App\Actions\Production\ArtefactDepartment\UpdateArtefactDepartment;
use App\Actions\Production\Artisan\AttachArtisan;
use App\Actions\Production\Artisan\DetachArtisan;
use App\Actions\Production\Artisan\ToggleArtisanInRoster;
use App\Actions\SysAdmin\Organisation\Seeders\SeedJobPositions;
use App\Actions\HumanResources\JobPosition\SyncEmployeeJobPositions;
use App\Actions\HumanResources\Employee\UpdateEmployee;
use App\Actions\HumanResources\Employee\GetEmployeeJobPositionsData;
use App\Models\HumanResources\JobPosition;
use App\Models\SysAdmin\Role;
use App\Actions\Production\PartnerShippingList\GetMixesToPrepare;
use App\Actions\Production\PartnerShippingList\GetMixJobOrders;
use App\Actions\Production\Artefact\SetArtefactAsMix;
use App\Actions\Production\JobOrderItem\GetJobOrderItemMissingMixes;
use App\Actions\Production\PartnerShippingList\StoreJobOrdersForMixes;
use App\Actions\HumanResources\Employee\StoreEmployee;
use App\Models\HumanResources\Employee;
use App\Enums\Helpers\Tag\TagScopeEnum;
use App\Models\Helpers\Tag;
use App\Actions\Production\JobOrder\ConfirmJobOrder;
use App\Actions\Production\JobOrder\StoreJobOrder;
use App\Actions\Production\JobOrder\UpdateJobOrder;
use App\Actions\Production\ManufactureTask\StoreManufactureTask;
use App\Actions\Production\Artefact\AttachManufactureTaskToArtefact;
use App\Actions\Production\Artefact\AttachRawMaterialToRecipeStep;
use App\Actions\Production\Artefact\DetachManufactureTaskFromArtefact;
use App\Actions\Production\Artefact\DetachRawMaterialFromRecipeStep;
use App\Models\Production\ArtefactManufactureTask;
use App\Models\Production\RecipeStepRawMaterial;
use App\Actions\Production\JobOrderItem\StoreJobOrderItem;
use App\Actions\Production\ManufactureTaskSession\CloseManufactureTaskSession;
use App\Actions\Production\ManufactureTaskSession\StartManufactureTaskSession;
use App\Actions\Production\ManufactureTaskSession\VoidManufactureTaskSession;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Enums\Production\JobOrderItemTask\JobOrderItemTaskStateEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum;
use App\Actions\Production\ManufactureTask\UpdateManufactureTask;
use App\Actions\Production\Production\StoreProduction;
use App\Actions\Production\Production\UpdateProduction;
use App\Actions\Production\RawMaterial\StoreRawMaterial;
use App\Actions\Production\RawMaterial\UpdateRawMaterial;
use App\Actions\SysAdmin\GetSectionRoute;
use App\Enums\Analytics\AikuSection\AikuSectionEnum;
use App\Enums\Production\ManufactureTask\ManufactureTaskOperativeRewardAllowanceTypeEnum;
use App\Enums\Production\ManufactureTask\ManufactureTaskOperativeRewardTermsEnum;
use App\Enums\Production\RawMaterial\RawMaterialStateEnum;
use App\Enums\Production\RawMaterial\RawMaterialStockStatusEnum;
use App\Enums\Production\RawMaterial\RawMaterialTypeEnum;
use App\Enums\Production\RawMaterial\RawMaterialUnitEnum;
use App\Models\Analytics\AikuScopedSection;
use App\Enums\Production\Artefact\ArtefactStateEnum;
use App\Models\Production\Artefact;
use App\Models\Production\JobOrder;
use App\Models\Production\JobOrderItem;
use App\Models\Production\ManufactureTask;
use App\Models\Production\Production;
use App\Models\Production\RawMaterial;
use Config;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\delete;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    $this->organisation = createOrganisation();
    $this->group        = group();
    setPermissionsTeamId($this->group->id);
    $this->guest        = createAdminGuest($this->group);

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

    $manufactureTask = ManufactureTask::orderBy('id')->first();
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

test('create production', function () {
    $production = StoreProduction::make()->action(
        $this->organisation,
        [
            'code' => 'ts12',
            'name' => 'testName',
        ]
    );

    $user = $this->guest->getUser();
    $user->refresh();

    expect($production)->toBeInstanceOf(Production::class)
        ->and($this->organisation->manufactureStats->number_productions)->toBe(2)
        ->and($this->organisation->manufactureStats->number_productions_state_in_process)->toBe(2)
        ->and($this->organisation->manufactureStats->number_productions_state_open)->toBe(0)
        ->and($this->organisation->manufactureStats->number_productions_state_closing_down)->toBe(0)
        ->and($this->organisation->manufactureStats->number_productions_state_closed)->toBe(0)
        ->and($this->organisation->group->manufactureStats->number_productions)->toBe(2)
        ->and($this->organisation->group->manufactureStats->number_productions_state_in_process)->toBe(2)
        ->and($this->organisation->group->manufactureStats->number_productions_state_open)->toBe(0)
        ->and($user->authorisedProductions()->where('organisation_id', $this->organisation->id)->count())->toBe(2)
        ->and($user->number_authorised_productions)->toBe(2);


    return $production;
});

test('production cannot be created with same code', function () {
    StoreProduction::make()->action(
        $this->organisation,
        [
            'code' => 'ts12',
            'name' => 'testName',
        ]
    );
})->depends('create production')->throws(ValidationException::class);

test('production cannot be created with same code case is sensitive', function () {
    StoreProduction::make()->action(
        $this->organisation,
        [
            'code' => 'TS12',
            'name' => 'testName',
        ]
    );
})->depends('create production')->throws(ValidationException::class);

test('update production', function ($production) {
    $production = UpdateProduction::make()->action($production, ['name' => 'Pika Ltd']);
    expect($production->name)->toBe('Pika Ltd');
})->depends('create production');

test('create production by command', function () {
    $this->artisan('production:create', [
        'organisation' => $this->organisation->slug,
        'code'         => 'AA',
        'name'         => 'testName A',
    ])->assertExitCode(0);

    $production = Production::where('code', 'AA')->first();

    $organisation = $this->organisation;
    $organisation->refresh();


    expect($organisation->manufactureStats->number_productions)->toBe(3)
        ->and($organisation->group->manufactureStats->number_productions)->toBe(3)
        ->and($production->roles()->count())->toBe(6);
});

test('seed production permissions', function () {
    setPermissionsTeamId($this->group->id);
    $this->artisan('production:seed-permissions')->assertExitCode(0);
    $production = Production::where('code', 'AA')->first();
    expect($production->roles()->count())->toBe(6);
});

test('can store a raw material', function (Production $production) {
    $data        = [
        'type'             => RawMaterialTypeEnum::STOCK,
        'state'            => RawMaterialStateEnum::IN_PROCESS,
        'code'             => 'RM001',
        'description'      => 'Test Raw Material',
        'unit'             => RawMaterialUnitEnum::KILOGRAM,
        'unit_cost'        => 10.5,
        'stock'            => 100,
        'stock_status'     => RawMaterialStockStatusEnum::UNLIMITED,
    ];
    $rawMaterial = StoreRawMaterial::make()->action(
        $production,
        $data
    );
    $production->refresh();

    expect($rawMaterial)->toBeInstanceOf(RawMaterial::class)
        ->and($rawMaterial->group_id)->toBe($this->organisation->group_id)
        ->and($production->stats->number_raw_materials)->toBe(1)
        ->and($rawMaterial->organisation->manufactureStats->number_raw_materials)->toBe(2)
        ->and($rawMaterial->group->manufactureStats->number_raw_materials)->toBe(2);


    return $rawMaterial;
})->depends('create production');

test('can update a raw material', function ($rawMaterial) {

    $data = [
        'type'                    => RawMaterialTypeEnum::INTERMEDIATE,
        'state'                   => RawMaterialStateEnum::DISCONTINUED,
        'code'                    => 'RM002',
        'description'             => 'Updated Raw Material',
        'unit'                    => RawMaterialUnitEnum::LITER,
        'unit_cost'               => 15.5,
        'stock'                   => 200,
    ];


    $updatedRawMaterial = UpdateRawMaterial::make()->action(
        $rawMaterial,
        $data
    );

    expect($updatedRawMaterial)->toBeInstanceOf(RawMaterial::class)
        ->and($updatedRawMaterial->id)->toBe($rawMaterial->id)
        ->and($updatedRawMaterial->type)->toBe($data['type'])
        ->and($updatedRawMaterial->state)->toBe($data['state'])
        ->and($updatedRawMaterial->unit)->toBe($data['unit']);

})->depends('can store a raw material');

test('create manufacture task', function (Production $production) {
    $data = [
        'code'                            => 'MT001',
        'name'                            => 'Test Manufacture Task',
        'task_materials_cost'             => 100.0,
        'task_energy_cost'                => 50.0,
        'task_other_cost'                 => 20.0,
        'task_work_cost'                  => 150.0,
        'task_lower_target'               => 200,
        'task_upper_target'               => 400,
        'operative_reward_terms'          => ManufactureTaskOperativeRewardTermsEnum::ABOVE_LOWER_LIMIT,
        'operative_reward_allowance_type' => ManufactureTaskOperativeRewardAllowanceTypeEnum::OFFSET_SALARY,
        'operative_reward_amount'         => 20.0,
    ];

    $manufactureTask = StoreManufactureTask::make()->action(
        $production,
        $data
    );

    expect($manufactureTask)->toBeInstanceOf(ManufactureTask::class)
    ->and($manufactureTask->code)->toBe($data['code'])
    ->and($manufactureTask->name)->toBe($data['name'])
    ->and($manufactureTask->task_materials_cost)->toBe($data['task_materials_cost'])
    ->and($manufactureTask->task_energy_cost)->toBe($data['task_energy_cost'])
    ->and($manufactureTask->task_other_cost)->toBe($data['task_other_cost'])
    ->and($manufactureTask->task_work_cost)->toBe($data['task_work_cost'])
    ->and($manufactureTask->task_lower_target)->toBe($data['task_lower_target'])
    ->and($manufactureTask->task_upper_target)->toBe($data['task_upper_target'])
    ->and($manufactureTask->operative_reward_terms)->toBe($data['operative_reward_terms'])
    ->and($manufactureTask->operative_reward_allowance_type)->toBe($data['operative_reward_allowance_type'])
    ->and($manufactureTask->operative_reward_amount)->toBe($data['operative_reward_amount']);

    return $manufactureTask;
})->depends('create production');

test('update manufacture task', function ($manufactureTask) {

    $data = [
        'code'                            => 'MT002',
        'name'                            => 'Updated Manufacture Task',
        'task_materials_cost'             => 150.0,
        'task_energy_cost'                => 70.0,
        'task_other_cost'                 => 30.0,
        'task_work_cost'                  => 180.0,
        'task_lower_target'               => 250,
        'task_upper_target'               => 450,
        'operative_reward_terms'          => ManufactureTaskOperativeRewardTermsEnum::ABOVE_UPPER_LIMIT,
        'operative_reward_allowance_type' => ManufactureTaskOperativeRewardAllowanceTypeEnum::ON_TOP_SALARY,
        'operative_reward_amount'         => 30.0,
    ];

    // Update the manufacture task
    $updatedManufactureTask = UpdateManufactureTask::make()->action(
        $manufactureTask,
        $data
    );

    // Assertions
    expect($updatedManufactureTask)->toBeInstanceOf(ManufactureTask::class)
    ->and($updatedManufactureTask->code)->toBe($data['code'])
    ->and($updatedManufactureTask->name)->toBe($data['name'])
    ->and($updatedManufactureTask->task_materials_cost)->toBe($data['task_materials_cost'])
    ->and($updatedManufactureTask->task_energy_cost)->toBe($data['task_energy_cost'])
    ->and($updatedManufactureTask->task_other_cost)->toBe($data['task_other_cost'])
    ->and($updatedManufactureTask->task_work_cost)->toBe($data['task_work_cost'])
    ->and($updatedManufactureTask->task_lower_target)->toBe($data['task_lower_target'])
    ->and($updatedManufactureTask->task_upper_target)->toBe($data['task_upper_target'])
    ->and($updatedManufactureTask->operative_reward_terms)->toBe($data['operative_reward_terms'])
    ->and($updatedManufactureTask->operative_reward_allowance_type)->toBe($data['operative_reward_allowance_type'])
    ->and($updatedManufactureTask->operative_reward_amount)->toBe($data['operative_reward_amount']);
})->depends('create manufacture task');

test('create job order', function ($production) {

    $data = [
        'public_notes'   => 'This is a public note for the job order.',
        'internal_notes' => 'These are internal notes for the job order.',
        'customer_notes' => 'These are internal notes for the job order.'
    ];

    // store job order
    $jobOrder = StoreJobOrder::make()->action(
        $production,
        $data
    );

    // Assertions
    expect($jobOrder)->toBeInstanceOf(JobOrder::class)
    ->and($jobOrder->public_notes)->toBe($data['public_notes'])
    ->and($jobOrder->internal_notes)->toBe($data['internal_notes'])
    ->and($jobOrder->customer_notes)->toBe($data['customer_notes']);

    return $jobOrder;
})->depends('create production');

test('update job order', function ($jobOrder) {

    $data = [
        'public_notes'   => 'This is an updated public note for the job order.',
        'internal_notes' => 'These are updated internal notes for the job order.',
        'customer_notes' => 'These are updated internal notes for the job order.'
    ];

    // Update the job order
    $updatedJobOrder = UpdateJobOrder::make()->action(
        $jobOrder->organisation,
        $jobOrder,
        $data
    );

    // Assertions
    expect($updatedJobOrder)->toBeInstanceOf(JobOrder::class)
    ->and($updatedJobOrder->public_notes)->toBe($data['public_notes'])
    ->and($updatedJobOrder->internal_notes)->toBe($data['internal_notes'])
    ->and($updatedJobOrder->customer_notes)->toBe($data['customer_notes']);
})->depends('create job order');

test('UI Index productions', function () {
    $response = $this->get(route('grp.org.productions.index', [$this->organisation->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Production/Productions')
            ->has('title')
            ->has('tabs')
            ->has('breadcrumbs', 2);
    });
});

test('UI show production', function () {
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.productions.show', [$this->organisation->slug, $this->production->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Production/Production')
            ->has('title')
            ->has('breadcrumbs', 2)
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $this->production->name)
                    ->etc()
            )
            ->has('tabs');

    });
});

test('UI Index raw materials', function () {
    $response = $this->get(route('grp.org.productions.show.crafts.raw_materials.index', [$this->organisation->slug, $this->production->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Production/RawMaterials')
            ->has('title')
            ->has('tabs')
            ->has('breadcrumbs', 3);
    });
});

test('UI create raw material', function () {
    $response = get(route('grp.org.productions.show.crafts.raw_materials.create', [$this->organisation->slug, $this->production->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CreateModel')
            ->has('title')->has('formData')->has('pageHead')->has('breadcrumbs', 4);
    });
});

test('UI show raw material', function () {
    $response = get(route('grp.org.productions.show.crafts.raw_materials.show', [$this->organisation->slug, $this->production->slug, $this->rawMaterial->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Production/RawMaterial')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $this->rawMaterial->code)
                    ->etc()
            )
            ->has('tabs');

    });
});

test('UI edit raw material', function () {
    $response = get(route('grp.org.productions.show.crafts.raw_materials.edit', [$this->organisation->slug, $this->production->slug, $this->rawMaterial->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('EditModel')
            ->has('title')
            ->has('formData.blueprint.0.fields', 7)
            ->has('pageHead')
            ->has('breadcrumbs', 4);
    });
});

test('update artefact category and tags', function () {
    $tag = Tag::create(['group_id' => $this->group->id, 'name' => 'lavender', 'scope' => TagScopeEnum::ARTEFACT]);

    $family = StoreArtefactDepartment::make()->action($this->production, ['code' => 'SOAP', 'name' => 'Soaps']);

    $artefact = UpdateArtefact::make()->action($this->artefact, [
        'artefact_department_id' => $family->id,
        'tags'               => [$tag->id],
    ]);

    expect($artefact->artefactDepartment->id)->toBe($family->id)
        ->and($family->refresh()->number_artefacts)->toBe(1)
        ->and($artefact->tags->pluck('name')->all())->toBe(['lavender']);

    $artefact = UpdateArtefact::make()->action($artefact, ['tags' => []]);
    expect($artefact->tags)->toHaveCount(0);

    $response = $this->get(route('grp.org.productions.show.crafts.artefact_departments.index', [$this->organisation->slug, $this->production->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page->component('Org/Production/ArtefactDepartments')->has('data.data', 1)->where('data.data.0.code', 'SOAP');
    });

    $response = $this->get(route('grp.org.productions.show.crafts.artefact_departments.show', [$this->organisation->slug, $this->production->slug, $family->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page->component('Org/Production/ArtefactDepartment')->has('artefacts.data', 1)->where('artefacts.data.0.artefact_department_name', 'Soaps');
    });

    $response = $this->get(route('grp.org.productions.show.crafts.artefact_departments.edit', [$this->organisation->slug, $this->production->slug, $family->slug]));
    $response->assertInertia(fn (AssertableInertia $page) => $page->component('EditModel')->has('formData.blueprint.0.fields', 3));

    $response = $this->get(route('grp.org.productions.show.crafts.artefact_departments.create', [$this->organisation->slug, $this->production->slug]));
    $response->assertInertia(fn (AssertableInertia $page) => $page->component('CreateModel'));

    $family = UpdateArtefactDepartment::make()->action($family, ['name' => 'Soap bars']);
    expect($family->name)->toBe('Soap bars');

    $otherFamily = StoreArtefactDepartment::make()->action($this->production, ['code' => 'BOMB', 'name' => 'Bath bombs']);
    $moved = MoveArtefactsToDepartment::make()->action($this->production, ['artefacts' => [$artefact->id], 'artefact_department_id' => $otherFamily->id]);
    expect($moved)->toBe(1)
        ->and($artefact->refresh()->artefact_department_id)->toBe($otherFamily->id)
        ->and($family->refresh()->number_artefacts)->toBe(0)
        ->and($otherFamily->refresh()->number_artefacts)->toBe(1);
});

test('UI Index artefacts', function () {
    $response = $this->get(route('grp.org.productions.show.crafts.artefacts.index', [$this->organisation->slug, $this->production->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Production/Artefacts')
            ->has('title')
            ->has('tabs')
            ->has('breadcrumbs', 3);
    });
});

test('UI create artefact', function () {
    $response = get(route('grp.org.productions.show.crafts.artefacts.create', [$this->organisation->slug, $this->production->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CreateModel')
            ->has('title')->has('formData')->has('pageHead')->has('breadcrumbs', 4);
    });
});

test('UI show artifact', function () {
    $response = get(route('grp.org.productions.show.crafts.artefacts.show', [$this->organisation->slug, $this->production->slug, $this->artefact->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Production/Artefact')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $this->artefact->name)
                    ->etc()
            )
            ->has('tabs');

    });
});

test('UI show artefact showcase exposes batch size and its update route', function () {
    $this->artefact->update(['recommended_batch_size' => null]);

    $showcase = GetArtefactShowcase::run($this->artefact->refresh());
    expect($showcase['recommended_batch_size'])->toBeNull()
        ->and($showcase['update_route']['name'])->toBe('grp.models.production.artefacts.update');

    UpdateArtefact::make()->action($this->artefact, ['recommended_batch_size' => 24]);

    expect(GetArtefactShowcase::run($this->artefact->refresh())['recommended_batch_size'])->toBe(24);
});

test('UI show artifact (manufacture task tab)', function () {
    $response = get(route('grp.org.productions.show.crafts.artefacts.show', [
        $this->organisation->slug,
        $this->production->slug,
        $this->artefact->slug,
        'tab' => 'manufacture_tasks'
    ]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Production/Artefact')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $this->artefact->name)
                    ->etc()
            )
            ->has('tabs');

    });
});

test('UI edit artefact', function () {
    $response = get(route('grp.org.productions.show.crafts.artefacts.edit', [$this->organisation->slug, $this->production->slug, $this->artefact->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('EditModel')
            ->has('title')
            ->has('formData.blueprint.0.fields', 9)
            ->has('pageHead')
            ->has('breadcrumbs', 4);
    });
});

test('UI Index production task', function () {
    $response = $this->get(route('grp.org.productions.show.operations.manufacture_tasks.index', [$this->organisation->slug, $this->production->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Production/ManufactureTasks')
            ->has('title')
            ->has('tabs')
            ->has('breadcrumbs', 3);
    });
});

test('UI create production task', function () {
    $response = get(route('grp.org.productions.show.operations.manufacture_tasks.create', [$this->organisation->slug, $this->production->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CreateModel')
            ->has('title')->has('formData')->has('pageHead')->has('breadcrumbs', 4);
    });
});

test('UI show production task', function () {
    $response = get(route('grp.org.productions.show.operations.manufacture_tasks.show', [$this->organisation->slug, $this->production->slug, $this->manufactureTask->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Production/ManufactureTask')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $this->manufactureTask->name)
                    ->etc()
            )
            ->has('tabs');

    });
});

test('UI show production task (Artefacts tab)', function () {
    $response = get(route('grp.org.productions.show.operations.manufacture_tasks.show', [
        $this->organisation->slug,
        $this->production->slug,
        $this->manufactureTask->slug,
        'tab' => 'artefact'
    ]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Production/ManufactureTask')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $this->manufactureTask->name)
                    ->etc()
            )
            ->has('tabs');

    });
});

test('UI edit manufacture task', function () {
    $response = get(route('grp.org.productions.show.operations.manufacture_tasks.edit', [$this->organisation->slug, $this->production->slug, $this->manufactureTask->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('EditModel')
            ->has('title')
            ->has('formData.blueprint.0.fields', 13)
            ->has('pageHead')
            ->has('breadcrumbs', 4);
    });
});

test('UI get section route craft index', function () {
    $sectionScope = GetSectionRoute::make()->handle('grp.org.productions.show.operations.manufacture_tasks.index', [
        'organisation' => $this->organisation->slug,
        'production'      => $this->production->slug
    ]);
    expect($sectionScope)->toBeInstanceOf(AikuScopedSection::class)
        ->and($sectionScope->organisation_id)->toBe($this->organisation->id)
        ->and($sectionScope->code)->toBe(AikuSectionEnum::PRODUCTION_OPERATION->value)
        ->and($sectionScope->model_slug)->toBe($this->production->slug);
});

test('UI get section route operation dashboard', function () {
    $sectionScope = GetSectionRoute::make()->handle('grp.org.productions.show.operations.dashboard', [
        'organisation' => $this->organisation->slug,
        'production'      => $this->production->slug
    ]);
    expect($sectionScope)->toBeInstanceOf(AikuScopedSection::class)
        ->and($sectionScope->organisation_id)->toBe($this->organisation->id)
        ->and($sectionScope->code)->toBe(AikuSectionEnum::PRODUCTION_OPERATION->value)
        ->and($sectionScope->model_slug)->toBe($this->production->slug);
});

test('UI get section route org productions index', function () {
    $sectionScope = GetSectionRoute::make()->handle('grp.org.productions.index', [
        'organisation' => $this->organisation->slug,
    ]);

    expect($sectionScope)->toBeInstanceOf(AikuScopedSection::class)
        ->and($sectionScope->code)->toBe(AikuSectionEnum::ORG_PRODUCTION->value)
        ->and($sectionScope->model_slug)->toBe($this->organisation->slug);
});

test('work queue is generated from the artefact recipe and sessions pay the worker', function () {
    $this->artefact->manufactureTasks()->syncWithoutDetaching([
        $this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 2],
    ]);

    $jobOrder = StoreJobOrder::make()->action($this->production, []);

    $jobOrderItem = StoreJobOrderItem::make()->action($jobOrder, [
        'artefact_id' => $this->artefact->id,
        'quantity'    => 10,
    ]);

    $task = $jobOrderItem->tasks()->first();
    expect($jobOrderItem->tasks()->count())->toBe(1)
        ->and($task->manufacture_task_id)->toBe($this->manufactureTask->id)
        ->and((float)$task->quantity_required)->toBe(20.0)
        ->and($task->state)->toBe(JobOrderItemTaskStateEnum::TODO)
        ->and($jobOrder->reference)->toStartWith('JO'.$this->organisation->slug.'-');

    $user = $this->guest->getUser();

    expect(fn () => StartManufactureTaskSession::make()->action($user, $task))
        ->toThrow(ValidationException::class);

    ConfirmJobOrder::make()->action($jobOrder);
    expect($jobOrder->refresh()->state)->toBe(JobOrderStateEnum::CONFIRMED);

    $session = StartManufactureTaskSession::make()->action($user, $task);
    expect($session->state)->toBe(ManufactureTaskSessionStateEnum::OPEN)
        ->and($session->started_at)->not->toBeNull()
        ->and($task->refresh()->state)->toBe(JobOrderItemTaskStateEnum::IN_PROGRESS);

    expect(fn () => StartManufactureTaskSession::make()->action($user, $task))
        ->toThrow(ValidationException::class);

    $session = CloseManufactureTaskSession::make()->action($session, [
        'quantity_made'     => 15,
        'quantity_rejected' => 1,
    ]);
    expect($session->state)->toBe(ManufactureTaskSessionStateEnum::CLOSED)
        ->and($session->ended_at)->not->toBeNull()
        ->and((float)$session->task_work_cost)->toBe((float)$this->manufactureTask->task_work_cost)
        ->and($task->refresh()->state)->toBe(JobOrderItemTaskStateEnum::IN_PROGRESS)
        ->and((float)$task->quantity_made)->toBe(15.0);

    $secondSession = StartManufactureTaskSession::make()->action($user, $task);
    CloseManufactureTaskSession::make()->action($secondSession, ['quantity_made' => 5]);

    $task->refresh();
    expect($task->state)->toBe(JobOrderItemTaskStateEnum::DONE)
        ->and((float)$task->quantity_made)->toBe(20.0)
        ->and((float)$task->quantity_rejected)->toBe(1.0);
});

test('closing short can finish the job or carry the shortfall to a new job order', function () {
    $this->artefact->manufactureTasks()->syncWithoutDetaching([
        $this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1],
    ]);
    $user = $this->guest->getUser();

    $jobOrder = StoreJobOrder::make()->action($this->production, []);
    $item     = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 25]);
    ConfirmJobOrder::make()->action($jobOrder);
    $task = $item->tasks()->first();

    $session = StartManufactureTaskSession::make()->action($user, $task);
    CloseManufactureTaskSession::make()->action($session, ['quantity_made' => 10, 'outcome' => 'complete']);
    expect($task->refresh()->state)->toBe(JobOrderItemTaskStateEnum::DONE)
        ->and((float)$task->quantity_required)->toBe(10.0)
        ->and($item->refresh()->quantity)->toBe(10)
        ->and(\App\Models\Production\JobOrder::where('production_id', $this->production->id)->count())->toBe($before = \App\Models\Production\JobOrder::where('production_id', $this->production->id)->count());

    $jobOrder = StoreJobOrder::make()->action($this->production, []);
    $item     = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 25]);
    ConfirmJobOrder::make()->action($jobOrder);
    $task = $item->tasks()->first();

    $session = StartManufactureTaskSession::make()->action($user, $task);
    CloseManufactureTaskSession::make()->action($session, ['quantity_made' => 10, 'outcome' => 'carry_over']);
    $carried = \App\Models\Production\JobOrder::where('production_id', $this->production->id)->orderByDesc('id')->first();
    expect($task->refresh()->state)->toBe(JobOrderItemTaskStateEnum::DONE)
        ->and($item->refresh()->quantity)->toBe(10)
        ->and($carried->id)->not->toBe($jobOrder->id)
        ->and($carried->state)->toBe(JobOrderStateEnum::CONFIRMED)
        ->and($carried->jobOrderItems()->first()->quantity)->toBe(15)
        ->and((float)$carried->jobOrderItems()->first()->tasks()->first()->quantity_required)->toBe(15.0);

});

test('historic job orders do not generate a work queue', function () {
    $jobOrder = StoreJobOrder::make()->action($this->production, [
        'state'       => JobOrderStateEnum::RECEIVED,
        'received_at' => now(),
    ]);

    $jobOrderItem = StoreJobOrderItem::make()->action($jobOrder, [
        'artefact_id' => $this->artefact->id,
        'quantity'    => 5,
    ]);

    expect($jobOrderItem->tasks()->count())->toBe(0);
});

test('recipe can be edited by attaching and detaching manufacture tasks', function () {
    $this->artefact->manufactureTasks()->detach();

    AttachManufactureTaskToArtefact::make()->action($this->artefact, [
        'manufacture_task_id' => $this->manufactureTask->id,
        'position'            => 2,
        'units_per_artefact'  => 3,
    ]);

    $recipeTask = $this->artefact->refresh()->manufactureTasks()->first();
    expect((int)$recipeTask->pivot->position)->toBe(2)
        ->and((float)$recipeTask->pivot->units_per_artefact)->toBe(3.0);

    AttachManufactureTaskToArtefact::make()->action($this->artefact, [
        'manufacture_task_id' => $this->manufactureTask->id,
        'position'            => 1,
        'units_per_artefact'  => 5,
    ]);

    $recipeTask = $this->artefact->refresh()->manufactureTasks()->first();
    expect($this->artefact->manufactureTasks()->count())->toBe(1)
        ->and((int)$recipeTask->pivot->position)->toBe(1)
        ->and((float)$recipeTask->pivot->units_per_artefact)->toBe(5.0);

    DetachManufactureTaskFromArtefact::make()->action($this->artefact, $this->manufactureTask);
    expect($this->artefact->manufactureTasks()->count())->toBe(0);
});

test('recipe steps consume raw materials', function () {
    $this->artefact->manufactureTasks()->syncWithoutDetaching([
        $this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1],
    ]);

    $step = ArtefactManufactureTask::where('artefact_id', $this->artefact->id)
        ->where('manufacture_task_id', $this->manufactureTask->id)
        ->first();

    AttachRawMaterialToRecipeStep::make()->action($step, [
        'raw_material_id'   => $this->rawMaterial->id,
        'quantity_per_unit' => 0.25,
    ]);

    expect($step->rawMaterials()->count())->toBe(1)
        ->and((float)$step->rawMaterials()->first()->quantity_per_unit)->toBe(0.25);

    AttachRawMaterialToRecipeStep::make()->action($step, [
        'raw_material_id'   => $this->rawMaterial->id,
        'quantity_per_unit' => 0.5,
    ]);

    expect($step->rawMaterials()->count())->toBe(1)
        ->and((float)$step->rawMaterials()->first()->quantity_per_unit)->toBe(0.5);

    DetachRawMaterialFromRecipeStep::make()->action($step, $this->rawMaterial);

    expect($step->rawMaterials()->count())->toBe(0);
});

test('UI show manufacture floor', function () {
    $response = get(route('grp.org.productions.show.floor', [
        $this->organisation->slug,
        $this->production->slug,
    ]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Production/ManufactureFloor')
            ->has('tasks')
            ->has('today', fn (AssertableInertia $page) => $page
                ->has('sessions')
                ->has('quantity_made')
                ->has('earned'))
            ->where('open_session', null);
    });
});

test('floor shows job orders addressed to the worker first and the dashboard lists artisans with nothing queued', function () {
    $employees = collect(range(1, 2))->map(function () {
        $modelData = Employee::factory()->make(['organisation_id' => $this->organisation->id])->toArray();
        $modelData['worker_number']   = 'W'.rand(1000, 9999);
        $modelData['alias']           = 'Alias '.rand(1000, 9999);
        $modelData['type']            = \App\Enums\HumanResources\Employee\EmployeeTypeEnum::EMPLOYEE;
        $modelData['employment_type'] = \App\Enums\HumanResources\Employee\EmploymentTypeEnum::FULL_TIME;
        $modelData['state']           = \App\Enums\HumanResources\Employee\EmployeeStateEnum::WORKING;

        return StoreEmployee::make()->action($this->organisation, $modelData);
    });
    [$worker, $idle] = $employees;

    $this->guest->getUser()->employees()->attach($worker->id, [
        'group_id'        => $this->group->id,
        'organisation_id' => $this->organisation->id,
    ]);
    AttachArtisan::make()->action($this->artefact, ['employee_id' => $idle->id]);

    $this->artefact->manufactureTasks()->syncWithoutDetaching([
        $this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1],
    ]);

    $addressed = StoreJobOrder::make()->action($this->production, ['employee_id' => $worker->id]);
    StoreJobOrderItem::make()->action($addressed, ['artefact_id' => $this->artefact->id, 'quantity' => 3]);
    ConfirmJobOrder::make()->action($addressed);

    $pool = StoreJobOrder::make()->action($this->production, []);
    StoreJobOrderItem::make()->action($pool, ['artefact_id' => $this->artefact->id, 'quantity' => 2]);
    ConfirmJobOrder::make()->action($pool);

    $props = get(route('grp.org.productions.show.floor', [$this->organisation->slug, $this->production->slug]))
        ->viewData('page')['props'];
    $tasks = collect($props['tasks']);

    expect($props['artisan'])->toBe($worker->contact_name)
        ->and($tasks->where('is_mine', true)->pluck('job_order_reference')->all())->toBe([$addressed->reference])
        ->and($tasks->where('is_mine', false)->pluck('job_order_reference'))->toContain($pool->reference);

    $draft = StoreJobOrder::make()->action($this->production, ['employee_id' => $idle->id]);
    StoreJobOrderItem::make()->action($draft, ['artefact_id' => $this->artefact->id, 'quantity' => 1]);

    $artisans = collect(get(route('grp.org.productions.show.artisans.dashboard', [$this->organisation->slug, $this->production->slug]))
        ->viewData('page')['props']['artisans']);

    $filtered = get(route('grp.org.productions.show.operations.job-orders.index', [
        $this->organisation->slug,
        $this->production->slug,
        'filter[employee_id]' => $idle->id,
        'filter[state]'       => 'in_process',
    ]))->viewData('page')['props']['data']['data'];
    expect(collect($filtered)->pluck('reference')->all())->toBe([$draft->reference]);

    expect($artisans->firstWhere('id', $worker->id)['queued'])->toBe(1)
        ->and($artisans->firstWhere('id', $worker->id)['assigned'])->toBe(0)
        ->and($artisans->firstWhere('id', $idle->id)['queued'])->toBe(0)
        ->and($artisans->firstWhere('id', $idle->id)['assigned'])->toBe(1);
});

test('UI show artisans dashboard', function () {
    $response = get(route('grp.org.productions.show.artisans.dashboard', [
        $this->organisation->slug,
        $this->production->slug,
    ]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Production/ArtisansDashboard')
            ->has('artisans')
            ->missing('payroll_export_route')
            ->has('breadcrumbs', 3);
    });
});

test('UI show manufacture payroll', function () {
    $response = get(route('grp.org.productions.show.artisans.payroll', [
        $this->organisation->slug,
        $this->production->slug,
    ]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Production/ManufacturePayroll')
            ->has('payroll_export_route')
            ->has('breadcrumbs', 4);
    });
});

test('UI index job orders', function () {
    $response = get(route('grp.org.productions.show.operations.job-orders.index', [
        $this->organisation->slug,
        $this->production->slug,
    ]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Production/JobOrders')
            ->has('data')
            ->has('pageHead')
            ->has('breadcrumbs');
    });
});

test('UI show job order', function () {
    $jobOrder = JobOrder::first();
    $response = get(route('grp.org.productions.show.operations.job-orders.show', [
        $this->organisation->slug,
        $this->production->slug,
        $jobOrder->slug,
    ]));
    $response->assertInertia(function (AssertableInertia $page) use ($jobOrder) {
        $page
            ->component('Org/Production/JobOrder')
            ->where('job_order.reference', $jobOrder->reference)
            ->has('items')
            ->has('artefact_options');
    });
});

test('payroll csv export aggregates closed sessions with snapshotted rates', function () {
    $response = get(route('grp.org.productions.show.artisans.payroll.export', [
        $this->organisation->slug,
        $this->production->slug,
        'from' => now()->toDateString(),
        'to'   => now()->toDateString(),
    ]));

    $response->assertOk();
    $response->assertDownload();

    ob_start();
    $response->sendContent();
    $csv = ob_get_clean();

    expect($csv)->toContain('"Worker","Task code"')
        ->and($csv)->toContain($this->manufactureTask->code);
});

test('a voided session removes its quantities from the task and payroll', function () {
    $this->artefact->manufactureTasks()->syncWithoutDetaching([
        $this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1],
    ]);
    $user     = $this->guest->getUser();
    $jobOrder = StoreJobOrder::make()->action($this->production, []);
    $item     = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 20]);
    ConfirmJobOrder::make()->action($jobOrder);

    $task = $item->tasks()->first();
    CloseManufactureTaskSession::make()->action(
        StartManufactureTaskSession::make()->action($user, $task),
        ['quantity_made' => 15]
    );
    $session = CloseManufactureTaskSession::make()->action(
        StartManufactureTaskSession::make()->action($user, $task),
        ['quantity_made' => 5]
    );

    expect($task->refresh()->state)->toBe(JobOrderItemTaskStateEnum::DONE);

    VoidManufactureTaskSession::make()->action($session);

    $task->refresh();
    expect($session->refresh()->state)->toBe(ManufactureTaskSessionStateEnum::VOIDED)
        ->and($task->state)->toBe(JobOrderItemTaskStateEnum::IN_PROGRESS)
        ->and((float)$task->quantity_made)->toBe(15.0);

    expect(fn () => VoidManufactureTaskSession::make()->action($session))
        ->toThrow(ValidationException::class);
});

test('UI index artisans aggregates worker sessions', function () {
    $this->artefact->manufactureTasks()->syncWithoutDetaching([
        $this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1],
    ]);
    $jobOrder = StoreJobOrder::make()->action($this->production, []);
    $jobOrderItem = StoreJobOrderItem::make()->action($jobOrder, [
        'artefact_id' => $this->artefact->id,
        'quantity'    => 5,
    ]);
    ConfirmJobOrder::make()->action($jobOrder);
    $session = StartManufactureTaskSession::make()->action($this->guest->getUser(), $jobOrderItem->tasks()->first());
    CloseManufactureTaskSession::make()->action($session, ['quantity_made' => 5]);

    $response = get(route('grp.org.productions.show.artisans.index', [
        $this->organisation->slug,
        $this->production->slug,
        'from' => now()->toDateString(),
        'to'   => now()->toDateString(),
    ]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Production/Artisans')
            ->has('period')
            ->has('artisans')
            ->has('artisans.0', fn (AssertableInertia $page) => $page
                ->has('worker')
                ->has('number_sessions')
                ->has('earned')
                ->has('sessions')
                ->etc());
    });
});

test('raw material stores with defaults and only human fields', function () {
    $rawMaterial = StoreRawMaterial::make()->action($this->production, [
        'type'        => RawMaterialTypeEnum::STOCK->value,
        'code'        => 'MINIMAL1',
        'description' => 'Minimal raw material',
        'unit'        => RawMaterialUnitEnum::LITER->value,
    ]);

    expect($rawMaterial->state)->toBe(RawMaterialStateEnum::IN_PROCESS)
        ->and($rawMaterial->stock_status)->toBe(RawMaterialStockStatusEnum::OPTIMAL)
        ->and((float)$rawMaterial->unit_cost)->toBe(0.0)
        ->and((float)$rawMaterial->quantity_on_location)->toBe(0.0)
        ->and($rawMaterial->trade_unit_id)->toBeNull()
        ->and($rawMaterial->org_stock_id)->toBeNull();
});

test('raw material linked to org stock derives quantities from it', function () {
    $stock = \App\Actions\Goods\Stock\StoreStock::make()->action(
        $this->group,
        array_merge(\App\Models\Goods\Stock::factory()->definition(), [
            'state' => \App\Enums\Goods\Stock\StockStateEnum::ACTIVE
        ])
    );
    $orgStock = \App\Actions\Inventory\OrgStock\StoreOrgStock::make()->action($this->organisation, $stock);
    $orgStock->update(['quantity_in_locations' => 321.5]);

    $rawMaterial = StoreRawMaterial::make()->action($this->production, [
        'type'         => RawMaterialTypeEnum::STOCK->value,
        'code'         => 'LINKED1',
        'description'  => 'Linked raw material',
        'unit'         => RawMaterialUnitEnum::UNIT->value,
        'org_stock_id' => $orgStock->id,
    ]);

    expect((float)$rawMaterial->quantity_on_location)->toBe(321.5)
        ->and($rawMaterial->org_stock_id)->toBe($orgStock->id);

    $orgStock->update(['quantity_in_locations' => 10]);
    \App\Actions\Production\RawMaterial\Hydrators\RawMaterialHydrateFromOrgStock::run($rawMaterial->refresh());

    expect((float)$rawMaterial->refresh()->quantity_on_location)->toBe(10.0);
});

test('artefact links to trade unit and org stock', function () {
    $stock = \App\Actions\Goods\Stock\StoreStock::make()->action(
        $this->group,
        array_merge(\App\Models\Goods\Stock::factory()->definition(), [
            'state' => \App\Enums\Goods\Stock\StockStateEnum::ACTIVE
        ])
    );
    $orgStock = \App\Actions\Inventory\OrgStock\StoreOrgStock::make()->action($this->organisation, $stock);
    $tradeUnit = $stock->tradeUnits()->first();

    $artefact = StoreArtefact::make()->action($this->production, [
        'code'                    => 'LINKEDART1',
        'name'                    => 'Linked artefact',
        'trade_unit_id'           => $tradeUnit?->id,
        'org_stock_id'            => $orgStock->id,
        'recommended_batch_size'  => 250,
    ]);

    expect($artefact->org_stock_id)->toBe($orgStock->id)
        ->and($artefact->trade_unit_id)->toBe($tradeUnit?->id)
        ->and($artefact->recommended_batch_size)->toBe(250);

    $artefactWithoutBatchSize = StoreArtefact::make()->action($this->production, [
        'code' => 'LINKEDART2',
        'name' => 'Linked artefact without batch size',
    ]);

    expect($artefactWithoutBatchSize->recommended_batch_size)->toBeNull();
});

test('completed job order is received into stock with a batch code', function () {
    $stock = \App\Actions\Goods\Stock\StoreStock::make()->action(
        $this->group,
        array_merge(\App\Models\Goods\Stock::factory()->definition(), [
            'state' => \App\Enums\Goods\Stock\StockStateEnum::ACTIVE
        ])
    );
    $orgStock = \App\Actions\Inventory\OrgStock\StoreOrgStock::make()->action($this->organisation, $stock);

    $artefact = StoreArtefact::make()->action($this->production, [
        'code'         => 'RECEIVEART1',
        'name'         => 'Receivable artefact',
        'org_stock_id' => $orgStock->id,
    ]);
    $artefact->manufactureTasks()->syncWithoutDetaching([
        $this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1],
    ]);

    $warehouse = \App\Actions\Inventory\Warehouse\StoreWarehouse::make()->action($this->organisation, [
        'code' => 'WH-REC',
        'name' => 'Warehouse for receiving',
    ]);
    $area = \App\Actions\Inventory\WarehouseArea\StoreWarehouseArea::make()->action($warehouse, [
        'code' => 'A-REC',
        'name' => 'Area receiving',
    ]);
    $location = \App\Actions\Inventory\Location\StoreLocation::make()->action(
        $area,
        [
            'code' => 'L-REC',
            'name' => 'Loc receiving',
        ] + \App\Models\Inventory\Location::factory()->definition()
    );

    $jobOrder = StoreJobOrder::make()->action($this->production, []);
    $jobOrderItem = StoreJobOrderItem::make()->action($jobOrder, [
        'artefact_id' => $artefact->id,
        'quantity'    => 10,
    ]);

    ConfirmJobOrder::make()->action($jobOrder);

    $task = $jobOrderItem->tasks()->first();
    $user = $this->guest->getUser();
    $session = StartManufactureTaskSession::make()->action($user, $task);
    CloseManufactureTaskSession::make()->action($session, ['quantity_made' => 10]);

    $jobOrder = \App\Actions\Production\JobOrder\ReceiveJobOrderIntoStock::make()->action($jobOrder, [
        'location_id' => $location->id,
    ]);

    expect($jobOrder->state)->toBe(JobOrderStateEnum::RECEIVED)
        ->and(fn () => \App\Actions\Production\JobOrder\ReceiveJobOrderIntoStock::make()->action($jobOrder->refresh(), [
            'location_id' => $location->id,
        ]))->toThrow(ValidationException::class);

    $movement = \App\Models\Inventory\OrgStockMovement::where('org_stock_id', $orgStock->id)
        ->where('type', \App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum::PRODUCTION)
        ->first();
    expect($movement)->not->toBeNull()
        ->and((float)$movement->quantity)->toBe(10.0);

    $batchCode = \App\Models\Dispatching\BatchCode::where('org_stock_id', $orgStock->id)->first();
    expect($batchCode)->not->toBeNull()
        ->and($batchCode->code)->toBe($jobOrder->reference.'-'.$artefact->code);

    $locationOrgStock = \App\Models\Inventory\LocationOrgStock::where('location_id', $location->id)
        ->where('org_stock_id', $orgStock->id)->first();
    expect($locationOrgStock)->not->toBeNull()
        ->and((float)$locationOrgStock->quantity)->toBe(10.0);
});

test('completed job order into stock converts units and deducts raw materials', function () {
    $stock = \App\Actions\Goods\Stock\StoreStock::make()->action(
        $this->group,
        array_merge(\App\Models\Goods\Stock::factory()->definition(), [
            'state' => \App\Enums\Goods\Stock\StockStateEnum::ACTIVE
        ])
    );
    $orgStock = \App\Actions\Inventory\OrgStock\StoreOrgStock::make()->action($this->organisation, $stock);

    $inputStock = \App\Actions\Goods\Stock\StoreStock::make()->action(
        $this->group,
        array_merge(\App\Models\Goods\Stock::factory()->definition(), [
            'state' => \App\Enums\Goods\Stock\StockStateEnum::ACTIVE
        ])
    );
    $inputOrgStock = \App\Actions\Inventory\OrgStock\StoreOrgStock::make()->action($this->organisation, $inputStock);

    $artefact = StoreArtefact::make()->action($this->production, [
        'code'         => 'RECEIVEART2',
        'name'         => 'Receivable artefact with recipe',
        'org_stock_id' => $orgStock->id,
    ]);
    $artefact->manufactureTasks()->syncWithoutDetaching([
        $this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 2],
    ]);

    $recipeStep = ArtefactManufactureTask::where('artefact_id', $artefact->id)
        ->where('manufacture_task_id', $this->manufactureTask->id)
        ->first();

    $rawMaterial = StoreRawMaterial::make()->action($this->production, [
        'type'        => RawMaterialTypeEnum::CONSUMABLE->value,
        'state'       => RawMaterialStateEnum::ORPHAN->value,
        'code'        => 'RECIPERM1',
        'description' => 'recipe raw material',
        'unit'        => RawMaterialUnitEnum::KILOGRAM->value,
        'unit_cost'   => 10,
    ]);
    UpdateRawMaterial::make()->action($rawMaterial, ['org_stock_id' => $inputOrgStock->id]);

    AttachRawMaterialToRecipeStep::make()->action($recipeStep, [
        'raw_material_id'   => $rawMaterial->id,
        'quantity_per_unit' => 0.5,
    ]);

    $warehouse = \App\Actions\Inventory\Warehouse\StoreWarehouse::make()->action($this->organisation, [
        'code' => 'WH-REC2',
        'name' => 'Warehouse for receiving 2',
    ]);
    $area = \App\Actions\Inventory\WarehouseArea\StoreWarehouseArea::make()->action($warehouse, [
        'code' => 'A-REC2',
        'name' => 'Area receiving 2',
    ]);
    $location = \App\Actions\Inventory\Location\StoreLocation::make()->action(
        $area,
        [
            'code' => 'L-REC2',
            'name' => 'Loc receiving 2',
        ] + \App\Models\Inventory\Location::factory()->definition()
    );
    $inputLocation = \App\Actions\Inventory\Location\StoreLocation::make()->action(
        $area,
        [
            'code' => 'L-INPUT2',
            'name' => 'Loc input 2',
        ] + \App\Models\Inventory\Location::factory()->definition()
    );

    $inputLocationOrgStock = \App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock::make()->action($inputOrgStock, $inputLocation, [
        'type' => \App\Enums\Inventory\LocationStock\LocationStockTypeEnum::PICKING,
    ]);
    \App\Actions\Inventory\OrgStockMovement\StoreOrgStockMovement::make()->action($inputOrgStock, $inputLocation, [
        'quantity' => 100,
        'type'     => \App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum::PRODUCTION,
    ]);

    $jobOrder = StoreJobOrder::make()->action($this->production, []);
    $jobOrderItem = StoreJobOrderItem::make()->action($jobOrder, [
        'artefact_id' => $artefact->id,
        'quantity'    => 10,
    ]);

    ConfirmJobOrder::make()->action($jobOrder);

    $task = $jobOrderItem->tasks()->first();
    $user = $this->guest->getUser();
    $session = StartManufactureTaskSession::make()->action($user, $task);
    CloseManufactureTaskSession::make()->action($session, ['quantity_made' => 20]);

    $jobOrder = \App\Actions\Production\JobOrder\ReceiveJobOrderIntoStock::make()->action($jobOrder, [
        'location_id' => $location->id,
    ]);

    expect($jobOrder->state)->toBe(JobOrderStateEnum::RECEIVED);

    $creditMovement = \App\Models\Inventory\OrgStockMovement::where('org_stock_id', $orgStock->id)
        ->where('type', \App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum::PRODUCTION)
        ->first();
    expect($creditMovement)->not->toBeNull()
        ->and((float) $creditMovement->quantity)->toBe(10.0);

    $deductionMovement = \App\Models\Inventory\OrgStockMovement::where('org_stock_id', $inputOrgStock->id)
        ->where('type', \App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum::PRODUCTION)
        ->where('quantity', '<', 0)
        ->first();
    expect($deductionMovement)->not->toBeNull()
        ->and((float) $deductionMovement->quantity)->toBe(-5.0);

    $inputLocationOrgStock->refresh();
    expect((float) $inputLocationOrgStock->quantity)->toBe(95.0);
});

test('artefact compliance status reflects its items', function () {
    $status = \App\Actions\Production\Artefact\GetArtefactComplianceStatus::run($this->artefact);
    expect($status['status'])->toBe(\App\Enums\Production\Artefact\ArtefactComplianceStatusEnum::NOT_CONFIGURED);

    $item = \App\Models\Production\ArtefactComplianceItem::create([
        'group_id'        => $this->artefact->group_id,
        'organisation_id' => $this->artefact->organisation_id,
        'artefact_id'     => $this->artefact->id,
        'type'            => \App\Enums\Production\Artefact\ArtefactComplianceTypeEnum::CERTIFICATE,
        'reference'       => 'CERT-1',
        'is_required'     => true,
        'valid_until'     => now()->addYear(),
    ]);
    $status = \App\Actions\Production\Artefact\GetArtefactComplianceStatus::run($this->artefact->refresh());
    expect($status['status'])->toBe(\App\Enums\Production\Artefact\ArtefactComplianceStatusEnum::OK);

    $problemItem = \App\Models\Production\ArtefactComplianceItem::create([
        'group_id'        => $this->artefact->group_id,
        'organisation_id' => $this->artefact->organisation_id,
        'artefact_id'     => $this->artefact->id,
        'type'            => \App\Enums\Production\Artefact\ArtefactComplianceTypeEnum::SAFETY_TEST,
        'reference'       => null,
        'is_required'     => true,
    ]);
    $status = \App\Actions\Production\Artefact\GetArtefactComplianceStatus::run($this->artefact->refresh());
    expect($status['status'])->toBe(\App\Enums\Production\Artefact\ArtefactComplianceStatusEnum::PROBLEM);

    $problemItem->update([
        'reference'   => 'SAFE-1',
        'valid_until' => now()->addDays(10),
    ]);
    $status = \App\Actions\Production\Artefact\GetArtefactComplianceStatus::run($this->artefact->refresh());
    expect($status['status'])->toBe(\App\Enums\Production\Artefact\ArtefactComplianceStatusEnum::EXPIRING);

    $problemItem->update(['valid_until' => now()->subDay()]);
    $status = \App\Actions\Production\Artefact\GetArtefactComplianceStatus::run($this->artefact->refresh());
    expect($status['status'])->toBe(\App\Enums\Production\Artefact\ArtefactComplianceStatusEnum::PROBLEM);

    $item->delete();
    $problemItem->delete();
});

test('a job order cannot be released while an artefact is not compliant', function () {
    \App\Models\Production\ArtefactComplianceItem::create([
        'group_id'        => $this->artefact->group_id,
        'organisation_id' => $this->artefact->organisation_id,
        'artefact_id'     => $this->artefact->id,
        'type'            => \App\Enums\Production\Artefact\ArtefactComplianceTypeEnum::CERTIFICATE,
        'reference'       => null,
        'is_required'     => true,
    ]);

    $jobOrder = StoreJobOrder::make()->action($this->production, []);
    StoreJobOrderItem::make()->action($jobOrder, [
        'artefact_id' => $this->artefact->id,
        'quantity'    => 5,
    ]);

    expect(fn () => ConfirmJobOrder::make()->action($jobOrder))->toThrow(ValidationException::class);

    $jobOrder = ConfirmJobOrder::make()->action($jobOrder, ['compliance_override' => true]);

    expect($jobOrder->state)->toBe(JobOrderStateEnum::CONFIRMED)
        ->and($jobOrder->data['compliance_override']['artefacts'])->toBe([$this->artefact->code]);

    $this->artefact->complianceItems()->delete();
});

test('a raw material can be updated while keeping its own code', function () {
    $rawMaterial = StoreRawMaterial::make()->action($this->production, [
        'type'        => RawMaterialTypeEnum::STOCK->value,
        'code'        => 'SELFCODE1',
        'description' => 'Self code raw material',
        'unit'        => RawMaterialUnitEnum::UNIT->value,
    ]);

    $updated = UpdateRawMaterial::make()->action($rawMaterial, [
        'code'        => 'SELFCODE1',
        'description' => 'Renamed while keeping the code',
    ]);

    expect($updated->code)->toBe('SELFCODE1')
        ->and($updated->description)->toBe('Renamed while keeping the code');
});

describe('production reward pay bands', function () {
    beforeEach(function () {
        $this->artefact->manufactureTasks()->syncWithoutDetaching([
            $this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1],
        ]);

        $jobOrder     = StoreJobOrder::make()->action($this->production, []);
        $jobOrderItem = StoreJobOrderItem::make()->action($jobOrder, [
            'artefact_id' => $this->artefact->id,
            'quantity'    => 1000,
        ]);
        ConfirmJobOrder::make()->action($jobOrder);
        $this->payBandJobOrderItemTask = $jobOrderItem->tasks()->first();

        foreach ([
            ['code' => '0', 'name' => 'Band 0', 'hourly_rate' => 12.71, 'target_multiplier' => null],
            ['code' => '1', 'name' => 'Band 1', 'hourly_rate' => 13.00, 'target_multiplier' => 1.0228],
            ['code' => '2', 'name' => 'Band 2', 'hourly_rate' => 14.00, 'target_multiplier' => 1.1015],
            ['code' => '3', 'name' => 'Band 3', 'hourly_rate' => 15.00, 'target_multiplier' => 1.1802],
            ['code' => 'D', 'name' => 'Development', 'hourly_rate' => 13.30, 'target_multiplier' => null],
            ['code' => 'DG', 'name' => 'Development Group', 'hourly_rate' => 15.00, 'target_multiplier' => null],
        ] as $bandData) {
            \App\Models\Production\ManufacturePayBand::query()->firstOrCreate(
                [
                    'production_id' => $this->production->id,
                    'code'          => $bandData['code'],
                ],
                array_merge($bandData, [
                    'group_id'        => $this->production->group_id,
                    'organisation_id' => $this->production->organisation_id,
                    'production_id'   => $this->production->id,
                    'effective_from'  => now()->subYear(),
                ])
            );
        }
    });

    function makePayBandSession(
        \App\Models\Production\JobOrderItemTask $jobOrderItemTask,
        float $quantityMade,
        float $hours,
        int $breakMinutes = 0,
        string $activityType = \App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionActivityTypeEnum::PRODUCTION->value
    ): \App\Models\Production\ManufactureTaskSession {
        $startedAt = now();
        $endedAt   = $startedAt->clone()->addSeconds((int) round(($hours + $breakMinutes / 60) * 3600));

        return \App\Models\Production\ManufactureTaskSession::create([
            'group_id'               => $jobOrderItemTask->group_id,
            'organisation_id'        => $jobOrderItemTask->organisation_id,
            'production_id'          => $jobOrderItemTask->production_id,
            'job_order_item_task_id' => $jobOrderItemTask->id,
            'manufacture_task_id'    => $jobOrderItemTask->manufacture_task_id,
            'user_id'                => auth()->user()->id,
            'state'                  => \App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum::CLOSED,
            'started_at'             => $startedAt,
            'ended_at'               => $endedAt,
            'quantity_made'          => $quantityMade,
            'break_minutes'          => $breakMinutes,
            'activity_type'          => $activityType,
        ]);
    }

    test('worked example lands in band 3 with the expected pay and bonus', function () {
        $this->payBandJobOrderItemTask->manufactureTask->update(['standard_rate' => 152]);
        $session = makePayBandSession($this->payBandJobOrderItemTask, 900, 4.9333);

        $session = \App\Actions\Production\ManufactureTaskSession\CalculateManufactureTaskSessionPay::run($session);

        expect($session->band_code)->toBe('3')
            ->and((float) $session->pay)->toBe(74.00)
            ->and((float) $session->bonus)->toBe(11.30)
            ->and((float) $session->units_per_hour)->toBe(182.0);
    });

    test('a closed session with band pay appears in the payroll export with the correct figures', function () {
        $exportTask = ManufactureTask::where('production_id', $this->production->id)->where('code', 'EXPRT1')->first();

        if (!$exportTask) {
            $exportTask = \App\Actions\Production\ManufactureTask\StoreManufactureTask::make()->action(
                $this->production,
                [
                    'code'                             => 'EXPRT1',
                    'name'                             => 'Export task',
                    'task_materials_cost'               => 1,
                    'task_energy_cost'                  => 1,
                    'task_other_cost'                   => 1,
                    'task_work_cost'                    => 1,
                    'task_lower_target'                 => 1,
                    'task_upper_target'                 => 1,
                    'operative_reward_terms'            => ManufactureTaskOperativeRewardTermsEnum::ABOVE_LOWER_LIMIT,
                    'operative_reward_allowance_type'   => ManufactureTaskOperativeRewardAllowanceTypeEnum::OFFSET_SALARY,
                    'operative_reward_amount'           => 1,
                ]
            );
        }
        $exportTask->update(['standard_rate' => 152]);

        $exportArtefact = Artefact::where('production_id', $this->production->id)->where('code', 'EXPRTA1')->first();

        if (!$exportArtefact) {
            $exportArtefact = StoreArtefact::make()->action($this->production, ['code' => 'EXPRTA1', 'name' => 'Export artefact']);
        }
        $exportArtefact->manufactureTasks()->syncWithoutDetaching([
            $exportTask->id => ['position' => 1, 'units_per_artefact' => 1],
        ]);
        $exportJobOrder     = StoreJobOrder::make()->action($this->production, []);
        $exportJobOrderItem = StoreJobOrderItem::make()->action($exportJobOrder, [
            'artefact_id' => $exportArtefact->id,
            'quantity'    => 1000,
        ]);
        ConfirmJobOrder::make()->action($exportJobOrder);
        $exportJobOrderItemTask = $exportJobOrderItem->tasks()->first();

        $session = makePayBandSession($exportJobOrderItemTask, 900, 4.9333);
        $session = \App\Actions\Production\ManufactureTaskSession\CalculateManufactureTaskSessionPay::run($session);

        $export = new \App\Exports\Production\ManufacturePayrollExport(
            $this->production,
            $session->ended_at->clone()->subDay(),
            $session->ended_at->clone()->addDay()
        );

        $rows = collect($export->array());
        $row  = $rows->first(fn ($row) => $row[1] === 'EXPRT1');

        expect($row)->not->toBeNull()
            ->and($row[11])->toBe('production')
            ->and((float) $row[16])->toBe((float) $session->hourly_rate)
            ->and((float) $row[17])->toBe((float) $session->pay)
            ->and((float) $row[18])->toBe((float) $session->bonus);
    });

    test('below every target lands in band 0', function () {
        $this->payBandJobOrderItemTask->manufactureTask->update(['standard_rate' => 216]);
        $session = makePayBandSession($this->payBandJobOrderItemTask, 380, 2);

        $session = \App\Actions\Production\ManufactureTaskSession\CalculateManufactureTaskSessionPay::run($session);

        expect($session->band_code)->toBe('0')
            ->and((float) $session->pay)->toBe(25.42)
            ->and((float) $session->bonus)->toBe(0.0);
    });

    test('units per hour exactly on a band target lands on that band', function () {
        $this->payBandJobOrderItemTask->manufactureTask->update(['standard_rate' => 10000]);

        $sessionAtBand1 = makePayBandSession($this->payBandJobOrderItemTask, 10228, 1);
        $sessionAtBand1 = \App\Actions\Production\ManufactureTaskSession\CalculateManufactureTaskSessionPay::run($sessionAtBand1);
        expect($sessionAtBand1->band_code)->toBe('1');

        $sessionAtBand3 = makePayBandSession($this->payBandJobOrderItemTask, 11802, 1);
        $sessionAtBand3 = \App\Actions\Production\ManufactureTaskSession\CalculateManufactureTaskSessionPay::run($sessionAtBand3);
        expect($sessionAtBand3->band_code)->toBe('3');
    });

    test('a non production activity always pays band 0 with no units per hour', function () {
        $this->payBandJobOrderItemTask->manufactureTask->update(['standard_rate' => 152]);
        $session = makePayBandSession(
            $this->payBandJobOrderItemTask,
            900,
            4.9333,
            0,
            \App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionActivityTypeEnum::CLEANING->value
        );

        $session = \App\Actions\Production\ManufactureTaskSession\CalculateManufactureTaskSessionPay::run($session);

        expect($session->band_code)->toBe('0')
            ->and($session->units_per_hour)->toBeNull();
    });

    test('manufacture floor shows live band feedback when the task has a standard rate', function () {
        $this->payBandJobOrderItemTask->manufactureTask->update(['standard_rate' => 152]);

        \App\Actions\Production\ManufactureTaskSession\StartManufactureTaskSession::make()->action(
            auth()->user(),
            $this->payBandJobOrderItemTask
        );

        $response = get(route('grp.org.productions.show.floor', [
            $this->organisation->slug,
            $this->production->slug,
        ]));

        $response->assertInertia(function (AssertableInertia $page) {
            $page->component('Org/Production/ManufactureFloor')
                ->has('open_session.band_feedback.bands', 3)
                ->where('open_session.band_feedback.band0_hourly_rate', 12.71)
                ->where('open_session.band_feedback.bands.0.target_units_per_hour', round(152 * 1.0228, 1))
                ->where('open_session.band_feedback.bands.1.target_units_per_hour', round(152 * 1.1015, 1))
                ->where('open_session.band_feedback.bands.2.target_units_per_hour', round(152 * 1.1802, 1));
        });

        $this->payBandJobOrderItemTask->manufactureTask->update(['standard_rate' => null]);
        \App\Models\Production\ManufactureTaskSession::where('user_id', auth()->user()->id)
            ->where('state', \App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum::OPEN)
            ->update(['job_order_item_task_id' => $this->payBandJobOrderItemTask->id]);

        $response = get(route('grp.org.productions.show.floor', [
            $this->organisation->slug,
            $this->production->slug,
        ]));

        $response->assertInertia(function (AssertableInertia $page) {
            $page->component('Org/Production/ManufactureFloor')
                ->where('open_session.band_feedback', null);
        });

        \App\Models\Production\ManufactureTaskSession::where('user_id', auth()->user()->id)
            ->where('state', \App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum::OPEN)
            ->update(['state' => \App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum::CLOSED]);
    });

    test('development activity pays the development band', function () {
        $session = makePayBandSession(
            $this->payBandJobOrderItemTask,
            0,
            2,
            0,
            \App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionActivityTypeEnum::DEVELOPMENT->value
        );

        $session = \App\Actions\Production\ManufactureTaskSession\CalculateManufactureTaskSessionPay::run($session);

        expect($session->band_code)->toBe('D')
            ->and((float) $session->hourly_rate)->toBe(13.30);
    });

    test('a production without any configured bands keeps the pay fields empty', function () {
        $unbandedProduction = \App\Actions\Production\Production\StoreProduction::make()->action(
            $this->organisation,
            ['code' => 'NOBAND1', 'name' => 'No band production']
        );
        $unbandedTask = \App\Actions\Production\ManufactureTask\StoreManufactureTask::make()->action(
            $unbandedProduction,
            [
                'code'                             => 'NBT1',
                'name'                             => 'No band task',
                'task_materials_cost'               => 1,
                'task_energy_cost'                  => 1,
                'task_other_cost'                   => 1,
                'task_work_cost'                    => 1,
                'task_lower_target'                 => 1,
                'task_upper_target'                 => 1,
                'operative_reward_terms'            => ManufactureTaskOperativeRewardTermsEnum::ABOVE_LOWER_LIMIT,
                'operative_reward_allowance_type'   => ManufactureTaskOperativeRewardAllowanceTypeEnum::OFFSET_SALARY,
                'operative_reward_amount'           => 1,
            ]
        );
        $unbandedTask->update(['standard_rate' => 152]);
        $unbandedArtefact = StoreArtefact::make()->action($unbandedProduction, ['code' => 'NBA1', 'name' => 'No band artefact']);
        $unbandedArtefact->manufactureTasks()->syncWithoutDetaching([
            $unbandedTask->id => ['position' => 1, 'units_per_artefact' => 1],
        ]);
        $jobOrder     = StoreJobOrder::make()->action($unbandedProduction, []);
        $jobOrderItem = StoreJobOrderItem::make()->action($jobOrder, [
            'artefact_id' => $unbandedArtefact->id,
            'quantity'    => 1000,
        ]);
        ConfirmJobOrder::make()->action($jobOrder);
        $jobOrderItemTask = $jobOrderItem->tasks()->first();

        $session = makePayBandSession($jobOrderItemTask, 900, 4.9333);
        $session = \App\Actions\Production\ManufactureTaskSession\CalculateManufactureTaskSessionPay::run($session);

        expect($session->band_code)->toBeNull()
            ->and($session->pay)->toBeNull()
            ->and($session->bonus)->toBeNull();
    });

    test('closing a non production session without a reason is rejected', function () {
        $session = makePayBandSession($this->payBandJobOrderItemTask, 0, 2);
        $session->update([
            'state'         => \App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum::OPEN,
            'activity_type' => \App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionActivityTypeEnum::PRODUCTION,
            'started_at'    => now()->subHours(2),
        ]);

        expect(
            fn () => \App\Actions\Production\ManufactureTaskSession\CloseManufactureTaskSession::make()->action($session, [
                'quantity_made' => 0,
                'activity_type' => \App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionActivityTypeEnum::CLEANING->value,
            ])
        )->toThrow(\Illuminate\Validation\ValidationException::class);

        $closed = \App\Actions\Production\ManufactureTaskSession\CloseManufactureTaskSession::make()->action($session, [
            'quantity_made'         => 0,
            'activity_type'         => \App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionActivityTypeEnum::CLEANING->value,
            'non_productive_reason' => 'End of shift line clean',
        ]);

        expect($closed->band_code)->toBe('0')
            ->and((float) $closed->hourly_rate)->toBe(12.71);
    });

    test('band rates stay cost neutral against the standard rate within two percent', function () {
        $bands = \App\Models\Production\ManufacturePayBand::where('production_id', $this->production->id)
            ->whereIn('code', ['1', '2', '3'])
            ->get();

        $standardRate = 152;
        $ratios       = $bands->map(fn ($band) => (float) $band->hourly_rate / ($standardRate * (float) $band->target_multiplier));

        $reference = $ratios->first();
        foreach ($ratios as $ratio) {
            expect(abs($ratio - $reference) / $reference)->toBeLessThan(0.02);
        }
    });
});

describe('reward sheet import', function () {
    beforeEach(function () {
        $this->rewardTask = \App\Models\Production\ManufactureTask::where('production_id', $this->production->id)
            ->where('code', 'RWT1')
            ->first();

        if (!$this->rewardTask) {
            $this->rewardTask = \App\Actions\Production\ManufactureTask\StoreManufactureTask::make()->action(
                $this->production,
                [
                    'code'                             => 'RWT1',
                    'name'                             => 'Reward task',
                    'task_materials_cost'               => 1,
                    'task_energy_cost'                  => 1,
                    'task_other_cost'                   => 1,
                    'task_work_cost'                    => 1,
                    'task_lower_target'                 => 1,
                    'task_upper_target'                 => 1,
                    'operative_reward_terms'            => ManufactureTaskOperativeRewardTermsEnum::ABOVE_LOWER_LIMIT,
                    'operative_reward_allowance_type'   => ManufactureTaskOperativeRewardAllowanceTypeEnum::OFFSET_SALARY,
                    'operative_reward_amount'           => 1,
                ]
            );
        }

        $this->rewardTask->update(['standard_rate' => null, 'target_override_reason' => null]);

        $this->payBandCountBeforeImport = \App\Models\Production\ManufacturePayBand::where('production_id', $this->production->id)->count();

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Product Families');

        $sheet->fromArray(['Family', 'Description', 'Piece Rate (Old)', '0', '1', '2', '3'], null, 'A2');
        $sheet->fromArray(['RWT1', 'Matches the reward task', 0.1, 130, 133, 143, 153], null, 'A3');
        $sheet->fromArray(['ORPHAN1', 'No matching task', 0.2, 65, 66, 71, 76], null, 'A4');
        $sheet->fromArray(['ZEROED', 'Zero piece rate is skipped', 0, 0, 0, 0, 0], null, 'A5');
        $sheet->fromArray(['RWT1', 'Hand typed target 0 override', 0.05, 100, 267, 287, 307], null, 'A6');

        $this->rewardSheetPath = tempnam(sys_get_temp_dir(), 'reward-sheet').'.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($this->rewardSheetPath);
    });

    afterEach(function () {
        if (isset($this->rewardSheetPath) && file_exists($this->rewardSheetPath)) {
            unlink($this->rewardSheetPath);
        }
    });

    test('dry run changes nothing', function () {
        $this->artisan('manufacture:import-reward-sheet', [
            'production' => $this->production->slug,
            'file'       => $this->rewardSheetPath,
            '--dry-run'  => true,
        ])->assertSuccessful();

        expect($this->rewardTask->refresh()->standard_rate)->toBeNull()
            ->and(\App\Models\Production\ManufacturePayBand::where('production_id', $this->production->id)->count())->toBe($this->payBandCountBeforeImport);
    });

    test('real run sets standard rate, seeds bands, flags override and reports the orphan', function () {
        $this->artisan('manufacture:import-reward-sheet', [
            'production' => $this->production->slug,
            'file'       => $this->rewardSheetPath,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('ORPHAN1');

        expect((float) $this->rewardTask->refresh()->standard_rate)->toBe(round(13.00 / 0.05, 4))
            ->and($this->rewardTask->target_override_reason)->not->toBeNull()
            ->and(\App\Models\Production\ManufacturePayBand::where('production_id', $this->production->id)->where('effective_from', now()->startOfYear())->count())->toBe(6);
    });

    test('create-missing with dry-run creates nothing', function () {
        \App\Models\Production\ManufactureTask::where('production_id', $this->production->id)->where('code', 'ORPHAN1')->forceDelete();

        $this->artisan('manufacture:import-reward-sheet', [
            'production'        => $this->production->slug,
            'file'              => $this->rewardSheetPath,
            '--create-missing'  => true,
            '--dry-run'         => true,
        ])->assertSuccessful();

        expect(\App\Models\Production\ManufactureTask::where('production_id', $this->production->id)->where('code', 'ORPHAN1')->exists())->toBeFalse();
    });

    test('create-missing creates the orphan task', function () {
        \App\Models\Production\ManufactureTask::where('production_id', $this->production->id)->where('code', 'ORPHAN1')->forceDelete();

        $this->artisan('manufacture:import-reward-sheet', [
            'production'        => $this->production->slug,
            'file'              => $this->rewardSheetPath,
            '--create-missing'  => true,
        ])->assertSuccessful();

        $orphanTask = \App\Models\Production\ManufactureTask::where('production_id', $this->production->id)
            ->where('code', 'ORPHAN1')
            ->first();

        expect($orphanTask)->not->toBeNull()
            ->and((float) $orphanTask->standard_rate)->toBe(round(13.00 / 0.2, 4))
            ->and($orphanTask->slug)->not->toBeNull();
    });

    test('create-missing is idempotent on re-run', function () {
        \App\Models\Production\ManufactureTask::where('production_id', $this->production->id)->where('code', 'ORPHAN1')->forceDelete();

        $this->artisan('manufacture:import-reward-sheet', [
            'production'        => $this->production->slug,
            'file'              => $this->rewardSheetPath,
            '--create-missing'  => true,
        ])->assertSuccessful();

        expect(\App\Models\Production\ManufactureTask::where('production_id', $this->production->id)->where('code', 'ORPHAN1')->count())->toBe(1);

        $this->artisan('manufacture:import-reward-sheet', [
            'production'        => $this->production->slug,
            'file'              => $this->rewardSheetPath,
            '--create-missing'  => true,
        ])->assertSuccessful();

        expect(\App\Models\Production\ManufactureTask::where('production_id', $this->production->id)->where('code', 'ORPHAN1')->count())->toBe(1);
    });
});

test('create job order from gate shortfall', function () {
    $organisation = $this->production->organisation;

    $orgStock = \App\Models\Inventory\OrgStock::where('organisation_id', $organisation->id)->first();
    if (!$orgStock) {
        $stocks   = createStocks($organisation->group);
        $orgStock = createOrgStocks($organisation, $stocks)[0];
    }
    $this->artefact->update(['org_stock_id' => $orgStock->id]);

    $result = \App\Actions\Dispatching\FulfilmentGate\StoreJobOrderFromShortfall::make()->action(
        $organisation,
        [
            ['org_stock_id' => $orgStock->id, 'quantity' => 7.3],
            ['org_stock_id' => -1, 'quantity' => 5],
        ]
    );

    expect($result['job_order'])->toBeInstanceOf(\App\Models\Production\JobOrder::class)
        ->and($result['job_order']->jobOrderItems()->count())->toBe(1)
        ->and($result['job_order']->jobOrderItems()->first()->artefact_id)->toBe($this->artefact->id)
        ->and((int) $result['job_order']->jobOrderItems()->first()->quantity)->toBe(8)
        ->and($result['skipped'])->toHaveCount(1);
});

test('artisans can be attached and detached from a family and an artefact, first one is primary', function () {
    $family   = StoreArtefactDepartment::make()->action($this->production, ['code' => 'ARTS', 'name' => 'Artisan family']);
    $artefact = StoreArtefact::make()->action($this->production, ['code' => 'ARTS-1', 'name' => 'Artisan artefact', 'artefact_department_id' => $family->id]);

    $employees = collect(range(1, 2))->map(function () {
        $modelData = Employee::factory()->make(['organisation_id' => $this->organisation->id])->toArray();
        $modelData['worker_number']   = 'W'.rand(1000, 9999);
        $modelData['alias']           = 'Alias '.rand(1000, 9999);
        $modelData['type']            = \App\Enums\HumanResources\Employee\EmployeeTypeEnum::EMPLOYEE;
        $modelData['employment_type'] = \App\Enums\HumanResources\Employee\EmploymentTypeEnum::FULL_TIME;
        $modelData['state']           = \App\Enums\HumanResources\Employee\EmployeeStateEnum::WORKING;

        return StoreEmployee::make()->action($this->organisation, $modelData);
    });

    AttachArtisan::make()->action($family, ['employee_id' => $employees[0]->id]);
    AttachArtisan::make()->action($family, ['employee_id' => $employees[1]->id]);
    AttachArtisan::make()->action($family, ['employee_id' => $employees[1]->id]);
    expect($family->artisans()->pluck('employees.id')->all())->toBe([$employees[0]->id, $employees[1]->id]);

    DetachArtisan::make()->action($family, $employees[0]);
    expect($family->artisans()->pluck('employees.id')->all())->toBe([$employees[1]->id]);

    AttachArtisan::make()->action($artefact, ['employee_id' => $employees[0]->id]);
    expect($artefact->artisans()->pluck('employees.id')->all())->toBe([$employees[0]->id]);
});

test('an employee can be hidden from and restored to the artisan roster', function () {
    $modelData = Employee::factory()->make(['organisation_id' => $this->organisation->id])->toArray();
    $modelData['worker_number']   = 'W'.rand(1000, 9999);
    $modelData['alias']           = 'Alias '.rand(1000, 9999);
    $modelData['type']            = \App\Enums\HumanResources\Employee\EmployeeTypeEnum::EMPLOYEE;
    $modelData['employment_type'] = \App\Enums\HumanResources\Employee\EmploymentTypeEnum::FULL_TIME;
    $modelData['state']           = \App\Enums\HumanResources\Employee\EmployeeStateEnum::WORKING;
    $employee = StoreEmployee::make()->action($this->organisation, $modelData);

    $production = ToggleArtisanInRoster::make()->action($this->production, $employee, true);
    expect($production->data['hidden_artisan_ids'])->toBe([$employee->id]);

    $production = ToggleArtisanInRoster::make()->action($production, $employee, true);
    expect($production->data['hidden_artisan_ids'])->toBe([$employee->id]);

    $production = ToggleArtisanInRoster::make()->action($production, $employee, false);
    expect($production->data['hidden_artisan_ids'])->toBe([]);
});

test('artefact can be marked as mix and back', function () {
    $rawMaterial = SetArtefactAsMix::make()->action($this->artefact, true);
    expect($rawMaterial)->not->toBeNull()
        ->and($rawMaterial->artefact_id)->toBe($this->artefact->id)
        ->and($rawMaterial->type)->toBe(RawMaterialTypeEnum::INTERMEDIATE)
        ->and(SetArtefactAsMix::make()->action($this->artefact, true)->id)->toBe($rawMaterial->id);

    expect(SetArtefactAsMix::make()->action($this->artefact, false))->toBeNull()
        ->and($rawMaterial->fresh()->artefact_id)->toBeNull();
});

test('mixes to prepare are derived from open job orders and become job orders', function () {
    $mixArtefact = StoreArtefact::make()->action($this->production, ['code' => 'MIX-BASE', 'name' => 'Bath bomb base mix']);
    $mix         = UpdateRawMaterial::make()->action($this->rawMaterial, ['artefact_id' => $mixArtefact->id, 'quantity_on_location' => 2]);
    $mix->update(['org_stock_id' => null]);

    $this->artefact->manufactureTasks()->syncWithoutDetaching([
        $this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1],
    ]);
    $step = ArtefactManufactureTask::where('artefact_id', $this->artefact->id)->where('manufacture_task_id', $this->manufactureTask->id)->first();
    $step->rawMaterials()->delete();
    AttachRawMaterialToRecipeStep::make()->action($step, ['raw_material_id' => $mix->id, 'quantity_per_unit' => 0.5]);

    $jobOrder = StoreJobOrder::make()->action($this->production, []);
    StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 10]);

    $openQuantity = JobOrderItem::where('artefact_id', $this->artefact->id)
        ->whereHas('jobOrder', fn ($query) => $query->whereIn('state', [JobOrderStateEnum::IN_PROCESS, JobOrderStateEnum::SUBMITTED, JobOrderStateEnum::CONFIRMED]))
        ->sum('quantity');
    $expectedNeeded = round($openQuantity * 0.5, 3);

    $mixes = collect(GetMixesToPrepare::run($this->production))->keyBy('code');
    expect($mixes->get('MIX-BASE'))->not->toBeNull()
        ->and($mixes->get('MIX-BASE')['needed'])->toBe($expectedNeeded)
        ->and($mixes->get('MIX-BASE')['on_hand'])->toBe(2.0)
        ->and($mixes->get('MIX-BASE')['shortfall'])->toBe(round($expectedNeeded - 2, 3))
        ->and($mixes->get('MIX-BASE')['needed_for'])->toBe([$this->artefact->code]);

    $shortfall = $mixes->get('MIX-BASE')['shortfall'];
    $created   = StoreJobOrdersForMixes::make()->action($this->production, [['artefact_id' => $mixArtefact->id, 'quantity' => $shortfall]]);
    expect($created)->toHaveCount(1)
        ->and($created[0]->jobOrderItems()->first()->artefact_id)->toBe($mixArtefact->id)
        ->and($created[0]->jobOrderItems()->first()->quantity)->toBe((int) ceil($shortfall));

    $mixes = collect(GetMixesToPrepare::run($this->production))->keyBy('code');
    expect($mixes->get('MIX-BASE')['in_progress'])->toBe((float) ceil($shortfall))
        ->and($mixes->get('MIX-BASE')['shortfall'])->toBe(0.0);

    $mixJobOrders = GetMixJobOrders::run($this->production);
    expect($mixJobOrders)->toHaveCount(1)
        ->and($mixJobOrders[0]['code'])->toBe('MIX-BASE')
        ->and($mixJobOrders[0]['job_order_id'])->toBe($created[0]->id)
        ->and($mixJobOrders[0]['job_order_state'])->toBe('in_process')
        ->and($mixJobOrders[0]['quantity'])->toBe((float) ceil($shortfall));

    $missing = GetJobOrderItemMissingMixes::run($jobOrder->jobOrderItems()->first());
    expect($missing)->toHaveCount(1)
        ->and($missing[0]['code'])->toBe('MIX-BASE')
        ->and($missing[0]['needed'])->toBe(5.0)
        ->and($missing[0]['on_hand'])->toBe(2.0);

    $mix->update(['quantity_on_location' => 50]);
    expect(GetJobOrderItemMissingMixes::run($jobOrder->jobOrderItems()->first()))->toBe([]);
});

test('a task that is not piece rate snapshots a zero rate when its session closes', function () {
    $this->manufactureTask->update(['is_piece_rate' => false]);
    $jobOrder = StoreJobOrder::make()->action($this->production, []);
    $this->artefact->manufactureTasks()->syncWithoutDetaching([
        $this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1],
    ]);
    $item = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 4]);
    ConfirmJobOrder::make()->action($jobOrder);
    $task = $item->tasks()->first();
    $user = createAdminGuest($this->group)->getUser();

    $session = StartManufactureTaskSession::make()->action($user, $task);
    $session = CloseManufactureTaskSession::make()->action($session, ['quantity_made' => 4]);

    expect((float) $session->task_work_cost)->toBe(0.0)
        ->and((float) $session->operative_reward_amount)->toBe(0.0)
        ->and((float) $session->quantity_made)->toBe(4.0);

    $this->manufactureTask->update(['is_piece_rate' => true]);
});

test('production job positions carry the factory roles all the way to the user', function () {
    SeedJobPositions::make()->handle($this->organisation);

    $supervisorPosition = JobPosition::where('organisation_id', $this->organisation->id)->where('code', 'prod-m')->first();
    $operativePosition  = JobPosition::where('organisation_id', $this->organisation->id)->where('code', 'prod-c')->first();
    $preparerPosition = JobPosition::where('organisation_id', $this->organisation->id)->where('code', 'prod-p')->first();
    expect($supervisorPosition->roles()->pluck('name')->all())->toContain('production-orchestrator-'.$this->production->id)
        ->and($operativePosition->roles()->pluck('name')->all())->toContain('production-operator-'.$this->production->id)
        ->and($preparerPosition->roles()->pluck('name')->all())->toContain('production-preparer-'.$this->production->id)
        ->and(Role::where('name', 'production-preparer-'.$this->production->id)->first()->permissions()->pluck('name')->all())
        ->toEqualCanonicalizing(['productions_operations.'.$this->production->id.'.view', 'productions_operations.'.$this->production->id.'.prepare']);

    $modelData = Employee::factory()->make(['organisation_id' => $this->organisation->id])->toArray();
    $modelData['worker_number']   = 'W'.rand(1000, 9999);
    $modelData['alias']           = 'Alias '.rand(1000, 9999);
    $modelData['type']            = \App\Enums\HumanResources\Employee\EmployeeTypeEnum::EMPLOYEE;
    $modelData['employment_type'] = \App\Enums\HumanResources\Employee\EmploymentTypeEnum::FULL_TIME;
    $modelData['state']           = \App\Enums\HumanResources\Employee\EmployeeStateEnum::WORKING;
    $modelData['username']        = 'lucy'.rand(1000, 9999);
    $modelData['password']        = 'secret-password';
    $employee = StoreEmployee::make()->action($this->organisation, $modelData);
    $user     = $employee->users()->first();
    expect($user)->not->toBeNull();

    SyncEmployeeJobPositions::make()->handle($employee, [
        $supervisorPosition->id => ['Production' => [$this->production->id]],
    ]);

    expect($user->refresh()->hasRole('production-orchestrator-'.$this->production->id))->toBeTrue()
        ->and($user->authorisedProductions()->where('productions.id', $this->production->id)->exists())->toBeTrue();

    UpdateEmployee::make()->action($employee, [
        'job_positions' => [
            ['slug' => $operativePosition->slug, 'scopes' => ['productions' => ['slug' => [$this->production->slug]]]],
        ],
    ]);

    expect($user->refresh()->hasRole('production-operator-'.$this->production->id))->toBeTrue()
        ->and($user->hasRole('production-orchestrator-'.$this->production->id))->toBeFalse()
        ->and(GetEmployeeJobPositionsData::run($employee->refresh()))->toBe(['prod-c' => ['productions' => [$this->production->slug]]]);

    UpdateEmployee::make()->action($employee, [
        'job_positions' => [
            ['slug' => $operativePosition->slug, 'scopes' => []],
        ],
    ]);

    expect($user->refresh()->hasRole('production-operator-'.$this->production->id))->toBeFalse();
});

test('costings import resolves materials, creates artefacts and writes per-unit recipes once', function () {
    $dir = sys_get_temp_dir().'/costings-'.uniqid();
    mkdir($dir);
    $existingArtefact = StoreArtefact::make()->action($this->production, ['code' => 'CST-EXIST', 'name' => 'Existing product']);
    file_put_contents($dir.'/import.json', json_encode([
        'materials' => [
            ['master_row' => 1, 'code' => $this->rawMaterial->code, 'name' => 'Whatever', 'cost' => 9, 'unit' => 'kilogram', 'cas' => null, 'inci' => null, 'section' => 'x', 'family' => null, 'times_used' => 1],
            ['master_row' => 2, 'code' => '1.0', 'name' => 'Lavender Essential Oil', 'cost' => 16, 'unit' => 'kilogram', 'cas' => '8000-28-0', 'inci' => null, 'section' => 'ESSENTIAL OILS', 'family' => 'EOKG', 'times_used' => 1],
            ['master_row' => 3, 'code' => null, 'name' => 'Unused thing', 'cost' => 1, 'unit' => 'unit', 'cas' => null, 'inci' => null, 'section' => 'x', 'family' => null, 'times_used' => 0],
            ['master_row' => 4, 'code' => null, 'name' => 'Bicarb', 'cost' => 0.72, 'unit' => 'kilogram', 'cas' => null, 'inci' => null, 'section' => 'x', 'family' => null, 'times_used' => 1, 'aiku_code' => $this->rawMaterial->code, 'pack_size' => 25],
        ],
        'artefacts' => [
            ['code' => 'CST-EXIST', 'name' => 'Existing product', 'create' => false, 'summary_row' => 10, 'sheet_codes' => ['CST-EXIST'], 'cost_per_unit' => 2.5, 'lines' => [['master_row' => 1, 'label' => 'Base', 'quantity_per_unit' => 0.25], ['master_row' => 2, 'label' => 'Lavender', 'quantity_per_unit' => 0.012]]],
            ['code' => 'CST-PACK', 'name' => 'Pack-size product', 'create' => true, 'summary_row' => 12, 'sheet_codes' => ['CST-PACK'], 'cost_per_unit' => 1, 'lines' => [['master_row' => 4, 'label' => 'Bicarb', 'quantity_per_unit' => 5]]],
            ['code' => 'CST-NEW', 'name' => 'Brand new product', 'create' => true, 'summary_row' => 11, 'sheet_codes' => ['CST-NEW'], 'cost_per_unit' => 1, 'lines' => [['master_row' => 2, 'label' => 'Lavender', 'quantity_per_unit' => 0.5]]],
        ],
    ]));

    $slug = $this->production->slug;
    $rawMaterialsBefore = RawMaterial::count();
    $this->artisan('manufacture:import-costings', ['production' => $slug, 'dir' => $dir, '--phase' => 'materials'])->assertExitCode(0);
    expect(RawMaterial::count())->toBe($rawMaterialsBefore)
        ->and(file_exists($dir.'/review/materials_review.csv'))->toBeTrue();

    $this->artisan('manufacture:import-costings', ['production' => $slug, 'dir' => $dir, '--phase' => 'materials', '--write' => true])->assertExitCode(0);
    $lavender = RawMaterial::where('source_id', 'costings:master:2')->first();
    expect(RawMaterial::count())->toBe($rawMaterialsBefore + 1)
        ->and($lavender)->not->toBeNull()
        ->and($lavender->code)->toBe('CST-2')
        ->and((float)$lavender->unit_cost)->toBe(16.0)
        ->and($lavender->data['cas'])->toBe('8000-28-0')
        ->and(RawMaterial::where('source_id', 'costings:master:1')->exists())->toBeFalse();

    $this->artisan('manufacture:import-costings', ['production' => $slug, 'dir' => $dir, '--phase' => 'artefacts', '--write' => true])->assertExitCode(0);
    $newArtefact = Artefact::where('code', 'CST-NEW')->first();
    expect($newArtefact)->not->toBeNull()
        ->and($newArtefact->source_id)->toBe('costings:summary:11');

    $this->artisan('manufacture:import-costings', ['production' => $slug, 'dir' => $dir, '--phase' => 'recipes', '--write' => true])->assertExitCode(0);
    $lines = fn (Artefact $artefact) => RecipeStepRawMaterial::whereIn('artefact_manufacture_task_id', ArtefactManufactureTask::where('artefact_id', $artefact->id)->pluck('id'))->get();
    expect($lines($existingArtefact)->count())->toBe(2)
        ->and((float)$lines($existingArtefact)->firstWhere('raw_material_id', $this->rawMaterial->id)->quantity_per_unit)->toBe(0.25)
        ->and((float)$lines($existingArtefact)->firstWhere('raw_material_id', $lavender->id)->quantity_per_unit)->toBe(0.012)
        ->and($lines($newArtefact)->count())->toBe(1)
        ->and((float)$lines(Artefact::where('code', 'CST-PACK')->first())->first()->quantity_per_unit)->toBe(0.2);

    $this->artisan('manufacture:import-costings', ['production' => $slug, 'dir' => $dir, '--phase' => 'recipes', '--write' => true])->assertExitCode(0);
    expect($lines($existingArtefact)->count())->toBe(2)
        ->and(RawMaterial::count())->toBe($rawMaterialsBefore + 1)
        ->and(str_contains(file_get_contents($dir.'/review/recipes_review.csv'), 'existing recipe kept'))->toBeTrue();

    $auroraArtefact = StoreArtefact::make()->action($this->production, ['code' => 'CST-AURORA2', 'name' => 'Aurora product', 'source_id' => '4:999']);
    $auroraArtefact->manufactureTasks()->syncWithoutDetaching([$this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1]]);
    AttachRawMaterialToRecipeStep::make()->action(ArtefactManufactureTask::where('artefact_id', $auroraArtefact->id)->first(), ['raw_material_id' => $this->rawMaterial->id, 'quantity_per_unit' => 3]);

    $edited = json_decode(file_get_contents($dir.'/import.json'), true);
    $edited['materials'][1]['cost'] = 20;
    $edited['artefacts'][0]['lines'][1]['quantity_per_unit'] = 0.02;
    $edited['artefacts'][] = ['code' => 'CST-AURORA2', 'name' => 'Aurora product', 'create' => false, 'summary_row' => 13, 'sheet_codes' => ['CST-AURORA2'], 'cost_per_unit' => 1, 'lines' => [['master_row' => 1, 'label' => 'Base', 'quantity_per_unit' => 9]]];
    file_put_contents($dir.'/import.json', json_encode($edited));

    $this->artisan('manufacture:import-costings', ['production' => $slug, 'dir' => $dir, '--phase' => 'recipes', '--write' => true])->assertExitCode(0);
    expect((float)$lines($existingArtefact)->firstWhere('raw_material_id', $lavender->id)->quantity_per_unit)->toBe(0.012);

    $this->artisan('manufacture:import-costings', ['production' => $slug, 'dir' => $dir, '--phase' => 'materials', '--write' => true, '--replace' => true])->assertExitCode(0);
    $this->artisan('manufacture:import-costings', ['production' => $slug, 'dir' => $dir, '--phase' => 'recipes', '--write' => true, '--replace' => true])->assertExitCode(0);
    expect((float)$lavender->refresh()->unit_cost)->toBe(20.0)
        ->and((float)$this->rawMaterial->refresh()->unit_cost)->not->toBe(9.0)
        ->and($lines($existingArtefact)->count())->toBe(2)
        ->and((float)$lines($existingArtefact)->firstWhere('raw_material_id', $lavender->id)->quantity_per_unit)->toBe(0.02)
        ->and((float)$lines($auroraArtefact)->first()->quantity_per_unit)->toBe(3.0)
        ->and(str_contains(file_get_contents($dir.'/review/recipes_review.csv'), 'replaced'))->toBeTrue();
});

test('aurora recipe quantities are divided by batch size exactly once', function () {
    $artefact = StoreArtefact::make()->action($this->production, ['code' => 'CST-AURORA', 'name' => 'Aurora product', 'source_id' => '4:99999', 'recommended_batch_size' => 10]);
    $artefact->manufactureTasks()->syncWithoutDetaching([$this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1]]);
    $step = ArtefactManufactureTask::where('artefact_id', $artefact->id)->first();
    AttachRawMaterialToRecipeStep::make()->action($step, ['raw_material_id' => $this->rawMaterial->id, 'quantity_per_unit' => 10]);

    $this->artisan('manufacture:normalise-aurora-recipes', ['production' => $this->production->slug])->assertExitCode(0);
    expect((float)$step->rawMaterials()->first()->quantity_per_unit)->toBe(10.0);

    $this->artisan('manufacture:normalise-aurora-recipes', ['production' => $this->production->slug, '--write' => true])->assertExitCode(0);
    expect((float)$step->rawMaterials()->first()->quantity_per_unit)->toBe(1.0);

    $this->artisan('manufacture:normalise-aurora-recipes', ['production' => $this->production->slug, '--write' => true])->assertExitCode(0);
    expect((float)$step->rawMaterials()->first()->quantity_per_unit)->toBe(1.0)
        ->and($artefact->refresh()->data['recipe_quantities_normalised_at'])->not->toBeNull();
});

test('an operative only sees the factory jobs page and nothing group or commercial', function () {
    SeedJobPositions::make()->handle($this->organisation);
    $operativePosition = JobPosition::where('organisation_id', $this->organisation->id)->where('code', 'prod-c')->first();

    $modelData                    = Employee::factory()->make(['organisation_id' => $this->organisation->id])->toArray();
    $modelData['worker_number']   = 'W'.rand(1000, 9999);
    $modelData['alias']           = 'Alias '.rand(1000, 9999);
    $modelData['type']            = \App\Enums\HumanResources\Employee\EmployeeTypeEnum::EMPLOYEE;
    $modelData['employment_type'] = \App\Enums\HumanResources\Employee\EmploymentTypeEnum::FULL_TIME;
    $modelData['state']           = \App\Enums\HumanResources\Employee\EmployeeStateEnum::WORKING;
    $modelData['username']        = 'operative'.rand(1000, 9999);
    $modelData['password']        = 'secret-password';
    $employee = StoreEmployee::make()->action($this->organisation, $modelData);
    SyncEmployeeJobPositions::make()->handle($employee, [
        $operativePosition->id => ['Production' => [$this->production->id]],
    ]);
    $user = $employee->users()->first()->refresh();

    expect($user->hasGroupAccess())->toBeFalse()
        ->and(array_keys(\App\Actions\UI\Grp\Layout\GetProductionNavigation::run($this->production, $user)))->toBe(['jobs'])
        ->and(array_keys(\App\Actions\UI\Grp\Layout\GetOrganisationNavigation::run($user, $this->organisation)))
        ->not->toContain('overview', 'chat', 'calendar_offers')
        ->and(array_keys(\App\Actions\UI\Grp\Layout\GetProductionNavigation::run($this->production, $this->guest->getUser())))
        ->toBe(['jobs', 'crafts', 'operations', 'partners', 'to_restock', 'pre_pick', 'artisans']);

    actingAs($user);
    get(route('grp.dashboard.show'))->assertRedirect(route('grp.org.dashboard.show', $this->organisation->slug));
    get(route('grp.org.dashboard.show', $this->organisation->slug))
        ->assertRedirect(route('grp.org.productions.show.floor', [$this->organisation->slug, $this->production->slug]));
    $this->artefact->manufactureTasks()->syncWithoutDetaching([
        $this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1],
    ]);
    $assigned = StoreJobOrder::make()->action($this->production, ['employee_id' => $employee->id]);
    StoreJobOrderItem::make()->action($assigned, ['artefact_id' => $this->artefact->id, 'quantity' => 2]);
    $pool = StoreJobOrder::make()->action($this->production, []);
    StoreJobOrderItem::make()->action($pool, ['artefact_id' => $this->artefact->id, 'quantity' => 2]);
    ConfirmJobOrder::make()->action($pool);

    $props = get(route('grp.org.productions.show.floor', [$this->organisation->slug, $this->production->slug]))
        ->assertOk()->viewData('page')['props'];
    expect($props['can_pick_open_jobs'])->toBeFalse()
        ->and(collect($props['tasks'])->pluck('job_order_reference')->all())->toBe([$assigned->reference]);

    expect(fn () => StartManufactureTaskSession::make()->action($user, $pool->jobOrderItems()->first()->tasks()->first()))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
    $session = StartManufactureTaskSession::make()->action($user, $assigned->jobOrderItems()->first()->tasks()->first());
    expect($assigned->refresh()->state)->toBe(JobOrderStateEnum::CONFIRMED)
        ->and(get(route('grp.org.productions.show.floor', [$this->organisation->slug, $this->production->slug]))
            ->viewData('page')['props']['open_session']['can_reject'])->toBeFalse();
    \Pest\Laravel\patch(route('grp.models.manufacture-task-session.close', $session->id), ['quantity_made' => 2, 'quantity_rejected' => 5])
        ->assertRedirect();
    expect((float) $session->refresh()->quantity_rejected)->toBe(0.0)
        ->and((float) $session->quantity_made)->toBe(2.0);

    get(route('grp.org.chat.dashboard', $this->organisation->slug))->assertForbidden();
    get(route('grp.org.offer.calendar', $this->organisation->slug))->assertForbidden();
    get(route('grp.org.overview.hub', $this->organisation->slug))->assertForbidden();
    actingAs($this->guest->getUser());
});

test('artefacts with nothing sold in three years go dormant and wake up when they sell again', function () {
    $repair = \App\Actions\Maintenance\Production\RepairDormantArtefacts::make();
    $since  = now()->subMonths(36)->toDateTimeString();

    $artefact = StoreArtefact::make()->action($this->production, ['code' => 'DORM-01', 'name' => 'Dormant candidate']);
    $artefact->update(['state' => ArtefactStateEnum::ACTIVE, 'created_at' => now()->subYears(4)]);
    expect($repair->toPark($this->production, $since)->pluck('id')->all())->toContain($artefact->id);

    $repair->handle($artefact, ArtefactStateEnum::DORMANT);
    expect($artefact->refresh()->state)->toBe(ArtefactStateEnum::DORMANT)
        ->and($repair->toWake($this->production, $since)->count())->toBe(0);

    $made = StoreArtefact::make()->action($this->production, ['code' => 'DORM-02', 'name' => 'Made but never sold']);
    $made->update(['state' => ArtefactStateEnum::ACTIVE]);
    StoreJobOrderItem::make()->action(StoreJobOrder::make()->action($this->production, []), ['artefact_id' => $made->id, 'quantity' => 1]);
    expect($repair->toPark($this->production, $since)->pluck('id')->all())->not->toContain($made->id);

    list($organisation, $user, $shop) = createShop();
    [, $product] = createProduct($shop);
    $orgStock = $product->orgStocks()->first();
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

test('to produce queue only shows lines with an artefact in this factory', function () {
    $stocks    = createStocks($this->group);
    $orgStocks = createOrgStocks($this->organisation, [$stocks[0], $stocks[1]]);

    \App\Models\Production\Artefact::where('production_id', $this->production->id)
        ->whereIn('org_stock_id', [$orgStocks[0]->id, $orgStocks[1]->id])
        ->update(['org_stock_id' => null]);

    $made = StoreArtefact::make()->action($this->production, ['code' => 'GATE-01', 'name' => 'Made here']);
    $made->update(['org_stock_id' => $orgStocks[0]->id]);
    $orgStocks[0]->update(['quantity_in_locations' => 0]);
    $orgStocks[1]->update(['quantity_in_locations' => 0]);

    foreach ($orgStocks as $orgStock) {
        \App\Models\Procurement\PartnerShoppingListItem::create([
            'group_id'        => $this->group->id,
            'organisation_id' => $this->organisation->id,
            'stock_id'        => $orgStock->stock_id,
            'org_stock_id'    => $orgStock->id,
            'quantity'        => 5,
        ]);
    }

    actingAs($this->guest->getUser());
    $routeParameters = [$this->organisation->slug, $this->production->slug];

    $board = get(route('grp.org.productions.show.to_produce.index', $routeParameters))
        ->assertOk()->viewData('page')['props'];
    $lanes = collect($board['groups'])->mapWithKeys(fn ($lane) => [$lane['label'] => collect($lane['items'])->pluck('stock_code')->all()]);
    expect($lanes)->not->toHaveKey('Pre-pick')
        ->and($lanes['Backlog'])->toBe([$stocks[0]->code]);

    $otherProduction = StoreProduction::make()->action($this->organisation, ['code' => 'GATEF2', 'name' => 'Other factory']);
    $elsewhere = StoreArtefact::make()->action($otherProduction, ['code' => 'GATE-02', 'name' => 'Made in the other factory']);
    $elsewhere->update(['org_stock_id' => $orgStocks[1]->id]);

    $lanes = collect(get(route('grp.org.productions.show.to_produce.index', $routeParameters))
        ->assertOk()->viewData('page')['props']['groups'])
        ->mapWithKeys(fn ($lane) => [$lane['label'] => collect($lane['items'])->pluck('stock_code')->all()]);
    expect($lanes['Backlog'])->toBe([$stocks[0]->code]);

    $covered = \App\Models\Procurement\PartnerShoppingListItem::where('org_stock_id', $orgStocks[0]->id)->first();
    $orgStocks[0]->update(['quantity_available' => 500, 'quantity_in_locations' => 500]);
    $covered->update(['preparing_at' => now()]);
    $lanes = collect(get(route('grp.org.productions.show.to_produce.index', $routeParameters))
        ->assertOk()->viewData('page')['props']['groups'])
        ->mapWithKeys(fn ($lane) => [$lane['label'] => collect($lane['items'])->pluck('stock_code')->all()]);
    expect($lanes['Preparing'])->toBe([$stocks[0]->code]);

    $covered->update(['preparing_at' => null]);

    \App\Actions\Production\PartnerShippingList\StoreJobOrdersFromToProduceItems::make()
        ->action($this->production, [$covered->id]);
    $lanes = collect(get(route('grp.org.productions.show.to_produce.index', $routeParameters))
        ->assertOk()->viewData('page')['props']['groups'])
        ->mapWithKeys(fn ($lane) => [$lane['label'] => collect($lane['items'])->pluck('stock_code')->all()]);
    expect($lanes['Assigned'])->toBe([$stocks[0]->code]);

    $made->manufactureTasks()->syncWithoutDetaching([$this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1]]);
    $jobOrder = \App\Models\Production\JobOrder::find($covered->refresh()->job_order_id);
    $jobOrderItem = $jobOrder->jobOrderItems()->where('artefact_id', $made->id)->first();
    \App\Actions\Production\JobOrderItemTask\GenerateJobOrderItemTasks::make()->handle($jobOrderItem);
    $task = $jobOrderItem->tasks()->first();
    ConfirmJobOrder::make()->action($jobOrder);
    $laneOf = fn () => collect(get(route('grp.org.productions.show.to_produce.index', $routeParameters))
        ->assertOk()->viewData('page')['props']['groups'])
        ->mapWithKeys(fn ($lane) => [$lane['label'] => collect($lane['items'])->pluck('stock_code')->all()]);
    expect($laneOf()['Assigned'])->toBe([$stocks[0]->code])
        ->and($laneOf()['Producing'])->toBe([]);

    $session = StartManufactureTaskSession::make()->action($this->guest->getUser(), $task);
    expect($laneOf()['Producing'])->toBe([$stocks[0]->code])
        ->and($laneOf()['Assigned'])->toBe([]);

    CloseManufactureTaskSession::make()->action($session, ['quantity_made' => $task->quantity_required]);
    expect($laneOf()['Done'])->toBe([$stocks[0]->code])
        ->and($laneOf()['Producing'])->toBe([]);

    $warehouse = \App\Actions\Inventory\Warehouse\StoreWarehouse::make()->action($this->organisation, ['code' => 'WH-BRD', 'name' => 'Board warehouse']);
    $area      = \App\Actions\Inventory\WarehouseArea\StoreWarehouseArea::make()->action($warehouse, ['code' => 'A-BRD', 'name' => 'Board area']);
    $location  = \App\Actions\Inventory\Location\StoreLocation::make()->action($area, ['code' => 'L-BRD', 'name' => 'Board loc'] + \App\Models\Inventory\Location::factory()->definition());
    $hubProps  = fn () => get(route('grp.org.warehouses.show.dispatching.backlog', [$this->organisation->slug, $warehouse->slug]))
        ->assertOk()->viewData('page')['props'];
    $hubOutput = fn () => collect($hubProps()['production_output'])->pluck('jobs')->flatten(1)->pluck('reference')->all();
    expect($hubOutput())->toContain($jobOrder->reference)
        ->and($hubProps()['tabs']['navigation']['production_output']['number'])->toBe(count($hubProps()['production_output']));

    \App\Actions\Dispatching\ProductionOutput\PutAwayFinishedJobOrder::make()->action($warehouse, [$jobOrder->id], 'L-BRD');
    expect($jobOrder->refresh()->state)->toBe(JobOrderStateEnum::RECEIVED)
        ->and($hubOutput())->not->toContain($jobOrder->reference)
        ->and($laneOf()->flatten()->all())->not->toContain($stocks[0]->code);

    $byArtisan = get(route('grp.org.productions.show.to_produce.by_artisan', $routeParameters))
        ->assertOk()->viewData('page')['props'];
    expect(collect($byArtisan['groups'])->pluck('items')->flatten(1)->pluck('stock_code')->all())->toBe([$stocks[0]->code]);

    $all = get(route('grp.org.productions.show.to_produce.list', $routeParameters))
        ->assertOk()->viewData('page')['props'];
    expect(collect($all['data']['data'])->pluck('stock_code')->sort()->values()->all())
        ->toBe(collect([$stocks[0]->code, $stocks[1]->code])->sort()->values()->all());
});

test('to restock bands rank artefacts by cover and queue them onto the to produce board', function () {
    $stocks    = createStocks($this->group);
    $orgStocks = createOrgStocks($this->organisation, [$stocks[0], $stocks[1]]);

    \App\Models\Production\Artefact::where('production_id', $this->production->id)
        ->whereIn('org_stock_id', [$orgStocks[0]->id, $orgStocks[1]->id])
        ->update(['org_stock_id' => null]);

    /* createStocks hands back the suite's shared stocks, so clear any board line an earlier test left. */
    \App\Models\Procurement\PartnerShoppingListItem::whereIn('stock_id', collect($orgStocks)->pluck('stock_id'))->forceDelete();

    $empty   = StoreArtefact::make()->action($this->production, ['code' => 'RES-01', 'name' => 'Sold out here']);
    $covered = StoreArtefact::make()->action($this->production, ['code' => 'RES-02', 'name' => 'Plenty here']);
    $empty->update(['org_stock_id' => $orgStocks[0]->id]);
    $covered->update(['org_stock_id' => $orgStocks[1]->id]);

    $orgStocks[0]->update(['quantity_available' => 0]);
    $orgStocks[1]->update(['quantity_available' => 500]);
    $orgStocks[1]->stats()->update(['days_of_cover' => 400, 'predicted_daily_usage' => 1, 'recommended_order_quantity' => 12]);
    /* days_of_cover 0 keeps it at the head of the lane whatever else the suite has left behind. */
    $orgStocks[0]->stats()->update(['recommended_order_quantity' => 9, 'days_of_cover' => 0]);

    $bucketOf = function (int $artefactId) {
        $buckets = \App\Actions\Production\Restock\GetProductionStockCoverBuckets::make();

        return collect(\App\Actions\Production\Restock\GetProductionStockCoverBuckets::BUCKETS)
            ->keys()
            ->first(fn (string $bucket) => in_array($artefactId, $buckets->artefactIdsInBucket($this->production, $bucket), true));
    };

    expect($bucketOf($empty->id))->toBe('out')
        ->and($bucketOf($covered->id))->toBe('ok');

    actingAs($this->guest->getUser());
    $routeParameters = [$this->organisation->slug, $this->production->slug];

    $props = get(route('grp.org.productions.show.to_restock.index', $routeParameters))
        ->assertOk()->viewData('page')['props'];
    $toDo = collect($props['lanes']['to_do'])->pluck('stock_code');

    expect($toDo)->toContain($orgStocks[0]->code)
        ->and($toDo)->not->toContain($orgStocks[1]->code)
        ->and($props['leadTime']['days'])->toBeGreaterThan(0);

    \App\Actions\Production\Restock\QueueArtefactsToProduce::make()
        ->action($this->organisation, $this->production, [['artefact_id' => $empty->id, 'quantity' => 9]]);

    $queued = \App\Models\Procurement\PartnerShoppingListItem::where('org_stock_id', $orgStocks[0]->id)->first();
    expect((float) $queued->quantity)->toBe(9.0)
        ->and($queued->partner_organisation_id)->toBeNull()
        ->and($queued->organisation_id)->toBe($this->organisation->id);

    $lanes = get(route('grp.org.productions.show.to_restock.index', $routeParameters))
        ->assertOk()->viewData('page')['props']['lanes'];

    expect(collect($lanes['to_do'])->pluck('stock_code'))->not->toContain($orgStocks[0]->code)
        ->and(collect($lanes['queued'])->pluck('stock_code'))->toContain($orgStocks[0]->code);
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

    $artefact = StoreArtefact::make()->action($this->production, [
        'code'                   => 'FAMART1',
        'name'                   => 'Artefact with org stock family',
        'org_stock_id'           => $orgStock->id,
        'artefact_department_id' => $department->id,
    ]);

    $orphan = StoreArtefact::make()->action($this->production, [
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
    StoreArtefact::make()->action($this->production, [
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
    $artefact = StoreArtefact::make()->action($this->production, [
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
    $artefact = StoreArtefact::make()->action($this->production, [
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

    $artefact = StoreArtefact::make()->action($this->production, [
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

    $artefact = StoreArtefact::make()->action($this->production, [
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

    $one = StoreArtefact::make()->action($this->production, [
        'code'               => 'STATE-01',
        'name'               => 'First',
        'artefact_family_id' => $family->id,
    ]);
    $two = StoreArtefact::make()->action($this->production, [
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

    $liveArtefact = StoreArtefact::make()->action($this->production, ['code' => 'HIDE-01', 'name' => 'Live', 'artefact_family_id' => $live->id]);
    $deadArtefact = StoreArtefact::make()->action($this->production, ['code' => 'HIDE-02', 'name' => 'Dead', 'artefact_family_id' => $parked->id]);

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

    $complete = StoreArtefact::make()->action($this->production, [
        'code'                   => 'GAP-01',
        'name'                   => 'Complete',
        'artefact_family_id'     => $family->id,
        'recommended_batch_size' => 40,
    ]);
    StoreArtefact::make()->action($this->production, [
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

    ArtefactFamilyHydrateArtefacts::run($family);

    expect($family->refresh()->number_artefacts_without_recipe)->toBe(1)
        ->and($family->number_artefacts_without_batch_size)->toBe(1);
});

test('crafts dashboard families card carries the family state counts', function () {
    $department = StoreArtefactDepartment::make()->action($this->production, ['code' => 'CARDDEP', 'name' => 'Card department']);
    $family     = StoreArtefactFamily::make()->action($department, ['code' => 'CARDFAM', 'name' => 'Card family']);

    $artefact = StoreArtefact::make()->action($this->production, [
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

    $one = StoreArtefact::make()->action($this->production, ['code' => 'BULK-01', 'name' => 'One', 'artefact_family_id' => $family->id]);
    $two = StoreArtefact::make()->action($this->production, ['code' => 'BULK-02', 'name' => 'Two', 'artefact_family_id' => $family->id]);

    expect($family->refresh()->number_artefacts_without_batch_size)->toBe(2);

    $changed = SetArtefactsBatchSize::make()->action($this->production, [
        'artefacts'              => [$one->id, $two->id],
        'recommended_batch_size' => 200,
    ]);

    expect($changed)->toBe(2)
        ->and($one->refresh()->recommended_batch_size)->toBe(200)
        ->and($two->refresh()->recommended_batch_size)->toBe(200)
        ->and($family->refresh()->number_artefacts_without_batch_size)->toBe(0);
});

test('bulk batch size leaves artefacts of another production alone', function () {
    $mine = StoreArtefact::make()->action($this->production, ['code' => 'SCOPE-01', 'name' => 'Mine']);

    $otherProduction = StoreProduction::make()->action($this->organisation, [
        'code' => 'SCOPEPROD',
        'name' => 'Scope production',
    ]);
    $theirs = StoreArtefact::make()->action($otherProduction, ['code' => 'SCOPE-02', 'name' => 'Theirs']);

    $changed = SetArtefactsBatchSize::make()->action($this->production, [
        'artefacts'              => [$mine->id, $theirs->id],
        'recommended_batch_size' => 50,
    ]);

    expect($changed)->toBe(1)
        ->and($mine->refresh()->recommended_batch_size)->toBe(50)
        ->and($theirs->refresh()->recommended_batch_size)->toBeNull();
});

test('discontinue artefacts in bulk and take the family down with them', function () {
    $department = StoreArtefactDepartment::make()->action($this->production, ['code' => 'DISCDEP', 'name' => 'Disc department']);
    $family     = StoreArtefactFamily::make()->action($department, ['code' => 'DISCFAM', 'name' => 'Disc family']);

    $one = StoreArtefact::make()->action($this->production, ['code' => 'DISC-01', 'name' => 'One', 'artefact_family_id' => $family->id]);
    $two = StoreArtefact::make()->action($this->production, ['code' => 'DISC-02', 'name' => 'Two', 'artefact_family_id' => $family->id]);
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

    $artefact = StoreArtefact::make()->action($this->production, [
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

test('units made become SKOs when the stock is packed in outers', function () {
    $stock = \App\Actions\Goods\Stock\StoreStock::make()->action(
        $this->group,
        array_merge(\App\Models\Goods\Stock::factory()->definition(), [
            'state' => \App\Enums\Goods\Stock\StockStateEnum::ACTIVE
        ])
    );
    $orgStock = \App\Actions\Inventory\OrgStock\StoreOrgStock::make()->action($this->organisation, $stock);
    $orgStock->update(['packed_in' => 10]);

    $artefact = StoreArtefact::make()->action($this->production, [
        'code'                   => 'PACKEDART1',
        'name'                   => 'Artefact sold in tens',
        'org_stock_id'           => $orgStock->id,
        'recommended_batch_size' => 16,
    ]);
    $artefact->manufactureTasks()->syncWithoutDetaching([
        $this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1],
    ]);

    $warehouse = \App\Actions\Inventory\Warehouse\StoreWarehouse::make()->action($this->organisation, [
        'code' => 'WH-PACK',
        'name' => 'Warehouse for packed receiving',
    ]);
    $area = \App\Actions\Inventory\WarehouseArea\StoreWarehouseArea::make()->action($warehouse, [
        'code' => 'A-PACK',
        'name' => 'Area packed receiving',
    ]);
    $location = \App\Actions\Inventory\Location\StoreLocation::make()->action(
        $area,
        [
            'code' => 'L-PACK',
            'name' => 'Loc packed receiving',
        ] + \App\Models\Inventory\Location::factory()->definition()
    );

    $jobOrder = StoreJobOrder::make()->action($this->production, []);
    $jobOrderItem = StoreJobOrderItem::make()->action($jobOrder, [
        'artefact_id' => $artefact->id,
        'quantity'    => 16,
    ]);

    ConfirmJobOrder::make()->action($jobOrder);

    $task = $jobOrderItem->tasks()->first();
    $session = StartManufactureTaskSession::make()->action($this->guest->getUser(), $task);
    CloseManufactureTaskSession::make()->action($session, ['quantity_made' => 16]);

    \App\Actions\Production\JobOrder\ReceiveJobOrderIntoStock::make()->action($jobOrder, [
        'location_id' => $location->id,
    ]);

    $movement = \App\Models\Inventory\OrgStockMovement::where('org_stock_id', $orgStock->id)
        ->where('type', \App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum::PRODUCTION)
        ->first();

    expect((float) $movement->quantity)->toBe(1.6);
});

test('a job order is raised in whole batches of units for the SKOs asked for', function () {
    $units = \App\Actions\Production\JobOrder\BatchedUnitsForDemand::run(12, 10, 16);

    expect($units)->toBe(128)
        ->and(\App\Actions\Production\JobOrder\BatchedUnitsForDemand::run(1, 10, 16))->toBe(16)
        ->and(\App\Actions\Production\JobOrder\BatchedUnitsForDemand::run(2.5, 1, 16))->toBe(16)
        ->and(\App\Actions\Production\JobOrder\BatchedUnitsForDemand::run(3, 10, null))->toBe(30)
        ->and(\App\Actions\Production\JobOrder\BatchedUnitsForDemand::run(0, null, null))->toBe(1);
});

test('the partner order quantum is the smallest whole SKO order that whole batches fill', function () {
    $quantum = fn (?int $packedIn, ?int $batchSize) => \App\Actions\Production\JobOrder\BatchedUnitsForDemand::make()->quantumInSkos($packedIn, $batchSize);

    expect($quantum(10, 16))->toBe(8)
        ->and($quantum(10, 20))->toBe(2)
        ->and($quantum(10, 10))->toBe(1)
        ->and($quantum(1, 16))->toBe(16)
        ->and($quantum(6, 4))->toBe(2)
        ->and($quantum(10, null))->toBe(1);
});

test('an order too small for a batch hitchhikes until something else fills the batch', function () {
    $stocks    = createStocks($this->group);
    $orgStocks = createOrgStocks($this->organisation, [$stocks[0]]);
    $orgStock  = $orgStocks[0];

    \App\Models\Production\Artefact::where('production_id', $this->production->id)
        ->where('org_stock_id', $orgStock->id)
        ->update(['org_stock_id' => null]);

    $orgStock->update(['packed_in' => 10, 'quantity_in_locations' => 0]);

    $artefact = StoreArtefact::make()->action($this->production, [
        'code'                   => 'HITCH-01',
        'name'                   => 'Made in batches of sixteen',
        'recommended_batch_size' => 16,
    ]);
    $artefact->update(['org_stock_id' => $orgStock->id]);

    \App\Models\Procurement\PartnerShoppingListItem::where('stock_id', $orgStock->stock_id)->forceDelete();

    $item = \App\Models\Procurement\PartnerShoppingListItem::create([
        'group_id'        => $this->group->id,
        'organisation_id' => $this->organisation->id,
        'stock_id'        => $orgStock->stock_id,
        'org_stock_id'    => $orgStock->id,
        'quantity'        => 1,
    ]);

    actingAs($this->guest->getUser());
    $routeParameters = [$this->organisation->slug, $this->production->slug];

    $props = fn (array $query = []) => get(route('grp.org.productions.show.to_produce.index', $routeParameters + $query))
        ->assertOk()->viewData('page')['props'];

    $backlogOf = fn (array $props) => collect($props['groups'])
        ->firstWhere('label', 'Backlog')['items'];

    $hidden = $props();
    expect($backlogOf($hidden))->toBe([])
        ->and($hidden['hitchhikers']['count'])->toBe(1)
        ->and($hidden['hitchhikers']['showing'])->toBeFalse();

    $shown = $props(['hitchhikers' => 1]);
    expect(collect($backlogOf($shown))->pluck('stock_code')->all())->toBe([$stocks[0]->code])
        ->and(collect($backlogOf($shown))->first()['is_hitchhiker'])->toBeTrue();

    /* Enough partners asking for the same thing fills the batch, so it stops hitchhiking. */
    $item->update(['quantity' => 8]);
    expect(collect($backlogOf($props()))->pluck('stock_code')->all())->toBe([$stocks[0]->code]);
});

test('a short day splits the made goods into whole destination trips and carries only what is still owed', function () {
    $this->artefact->manufactureTasks()->detach();
    $this->artefact->manufactureTasks()->syncWithoutDetaching([
        $this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1],
    ]);

    $stocks    = createStocks($this->group);
    $orgStocks = createOrgStocks($this->organisation, [$stocks[0]]);
    $orgStock  = $orgStocks[0];
    $stock     = $stocks[0];
    \App\Models\Procurement\PartnerShoppingListItem::where('org_stock_id', $orgStock->id)->forceDelete();
    \App\Models\Production\Artefact::where('production_id', $this->production->id)
        ->where('org_stock_id', $orgStock->id)
        ->where('id', '!=', $this->artefact->id)
        ->update(['org_stock_id' => null]);
    $this->artefact->update(['org_stock_id' => $orgStock->id]);
    $orgStock->update(['packed_in' => 5]);

    $warehouse = \App\Actions\Inventory\Warehouse\StoreWarehouse::make()->action($this->organisation, ['code' => 'WH-TRIP', 'name' => 'Trip warehouse']);
    $area      = \App\Actions\Inventory\WarehouseArea\StoreWarehouseArea::make()->action($warehouse, ['code' => 'A-TRIP', 'name' => 'Trip area']);
    $bayFor    = fn (string $code) => \App\Actions\Inventory\Location\StoreLocation::make()->action($area, ['code' => $code, 'name' => $code] + \App\Models\Inventory\Location::factory()->definition());

    $buyers = collect(['SKBUY', 'ESBUY'])->map(function (string $code) use ($bayFor) {
        $buyer = \App\Models\SysAdmin\Organisation::where('code', $code)->first()
            ?? \App\Actions\SysAdmin\Organisation\StoreOrganisation::make()->action($this->group, [
                'code' => $code,
                'name' => $code,
                'type' => \App\Enums\SysAdmin\Organisation\OrganisationTypeEnum::SHOP,
            ] + \App\Models\SysAdmin\Organisation::factory()->definition());
        $bay        = $bayFor('BAY-'.$code);
        $orgPartner = \App\Models\Procurement\OrgPartner::firstOrCreate(
            ['group_id' => $this->group->id, 'organisation_id' => $this->organisation->id, 'partner_id' => $buyer->id],
            ['status' => true],
        );
        $orgPartner->update(['goods_out_location_id' => $bay->id]);

        return ['organisation' => $buyer, 'bay' => $bay];
    });

    $lineFor = fn (array $buyer, int $quantity) => \App\Models\Procurement\PartnerShoppingListItem::create([
        'group_id'                => $this->group->id,
        'organisation_id'         => $buyer['organisation']->id,
        'partner_organisation_id' => $this->organisation->id,
        'stock_id'                => $stock->id,
        'org_stock_id'            => $orgStock->id,
        'quantity'                => $quantity,
    ]);

    /* Lines count SKOs, the job counts artefact units: 6 + 4 SKOs of five = 50 units. */
    $skLine = $lineFor($buyers[0], 6);
    $esLine = $lineFor($buyers[1], 4);

    $jobOrder = StoreJobOrder::make()->action($this->production, []);
    $item     = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 50]);
    ConfirmJobOrder::make()->action($jobOrder);
    $skLine->update(['job_order_id' => $jobOrder->id]);
    $esLine->update(['job_order_id' => $jobOrder->id]);

    $session = StartManufactureTaskSession::make()->action($this->guest->getUser(), $item->tasks()->first());
    CloseManufactureTaskSession::make()->action($session, ['quantity_made' => 35, 'outcome' => 'carry_over']);

    /* The big destination is filled whole, only the leftover walk is short. */
    $allocation = collect(\App\Actions\Production\JobOrder\GetJobOrderDestinationAllocation::run($jobOrder->refresh()))
        ->map(fn (array $row) => [$row['location_id'], $row['quantity']]);
    expect($allocation->all())->toBe([
        [$buyers[0]['bay']->id, 30.0],
        [$buyers[1]['bay']->id, 5.0],
    ]);

    /* What is still owed follows the carried job, and only that. */
    $carried = \App\Models\Production\JobOrder::where('production_id', $this->production->id)->orderByDesc('id')->first();
    expect($carried->jobOrderItems()->first()->quantity)->toBe(15)
        ->and($skLine->refresh()->job_order_id)->toBe($jobOrder->id)
        ->and($esLine->refresh()->job_order_id)->toBe($jobOrder->id)
        ->and((float) $esLine->quantity_to_produce)->toBe(1.0)
        ->and((float) $esLine->quantity)->toBe(1.0)
        ->and(\App\Models\Procurement\PartnerShoppingListItem::where('job_order_id', $carried->id)->sum('quantity_to_produce'))->toEqual(3)
        ->and(\App\Models\Procurement\PartnerShoppingListItem::where('job_order_id', $carried->id)->sum('quantity'))->toEqual(3);

    /* The warehouse gets one walk per bay, not one per job order. */
    $tripsTo = fn () => collect(\App\Actions\Dispatching\ProductionOutput\GetFinishedProductionJobOrders::run($warehouse))
        ->filter(fn (array $trip) => str_starts_with((string) $trip['destination']['location_code'], 'BAY-'))
        ->values();
    $trips = $tripsTo();
    expect($trips->pluck('destination.location_code')->all())->toBe(['BAY-SKBUY', 'BAY-ESBUY'])
        ->and($trips->map(fn (array $trip) => $trip['jobs'][0]['items'][0]['quantity'])->all())->toBe([6.0, 1.0]);

    /* Nothing of this job is bound for plain stock, so a shelf code is refused, not silently ignored. */
    $bayFor('SHELF-CO');
    expect(fn () => \App\Actions\Dispatching\ProductionOutput\PutAwayFinishedJobOrder::make()->action($warehouse, $trips[0]['job_order_ids'], 'SHELF-CO'))
        ->toThrow(ValidationException::class);

    \App\Actions\Dispatching\ProductionOutput\PutAwayFinishedJobOrder::make()->action($warehouse, $trips[0]['job_order_ids'], 'BAY-SKBUY');

    expect($jobOrder->refresh()->state)->toBe(JobOrderStateEnum::CONFIRMED)
        ->and((float) $item->refresh()->quantity_received)->toBe(30.0)
        ->and($tripsTo()->pluck('destination.location_code')->all())->toBe(['BAY-ESBUY']);

    \App\Actions\Dispatching\ProductionOutput\PutAwayFinishedJobOrder::make()->action($warehouse, $trips[1]['job_order_ids'], 'BAY-ESBUY');

    expect($jobOrder->refresh()->state)->toBe(JobOrderStateEnum::RECEIVED)
        ->and((float) $item->refresh()->quantity_received)->toBe(35.0)
        ->and((float) \App\Models\Inventory\LocationOrgStock::where('location_id', $buyers[0]['bay']->id)->where('org_stock_id', $orgStock->id)->value('quantity'))->toBe(6.0)
        ->and((float) \App\Models\Inventory\LocationOrgStock::where('location_id', $buyers[1]['bay']->id)->where('org_stock_id', $orgStock->id)->value('quantity'))->toBe(1.0);
});

test('carrying over a job does not ask for, or pay, the earlier steps twice', function () {
    $pack = StoreManufactureTask::make()->action($this->production, [
        'code'                            => 'PACK-CO',
        'name'                            => 'Pack',
        'task_materials_cost'             => 0,
        'task_energy_cost'                => 0,
        'task_other_cost'                 => 0,
        'task_work_cost'                  => 0.1,
        'task_lower_target'               => 10,
        'task_upper_target'               => 10,
        'operative_reward_terms'          => ManufactureTaskOperativeRewardTermsEnum::ABOVE_LOWER_LIMIT->value,
        'operative_reward_allowance_type' => ManufactureTaskOperativeRewardAllowanceTypeEnum::OFFSET_SALARY->value,
        'operative_reward_amount'         => 0,
    ]);
    $this->artefact->manufactureTasks()->detach();
    $this->artefact->manufactureTasks()->syncWithoutDetaching([
        $this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1],
        $pack->id                  => ['position' => 2, 'units_per_artefact' => 1],
    ]);
    $user = $this->guest->getUser();

    $jobOrder = StoreJobOrder::make()->action($this->production, []);
    $item     = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 100]);
    ConfirmJobOrder::make()->action($jobOrder);
    [$make, $packTask] = $item->tasks()->orderBy('position')->get();

    CloseManufactureTaskSession::make()->action(StartManufactureTaskSession::make()->action($user, $make), ['quantity_made' => 100]);
    CloseManufactureTaskSession::make()->action(StartManufactureTaskSession::make()->action($user, $packTask), ['quantity_made' => 60, 'outcome' => 'carry_over']);

    $carried      = \App\Models\Production\JobOrder::where('production_id', $this->production->id)->orderByDesc('id')->first();
    $carriedTasks = $carried->jobOrderItems()->first()->tasks()->orderBy('position')->get();

    expect($item->refresh()->quantity)->toBe(60)
        ->and($carried->jobOrderItems()->first()->quantity)->toBe(40)
        ->and((float) $carriedTasks[0]->quantity_required)->toBe(0.0)
        ->and($carriedTasks[0]->state)->toBe(JobOrderItemTaskStateEnum::DONE)
        ->and((float) $carriedTasks[1]->quantity_required)->toBe(40.0);

    /* Nobody can book more than what is left on a task. */
    expect(fn () => CloseManufactureTaskSession::make()->action(StartManufactureTaskSession::make()->action($user, $carriedTasks[1]), ['quantity_made' => 41]))
        ->toThrow(ValidationException::class);
});

test('factory search is gated by production permissions', function () {
    $stranger = \App\Models\SysAdmin\User::factory()->create(['group_id' => $this->group->id]);

    actingAs($stranger);
    get(route('grp.search.index', ['route_src' => 'grp.org.productions.show', 'production' => $this->production->slug, 'q' => 'a']))
        ->assertForbidden();

    actingAs($this->guest->getUser());
    get(route('grp.search.index', ['route_src' => 'grp.org.productions.show', 'production' => $this->production->slug, 'q' => 'a']))
        ->assertOk();
});

test('artefact labels can be saved, updated and deleted', function () {
    $layout = [
        'orientation' => 'portrait',
        'columns'     => 3,
        'rows'        => 8,
        'page_margin' => 8,
        'gap'         => 3,
        'fields'      => [['source' => 'batch_code', 'text' => 'B-1', 'x' => 0.1, 'y' => 0.2, 'font_size' => 8, 'color' => '#111827']],
    ];

    $labelId = \Pest\Laravel\postJson(route('grp.models.artefact.labels.store', $this->artefact->id), array_merge($layout, ['name' => 'Front']))
        ->assertCreated()
        ->json('data.id');

    $label = \App\Models\Production\ArtefactLabel::find($labelId);
    expect($label->name)->toBe('Front')
        ->and($label->layout['columns'])->toBe(3)
        ->and($label->layout['fields'][0]['text'])->toBe('B-1');

    \Pest\Laravel\postJson(route('grp.models.artefact.labels.update', [$this->artefact->id, $labelId]), array_merge($layout, ['name' => 'Back', 'columns' => 4]))
        ->assertOk();
    expect($label->refresh()->name)->toBe('Back')
        ->and($label->layout['columns'])->toBe(4);

    \Pest\Laravel\deleteJson(route('grp.models.artefact.labels.delete', [$this->artefact->id, $labelId]))
        ->assertOk();
    expect(\App\Models\Production\ArtefactLabel::find($labelId))->toBeNull();
});

test('artefact labels cannot be changed with view only production access', function () {
    $label = \App\Models\Production\ArtefactLabel::create([
        'group_id'        => $this->artefact->group_id,
        'organisation_id' => $this->artefact->organisation_id,
        'artefact_id'     => $this->artefact->id,
        'name'            => 'Kept',
        'layout'          => ['columns' => 3],
    ]);

    $operator = \App\Models\SysAdmin\User::factory()->create(['group_id' => $this->group->id]);
    $operator->syncRoles(['production-operator-'.$this->production->id]);
    actingAs($operator->fresh());

    \Pest\Laravel\postJson(route('grp.models.artefact.labels.store', $this->artefact->id), ['name' => 'New'])
        ->assertForbidden();
    \Pest\Laravel\deleteJson(route('grp.models.artefact.labels.delete', [$this->artefact->id, $label->id]))
        ->assertForbidden();

    expect($label->refresh()->deleted_at)->toBeNull();
});
