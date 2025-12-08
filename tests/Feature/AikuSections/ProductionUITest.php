<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 25 Nov 2024 11:47:23 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Analytics\GetSectionRoute;
use App\Actions\Production\Artefact\StoreArtefact;
use App\Actions\Production\ManufactureTask\StoreManufactureTask;
use App\Actions\Production\Production\StoreProduction;
use App\Actions\Production\RawMaterial\StoreRawMaterial;
use App\Enums\Analytics\AikuSection\AikuSectionEnum;
use App\Enums\Production\ManufactureTask\ManufactureTaskOperativeRewardAllowanceTypeEnum;
use App\Enums\Production\ManufactureTask\ManufactureTaskOperativeRewardTermsEnum;
use App\Enums\Production\RawMaterial\RawMaterialStateEnum;
use App\Enums\Production\RawMaterial\RawMaterialTypeEnum;
use App\Enums\Production\RawMaterial\RawMaterialUnitEnum;
use App\Models\Analytics\AikuScopedSection;
use App\Models\Production\Artefact;
use App\Models\Production\ManufactureTask;
use App\Models\Production\Production;
use App\Models\Production\RawMaterial;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {

    $this->organisation = createOrganisation();
    $this->adminGuest = createAdminGuest($this->organisation->group);

    $production = Production::first();
    if (! $production) {
        data_set($storeData, 'code', 'CODE');
        data_set($storeData, 'name', 'NAME');

        $production = StoreProduction::make()->action(
            $this->organisation,
            $storeData
        );
    }
    $this->production = $production;

    $artefact = Artefact::first();
    if (! $artefact) {
        data_set($storeData, 'code', 'CODE');
        data_set($storeData, 'name', 'NAME');

        $artefact = StoreArtefact::make()->action(
            $this->production,
            $storeData
        );
    }
    $this->artefact = $artefact;

    $rawMaterial = RawMaterial::first();
    if (! $rawMaterial) {
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
    if (! $manufactureTask) {
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
    actingAs($this->adminGuest->getUser());
});

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
        'tab' => 'manufacture_tasks',
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
        'tab' => 'artefact',
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
        'production' => $this->production->slug,
    ]);
    expect($sectionScope)->toBeInstanceOf(AikuScopedSection::class)
        ->and($sectionScope->organisation_id)->toBe($this->organisation->id)
        ->and($sectionScope->code)->toBe(AikuSectionEnum::PRODUCTION_CRAFT->value)
        ->and($sectionScope->model_slug)->toBe($this->production->slug);
});

test('UI get section route operation dashboard', function () {
    $sectionScope = GetSectionRoute::make()->handle('grp.org.productions.show.operations.dashboard', [
        'organisation' => $this->organisation->slug,
        'production' => $this->production->slug,
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
