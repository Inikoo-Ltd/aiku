<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 25 Nov 2024 11:48:10 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Feature;

use App\Actions\Production\Artefact\StoreArtefact;
use App\Actions\Production\ArtefactDepartment\StoreArtefactDepartment;
use App\Actions\Production\Artefact\SetArtefactsRecipe;
use App\Actions\Production\Artisan\AttachArtisan;
use App\Actions\Production\Artisan\DetachArtisan;
use App\Actions\Production\Artisan\ToggleArtisanInRoster;
use App\Actions\HumanResources\Employee\StoreEmployee;
use App\Models\HumanResources\Employee;
use App\Actions\Production\JobOrder\ConfirmJobOrder;
use App\Actions\Production\JobOrder\StoreJobOrder;
use App\Actions\Production\ManufactureTask\StoreManufactureTask;
use App\Actions\Production\Artefact\AttachManufactureTaskToArtefact;
use App\Actions\Production\JobOrderItem\StoreJobOrderItem;
use App\Actions\Production\ManufactureTaskSession\CloseManufactureTaskSession;
use App\Actions\Production\ManufactureTaskSession\StartManufactureTaskSession;
use App\Actions\Production\ManufactureTaskSession\VoidManufactureTaskSession;
use App\Enums\Production\JobOrderItemTask\JobOrderItemTaskStateEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum;
use App\Actions\Production\Production\StoreProduction;
use App\Actions\Production\RawMaterial\StoreRawMaterial;
use App\Enums\Production\ManufactureTask\ManufactureTaskOperativeRewardAllowanceTypeEnum;
use App\Enums\Production\ManufactureTask\ManufactureTaskOperativeRewardTermsEnum;
use App\Enums\Production\RawMaterial\RawMaterialStateEnum;
use App\Enums\Production\RawMaterial\RawMaterialTypeEnum;
use App\Enums\Production\RawMaterial\RawMaterialUnitEnum;
use App\Models\Production\Artefact;
use App\Models\Production\ManufactureTaskSession;
use App\Models\Production\ManufactureTask;
use App\Models\Production\Production;
use App\Models\Production\RawMaterial;
use Config;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

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
        'inertia.pages.paths',
        [resource_path('js/Pages/Grp')]
    );
    actingAs($this->guest->getUser());

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
            ->where('breadcrumbs', fn ($breadcrumbs) => collect($breadcrumbs)->last()['simple']['route']['name'] === 'grp.org.productions.show.artisans.payroll');
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
    $jobOrder = StoreJobOrder::make()->action($this->production, []);
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

test('job order prints a job list pdf', function () {
    $jobOrder = StoreJobOrder::make()->action($this->production, []);
    get(route('grp.org.productions.show.operations.job-orders.pdf', [
        $this->organisation->slug,
        $this->production->slug,
        $jobOrder->slug,
    ]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

test('payroll csv export aggregates closed sessions with snapshotted rates', function () {
    $this->artefact->manufactureTasks()->sync([
        $this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1],
    ]);
    $jobOrder = StoreJobOrder::make()->action($this->production, []);
    $item     = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 5]);
    ConfirmJobOrder::make()->action($jobOrder);
    CloseManufactureTaskSession::make()->action(
        StartManufactureTaskSession::make()->action($this->guest->getUser(), $item->tasks()->first()),
        ['quantity_made' => 5]
    );

    $response = get(route('grp.org.productions.show.artisans.payroll.export', [
        $this->organisation->slug,
        $this->production->slug,
        'from'     => now()->toDateString(),
        'to'       => now()->toDateString(),
        'detailed' => 1,
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
    $this->artefact->manufactureTasks()->sync([
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
    $this->artefact->manufactureTasks()->sync([
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
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Org/Production/Artisans')
        ->has('period')
        ->has('manufacture_tasks')
        ->has('artisans.0', fn (AssertableInertia $page) => $page
            ->has('worker')
            ->has('number_sessions')
            ->has('earned')
            ->has('jobs')
            ->etc()));

    $job = collect(collect($response->viewData('page')['props']['artisans'])->firstWhere('user_id', $this->guest->getUser()->id)['jobs'])
        ->firstWhere('job_order_item_id', $jobOrderItem->id);
    expect($job['job_order_reference'])->toBe($jobOrder->reference)
        ->and($job['steps'][0]['task_name'])->toBe($this->manufactureTask->name)
        ->and($job['steps'][0]['quantity_made'])->toEqual(5)
        ->and($job['steps'][0]['sessions'])->toHaveCount(1);
});

test('performance breakdown groups a job with steps from two different operators', function () {
    $secondTask = stepTestManufactureTask($this->production);

    $this->artefact->manufactureTasks()->sync([
        $this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1],
        $secondTask->id            => ['position' => 2, 'units_per_artefact' => 1],
    ]);

    $jobOrder     = StoreJobOrder::make()->action($this->production, []);
    $jobOrderItem = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 5]);
    ConfirmJobOrder::make()->action($jobOrder);

    $firstUser  = $this->guest->getUser();
    $secondUser = stepTestSecondOperator($this->group);

    [$firstTask, $lastTask] = $jobOrderItem->tasks()->orderBy('position')->get();

    CloseManufactureTaskSession::make()->action(
        StartManufactureTaskSession::make()->action($firstUser, $firstTask),
        ['quantity_made' => 5]
    );
    CloseManufactureTaskSession::make()->action(
        StartManufactureTaskSession::make()->action($secondUser, $lastTask),
        ['quantity_made' => 5]
    );

    $props = get(route('grp.org.productions.show.artisans.index', [
        $this->organisation->slug,
        $this->production->slug,
        'from' => now()->toDateString(),
        'to'   => now()->toDateString(),
    ]))->viewData('page')['props'];

    $firstUsersJob = collect(collect($props['artisans'])->firstWhere('user_id', $firstUser->id)['jobs'])->firstWhere('job_order_item_id', $jobOrderItem->id);
    expect($firstUsersJob['job_order_reference'])->toBe($jobOrder->reference)
        ->and(collect($firstUsersJob['steps'])->pluck('task_name')->all())->toBe([$this->manufactureTask->name]);

    $filteredProps = get(route('grp.org.productions.show.artisans.index', [
        $this->organisation->slug,
        $this->production->slug,
        'from'                => now()->toDateString(),
        'to'                  => now()->toDateString(),
        'manufacture_task_id' => $secondTask->id,
    ]))->viewData('page')['props'];

    $filteredArtisans = collect($filteredProps['artisans']);
    expect($filteredArtisans->pluck('user_id')->all())->toContain($secondUser->id)
        ->and($filteredArtisans->flatMap(fn ($artisan) => $artisan['jobs'])->flatMap(fn ($job) => $job['steps'])->pluck('manufacture_task_id')->unique()->values()->all())
        ->toBe([$secondTask->id]);
});

test('performance breakdown keeps two artefacts of one job order apart and takes breaks out of the hours', function () {
    $otherArtefact = Artefact::where('production_id', $this->production->id)->where('code', 'PERF-2')->first()
        ?? StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'PERF-2', 'name' => 'Second artefact']);
    $sameNamedTask = stepTestManufactureTask($this->production, 'SAMENAME', $this->manufactureTask->name);
    $this->artefact->manufactureTasks()->sync([
        $this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1],
        $sameNamedTask->id         => ['position' => 2, 'units_per_artefact' => 1],
    ]);
    $otherArtefact->manufactureTasks()->sync([$this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1]]);

    $jobOrder  = StoreJobOrder::make()->action($this->production, []);
    $firstItem = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 4]);
    $otherItem = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $otherArtefact->id, 'quantity' => 6]);
    ConfirmJobOrder::make()->action($jobOrder);

    $user = $this->guest->getUser();
    foreach ($firstItem->tasks()->orderBy('position')->get() as $step) {
        CloseManufactureTaskSession::make()->action(StartManufactureTaskSession::make()->action($user, $step), ['quantity_made' => 4]);
    }
    $session = CloseManufactureTaskSession::make()->action(StartManufactureTaskSession::make()->action($user, $otherItem->tasks()->first()), ['quantity_made' => 6]);
    $session->update(['started_at' => $session->ended_at->copy()->subHours(2), 'break_minutes' => 30]);

    $props = get(route('grp.org.productions.show.artisans.index', [
        $this->organisation->slug,
        $this->production->slug,
        'from' => now()->toDateString(),
        'to'   => now()->toDateString(),
    ]))->viewData('page')['props'];

    $jobs      = collect(collect($props['artisans'])->firstWhere('user_id', $user->id)['jobs']);
    $firstJob  = $jobs->firstWhere('job_order_item_id', $firstItem->id);
    $otherJob  = $jobs->firstWhere('job_order_item_id', $otherItem->id);

    expect($firstJob['artefact_code'])->toBe($this->artefact->code)
        ->and(collect($firstJob['steps'])->pluck('manufacture_task_id')->sort()->values()->all())->toBe(collect([$this->manufactureTask->id, $sameNamedTask->id])->sort()->values()->all())
        ->and(collect($firstJob['steps'])->pluck('quantity_made')->all())->toEqual([4, 4])
        ->and($otherJob['artefact_code'])->toBe($otherArtefact->code)
        ->and($otherJob['job_order_reference'])->toBe($jobOrder->reference)
        ->and($otherJob['steps'][0]['quantity_made'])->toEqual(6)
        ->and($otherJob['steps'][0]['hours'])->toEqual(1.5)
        ->and($otherJob['hours'])->toEqual(1.5);

    $this->artefact->manufactureTasks()->sync([$this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1]]);
});

