<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 25 Nov 2024 11:48:10 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Feature;

use App\Actions\Production\Artefact\StoreArtefact;
use App\Actions\Production\JobOrder\ConfirmJobOrder;
use App\Actions\Production\JobOrder\StoreJobOrder;
use App\Actions\Production\JobOrder\UpdateJobOrder;
use App\Actions\Production\ManufactureTask\StoreManufactureTask;
use App\Actions\Production\Artefact\AttachManufactureTaskToArtefact;
use App\Actions\Production\Artefact\AttachRawMaterialToRecipeStep;
use App\Actions\Production\Artefact\DetachManufactureTaskFromArtefact;
use App\Actions\Production\Artefact\DetachRawMaterialFromRecipeStep;
use App\Models\Production\ArtefactManufactureTask;
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
use App\Models\Production\Artefact;
use App\Models\Production\JobOrder;
use App\Models\Production\ManufactureTask;
use App\Models\Production\Production;
use App\Models\Production\RawMaterial;
use Config;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    $this->organisation = createOrganisation();
    $this->group        = group();
    setPermissionsTeamId($this->group->id);
    $this->guest        = createAdminGuest($this->group);

    $production = Production::first();
    if (!$production) {
        data_set($storeData, 'code', 'CODE');
        data_set($storeData, 'name', 'NAME');

        $production = StoreProduction::make()->action(
            $this->organisation,
            $storeData
        );
    }
    $this->production = $production;

    $artefact = Artefact::first();
    if (!$artefact) {
        data_set($storeData, 'code', 'CODE');
        data_set($storeData, 'name', 'NAME');

        $artefact = StoreArtefact::make()->action(
            $this->production,
            $storeData
        );
    }
    $this->artefact = $artefact;

    $rawMaterial = RawMaterial::first();
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

    $manufactureTask = ManufactureTask::first();
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
        ->and($production->roles()->count())->toBe(5);
});

test('seed production permissions', function () {
    setPermissionsTeamId($this->group->id);
    $this->artisan('production:seed-permissions')->assertExitCode(0);
    $production = Production::where('code', 'AA')->first();
    expect($production->roles()->count())->toBe(5);
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
            ->has('formData.blueprint.0.fields', 6)
            ->has('pageHead')
            ->has('breadcrumbs', 4);
    });
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
            ->has('formData.blueprint.0.fields', 5)
            ->has('pageHead')
            ->has('breadcrumbs', 4);
    });
});

test('UI Index production task', function () {
    $response = $this->get(route('grp.org.productions.show.crafts.manufacture_tasks.index', [$this->organisation->slug, $this->production->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Production/ManufactureTasks')
            ->has('title')
            ->has('tabs')
            ->has('breadcrumbs', 3);
    });
});

test('UI create production task', function () {
    $response = get(route('grp.org.productions.show.crafts.manufacture_tasks.create', [$this->organisation->slug, $this->production->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CreateModel')
            ->has('title')->has('formData')->has('pageHead')->has('breadcrumbs', 4);
    });
});

test('UI show production task', function () {
    $response = get(route('grp.org.productions.show.crafts.manufacture_tasks.show', [$this->organisation->slug, $this->production->slug, $this->manufactureTask->slug]));
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
    $response = get(route('grp.org.productions.show.crafts.manufacture_tasks.show', [
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
    $response = get(route('grp.org.productions.show.crafts.manufacture_tasks.edit', [$this->organisation->slug, $this->production->slug, $this->manufactureTask->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('EditModel')
            ->has('title')
            ->has('formData.blueprint.0.fields', 12)
            ->has('pageHead')
            ->has('breadcrumbs', 4);
    });
});

test('UI get section route craft index', function () {
    $sectionScope = GetSectionRoute::make()->handle('grp.org.productions.show.crafts.manufacture_tasks.index', [
        'organisation' => $this->organisation->slug,
        'production'      => $this->production->slug
    ]);
    expect($sectionScope)->toBeInstanceOf(AikuScopedSection::class)
        ->and($sectionScope->organisation_id)->toBe($this->organisation->id)
        ->and($sectionScope->code)->toBe(AikuSectionEnum::PRODUCTION_CRAFT->value)
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
    $response = get(route('grp.org.productions.show.operations.payroll.export', [
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
    $session = \App\Models\Production\ManufactureTaskSession::where('state', ManufactureTaskSessionStateEnum::CLOSED)
        ->orderByDesc('id')->first();
    $task = $session->jobOrderItemTask;
    expect($task->state)->toBe(JobOrderItemTaskStateEnum::DONE);

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

    $response = get(route('grp.org.productions.show.operations.artisans.index', [
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
