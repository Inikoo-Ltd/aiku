<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 25 Nov 2024 11:48:10 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Feature;

use App\Actions\Analytics\GetSectionRoute;
use App\Actions\Production\Artefact\StoreArtefact;
use App\Actions\Production\JobOrder\StoreJobOrder;
use App\Actions\Production\JobOrder\UpdateJobOrder;
use App\Actions\Production\ManufactureTask\StoreManufactureTask;
use App\Actions\Production\ManufactureTask\UpdateManufactureTask;
use App\Actions\Production\Production\StoreProduction;
use App\Actions\Production\Production\UpdateProduction;
use App\Actions\Production\RawMaterial\StoreRawMaterial;
use App\Actions\Production\RawMaterial\UpdateRawMaterial;
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
            ->has('formData.blueprint.0.fields', 8)
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
            ->has('formData.blueprint.0.fields', 2)
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