describe('production reward pay bands', function () {
    beforeEach(function () {
        $this->artefact->manufactureTasks()->sync([
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
            $exportArtefact = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'EXPRTA1', 'name' => 'Export artefact']);
        }
        $exportArtefact->manufactureTasks()->sync([
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

    test('maintenance pays band 2 even for an hourly artisan and stays out of targets', function () {
        $this->payBandJobOrderItemTask->manufactureTask->update(['standard_rate' => 152]);
        $session = makePayBandSession(
            $this->payBandJobOrderItemTask,
            0,
            2,
            0,
            \App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionActivityTypeEnum::MAINTENANCE->value
        );
        $session->update(['artisan_pay_type' => \App\Enums\Production\Artisan\ArtisanPayTypeEnum::HOURLY]);

        $session = \App\Actions\Production\ManufactureTaskSession\CalculateManufactureTaskSessionPay::run($session);

        expect($session->band_code)->toBe('2')
            ->and((float) $session->hours)->toBe(2.0)
            ->and((float) $session->hourly_rate)->toBe(14.0)
            ->and((float) $session->pay)->toBe(28.0)
            ->and((float) $session->bonus)->toBe(2.58)
            ->and($session->units_per_hour)->toBeNull();
    });

    test('manufacture floor shows live band feedback when the task has a standard rate', function () {
        $this->payBandJobOrderItemTask->manufactureTask->update(['standard_rate' => 152]);
        $this->production->update(['settings' => \Illuminate\Support\Arr::except($this->production->settings ?? [], 'show_pay_bands_to_artisans')]);

        \App\Actions\Production\ManufactureTaskSession\StartManufactureTaskSession::make()->action(
            auth()->user(),
            $this->payBandJobOrderItemTask
        );

        get(route('grp.org.productions.show.floor', [$this->organisation->slug, $this->production->slug]))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('open_session.band_feedback', null));

        $payroll = route('grp.org.productions.show.artisans.payroll', [$this->organisation->slug, $this->production->slug]);
        get($payroll)->assertInertia(fn (AssertableInertia $page) => $page->where('show_pay_bands_to_artisans', false));
        post(route('grp.org.productions.show.artisans.payroll.pay_bands_visible', [$this->organisation->slug, $this->production->slug]), ['show_pay_bands_to_artisans' => true])
            ->assertRedirect();
        get($payroll)->assertInertia(fn (AssertableInertia $page) => $page->where('show_pay_bands_to_artisans', true));

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
        $unbandedArtefact = StoreArtefact::make()->action($unbandedProduction, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'NBA1', 'name' => 'No band artefact']);
        $unbandedArtefact->manufactureTasks()->sync([
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
                'activity_type' => \App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionActivityTypeEnum::MACHINE_DOWN->value,
            ])
        )->toThrow(\Illuminate\Validation\ValidationException::class);

        $closed = \App\Actions\Production\ManufactureTaskSession\CloseManufactureTaskSession::make()->action($session, [
            'quantity_made'         => 0,
            'activity_type'         => \App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionActivityTypeEnum::MACHINE_DOWN->value,
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

    test('a recipe step target overrides the task target, sets the pay band and is snapshotted when the session closes', function () {
        $this->manufactureTask->update(['standard_rate' => 10000]);

        SetArtefactsRecipe::make()->action($this->production, [
            'artefacts' => [$this->artefact->id],
            'steps'     => [[
                'manufacture_task_id' => $this->manufactureTask->id,
                'position'            => 1,
                'units_per_artefact'  => 1,
                'standard_rate'       => 152,
            ]],
        ]);

        $session = makePayBandSession($this->payBandJobOrderItemTask, 900, 4.9333);
        expect($session->recipeStandardRate())->toBe(152.0);

        $session = \App\Actions\Production\ManufactureTaskSession\CalculateManufactureTaskSessionPay::run($session);
        expect($session->band_code)->toBe('3');

        $openSession = makePayBandSession($this->payBandJobOrderItemTask, 0, 1);
        $openSession->update(['state' => \App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum::OPEN, 'started_at' => now()->subHour(), 'ended_at' => null]);

        $closed = CloseManufactureTaskSession::make()->action($openSession, ['quantity_made' => 200])->refresh();

        expect((float) $closed->standard_rate)->toBe(152.0)
            ->and($closed->is_under_target)->toBeFalse();
    });

    test('a step given its first target repays this week\'s sessions made without one and leaves earlier weeks alone', function () {
        $this->manufactureTask->update(['standard_rate' => null]);
        $recipe = fn (?float $standardRate) => SetArtefactsRecipe::make()->action($this->production, [
            'artefacts' => [$this->artefact->id],
            'steps'     => [[
                'manufacture_task_id' => $this->manufactureTask->id,
                'position'            => 1,
                'units_per_artefact'  => 1,
                'standard_rate'       => $standardRate,
            ]],
        ]);
        $recipe(null);

        $thisWeek = \App\Actions\Production\ManufactureTaskSession\CalculateManufactureTaskSessionPay::run(makePayBandSession($this->payBandJobOrderItemTask, 900, 4.9333));
        $lastWeek = makePayBandSession($this->payBandJobOrderItemTask, 900, 4.9333);
        $lastWeek->update(['started_at' => now()->startOfWeek()->subDays(3), 'ended_at' => now()->startOfWeek()->subDays(3)->addSeconds(17760)]);
        $lastWeek = \App\Actions\Production\ManufactureTaskSession\CalculateManufactureTaskSessionPay::run($lastWeek);

        expect($thisWeek->band_code)->toBe('0')
            ->and($lastWeek->band_code)->toBe('0');

        $recipe(152);

        $thisWeek->refresh();
        $lastWeek->refresh();

        expect((float) $thisWeek->standard_rate)->toBe(152.0)
            ->and($thisWeek->band_code)->toBe('3')
            ->and((float) $thisWeek->pay)->toBe(74.0)
            ->and((float) $thisWeek->bonus)->toBe(11.3)
            ->and($thisWeek->is_under_target)->toBeFalse()
            ->and($lastWeek->standard_rate)->toBeNull()
            ->and($lastWeek->band_code)->toBe('0')
            ->and((float) $lastWeek->bonus)->toBe(0.0);
    });

    test('a short session well above a low step target is not flagged', function () {
        AttachManufactureTaskToArtefact::make()->action($this->artefact, [
            'manufacture_task_id' => $this->manufactureTask->id,
            'position'            => 1,
            'units_per_artefact'  => 1,
            'standard_rate'       => 20,
        ]);

        $openSession = makePayBandSession($this->payBandJobOrderItemTask, 0, 1);
        $openSession->update(['state' => \App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum::OPEN, 'started_at' => now()->subMinute(), 'ended_at' => null, 'break_minutes' => 0]);

        $closed = CloseManufactureTaskSession::make()->action($openSession, ['quantity_made' => 1])->refresh();

        expect((float) $closed->standard_rate)->toBe(20.0)
            ->and($closed->paidHours())->toBeLessThan(0.1)
            ->and($closed->is_under_target)->toBeFalse();
    });

    test('a session closed below its step target is flagged and a manager logs the reason', function () {
        AttachManufactureTaskToArtefact::make()->action($this->artefact, [
            'manufacture_task_id' => $this->manufactureTask->id,
            'position'            => 1,
            'units_per_artefact'  => 1,
            'standard_rate'       => 152,
        ]);

        $openSession = makePayBandSession($this->payBandJobOrderItemTask, 0, 1);
        $openSession->update(['state' => \App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum::OPEN, 'started_at' => now()->subHour(), 'ended_at' => null]);

        $closed = CloseManufactureTaskSession::make()->action($openSession, ['quantity_made' => 40])->refresh();

        expect($closed->is_under_target)->toBeTrue()
            ->and($closed->band_code)->toBe('0');

        $reviewed = \App\Actions\Production\ManufactureTaskSession\ReviewUnderTargetManufactureTaskSession::make()->action($closed, auth()->user(), [
            'under_target_reason' => 'machine_breakdown',
            'under_target_note'   => 'Shrink tunnel down 30 minutes',
        ]);

        expect($reviewed->under_target_reason)->toBe(\App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionUnderTargetReasonEnum::MACHINE_BREAKDOWN)
            ->and($reviewed->under_target_note)->toBe('Shrink tunnel down 30 minutes')
            ->and($reviewed->under_target_reviewed_by)->toBe(auth()->user()->id)
            ->and($reviewed->under_target_reviewed_at)->not->toBeNull();

        $onTarget = makePayBandSession($this->payBandJobOrderItemTask, 200, 1);

        expect(fn () => \App\Actions\Production\ManufactureTaskSession\ReviewUnderTargetManufactureTaskSession::make()->action($onTarget, auth()->user(), [
            'under_target_reason' => 'other',
        ]))->toThrow(\Illuminate\Validation\ValidationException::class);
    });

    test('an hourly artisan gets no tier, bonus or under-target flag, and their sessions this week follow the pay type', function () {
        AttachManufactureTaskToArtefact::make()->action($this->artefact, [
            'manufacture_task_id' => $this->manufactureTask->id,
            'position'            => 1,
            'units_per_artefact'  => 1,
            'standard_rate'       => 152,
        ]);
        $this->manufactureTask->update(['operative_reward_terms' => 'above_lower_limit']);

        $modelData                    = Employee::factory()->make(['organisation_id' => $this->organisation->id])->toArray();
        $modelData['worker_number']   = 'W'.rand(1000, 9999);
        $modelData['alias']           = 'Alias '.rand(1000, 9999);
        $modelData['type']            = \App\Enums\HumanResources\Employee\EmployeeTypeEnum::EMPLOYEE;
        $modelData['employment_type'] = \App\Enums\HumanResources\Employee\EmploymentTypeEnum::FULL_TIME;
        $modelData['state']           = \App\Enums\HumanResources\Employee\EmployeeStateEnum::WORKING;
        $artisan                      = StoreEmployee::make()->action($this->organisation, $modelData);

        $closeOpen = function (float $quantityMade) use ($artisan) {
            $session = makePayBandSession($this->payBandJobOrderItemTask, 0, 1);
            $session->update(['state' => \App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum::OPEN, 'employee_id' => $artisan->id, 'started_at' => now()->subHour(), 'ended_at' => null]);

            return CloseManufactureTaskSession::make()->action($session, ['quantity_made' => $quantityMade])->refresh();
        };

        $pieceRate = $closeOpen(40);
        expect($artisan->artisan_pay_type)->toBe(\App\Enums\Production\Artisan\ArtisanPayTypeEnum::PIECE_RATE)
            ->and($pieceRate->is_under_target)->toBeTrue()
            ->and($pieceRate->operative_reward_terms)->toBe('above_lower_limit');

        \App\Actions\Production\Artisan\SetArtisanPayType::make()->action($this->production, $artisan, ['artisan_pay_type' => 'hourly']);

        $pieceRate->refresh();
        expect($artisan->refresh()->artisan_pay_type)->toBe(\App\Enums\Production\Artisan\ArtisanPayTypeEnum::HOURLY)
            ->and($pieceRate->artisan_pay_type)->toBe(\App\Enums\Production\Artisan\ArtisanPayTypeEnum::HOURLY)
            ->and($pieceRate->is_under_target)->toBeFalse()
            ->and($pieceRate->operative_reward_terms)->toBe('never')
            ->and((float) $pieceRate->task_work_cost)->toBe(0.0);

        $fast = $closeOpen(900);
        expect($fast->artisan_pay_type)->toBe(\App\Enums\Production\Artisan\ArtisanPayTypeEnum::HOURLY)
            ->and($fast->band_code)->toBe('0')
            ->and((float) $fast->bonus)->toBe(0.0)
            ->and($fast->is_under_target)->toBeFalse()
            ->and((float) $fast->standard_rate)->toBe(152.0)
            ->and((float) $fast->units_per_hour)->toBeGreaterThan(152.0)
            ->and($fast->operative_reward_terms)->toBe('never');

        $development = makePayBandSession($this->payBandJobOrderItemTask, 0, 2, 0, \App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionActivityTypeEnum::DEVELOPMENT->value);
        $development->update(['artisan_pay_type' => \App\Enums\Production\Artisan\ArtisanPayTypeEnum::HOURLY]);
        $development = \App\Actions\Production\ManufactureTaskSession\CalculateManufactureTaskSessionPay::run($development);
        expect($development->band_code)->toBe('D')
            ->and((float) $development->hourly_rate)->toBe(13.30);

        $artisans = collect(get(route('grp.org.productions.show.artisans.index', [$this->organisation->slug, $this->production->slug, 'artisan_pay_type' => 'hourly']))
            ->viewData('page')['props']['artisans']);
        $sessions = $artisans->pluck('jobs')->flatten(1)->pluck('steps')->flatten(1)->pluck('sessions')->flatten(1);
        expect($sessions->pluck('id'))->toContain($fast->id)
            ->and($sessions->every(fn (array $session) => $session['is_hourly']))->toBeTrue();

        $labels  = \App\Enums\Production\Artisan\ArtisanPayTypeEnum::labels();
        $history = $artisans->firstWhere('pay_type_route.parameters.employee', $artisan->id)['pay_type_history'];
        expect($history)->toHaveCount(1)
            ->and($history[0]['from'])->toBe($labels['piece_rate'])
            ->and($history[0]['to'])->toBe($labels['hourly'])
            ->and($history[0]['effective_from'])->toBe(now()->startOfWeek()->toDateString());
    });

    test('a guru gets the guru rate for every paid hour while a guru, at the rate of the day, in the payroll export', function () {
        \App\Models\Production\ManufacturePayBand::query()->firstOrCreate(
            ['production_id' => $this->production->id, 'code' => 'GURU'],
            [
                'group_id'          => $this->production->group_id,
                'organisation_id'   => $this->production->organisation_id,
                'name'              => 'Guru bonus',
                'hourly_rate'       => 1.00,
                'target_multiplier' => null,
                'effective_from'    => now()->subYear(),
            ]
        );

        $modelData                    = Employee::factory()->make(['organisation_id' => $this->organisation->id])->toArray();
        $modelData['worker_number']   = 'W'.rand(1000, 9999);
        $modelData['alias']           = 'Alias '.rand(1000, 9999);
        $modelData['type']            = \App\Enums\HumanResources\Employee\EmployeeTypeEnum::EMPLOYEE;
        $modelData['employment_type'] = \App\Enums\HumanResources\Employee\EmploymentTypeEnum::FULL_TIME;
        $modelData['state']           = \App\Enums\HumanResources\Employee\EmployeeStateEnum::WORKING;
        $guru                         = StoreEmployee::make()->action($this->organisation, $modelData);

        $this->patch(route('grp.org.productions.show.artisans.guru.update', [$this->organisation->slug, $this->production->slug, $guru->id]), [
            'guru_since' => now()->subDay()->toDateString(),
            'guru_until' => null,
        ])->assertRedirect();
        expect($guru->refresh()->guru_since->toDateString())->toBe(now()->subDay()->toDateString())
            ->and($guru->guru_until)->toBeNull();

        $worked = makePayBandSession($this->payBandJobOrderItemTask, 0, 2.5, 30, \App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionActivityTypeEnum::DEVELOPMENT->value);
        $worked->update(['employee_id' => $guru->id]);
        $worked = \App\Actions\Production\ManufactureTaskSession\CalculateManufactureTaskSessionPay::run($worked);

        $leftOpen = makePayBandSession($this->payBandJobOrderItemTask, 0, 3, 0, \App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionActivityTypeEnum::DEVELOPMENT->value);
        $leftOpen->update(['employee_id' => $guru->id, 'left_open_after_shift_at' => $leftOpen->started_at->clone()->addHour()]);
        \App\Actions\Production\ManufactureTaskSession\CalculateManufactureTaskSessionPay::run($leftOpen);

        $guruTotals = function () use ($worked) {
            $rows = collect((new \App\Exports\Production\ManufacturePayrollExport($this->production, $worked->ended_at->clone()->subDay(), $worked->ended_at->clone()->addDay()))->array())
                ->filter(fn ($row) => $row[1] === $this->manufactureTask->code && $row[15] === 'DG');

            expect($rows->every(fn ($row) => $row[23] === round((float) $row[17] + $row[22], 2)))->toBeTrue();

            return [round($rows->sum(fn ($row) => $row[21]), 4), round($rows->sum(fn ($row) => $row[22]), 2)];
        };

        expect((float) $worked->hours)->toBe(2.5)
            ->and($guruTotals())->toBe([2.5, 2.5]);

        $leftOpen->update(['shift_end_resolved_at' => now()]);
        expect($guruTotals())->toBe([5.5, 5.5]);

        \App\Models\Production\ManufacturePayBand::query()->create([
            'group_id'        => $this->production->group_id,
            'organisation_id' => $this->production->organisation_id,
            'production_id'   => $this->production->id,
            'code'            => 'GURU',
            'name'            => 'Guru bonus',
            'hourly_rate'     => 2.00,
            'effective_from'  => $leftOpen->ended_at->clone()->addMinute(),
        ]);
        expect($guruTotals())->toBe([5.5, 5.5]);

        \App\Actions\Production\Artisan\SetArtisanGuru::make()->action($this->production, $guru, ['guru_since' => now()->subDays(10)->toDateString(), 'guru_until' => now()->subDays(2)->toDateString()]);
        expect($guruTotals())->toBe([0.0, 0.0]);
    });

    test('hours on a product in development go to the development band, guru dev for a guru, with no target check or manager pin', function () {
        $this->manufactureTask->update(['standard_rate' => null, 'is_piece_rate' => true]);
        $this->artefact->manufactureTasks()->updateExistingPivot($this->manufactureTask->id, ['standard_rate' => null]);

        $modelData                    = Employee::factory()->make(['organisation_id' => $this->organisation->id])->toArray();
        $modelData['worker_number']   = 'W'.rand(1000, 9999);
        $modelData['alias']           = 'Alias '.rand(1000, 9999);
        $modelData['type']            = \App\Enums\HumanResources\Employee\EmployeeTypeEnum::EMPLOYEE;
        $modelData['employment_type'] = \App\Enums\HumanResources\Employee\EmploymentTypeEnum::FULL_TIME;
        $modelData['state']           = \App\Enums\HumanResources\Employee\EmployeeStateEnum::WORKING;
        $artisan                      = StoreEmployee::make()->action($this->organisation, $modelData);
        \App\Actions\Production\Artisan\SetArtisanPayType::make()->action($this->production, $artisan->refresh(), ['artisan_pay_type' => 'hourly']);

        $closeOpen = function () use ($artisan) {
            $session = makePayBandSession($this->payBandJobOrderItemTask, 0, 1);
            $session->update(['state' => \App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum::OPEN, 'employee_id' => $artisan->id, 'started_at' => now()->subHour(), 'ended_at' => null]);

            try {
                return CloseManufactureTaskSession::make()->action($session, ['quantity_made' => 30])->refresh();
            } catch (\Illuminate\Validation\ValidationException $e) {
                $session->delete();
                throw $e;
            }
        };
        $setInDevelopment = fn (bool $isInDevelopment) => $this->patch(
            route('grp.models.production.artefacts.update', [$this->production->id, $this->artefact->id]),
            ['is_in_development' => $isInDevelopment]
        )->assertSessionHasNoErrors();

        expect($closeOpen)->toThrow(\Illuminate\Validation\ValidationException::class, 'a step with no target, by someone paid by the hour: a manager must confirm it');

        $setInDevelopment(true);
        expect($this->artefact->refresh()->is_in_development)->toBeTrue();

        $development = $closeOpen();
        expect($development->activity_type)->toBe(\App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionActivityTypeEnum::DEVELOPMENT)
            ->and($development->band_code)->toBe('D')
            ->and((float) $development->hourly_rate)->toBe(13.30)
            ->and($development->is_under_target)->toBeFalse()
            ->and($development->artefactCode())->toBe($this->artefact->code);

        \App\Actions\Production\Artisan\SetArtisanGuru::make()->action($this->production, $artisan, ['guru_since' => now()->subDay()->toDateString(), 'guru_until' => null]);
        $guruDevelopment = $closeOpen();
        expect($guruDevelopment->band_code)->toBe('DG')
            ->and((float) $guruDevelopment->hourly_rate)->toBe(15.00);

        $setInDevelopment(false);
        expect($closeOpen)->toThrow(\Illuminate\Validation\ValidationException::class, 'a step with no target, by someone paid by the hour: a manager must confirm it');

        expect(\App\Actions\Production\ManufactureTaskSession\CalculateManufactureTaskSessionPay::run($guruDevelopment)->band_code)->toBe('DG');
    });

    test('job lines that share a step are made as one combined batch and the quantity and time are shared back to each line', function () {
        $suffix    = rand(10000, 99999);
        $artefacts = collect(['CMB-A', 'CMB-B'])->map(function (string $code) use ($suffix) {
            $artefact = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => $code.$suffix, 'name' => 'Combined '.$code]);
            $artefact->manufactureTasks()->sync([]);
            AttachManufactureTaskToArtefact::make()->action($artefact, [
                'manufacture_task_id' => $this->manufactureTask->id,
                'position'            => 1,
                'units_per_artefact'  => 1,
                'standard_rate'       => 21,
            ]);

            return $artefact->refresh();
        });

        $tasks = $artefacts->map(function (Artefact $artefact) {
            $jobOrder = StoreJobOrder::make()->action($this->production, []);
            $item     = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $artefact->id, 'quantity' => 100]);
            ConfirmJobOrder::make()->action($jobOrder);

            return $item->tasks()->first();
        });
        [$taskA, $taskB] = $tasks->all();

        $combineUrl = route('grp.models.production.combined_task.store', ['production' => $this->production->id]);
        \Pest\Laravel\post($combineUrl, ['job_order_item_task_ids' => [$taskA->id]])->assertSessionHasErrors('job_order_item_task_ids');
        \Pest\Laravel\post($combineUrl, ['job_order_item_task_ids' => [$taskA->id, $taskB->id]])->assertSessionHasNoErrors();
        \Pest\Laravel\post($combineUrl, ['job_order_item_task_ids' => [$taskA->id, $taskB->id]])->assertSessionHasErrors('job_order_item_task_ids');

        expect($taskA->refresh()->combined_task_id)->toBe($taskA->id)
            ->and($taskB->refresh()->combined_task_id)->toBe($taskA->id);

        $floorTasks = collect(get(route('grp.org.productions.show.floor', [$this->organisation->slug, $this->production->slug]))
            ->viewData('page')['props']['tasks'])->whereIn('id', [$taskA->id, $taskB->id]);
        expect($floorTasks)->toHaveCount(1)
            ->and($floorTasks->first()['quantity_required'])->toBe(200.0)
            ->and(collect($floorTasks->first()['combined'])->pluck('id')->all())->toBe([$taskA->id, $taskB->id])
            ->and($floorTasks->first()['separate_route'])->not->toBeNull();

        $session = StartManufactureTaskSession::make()->action($this->guest->getUser(), $taskB);
        expect($session->job_order_item_task_id)->toBe($taskA->id)
            ->and($session->is_combined)->toBeTrue()
            ->and($taskB->refresh()->state)->toBe(JobOrderItemTaskStateEnum::IN_PROGRESS);

        expect(fn () => \App\Actions\Production\JobOrderItemTask\SeparateJobOrderItemTasks::make()->action($taskA))
            ->toThrow(\Illuminate\Validation\ValidationException::class);

        $session->update(['started_at' => now()->subHours(8)]);
        $closed = CloseManufactureTaskSession::make()->action($session, ['quantity_made' => 200])->refresh();

        expect((float) $closed->quantity_made)->toBe(200.0)
            ->and((float) $closed->standard_rate)->toBe(21.0)
            ->and($closed->is_under_target)->toBeFalse()
            ->and($closed->band_code)->toBe('3')
            ->and((float) $closed->pay)->toBe(120.0)
            ->and($closed->shares->pluck('quantity_made', 'job_order_item_task_id')->map(fn ($quantity) => (float) $quantity)->all())->toBe([$taskA->id => 100.0, $taskB->id => 100.0])
            ->and((float) $taskA->refresh()->quantity_made)->toBe(100.0)
            ->and((float) $taskB->refresh()->quantity_made)->toBe(100.0)
            ->and($taskA->state)->toBe(JobOrderItemTaskStateEnum::DONE)
            ->and($taskB->state)->toBe(JobOrderItemTaskStateEnum::DONE);

        $item = collect(get(route('grp.org.productions.show.operations.job-orders.show', [$this->organisation->slug, $this->production->slug, $taskB->jobOrder->slug]))
            ->viewData('page')['props']['items'])->first();
        expect($item['tasks'][0]['combined_with'])->toBe($artefacts[0]->code.' ('.$taskA->jobOrder->reference.')');

        \App\Actions\Production\ManufactureTaskSession\VoidManufactureTaskSession::make()->action($closed);
        expect((float) $taskA->refresh()->quantity_made)->toBe(0.0)
            ->and((float) $taskB->refresh()->quantity_made)->toBe(0.0);

        \App\Actions\Production\JobOrderItemTask\SeparateJobOrderItemTasks::make()->action($taskA);
        expect($taskA->refresh()->combined_task_id)->toBeNull()
            ->and($taskB->refresh()->combined_task_id)->toBeNull();

        expect(CloseManufactureTaskSession::apportion(201, collect([$taskA->id => 0.5, $taskB->id => 0.5])))->toBe([$taskA->id => 101.0, $taskB->id => 100.0])
            ->and(CloseManufactureTaskSession::apportion(10.5, collect([$taskA->id => 0.5, $taskB->id => 0.5])))->toBe([$taskA->id => 5.25, $taskB->id => 5.25]);
    });

    test('changing a recipe step position keeps its target', function () {
        AttachManufactureTaskToArtefact::make()->action($this->artefact, [
            'manufacture_task_id' => $this->manufactureTask->id,
            'position'            => 1,
            'standard_rate'       => 152,
        ]);
        AttachManufactureTaskToArtefact::make()->action($this->artefact, [
            'manufacture_task_id' => $this->manufactureTask->id,
            'position'            => 2,
        ]);

        expect((float) $this->artefact->manufactureTasks()->first()->pivot->standard_rate)->toBe(152.0);
    });

    function timeEditSession(\App\Models\Production\JobOrderItemTask $task, \App\Models\SysAdmin\User $user, \Illuminate\Support\Carbon $startedAt, \Illuminate\Support\Carbon $endedAt, float $quantityMade, array $extra = []): ManufactureTaskSession
    {
        return ManufactureTaskSession::create(array_merge([
            'group_id'               => $task->group_id,
            'organisation_id'        => $task->organisation_id,
            'production_id'          => $task->production_id,
            'job_order_item_task_id' => $task->id,
            'manufacture_task_id'    => $task->manufacture_task_id,
            'user_id'                => $user->id,
            'state'                  => ManufactureTaskSessionStateEnum::CLOSED,
            'started_at'             => $startedAt,
            'ended_at'               => $endedAt,
            'quantity_made'          => $quantityMade,
            'standard_rate'          => 152,
            'activity_type'          => \App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionActivityTypeEnum::PRODUCTION,
        ], $extra));
    }

    function employeeOnDayShift(\App\Models\SysAdmin\Organisation $organisation, string $startTime = '08:00:00', string $endTime = '16:00:00'): Employee
    {
        $employee = dayPlanOperator($organisation);
        $schedule = \App\Models\HumanResources\WorkSchedule::create([
            'schedulable_type' => 'Employee',
            'schedulable_id'   => $employee->id,
            'name'             => 'Day shift',
            'type'             => 'default',
            'is_active'        => true,
        ]);
        foreach (range(1, 7) as $day) {
            $schedule->days()->create(['day_of_week' => $day, 'is_working_day' => true, 'start_time' => $startTime, 'end_time' => $endTime]);
        }

        return $employee;
    }

    test('a supervisor edits a session left open and its hours, rate and pay are worked out again', function () {
        $this->travelTo(now()->startOfWeek()->addDays(2)->setTime(18, 0));
        $this->payBandJobOrderItemTask->manufactureTask->update(['standard_rate' => 152, 'is_piece_rate' => true]);
        $operator   = stepTestSecondOperator($this->group);
        $supervisor = stepTestSecondOperator($this->group);
        $session    = timeEditSession($this->payBandJobOrderItemTask, $operator, now()->subHours(6), now()->subHours(6)->addSeconds((int) round(4.9333 * 3600)), 900);
        $session    = \App\Actions\Production\ManufactureTaskSession\CalculateManufactureTaskSessionPay::run($session);
        $started    = $session->started_at->copy();
        $ended      = $session->ended_at->copy();
        expect($session->band_code)->toBe('3');

        $edit = \App\Actions\Production\ManufactureTaskSession\EditManufactureTaskSessionTimes::make();
        $session = $edit->action($session, [
            'started_at' => $started->toIso8601String(),
            'ended_at'   => $started->copy()->addHours(5)->addMinutes(30)->toIso8601String(),
            'reason'     => 'forgot_to_stop',
        ], $supervisor)->refresh();

        expect($session->band_code)->toBe('1')
            ->and((float) $session->hours)->toBe(5.5)
            ->and((float) $session->pay)->toBe(71.5)
            ->and($session->is_under_target)->toBeFalse()
            ->and($session->original_ended_at->equalTo($ended->copy()->startOfSecond()))->toBeTrue()
            ->and($session->time_edited_by)->toBe($supervisor->id)
            ->and($session->time_edit_reason->value)->toBe('forgot_to_stop');

        $session = \App\Actions\Production\ManufactureTaskSession\EditManufactureTaskSessionTimes::make()->action($session, [
            'started_at' => $started->toIso8601String(),
            'ended_at'   => $started->copy()->addHours(10)->toIso8601String() > now()->toIso8601String() ? now()->toIso8601String() : $started->copy()->addHours(6)->toIso8601String(),
            'reason'     => 'other',
            'note'       => 'Stayed to clean the line',
        ], $supervisor)->refresh();

        expect($session->band_code)->toBe('0')
            ->and($session->is_under_target)->toBeTrue()
            ->and($session->original_ended_at->equalTo($ended->copy()->startOfSecond()))->toBeTrue()
            ->and($session->time_edit_note)->toBe('Stayed to clean the line');
    });

    test('times are refused when wrong, overlapping, own, in a closed pay week or with no note for other', function () {
        $this->travelTo(now()->startOfWeek()->addDays(2)->setTime(18, 0));
        $operator   = stepTestSecondOperator($this->group);
        $supervisor = stepTestSecondOperator($this->group);
        $session    = timeEditSession($this->payBandJobOrderItemTask, $operator, now()->subHours(8), now()->subHours(6), 100);
        timeEditSession($this->payBandJobOrderItemTask, $operator, now()->subHours(5), now()->subHours(4), 50);
        $lastWeek   = timeEditSession($this->payBandJobOrderItemTask, $operator, now()->subWeek()->subHours(3), now()->subWeek()->subHours(2), 50);
        $edit       = fn (ManufactureTaskSession $session, array $data, $editor = null, bool $sysadmin = false) => \App\Actions\Production\ManufactureTaskSession\EditManufactureTaskSessionTimes::make()->action($session, array_merge(['reason' => 'forgot_to_stop'], $data), $editor ?? $supervisor, $sysadmin);

        expect(fn () => $edit($session, ['started_at' => now()->subHours(6), 'ended_at' => now()->subHours(7)]))->toThrow(ValidationException::class, 'The end must be after the start')
            ->and(fn () => $edit($session, ['started_at' => now()->subHours(8), 'ended_at' => now()->subHours(4)->subMinutes(30)]))->toThrow(ValidationException::class, 'This overlaps')
            ->and(fn () => $edit($session, ['started_at' => now()->subHours(8), 'ended_at' => now()->subHours(7)], $operator))->toThrow(ValidationException::class, 'your own session')
            ->and(fn () => $edit($session, ['started_at' => now()->subHours(8), 'ended_at' => now()->subHours(7), 'reason' => 'other']))->toThrow(ValidationException::class)
            ->and(fn () => $edit($lastWeek, ['started_at' => now()->subWeek()->subHours(3), 'ended_at' => now()->subWeek()->subHours(2)->subMinutes(30)]))->toThrow(ValidationException::class, 'pay week of this session is closed');

        $lastWeek = $edit($lastWeek, ['started_at' => now()->subWeek()->subHours(3), 'ended_at' => now()->subWeek()->subHours(2)->subMinutes(30)], null, true);
        expect($lastWeek->refresh()->time_edited_at)->not->toBeNull();

        $tooFast = ['started_at' => now()->subHours(8), 'ended_at' => now()->subHours(8)->addMinutes(2)];
        expect(fn () => $edit($session, $tooFast))->toThrow(ValidationException::class, 'save again if these times are right');
        expect((float) $edit($session, $tooFast + ['impossible_rate_confirmed' => true])->refresh()->paidHours())->toBe(2 / 60);
    });

    test('a night shift is still the shift of the day it started after midnight', function () {
        $employee = employeeOnDayShift($this->organisation, '22:00:00', '06:00:00');
        $timezone = $employee->getEffectiveWorkSchedule()->timezone?->name ?? $this->organisation->timezone?->name ?? config('app.timezone');
        $tuesday  = \Illuminate\Support\Carbon::parse('2026-10-20 02:00:00', $timezone);

        [$start, $end] = $employee->shiftWindowOn($tuesday);
        expect($start->format('Y-m-d H:i'))->toBe('2026-10-19 22:00')
            ->and($end->format('Y-m-d H:i'))->toBe('2026-10-20 06:00');

        [$start] = $employee->shiftWindowOn($tuesday->copy()->setTime(12, 0));
        expect($start->format('Y-m-d H:i'))->toBe('2026-10-20 22:00');
    });

    test('times outside the operator shift ask to be saved again', function () {
        $this->travelTo(now()->startOfWeek()->addDays(2)->setTime(23, 0));
        $employee = employeeOnDayShift($this->organisation);
        $shiftStart = $employee->shiftWindowOn(now()->subHours(12))[0]->setTimezone(config('app.timezone'));
        $session  = timeEditSession($this->payBandJobOrderItemTask, stepTestSecondOperator($this->group), $shiftStart->copy()->addHour(), $shiftStart->copy()->addHours(3), 100, ['employee_id' => $employee->id]);
        $data     = ['started_at' => $shiftStart->copy()->subHour(), 'ended_at' => $shiftStart->copy()->addHours(3), 'reason' => 'forgot_to_start'];
        $editor   = stepTestSecondOperator($this->group);

        expect(fn () => \App\Actions\Production\ManufactureTaskSession\EditManufactureTaskSessionTimes::make()->action($session, $data, $editor))
            ->toThrow(ValidationException::class, "shift of 08:00 to 16:00");

        $session = \App\Actions\Production\ManufactureTaskSession\EditManufactureTaskSessionTimes::make()->action($session, $data + ['outside_shift_confirmed' => true], $editor);
        expect($session->refresh()->started_at->equalTo($shiftStart->copy()->subHour()))->toBeTrue();
    });

    test('preparation left running is closed at the end of the shift and production left open is flagged', function () {
        $this->travelTo(now()->startOfWeek()->addDays(2)->setTime(12, 0));
        $employee = employeeOnDayShift($this->organisation);
        [$shiftStart, $shiftEnd] = array_map(fn ($moment) => $moment->setTimezone(config('app.timezone')), $employee->shiftWindowOn(now()));
        $base = [
            'group_id'        => $this->production->group_id,
            'organisation_id' => $this->production->organisation_id,
            'production_id'   => $this->production->id,
            'employee_id'     => $employee->id,
            'state'           => ManufactureTaskSessionStateEnum::OPEN,
            'started_at'      => $shiftStart->copy()->addHour(),
        ];
        $cleaning = ManufactureTaskSession::create($base + [
            'user_id'       => stepTestSecondOperator($this->group)->id,
            'activity_type' => \App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionActivityTypeEnum::CLEANING,
        ]);
        $making = ManufactureTaskSession::create($base + [
            'user_id'                => stepTestSecondOperator($this->group)->id,
            'job_order_item_task_id' => $this->payBandJobOrderItemTask->id,
            'manufacture_task_id'    => $this->payBandJobOrderItemTask->manufacture_task_id,
            'activity_type'          => \App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionActivityTypeEnum::PRODUCTION,
        ]);

        $this->travelTo($shiftEnd->copy()->addMinutes(10));
        \App\Actions\Production\ManufactureTaskSession\StopManufactureTaskSessionsAfterShift::run();
        expect($cleaning->refresh()->state)->toBe(ManufactureTaskSessionStateEnum::OPEN);

        $this->travelTo($shiftEnd->copy()->addMinutes(31));
        $this->artisan('production:stop_sessions_after_shift')->assertExitCode(0);

        $cleaning->refresh();
        $making->refresh();
        expect($cleaning->state)->toBe(ManufactureTaskSessionStateEnum::CLOSED)
            ->and($cleaning->ended_at->equalTo($shiftEnd))->toBeTrue()
            ->and((float) $cleaning->hours)->toBe(7.0)
            ->and($cleaning->time_edit_reason->value)->toBe('closed_at_shift_end')
            ->and($making->state)->toBe(ManufactureTaskSessionStateEnum::OPEN)
            ->and($making->left_open_after_shift_at->equalTo($shiftEnd))->toBeTrue();
    });

    test('a job paused over the end of the shift closes at the pause or carries on, as the operator answers', function () {
        $this->travelTo(now()->startOfWeek()->addDays(2)->setTime(12, 0));
        $employee = employeeOnDayShift($this->organisation);
        [$shiftStart, $shiftEnd] = array_map(fn ($moment) => $moment->setTimezone(config('app.timezone')), $employee->shiftWindowOn(now()));
        $operator = stepTestSecondOperator($this->group);
        ManufactureTaskSession::where('user_id', $operator->id)->where('state', ManufactureTaskSessionStateEnum::OPEN)->update(['state' => ManufactureTaskSessionStateEnum::VOIDED]);
        \App\Models\Production\ManufactureBreak::where('user_id', $operator->id)->whereNull('ended_at')->update(['ended_at' => now(), 'minutes' => 0]);

        $openJob = fn () => ManufactureTaskSession::create([
            'group_id'               => $this->production->group_id,
            'organisation_id'        => $this->production->organisation_id,
            'production_id'          => $this->production->id,
            'employee_id'            => $employee->id,
            'user_id'                => $operator->id,
            'state'                  => ManufactureTaskSessionStateEnum::OPEN,
            'started_at'             => $shiftEnd->copy()->subHour(),
            'job_order_item_task_id' => $this->payBandJobOrderItemTask->id,
            'manufacture_task_id'    => $this->payBandJobOrderItemTask->manufacture_task_id,
            'activity_type'          => \App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionActivityTypeEnum::PRODUCTION,
        ]);
        $pauseOverShiftEnd = fn () => \App\Models\Production\ManufactureBreak::create([
            'group_id'        => $this->production->group_id,
            'organisation_id' => $this->production->organisation_id,
            'production_id'   => $this->production->id,
            'user_id'         => $operator->id,
            'employee_id'     => $employee->id,
            'planned_minutes' => null,
            'started_at'      => $shiftEnd->copy()->addMinute(),
        ]);
        $nextMorning = fn ($break) => $break->update(['ended_at' => $shiftEnd->copy()->addHours(16), 'minutes' => 16 * 60 - 1]);

        $finished = $openJob();
        $break    = $pauseOverShiftEnd();
        $this->travelTo($shiftEnd->copy()->addMinutes(31));
        \App\Actions\Production\ManufactureTaskSession\StopManufactureTaskSessionsAfterShift::run();
        expect($finished->refresh()->shiftEndPausedAt()->equalTo($break->started_at))->toBeTrue()
            ->and($finished->workedSecondsUntil($break->started_at))->toBe(61 * 60);

        $this->travelTo($shiftEnd->copy()->addHours(16)->addMinutes(10));
        $nextMorning($break);
        $finished = \App\Actions\Production\ManufactureTaskSession\CloseManufactureTaskSession::make()->action($finished, ['quantity_made' => 0, 'finished_before_shift_end' => true])->refresh();
        expect($finished->ended_at->equalTo($break->started_at))->toBeTrue()
            ->and((int) $finished->break_minutes)->toBe(0)
            ->and($finished->shift_end_answer)->toBe('finished')
            ->and($finished->shift_end_resolved_at)->not->toBeNull();

        $this->travelTo($shiftEnd->copy()->subHours(2));
        $continued = $openJob();
        $break     = $pauseOverShiftEnd();
        $this->travelTo($shiftEnd->copy()->addMinutes(31));
        \App\Actions\Production\ManufactureTaskSession\StopManufactureTaskSessionsAfterShift::run();
        $this->travelTo($shiftEnd->copy()->addHours(16)->addMinutes(10));
        $nextMorning($break);
        $continued = \App\Actions\Production\ManufactureTaskSession\ContinueManufactureTaskSessionAfterShift::make()->action($continued->refresh())->refresh();
        expect($continued->state)->toBe(ManufactureTaskSessionStateEnum::OPEN)
            ->and($continued->shift_end_answer)->toBe('continued')
            ->and($continued->shift_end_resolved_at)->not->toBeNull();
        $continued = \App\Actions\Production\ManufactureTaskSession\CloseManufactureTaskSession::make()->action($continued, ['quantity_made' => 0, 'finished_before_shift_end' => true])->refresh();
        expect($continued->ended_at->equalTo(now()))->toBeTrue()
            ->and($continued->shift_end_answer)->toBe('continued');
        \App\Models\Production\ManufactureBreak::where('user_id', $operator->id)->where('started_at', '>=', $shiftEnd->copy()->subHour())->delete();

        $this->travelTo($shiftEnd->copy()->subHours(2));
        $neverClockedOut = $openJob();
        $this->travelTo($shiftEnd->copy()->addMinutes(31));
        \App\Actions\Production\ManufactureTaskSession\StopManufactureTaskSessionsAfterShift::run();
        $this->travelTo($shiftEnd->copy()->addHours(20));
        $nextDayLunch = $pauseOverShiftEnd();
        $nextDayLunch->update(['started_at' => $shiftEnd->copy()->addHours(19), 'ended_at' => $shiftEnd->copy()->addHours(19)->addMinutes(30), 'minutes' => 30]);
        $briefBreak = $pauseOverShiftEnd();
        $briefBreak->update(['started_at' => $shiftEnd->copy()->subMinutes(10), 'ended_at' => $shiftEnd->copy()->addMinutes(5), 'minutes' => 15]);
        expect($neverClockedOut->refresh()->shiftEndPause())->toBeNull()
            ->and($neverClockedOut->shiftEndPausedAt()->equalTo($shiftEnd))->toBeTrue();
        $neverClockedOut = \App\Actions\Production\ManufactureTaskSession\ContinueManufactureTaskSessionAfterShift::make()->action($neverClockedOut->refresh())->refresh();
        expect($neverClockedOut->shift_end_answer)->toBe('continued')
            ->and($neverClockedOut->shift_end_resolved_at)->toBeNull();
        $neverClockedOut->update(['state' => ManufactureTaskSessionStateEnum::VOIDED]);
    });

    test('a tablet break left open after the shift is flagged, shows on performance and a supervisor sets its end', function () {
        $this->travelTo(now()->startOfWeek()->addDays(2)->setTime(12, 0));
        $employee = employeeOnDayShift($this->organisation);
        [, $shiftEnd] = array_map(fn ($moment) => $moment->setTimezone(config('app.timezone')), $employee->shiftWindowOn(now()));
        $operator   = stepTestSecondOperator($this->group);
        $supervisor = stepTestSecondOperator($this->group);
        \App\Models\Production\ManufactureBreak::where('production_id', $this->production->id)->whereNull('ended_at')->update(['ended_at' => now(), 'minutes' => 0]);
        $breaks = fn () => \App\Models\Production\ManufactureBreak::where('user_id', $operator->id);

        $this->travelTo($shiftEnd->copy()->subMinutes(40));
        $overran = \App\Actions\Production\ManufactureBreak\StartManufactureBreak::make()->action($operator, $this->production, ['planned_minutes' => 5]);
        $overran->update(['employee_id' => $employee->id]);
        $this->travel(11)->minutes();
        expect($overran->isOverrun(\App\Models\Production\ManufactureBreak::overrunToleranceMinutes($this->production)))->toBeTrue();
        \App\Actions\Production\ManufactureBreak\SetManufactureBreakOverrunTolerance::make()->action($this->production, ['tolerance_minutes' => 10]);
        expect(\App\Models\Production\ManufactureBreak::overrunToleranceMinutes($this->production->refresh()))->toBe(10)
            ->and($overran->isOverrun(10))->toBeFalse();
        \App\Actions\Production\ManufactureBreak\EndManufactureBreak::make()->action($overran);

        $this->travel(4)->minutes();
        $leftOpen = \App\Actions\Production\ManufactureBreak\StartManufactureBreak::make()->action($operator, $this->production, ['planned_minutes' => 20]);
        $leftOpen->update(['employee_id' => $employee->id]);

        $this->travelTo($shiftEnd->copy()->addMinutes(10));
        \App\Actions\Production\ManufactureTaskSession\StopManufactureTaskSessionsAfterShift::run();
        expect($leftOpen->refresh()->left_open_after_shift_at)->toBeNull();

        $this->travelTo($shiftEnd->copy()->addMinutes(31));
        expect(\App\Actions\Production\ManufactureTaskSession\StopManufactureTaskSessionsAfterShift::run()['breaks_flagged'])->toBe(1)
            ->and($leftOpen->refresh()->left_open_after_shift_at->equalTo($shiftEnd))->toBeTrue()
            ->and($leftOpen->ended_at)->toBeNull();

        $props = get(route('grp.org.productions.show.artisans.index', [$this->organisation->slug, $this->production->slug, 'from' => $shiftEnd->copy()->subDay()->toDateString(), 'to' => $shiftEnd->toDateString()]))
            ->assertOk()->original->getData()['page']['props'];
        $day   = collect($props['breaks'])->firstWhere('user_id', $operator->id);
        $issue = collect($props['break_issues'])->firstWhere('id', $leftOpen->id);
        expect($day['number_breaks'])->toBe(2)
            ->and($day['planned_minutes'])->toBe(25)
            ->and($day['actual_minutes'])->toBe(11 + 56)
            ->and($day['overrun_minutes'])->toBe(6 + 36)
            ->and($day['number_overrun'])->toBe(1)
            ->and($day['is_open'])->toBeTrue()
            ->and($day['times'])->toHaveCount(2)
            ->and($day['times'][0]['started_at']->equalTo($overran->started_at))->toBeTrue()
            ->and($day['times'][1]['ended_at'])->toBeNull()
            ->and($day['times'][0]['is_overrun'])->toBeFalse()
            ->and($day['times'][0]['overrun_minutes'])->toBe(6)
            ->and($day['times'][1]['is_overrun'])->toBeTrue()
            ->and($issue['correct_route']['name'])->toBe('grp.models.manufacture-break.correct_end')
            ->and($props['break_overrun_tolerance_minutes'])->toBe(10);

        $overranOnly = get(route('grp.org.productions.show.artisans.index', [$this->organisation->slug, $this->production->slug, 'from' => $shiftEnd->copy()->subDay()->toDateString(), 'to' => $shiftEnd->toDateString(), 'break_overrun' => 1]))
            ->assertOk()->original->getData()['page']['props']['breaks'];
        expect(collect($overranOnly)->firstWhere('user_id', $operator->id)['number_breaks'])->toBe(1);

        $correct = fn (array $data, $editor = null) => \App\Actions\Production\ManufactureBreak\CorrectManufactureBreakEnd::make()->action($leftOpen, $data, $editor ?? $supervisor);
        expect(fn () => $correct(['ended_at' => $leftOpen->started_at->copy()]))->toThrow(ValidationException::class, 'after the start')
            ->and(fn () => $correct(['ended_at' => now()->addHour()]))->toThrow(ValidationException::class, 'future')
            ->and(fn () => $correct(['ended_at' => $leftOpen->started_at->copy()->addMinutes(25)], $operator))->toThrow(ValidationException::class, 'your own break');

        $spanning = timeEditSession($this->payBandJobOrderItemTask, $operator, $shiftEnd->copy()->subMinutes(55), $shiftEnd->copy(), 0, ['employee_id' => $employee->id]);
        $running  = timeEditSession($this->payBandJobOrderItemTask, $operator, $shiftEnd->copy()->addMinute(), $shiftEnd->copy()->addMinute(), 0, ['employee_id' => $employee->id, 'state' => ManufactureTaskSessionStateEnum::OPEN, 'ended_at' => null, 'break_minutes' => 99]);

        $leftOpen = $correct(['ended_at' => $leftOpen->started_at->copy()->addMinutes(25)]);
        expect($spanning->refresh()->break_minutes)->toBe(11 + 25)
            ->and(round($spanning->paidHours(), 4))->toBe(round(19 / 60, 4))
            ->and($running->refresh()->break_minutes)->toBe(0);
        $running->update(['state' => ManufactureTaskSessionStateEnum::VOIDED]);
        expect($leftOpen->minutes)->toBe(25)
            ->and($leftOpen->corrected_by_user_id)->toBe($supervisor->id)
            ->and($leftOpen->original_ended_at)->toBeNull()
            ->and($breaks()->open()->count())->toBe(0);

        $issues = get(route('grp.org.productions.show.artisans.index', [$this->organisation->slug, $this->production->slug]))
            ->assertOk()->original->getData()['page']['props']['break_issues'];
        expect(collect($issues)->firstWhere('id', $leftOpen->id))->toBeNull();

        \App\Actions\Production\ManufactureBreak\SetManufactureBreakOverrunTolerance::make()->action($this->production, ['tolerance_minutes' => \App\Models\Production\ManufactureBreak::DEFAULT_OVERRUN_TOLERANCE_MINUTES]);
    });

    test('performance and payroll flag sessions with edited times and list only them on request', function () {
        $this->travelTo(now()->startOfWeek()->addDays(2)->setTime(18, 0));
        $operator = stepTestSecondOperator($this->group);
        $edited   = timeEditSession($this->payBandJobOrderItemTask, $operator, now()->subMinutes(50), now()->subMinutes(40), 10);
        timeEditSession($this->payBandJobOrderItemTask, $operator, now()->subMinutes(30), now()->subMinutes(20), 10);

        $this->patch(route('grp.models.manufacture-task-session.times', $edited->id), [
            'started_at' => now()->subMinutes(55)->toIso8601String(),
            'ended_at'   => now()->subMinutes(40)->toIso8601String(),
            'reason'     => 'forgot_to_start',
        ])->assertSessionHasNoErrors();
        expect($edited->refresh()->time_edited_by)->toBe($this->guest->getUser()->id)
            ->and($edited->started_at->equalTo(now()->subMinutes(55)))->toBeTrue();

        $response = get(route('grp.org.productions.show.artisans.index', [
            $this->organisation->slug,
            $this->production->slug,
            'from'        => now()->toDateString(),
            'to'          => now()->toDateString(),
            'time_edited' => 1,
        ]))->assertOk();

        $jobs     = collect($response->viewData('page')['props']['artisans'])->firstWhere('user_id', $operator->id)['jobs'];
        $sessions = collect($jobs)->flatMap(fn ($job) => collect($job['steps'])->flatMap(fn ($step) => $step['sessions']));
        expect($sessions->pluck('id')->all())->toBe([$edited->id])
            ->and($sessions->first()['time_edit']['reason'])->toBe('Forgot to start')
            ->and($sessions->first()['times_route'])->not->toBeNull();

        $rows = collect((new \App\Exports\Production\ManufacturePayrollExport($this->production, now()->startOfDay(), now()->endOfDay(), true))->array());
        expect($rows->sum(fn ($row) => $row[3]))->toBe($rows->sum(fn ($row) => $row[20]))
            ->and($rows->sum(fn ($row) => $row[20]))->toBeGreaterThanOrEqual(1);
    });

    test('payroll summary pays working hours at band 0, each band above it, rounds lines to add up and leaves out jobs left running', function () {
        $day      = now()->addYears(5)->addDays(rand(0, 300))->setTime(9, 0);
        $sessions = [];
        foreach ([['3', 1.3333], ['0', 1.3333], ['1', 1.3334], ['0', 10]] as $index => [$band, $hours]) {
            $sessions[] = \App\Models\Production\ManufactureTaskSession::create([
                'group_id'               => $this->payBandJobOrderItemTask->group_id,
                'organisation_id'        => $this->payBandJobOrderItemTask->organisation_id,
                'production_id'          => $this->production->id,
                'job_order_item_task_id' => $this->payBandJobOrderItemTask->id,
                'manufacture_task_id'    => $this->payBandJobOrderItemTask->manufacture_task_id,
                'user_id'                => auth()->user()->id,
                'state'                  => ManufactureTaskSessionStateEnum::CLOSED,
                'started_at'             => $day->clone()->addMinutes($index),
                'ended_at'               => $day->clone()->addMinutes($index)->addSeconds((int) round($hours * 3600)),
                'quantity_made'          => 10.33,
                'hours'                  => $hours,
                'band_code'              => $band,
                'left_open_after_shift_at' => $hours == 10 ? $day->clone()->addHours(8) : null,
            ]);
        }

        $artisan = \App\Actions\Production\ManufactureTaskSession\GetManufacturePayroll::run($this->production, $day, $day, auth()->user()->id)[0];

        expect($artisan['working_hours'])->toBe(4.0)
            ->and(round($artisan['band_0_hours'] + $artisan['band_1_hours'] + $artisan['band_3_hours'], 2))->toBe(4.0)
            ->and($artisan['band_1_hours'])->toBe(1.34)
            ->and($artisan['working_amount'])->toBe(50.84)
            ->and($artisan['band_0_amount'])->toBe(0.0)
            ->and($artisan['band_1_amount'])->toBe(0.39)
            ->and($artisan['band_3_amount'])->toBe(3.05)
            ->and($artisan['band_d_hours'])->toBe(0.0)
            ->and($artisan['total'])->toBe(54.28)
            ->and($artisan['excluded_sessions'])->toBe(1)
            ->and(collect($artisan['lines'])->where('excluded', true)->first()['hours'])->toBe(10.0);

        $response = get(route('grp.org.productions.show.artisans.payroll.export', [
            $this->organisation->slug,
            $this->production->slug,
            'from' => $day->toDateString(),
            'to'   => $day->toDateString(),
        ]))->assertOk();
        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();

        expect($csv)->toStartWith('"Worker","Working hours hours","Working hours £","Band 0 hours"')
            ->and($csv)->toContain('"4.00","50.84","1.33","0.00","1.34","0.39","0.00","0.00","1.33","3.05","0.00","0.00","0.00","0.00","0.00","0.00","54.28"');

        get(route('grp.org.productions.show.artisans.payroll', [$this->organisation->slug, $this->production->slug, 'from' => $day->toDateString(), 'to' => $day->toDateString()]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Org/Production/ManufacturePayroll')
                ->where('data.data.0.total', 54.28)
                ->where('data.data.0.excluded_sessions', 1));

        get(route('grp.org.productions.show.artisans.payroll.show', [$this->organisation->slug, $this->production->slug, auth()->user()->id, 'from' => $day->toDateString(), 'to' => $day->toDateString()]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Org/Production/ManufacturePayrollArtisan')
                ->where('summary.working_hours', 4)
                ->has('data.data', 4));
    });

    test('daily hours keeps a summer time shift at its local hours', function () {
        $tuesday = \Illuminate\Support\Carbon::parse('2026-10-20 18:00:00', 'Europe/London');
        $this->travelTo($tuesday);
        $employee = employeeOnDayShift($this->organisation);
        $employee->getEffectiveWorkSchedule()->update(['timezone_id' => \App\Models\Helpers\Timezone::where('name', 'Europe/London')->value('id')]);
        $operator = stepTestSecondOperator($this->group);
        timeEditSession($this->payBandJobOrderItemTask, $operator, $tuesday->copy()->setTime(8, 0)->utc(), $tuesday->copy()->setTime(12, 0)->utc(), 10, ['employee_id' => $employee->id]);

        get(route('grp.org.productions.show.artisans.daily_hours', [$this->organisation->slug, $this->production->slug, 'from' => '2026-10-20', 'to' => '2026-10-20']))->assertOk();

        $day = \App\Models\Production\ManufactureArtisanDay::where('employee_id', $employee->id)->sole();
        expect($day->shift_start->toIso8601String())->toBe('2026-10-20T07:00:00+00:00')
            ->and($day->shift_end->toIso8601String())->toBe('2026-10-20T15:00:00+00:00');
    });

    test('each artisan day is checked against the shift: overtime is approved or not, a short day gets a reason, a job left running is not overtime', function () {
        $friday = now()->startOfWeek()->addDays(4)->setTime(20, 0);
        $this->travelTo($friday->copy()->addDay());
        $employee = employeeOnDayShift($this->organisation);
        $employee->getEffectiveWorkSchedule()->days->each(fn ($day) => $day->breaks()->create(['start_time' => '12:30:00', 'end_time' => '13:30:00', 'is_paid' => false]));
        $timezone = $employee->organisation->timezone?->name ?? config('app.timezone');
        $operator = stepTestSecondOperator($this->group);
        $at       = fn (int $weekday, string $time) => \Illuminate\Support\Carbon::parse($friday->copy()->startOfWeek()->addDays($weekday)->toDateString().' '.$time, $timezone)->utc();
        $session  = fn (int $weekday, string $from, string $to, array $extra = []) => timeEditSession($this->payBandJobOrderItemTask, $operator, $at($weekday, $from), $at($weekday, $to), 10, ['employee_id' => $employee->id] + $extra);

        $mondayLong = $session(0, '08:00', '17:00');
        $session(1, '08:00', '14:00');
        $session(2, '08:00', '12:00');
        $session(2, '12:00', '15:30', ['job_order_item_task_id' => null, 'manufacture_task_id' => null, 'activity_type' => \App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionActivityTypeEnum::CLEANING]);
        $session(3, '08:00', '18:00', ['left_open_after_shift_at' => $at(3, '16:00')]);
        $session(4, '08:00', '12:00');
        $session(4, '12:30', '16:00');
        foreach ([0, 1, 2] as $weekday) {
            \App\Models\Production\ManufactureBreak::create([
                'group_id'        => $this->production->group_id,
                'organisation_id' => $this->production->organisation_id,
                'production_id'   => $this->production->id,
                'user_id'         => $operator->id,
                'employee_id'     => $employee->id,
                'planned_minutes' => 30,
                'minutes'         => 30,
                'started_at'      => $at($weekday, '12:00'),
                'ended_at'        => $at($weekday, '12:30'),
            ]);
        }

        $period = ['from' => $friday->copy()->startOfWeek()->toDateString(), 'to' => $friday->toDateString()];
        $days   = function () use ($employee, $period) {
            get(route('grp.org.productions.show.artisans.daily_hours', [$this->organisation->slug, $this->production->slug] + $period))->assertOk();

            return \App\Models\Production\ManufactureArtisanDay::where('employee_id', $employee->id)->orderBy('date')->get()->keyBy(fn ($day) => $day->date->dayOfWeekIso);
        };

        $week = $days();
        expect($week)->toHaveCount(5)
            ->and($week[1]->expected_minutes)->toBe(450)
            ->and($week[1]->recorded_minutes)->toBe(540)
            ->and($week[1]->status->value)->toBe('over')
            ->and($week[2]->recorded_minutes)->toBe(360)
            ->and($week[2]->status->value)->toBe('under')
            ->and($week[3]->recorded_minutes)->toBe(450)
            ->and($week[3]->status->value)->toBe('ok')
            ->and($week[3]->is_warning_open)->toBeFalse()
            ->and($week[4]->recorded_minutes)->toBe(0)
            ->and($week[4]->unchecked_running_jobs)->toBe(1)
            ->and($week[4]->status->value)->toBe('running_job')
            ->and($week[5]->recorded_minutes)->toBe(450)
            ->and($week[5]->status->value)->toBe('no_break')
            ->and($week->where('is_warning_open', true)->keys()->all())->toBe([1, 2, 4, 5]);

        get(route('grp.org.productions.show.artisans.daily_hours', [$this->organisation->slug, $this->production->slug, 'open_only' => 1] + $period))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Org/Production/ArtisanDays')
                ->where('tolerance_minutes', 30)
                ->where('data.data', fn ($rows) => collect($rows)->where('user_id', $operator->id)->pluck('status')->sort()->values()->all() === ['no_break', 'over', 'running_job', 'under']));

        $resolve = \App\Actions\Production\ManufactureArtisanDay\ResolveManufactureArtisanDay::make();
        expect(fn () => $resolve->action($week[1], $operator, ['resolution' => 'not_approved']))->toThrow(ValidationException::class)
            ->and(fn () => $resolve->action($week[1], $operator, ['resolution' => 'reason_given', 'resolution_reason' => 'sick']))->toThrow(ValidationException::class)
            ->and(fn () => $resolve->action($week[1], $operator, ['resolution' => 'checked']))->toThrow(ValidationException::class);

        $resolve->action($week[1], $operator, ['resolution' => 'not_approved', 'resolution_note' => 'Not asked to stay']);
        $resolve->action($week[2], $operator, ['resolution' => 'reason_given', 'resolution_reason' => 'sick']);
        $resolve->action($week[5], $operator, ['resolution' => 'checked', 'resolution_note' => 'Skipped lunch to leave on time']);
        $week = $days();
        expect($week[1]->is_warning_open)->toBeFalse()
            ->and($week[1]->resolved_by_user_id)->toBe($operator->id)
            ->and($week[2]->is_warning_open)->toBeFalse()
            ->and($week[2]->resolution_reason->value)->toBe('sick')
            ->and($week[5]->is_warning_open)->toBeFalse()
            ->and($week[5]->recorded_minutes)->toBe(450);

        get(route('grp.org.productions.show.artisans.payroll', [$this->organisation->slug, $this->production->slug, 'to' => $friday->copy()->addDay()->toDateString()] + $period))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('data.data', fn ($rows) => collect($rows)->firstWhere('user_id', $operator->id)['not_authorised_overtime']['minutes'] === 90
                    && collect($rows)->firstWhere('user_id', $operator->id)['working_hours'] == 30));

        $mondayLong->update(['ended_at' => $at(0, '16:45')]);
        expect($days()[1]->recorded_minutes)->toBe(525)
            ->and($days()[1]->is_warning_open)->toBeTrue();

        \App\Actions\Production\ManufactureArtisanDay\SetManufactureHoursTolerance::make()->action($this->production, $operator, ['tolerance_minutes' => 90, 'effective_from' => $period['from']]);
        $week = $days();
        expect($week[1]->tolerance_minutes)->toBe(90)
            ->and($week[1]->status->value)->toBe('ok')
            ->and($week[2]->status->value)->toBe('ok')
            ->and($week->where('is_warning_open', true)->keys()->all())->toBe([4]);
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
            ->and(\App\Models\Production\ManufacturePayBand::where('production_id', $this->production->id)->where('effective_from', now()->startOfYear())->count())->toBe(7);
    });

    test('without a file only the pay bands are seeded, with the sheet target multipliers', function () {
        \App\Models\Production\ManufacturePayBand::where('production_id', $this->production->id)->where('effective_from', now()->startOfYear())->delete();

        $this->artisan('manufacture:import-reward-sheet', [
            'production' => $this->production->slug,
        ])->assertSuccessful();

        $multipliers = \App\Models\Production\ManufacturePayBand::where('production_id', $this->production->id)
            ->where('effective_from', now()->startOfYear())
            ->pluck('target_multiplier', 'code')
            ->map(fn ($multiplier) => $multiplier === null ? null : (float) $multiplier)
            ->all();

        expect($this->rewardTask->refresh()->standard_rate)->toBeNull()
            ->and($multipliers)->toEqual(['0' => 1.0, '1' => 1.025, '2' => 1.105, '3' => 1.185, 'D' => null, 'DG' => null, 'GURU' => null]);
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

test('artisans can be attached and detached from a family and an artefact, first one is primary', function () {
    $family   = StoreArtefactDepartment::make()->action($this->production, ['code' => 'ARTS', 'name' => 'Artisan family']);
    $artefact = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'ARTS-1', 'name' => 'Artisan artefact', 'artefact_department_id' => $family->id]);

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

test('a task that is not piece rate snapshots a zero rate when its session closes', function () {
    $this->manufactureTask->update(['is_piece_rate' => false]);
    $jobOrder = StoreJobOrder::make()->action($this->production, []);
    $this->artefact->manufactureTasks()->sync([
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

test('the artisans section is hidden from foremen and mixers, and its money from floor supervisors', function () {
    $jobOrder   = StoreJobOrder::make()->action($this->production, []);
    $parameters = [$this->organisation->slug, $this->production->slug];

    $userWith = function (array $permissions) {
        $user = \App\Models\SysAdmin\User::factory()->create(['group_id' => $this->group->id]);
        $user->givePermissionTo($permissions);

        return $user;
    };

    $foreman = $userWith(["productions_operations.{$this->production->id}.edit", "productions_operations.{$this->production->id}.view"]);
    $mixer   = $userWith(["productions_operations.{$this->production->id}.prepare", "productions_operations.{$this->production->id}.view"]);

    foreach ([$foreman, $mixer] as $user) {
        actingAs($user);
        foreach (['dashboard', 'index', 'payroll', 'payroll.export'] as $page) {
            get(route("grp.org.productions.show.artisans.$page", $parameters))->assertForbidden();
        }
        get(route('grp.org.productions.show.artisans.payroll.show', [...$parameters, $user->id]))->assertForbidden();

        expect(\App\Actions\UI\Grp\Layout\GetProductionNavigation::run($this->production, $user))->not->toHaveKey('artisans');

        get(route('grp.org.productions.show.operations.job-orders.show', [...$parameters, $jobOrder->slug]))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('can_see_pay', false));
    }

    $supervisor = $userWith(["productions_operations.{$this->production->id}"]);
    $accountant = $userWith(["accounting.{$this->organisation->id}.view"]);

    $payrollMenuItem = fn ($user) => collect(\App\Actions\UI\Grp\Layout\GetProductionNavigation::run($this->production, $user)['artisans']['topMenu']['subSections'])
        ->contains('label', __('Payroll'));

    actingAs($supervisor);
    get(route('grp.org.productions.show.artisans.index', $parameters))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('can_see_pay', false)->where('artisans', fn ($artisans) => collect($artisans)->every(fn ($artisan) => $artisan['earned'] === null)));
    foreach (['payroll', 'payroll.export'] as $page) {
        get(route("grp.org.productions.show.artisans.$page", $parameters))->assertForbidden();
    }
    get(route('grp.org.productions.show.operations.job-orders.show', [...$parameters, $jobOrder->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('can_see_pay', false));
    expect($payrollMenuItem($supervisor))->toBeFalse();

    actingAs($accountant);
    get(route('grp.org.productions.show.artisans.payroll', $parameters))->assertOk();
    get(route('grp.org.productions.show.artisans.index', $parameters))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('can_see_pay', true));
    expect($payrollMenuItem($accountant))->toBeTrue();

    actingAs($this->guest->getUser());
});
