<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 25 Nov 2024 11:48:10 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Feature;

use App\Actions\Production\Artefact\StoreArtefact;
use App\Actions\Production\Artefact\SetArtefactsRecipe;
use App\Actions\HumanResources\Employee\StoreEmployee;
use App\Models\HumanResources\Employee;
use App\Actions\Production\JobOrder\ConfirmJobOrder;
use App\Actions\Production\JobOrder\StoreJobOrder;
use App\Actions\Production\ManufactureTask\StoreManufactureTask;
use App\Actions\Production\Artefact\AttachRawMaterialToRecipeStep;
use App\Models\Production\ArtefactManufactureTask;
use App\Actions\Production\JobOrderItem\StoreJobOrderItem;
use App\Actions\Production\ManufactureTaskSession\CloseManufactureTaskSession;
use App\Actions\Production\ManufactureTaskSession\StartManufactureNonProductiveSession;
use App\Actions\Production\ManufactureTaskSession\StartManufactureTaskSession;
use App\Actions\Production\ManufactureTaskSession\VoidManufactureTaskSession;
use App\Actions\Production\Production\UpdateManufacturePreparationReasons;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Enums\Production\JobOrderItemTask\JobOrderItemTaskStateEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum;
use App\Actions\Production\Production\StoreProduction;
use App\Actions\Production\RawMaterial\StoreRawMaterial;
use App\Actions\Production\RawMaterial\UpdateRawMaterial;
use App\Enums\Production\ManufactureTask\ManufactureTaskOperativeRewardAllowanceTypeEnum;
use App\Enums\Production\ManufactureTask\ManufactureTaskOperativeRewardTermsEnum;
use App\Enums\Production\RawMaterial\RawMaterialStateEnum;
use App\Enums\Production\RawMaterial\RawMaterialTypeEnum;
use App\Enums\Production\RawMaterial\RawMaterialUnitEnum;
use App\Models\Production\Artefact;
use App\Models\Production\JobOrder;
use App\Models\Production\ManufactureTaskSession;
use App\Models\Production\ManufactureTask;
use App\Models\Production\Production;
use App\Models\Production\RawMaterial;
use Config;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;

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
    $this->artefact->manufactureTasks()->sync([
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

test('a carried job gets the kg already mixed for the whole job as a done mix step, despite float drift (HELP-4235)', function () {
    $user = auth()->user();
    ManufactureTaskSession::where('user_id', $user->id)->where('state', ManufactureTaskSessionStateEnum::OPEN)
        ->update(['state' => ManufactureTaskSessionStateEnum::CLOSED, 'ended_at' => now()]);

    $suffix     = rand(10000, 99999);
    $mixTask    = StoreManufactureTask::make()->action($this->production, ['code' => 'MXC'.$suffix, 'name' => 'Mixing '.$suffix, 'is_piece_rate' => false, 'is_recorded_in_kg' => true]);
    $makingTask = StoreManufactureTask::make()->action($this->production, ['code' => 'MKC'.$suffix, 'name' => 'Making '.$suffix, 'is_piece_rate' => true]);
    $artefact   = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'ZSS'.$suffix, 'name' => 'Steamer '.$suffix]);
    $artefact->manufactureTasks()->sync([
        $mixTask->id    => ['position' => 1, 'units_per_artefact' => 0.039],
        $makingTask->id => ['position' => 2, 'units_per_artefact' => 1],
    ]);

    $jobOrder = StoreJobOrder::make()->action($this->production, []);
    $item     = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $artefact->id, 'quantity' => 2400]);
    ConfirmJobOrder::make()->action($jobOrder);
    $mix    = $item->tasks()->where('manufacture_task_id', $mixTask->id)->first();
    $making = $item->tasks()->where('manufacture_task_id', $makingTask->id)->first();

    CloseManufactureTaskSession::make()->action(StartManufactureTaskSession::make()->action($user, $mix), ['quantity_made' => 93.6]);
    CloseManufactureTaskSession::make()->action(StartManufactureTaskSession::make()->action($user, $making), ['quantity_made' => 2100, 'outcome' => 'carry_over']);

    $carriedMix = \App\Models\Production\JobOrder::where('data->carried_from', $jobOrder->id)->firstOrFail()
        ->jobOrderItems()->first()->tasks()->where('manufacture_task_id', $mixTask->id)->first();

    expect((float) $carriedMix->quantity_required)->toBe(0.0)
        ->and($carriedMix->state)->toBe(JobOrderItemTaskStateEnum::DONE);
});

test('breaks belong to the artisan, last until they are finished and only their overlap is deducted from a session', function () {
    $this->artefact->manufactureTasks()->sync([
        $this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1],
    ]);
    $jobOrder     = StoreJobOrder::make()->action($this->production, []);
    $jobOrderItem = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 10]);
    ConfirmJobOrder::make()->action($jobOrder);
    $task = $jobOrderItem->tasks()->first();
    $user = $this->guest->getUser();

    \App\Models\Production\ManufactureTaskSession::where('user_id', $user->id)->where('state', \App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum::OPEN)
        ->update(['state' => \App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum::CLOSED, 'ended_at' => now()]);
    \App\Models\Production\ManufactureBreak::where('user_id', $user->id)->open()->update(['ended_at' => now()]);
    \App\Models\Production\ManufacturePayBand::query()->firstOrCreate(
        ['production_id' => $this->production->id, 'code' => '0'],
        ['name' => 'Band 0', 'hourly_rate' => 12.71, 'group_id' => $this->production->group_id, 'organisation_id' => $this->production->organisation_id, 'effective_from' => now()->subYear()]
    );

    expect(fn () => \App\Actions\Production\ManufactureBreak\StartManufactureBreak::make()->action($user, $this->production, ['planned_minutes' => 7]))
        ->toThrow(ValidationException::class);

    $breakBetweenJobs = \App\Actions\Production\ManufactureBreak\StartManufactureBreak::make()->action($user, $this->production, ['planned_minutes' => 20]);
    expect($breakBetweenJobs->ended_at)->toBeNull();
    expect(fn () => \App\Actions\Production\ManufactureBreak\StartManufactureBreak::make()->action($user, $this->production, ['planned_minutes' => 5]))
        ->toThrow(ValidationException::class)
        ->and(fn () => StartManufactureTaskSession::make()->action($user, $task))->toThrow(ValidationException::class, 'Finish your break first')
        ->and(fn () => StartManufactureNonProductiveSession::make()->action($user, $this->production, ['activity_type' => 'cleaning']))->toThrow(ValidationException::class, 'Finish your break first');

    $this->travel(4)->minutes();
    $breakBetweenJobs = \App\Actions\Production\ManufactureBreak\EndManufactureBreak::make()->action($breakBetweenJobs);
    expect($breakBetweenJobs->minutes)->toBe(4);

    $session = StartManufactureTaskSession::make()->action($user, $task);
    $this->travel(10)->minutes();
    $breakInJob = \App\Actions\Production\ManufactureBreak\StartManufactureBreak::make()->action($user, $this->production, ['planned_minutes' => 5]);
    get(route('grp.org.productions.show.floor', [$this->organisation->slug, $this->production->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('open_break.id', $breakInJob->id)
            ->where('open_break.planned_minutes', 5)
            ->where('open_break.overrun_tolerance_minutes', \App\Models\Production\ManufactureBreak::DEFAULT_OVERRUN_TOLERANCE_MINUTES)
            ->etc());
    $this->travel(3)->minutes();
    \App\Actions\Production\ManufactureBreak\EndManufactureBreak::make()->action($breakInJob);
    expect($session->refresh()->break_minutes)->toBe(3);

    \App\Actions\Production\ManufactureBreak\StartManufactureBreak::make()->action($user, $this->production, ['planned_minutes' => 5]);
    $this->travel(47)->minutes();
    $session = CloseManufactureTaskSession::make()->action($session, ['quantity_made' => 10]);

    expect(\App\Models\Production\ManufactureBreak::where('user_id', $user->id)->open()->count())->toBe(0)
        ->and(\App\Models\Production\ManufactureBreak::where('user_id', $user->id)->latest('id')->first()->minutes)->toBe(47)
        ->and(\App\Models\Production\ManufactureBreak::where('user_id', $user->id)->latest('id')->first()->overrunMinutes())->toBe(42)
        ->and($session->break_minutes)->toBe(50)
        ->and((float) $session->hours)->toBe(round(10 / 60, 4));
    $this->travelBack();
});

test('a break clocks a clocked in artisan out and back in, and leaves one who is not clocked in alone', function () {
    $this->travelTo(now($this->organisation->timezone?->name ?? 'UTC')->setTime(9, 0));
    $employee = StoreEmployee::make()->action($this->organisation, array_merge(Employee::factory()->definition(), [
        'worker_number'   => 'BRK-'.uniqid(),
        'alias'           => 'brk'.uniqid(),
        'username'        => 'brk'.uniqid(),
        'password'        => 'secret-password',
        'state'           => \App\Enums\HumanResources\Employee\EmployeeStateEnum::WORKING,
        'type'            => \App\Enums\HumanResources\Employee\EmployeeTypeEnum::EMPLOYEE,
        'employment_type' => \App\Enums\HumanResources\Employee\EmploymentTypeEnum::FULL_TIME,
    ]));
    $user      = $employee->getUser();
    $workplace = \App\Actions\HumanResources\Workplace\StoreWorkplace::make()->action($this->organisation, [
        'name' => 'Floor '.uniqid(),
        'type' => \App\Enums\HumanResources\Workplace\WorkplaceTypeEnum::HQ,
    ]);
    $openTrackers = fn () => $employee->timeTrackers()->where('status', \App\Enums\HumanResources\TimeTracker\TimeTrackerStatusEnum::OPEN)->count();

    $notClockedIn = \App\Actions\Production\ManufactureBreak\StartManufactureBreak::make()->action($user, $this->production, ['planned_minutes' => 20]);
    $notClockedIn = \App\Actions\Production\ManufactureBreak\EndManufactureBreak::make()->action($notClockedIn);
    expect($notClockedIn->clock_out_clocking_id)->toBeNull()
        ->and($notClockedIn->clock_in_clocking_id)->toBeNull()
        ->and($employee->clockings()->count())->toBe(0);

    \App\Actions\HumanResources\Clocking\StoreClocking::make()->action($user, $workplace, $employee, []);
    $this->travel(1)->hours();

    $break = \App\Actions\Production\ManufactureBreak\StartManufactureBreak::make()->action($user, $this->production, ['planned_minutes' => 20]);
    expect($break->clock_out_clocking_id)->not->toBeNull()
        ->and($openTrackers())->toBe(0);

    $this->travel(20)->minutes();
    $break = \App\Actions\Production\ManufactureBreak\EndManufactureBreak::make()->action($break);
    $clockIn = \App\Models\HumanResources\Clocking::find($break->clock_in_clocking_id);
    expect($clockIn->workplace_id)->toBe($workplace->id)
        ->and($clockIn->clocked_at->equalTo($break->ended_at))->toBeTrue()
        ->and($openTrackers())->toBe(1)
        ->and($clockIn->timesheet->refresh()->breaks_duration)->toBe(20 * 60);

    $this->travel(1)->hours();
    $leftDuringBreak = \App\Actions\Production\ManufactureBreak\StartManufactureBreak::make()->action($user, $this->production, ['planned_minutes' => 30]);
    $this->travel(5)->minutes();
    \App\Actions\HumanResources\Clocking\StoreClocking::make()->action($user, $workplace, $employee, []);
    $this->travel(1)->minutes();
    \App\Actions\HumanResources\Clocking\StoreClocking::make()->action($user, $workplace, $employee, []);
    $this->travel(1)->hours();
    $leftDuringBreak = \App\Actions\Production\ManufactureBreak\EndManufactureBreak::make()->action($leftDuringBreak);
    expect($leftDuringBreak->clock_out_clocking_id)->not->toBeNull()
        ->and($leftDuringBreak->clock_in_clocking_id)->toBeNull()
        ->and($openTrackers())->toBe(0);
    $this->travelBack();
});

test('an hourly artisan clocking out at the kiosk pauses their job until they clock back in, and gets no timed breaks', function () {
    $this->travelTo(now($this->organisation->timezone?->name ?? 'UTC')->setTime(9, 0));
    $this->artefact->manufactureTasks()->sync([
        $this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1, 'standard_rate' => 10],
    ]);
    $jobOrder     = StoreJobOrder::make()->action($this->production, []);
    $jobOrderItem = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 10]);
    ConfirmJobOrder::make()->action($jobOrder);
    $task = $jobOrderItem->tasks()->first();

    $employee = StoreEmployee::make()->action($this->organisation, array_merge(Employee::factory()->definition(), [
        'worker_number'   => 'HRL-'.uniqid(),
        'alias'           => 'hrl'.uniqid(),
        'username'        => 'hrl'.uniqid(),
        'password'        => 'secret-password',
        'state'           => \App\Enums\HumanResources\Employee\EmployeeStateEnum::WORKING,
        'type'            => \App\Enums\HumanResources\Employee\EmployeeTypeEnum::EMPLOYEE,
        'employment_type' => \App\Enums\HumanResources\Employee\EmploymentTypeEnum::FULL_TIME,
    ]));
    $employee->update(['artisan_pay_type' => \App\Enums\Production\Artisan\ArtisanPayTypeEnum::HOURLY]);
    $task->update(['employee_id' => $employee->id]);
    $user      = $employee->getUser();
    $workplace = \App\Actions\HumanResources\Workplace\StoreWorkplace::make()->action($this->organisation, [
        'name' => 'Floor '.uniqid(),
        'type' => \App\Enums\HumanResources\Workplace\WorkplaceTypeEnum::HQ,
    ]);
    $kiosk = \App\Actions\HumanResources\ClockingMachine\StoreClockingMachine::make()->action($workplace, [
        'name' => 'Kiosk '.uniqid(),
        'type' => \App\Enums\HumanResources\ClockingMachine\ClockingMachineTypeEnum::QR_CODE->value,
    ]);
    $scan   = fn () => \App\Actions\HumanResources\Clocking\StoreClocking::make()->action($user, $kiosk, $employee, []);
    $breaks = fn () => \App\Models\Production\ManufactureBreak::where('user_id', $user->id);

    $scan();
    expect($breaks()->count())->toBe(0);

    expect(fn () => \App\Actions\Production\ManufactureBreak\StartManufactureBreak::make()->action($user, $this->production, ['planned_minutes' => 20]))
        ->toThrow(ValidationException::class);

    $session = StartManufactureTaskSession::make()->action($user, $task);
    $this->travel(30)->minutes();
    $clockOut = $scan();
    $pause    = $breaks()->open()->first();
    expect($pause->planned_minutes)->toBeNull()
        ->and($pause->clock_out_clocking_id)->toBe($clockOut->id);

    $this->travel(15)->minutes();
    $clockIn = $scan();
    expect($pause->refresh()->ended_at)->not->toBeNull()
        ->and($pause->minutes)->toBe(15)
        ->and($pause->clock_in_clocking_id)->toBe($clockIn->id)
        ->and($session->refresh()->break_minutes)->toBe(15);

    $this->travel(10)->minutes();
    $session = CloseManufactureTaskSession::make()->action($session, ['quantity_made' => 4]);
    expect($session->break_minutes)->toBe(15)
        ->and(round($session->paidHours(), 4))->toBe(round(40 / 60, 4));

    $this->travel(5)->minutes();
    $scan();
    expect($breaks()->count())->toBe(1);

    $employee->update(['artisan_pay_type' => \App\Enums\Production\Artisan\ArtisanPayTypeEnum::PIECE_RATE]);
    $this->travel(5)->minutes();
    $scan();
    StartManufactureTaskSession::make()->action($user, $task);
    $this->travel(5)->minutes();
    $scan();
    expect($breaks()->count())->toBe(1);

    $tabletBreak = \App\Actions\Production\ManufactureBreak\StartManufactureBreak::make()->action($user, $this->production, ['planned_minutes' => 5]);
    $this->travel(12)->minutes();
    $clockIn = $scan();
    expect($tabletBreak->refresh()->ended_at)->not->toBeNull()
        ->and($tabletBreak->minutes)->toBe(12)
        ->and($tabletBreak->clock_in_clocking_id)->toBe($clockIn->id);
    $this->artefact->manufactureTasks()->updateExistingPivot($this->manufactureTask->id, ['standard_rate' => null]);
    $this->travelBack();
});

test('preparation and cleaning are timed at the base rate, never overlap production and stay out of performance figures', function () {
    $this->artefact->manufactureTasks()->sync([
        $this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1],
    ]);
    $user = $this->guest->getUser();
    ManufactureTaskSession::where('user_id', $user->id)->where('state', ManufactureTaskSessionStateEnum::OPEN)
        ->update(['state' => ManufactureTaskSessionStateEnum::CLOSED, 'ended_at' => now()]);
    \App\Models\Production\ManufactureBreak::where('user_id', $user->id)->where(fn ($query) => $query->whereNull('ended_at')->orWhere('ended_at', '>', now()->subMinute()))->delete();
    $bandAttributes = ['group_id' => $this->production->group_id, 'organisation_id' => $this->production->organisation_id, 'effective_from' => now()->subYear()];
    $band0 = \App\Models\Production\ManufacturePayBand::query()->firstOrCreate(['production_id' => $this->production->id, 'code' => '0'], ['name' => 'Band 0', 'hourly_rate' => 12.71] + $bandAttributes);
    \App\Models\Production\ManufacturePayBand::query()->firstOrCreate(['production_id' => $this->production->id, 'code' => '1'], ['name' => 'Band 1', 'hourly_rate' => 13, 'target_multiplier' => 0.0001] + $bandAttributes);

    $jobOrder     = StoreJobOrder::make()->action($this->production, []);
    $jobOrderItem = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 10]);
    ConfirmJobOrder::make()->action($jobOrder);
    $task = $jobOrderItem->tasks()->first();

    expect(fn () => StartManufactureNonProductiveSession::make()->action($user, $this->production, ['activity_type' => 'production']))
        ->toThrow(ValidationException::class);

    $this->travelTo(now('UTC')->setTime(9, 0));
    $preparation = StartManufactureNonProductiveSession::make()->action($user, $this->production, ['activity_type' => 'preparation', 'job_order_id' => $jobOrder->id]);

    expect(fn () => StartManufactureTaskSession::make()->action($user, $task))->toThrow(ValidationException::class)
        ->and(fn () => StartManufactureNonProductiveSession::make()->action($user, $this->production, ['activity_type' => 'cleaning']))->toThrow(ValidationException::class);

    get(route('grp.org.productions.show.floor', [$this->organisation->slug, $this->production->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('open_session.activity.type', 'preparation')
            ->where('open_session.task.job_order_reference', $jobOrder->reference)
            ->has('non_productive.activities', 2)
            ->where('non_productive.job_orders', fn ($jobOrders) => collect($jobOrders)->contains('id', $jobOrder->id))
            ->has('open_session.preparation_reasons', count(\App\Models\Production\ManufacturePreparationReason::DEFAULT_REASONS) + 1)
            ->where('open_session.preparation_reasons.'.count(\App\Models\Production\ManufacturePreparationReason::DEFAULT_REASONS).'.is_other', true)
            ->etc());

    $this->travel(20)->minutes();
    $break = \App\Actions\Production\ManufactureBreak\StartManufactureBreak::make()->action($user, $this->production, ['planned_minutes' => 20]);
    $this->travel(10)->minutes();
    \App\Actions\Production\ManufactureBreak\EndManufactureBreak::make()->action($break);
    $this->travel(10)->minutes();
    $otherReason = \App\Models\Production\ManufacturePreparationReason::forProduction($this->production)->firstWhere('is_other', true);
    expect(fn () => CloseManufactureTaskSession::make()->action($preparation, []))->toThrow(ValidationException::class)
        ->and(fn () => CloseManufactureTaskSession::make()->action($preparation, ['preparation_reason_id' => $otherReason->id]))->toThrow(ValidationException::class);
    $malay = \App\Models\Helpers\Language::where('code', 'ms')->first() ?? \App\Models\Helpers\Language::where('code', '!=', 'en')->first();
    \App\Actions\Helpers\Translations\DetectLanguage::mock()->shouldReceive('handle')->andReturn($malay);
    \App\Actions\Helpers\Translations\Translate::mock()->shouldReceive('handle')->andReturn('Sorting the labels');
    $preparation = CloseManufactureTaskSession::make()->action($preparation, ['preparation_reason_id' => $otherReason->id, 'preparation_reason_note' => ' Menyusun label ']);
    expect($preparation->refresh()->preparation_reason_note)->toBe('Menyusun label')
        ->and($preparation->preparation_reason_note_en)->toBe('Sorting the labels')
        ->and($preparation->preparation_reason_note_language_id)->toBe($malay->id);

    expect($preparation->state)->toBe(ManufactureTaskSessionStateEnum::CLOSED)
        ->and($preparation->break_minutes)->toBe(10)
        ->and((float) $preparation->hours)->toBe(0.5)
        ->and($preparation->band_code)->toBe('0')
        ->and((float) $preparation->pay)->toBe(round(0.5 * (float) $band0->hourly_rate, 2))
        ->and((float) $preparation->bonus)->toBe(0.0)
        ->and($preparation->units_per_hour)->toBeNull()
        ->and($preparation->is_under_target)->toBeFalse();

    $production = CloseManufactureTaskSession::make()->action(StartManufactureTaskSession::make()->action($user, $task), ['quantity_made' => 10]);
    $cleaning   = StartManufactureNonProductiveSession::make()->action($user, $this->production, ['activity_type' => 'cleaning']);
    $this->travel(15)->minutes();
    $cleaning = CloseManufactureTaskSession::make()->action($cleaning, []);

    $artisan = collect(get(route('grp.org.productions.show.artisans.index', [$this->organisation->slug, $this->production->slug, 'from' => now()->toDateString(), 'to' => now()->toDateString()]))
        ->viewData('page')['props']['artisans'])->firstWhere('user_id', $user->id);
    $sessionIdsInJobs = collect($artisan['jobs'])->flatMap(fn ($job) => collect($job['steps'])->flatMap(fn ($step) => collect($step['sessions'])->pluck('id')));
    $preparationRow   = collect($artisan['non_productive']['job_orders'])->firstWhere('label', $jobOrder->reference);
    $generalRow       = collect($artisan['non_productive']['job_orders'])->firstWhere('label', null);

    expect($sessionIdsInJobs)->toContain($production->id)
        ->not->toContain($preparation->id)
        ->not->toContain($cleaning->id)
        ->and(collect($artisan['non_productive']['sessions'])->pluck('id')->all())->toContain($preparation->id, $cleaning->id)
        ->and($preparationRow['hours']['preparation'])->toEqual(0.5)
        ->and($generalRow['hours']['cleaning'])->toBeGreaterThanOrEqual(0.25)
        ->and($artisan['non_productive']['days'])->not->toBeEmpty()
        ->and(collect($artisan['non_productive']['sessions'])->firstWhere('id', $preparation->id))->toMatchArray(['reason' => 'Other', 'reason_note' => 'Sorting the labels', 'reason_note_original' => 'Menyusun label']);

    $performance = fn (array $filters) => get(route('grp.org.productions.show.artisans.index', [$this->organisation->slug, $this->production->slug, 'from' => now()->toDateString(), 'to' => now()->toDateString()] + $filters))
        ->viewData('page')['props'];
    $sessionIds  = fn (array $props) => collect($props['artisans'])->flatMap(fn ($row) => collect($row['non_productive']['sessions'])->pluck('id')
        ->merge(collect($row['jobs'])->flatMap(fn ($job) => collect($job['steps'])->flatMap(fn ($step) => collect($step['sessions'])->pluck('id')))));

    $preparationOnly = $performance(['activity' => 'preparation']);
    $otherTotals     = collect($preparationOnly['activity_totals']['reasons'])->firstWhere('key', (string) $otherReason->id);
    expect($sessionIds($preparationOnly))->toContain($preparation->id)->not->toContain($cleaning->id, $production->id)
        ->and($otherTotals['label'])->toBe('Other')
        ->and(collect($otherTotals['notes'])->firstWhere('label', 'Sorting the labels'))->not->toBeNull()
        ->and(collect($preparationOnly['activity_totals']['artisans'])->pluck('worker'))->toContain($user->contact_name ?: $user->username)
        ->and($sessionIds($performance(['activity' => 'preparation', 'preparation_reason' => 'none'])))->not->toContain($preparation->id)
        ->and($sessionIds($performance(['activity' => 'preparation', 'preparation_reason' => (string) $otherReason->id])))->toContain($preparation->id)
        ->and($sessionIds($performance(['activity' => 'cleaning'])))->toContain($cleaning->id)->not->toContain($preparation->id)
        ->and($sessionIds($performance(['activity' => 'production'])))->toContain($production->id)->not->toContain($preparation->id, $cleaning->id)
        ->and($performance(['activity' => 'cleaning'])['activity_totals']['reasons'])->toBeEmpty();

    $untold = $preparation->replicate()->fill(['preparation_reason_id' => null, 'preparation_reason_note' => null, 'preparation_reason_note_en' => null, 'preparation_reason_note_language_id' => null]);
    $untold->saveQuietly();
    $noReasonRow = collect($performance(['activity' => 'preparation'])['activity_totals']['reasons'])->firstWhere('key', 'none');
    expect($noReasonRow['label'])->toBe('No reason recorded')
        ->and($noReasonRow['number_sessions'])->toBeGreaterThanOrEqual(1);
    $untold->delete();

    get(route('grp.org.productions.show.operations.job-orders.show', [$this->organisation->slug, $this->production->slug, $jobOrder->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('job_order.non_productive.0.activity', 'Preparation')
            ->where('job_order.non_productive.0.hours', 0.5)
            ->etc());

    get(route('grp.org.productions.show.operations.dashboard', [$this->organisation->slug, $this->production->slug]))->assertOk();

    $export = get(route('grp.org.productions.show.artisans.payroll.export', [$this->organisation->slug, $this->production->slug, 'from' => now()->toDateString(), 'to' => now()->toDateString(), 'detailed' => 1]));
    ob_start();
    $export->sendContent();
    expect(ob_get_clean())->toContain('Preparation')->toContain('Cleaning');

    VoidManufactureTaskSession::make()->action($cleaning);
    expect($cleaning->refresh()->state)->toBe(ManufactureTaskSessionStateEnum::VOIDED);
    $this->travelBack();
});

test('the production manager edits the preparation reasons, and a reason taken off the list stays on its sessions', function () {
    $reasons = \App\Models\Production\ManufacturePreparationReason::forProduction($this->production);
    $kept    = $reasons->reject(fn ($reason) => $reason->is_other)->values();

    UpdateManufacturePreparationReasons::make()->action($this->production, ['reasons' => [
        ['id' => $kept[1]->id, 'name' => 'Cutting rope and twine'],
        ['id' => null, 'name' => 'Looking for tools'],
        ['id' => $kept[0]->id, 'name' => $kept[0]->name],
    ]]);

    $after = \App\Models\Production\ManufacturePreparationReason::forProduction($this->production);
    expect($after->pluck('name')->all())->toBe(['Cutting rope and twine', 'Looking for tools', $kept[0]->name, 'Other'])
        ->and(\App\Models\Production\ManufacturePreparationReason::onlyTrashed()->whereKey($kept[2]->id)->exists())->toBeTrue()
        ->and(fn () => UpdateManufacturePreparationReasons::make()->action($this->production, ['reasons' => [['name' => 'Same'], ['name' => 'same']]]))->toThrow(ValidationException::class)
        ->and(fn () => UpdateManufacturePreparationReasons::make()->action($this->production, ['reasons' => [['id' => $after->last()->id, 'name' => 'Renamed other']]]))->toThrow(ValidationException::class);

    UpdateManufacturePreparationReasons::make()->action($this->production, ['reasons' => $kept->map(fn ($reason) => ['name' => $reason->name])->all()]);
    expect(\App\Models\Production\ManufacturePreparationReason::forProduction($this->production)->pluck('name')->all())->toBe([...$kept->pluck('name')->all(), 'Other']);
});

test('a job carried at a part pack still walks whole units, and the bays can be served in any order', function () {
    $this->artefact->manufactureTasks()->detach();
    $this->artefact->manufactureTasks()->sync([
        $this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1],
    ]);
    $user = $this->guest->getUser();
    \App\Models\Production\ManufactureTaskSession::where('user_id', $user->id)
        ->where('state', \App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum::OPEN)->delete();

    $stock    = createStocks($this->group)[0];
    $orgStock = createOrgStocks($this->organisation, [$stock])[0];
    \App\Models\Procurement\PartnerShoppingListItem::where('org_stock_id', $orgStock->id)->forceDelete();
    \App\Models\Production\Artefact::where('production_id', $this->production->id)
        ->where('org_stock_id', $orgStock->id)
        ->where('id', '!=', $this->artefact->id)
        ->update(['org_stock_id' => null]);
    $this->artefact->update(['org_stock_id' => $orgStock->id]);
    $orgStock->update(['packed_in' => 3]);

    $warehouse = \App\Actions\Inventory\Warehouse\StoreWarehouse::make()->action($this->organisation, ['code' => 'WH-THRD', 'name' => 'Thirds warehouse']);
    $area      = \App\Actions\Inventory\WarehouseArea\StoreWarehouseArea::make()->action($warehouse, ['code' => 'A-THRD', 'name' => 'Thirds area']);

    $buyers = collect(['SKBUY', 'ESBUY'])->map(function (string $code) use ($area) {
        $buyer = \App\Models\SysAdmin\Organisation::where('code', $code)->first()
            ?? \App\Actions\SysAdmin\Organisation\StoreOrganisation::make()->action($this->group, [
                'code' => $code,
                'name' => $code,
                'type' => \App\Enums\SysAdmin\Organisation\OrganisationTypeEnum::SHOP,
            ] + \App\Models\SysAdmin\Organisation::factory()->definition());
        $bay        = \App\Actions\Inventory\Location\StoreLocation::make()->action($area, ['code' => 'THRD-'.$code, 'name' => $code] + \App\Models\Inventory\Location::factory()->definition());
        $orgPartner = \App\Models\Procurement\OrgPartner::firstOrCreate(
            ['group_id' => $this->group->id, 'organisation_id' => $this->organisation->id, 'partner_id' => $buyer->id],
            ['status' => true],
        );
        $orgPartner->update(['goods_out_location_id' => $bay->id, 'cosmetic_goods_out_location_id' => null, 'gb_goods_out_location_id' => null]);

        return ['organisation' => $buyer, 'bay' => $bay];
    });

    $lineFor = fn (array $buyer, int $quantity) => \App\Models\Procurement\PartnerShoppingListItem::create([
        'group_id'                => $this->group->id,
        'organisation_id'         => $buyer['organisation']->id,
        'partner_organisation_id' => $this->organisation->id,
        'stock_id'                => $stock->id,
        'org_stock_id'            => createOrgStocks($buyer['organisation'], [$stock])[0]->id,
        'quantity'                => $quantity,
    ]);

    /* 4 + 2 SKOs of three = 18 units; day one stops at 10, a third of a pack into the SK line. */
    $skLine = $lineFor($buyers[0], 4);
    $esLine = $lineFor($buyers[1], 2);

    $jobOrder = StoreJobOrder::make()->action($this->production, []);
    $item     = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 18]);
    ConfirmJobOrder::make()->action($jobOrder);
    $skLine->update(['job_order_id' => $jobOrder->id]);
    $esLine->update(['job_order_id' => $jobOrder->id]);

    CloseManufactureTaskSession::make()->action(
        StartManufactureTaskSession::make()->action($user, $item->tasks()->first()),
        ['quantity_made' => 10, 'outcome' => 'carry_over']
    );

    $outstanding = fn (\App\Models\Production\JobOrder $order) => collect(\App\Actions\Production\JobOrder\GetJobOrderDestinationAllocation::make()->outstanding($order->refresh()))
        ->map(fn (array $row) => [$row['location_id'], $row['quantity']])->all();

    /* Day two is still being made: day one walks three whole packs, the loose unit waits at the desk. */
    expect($outstanding($jobOrder))->toBe([[$buyers[0]['bay']->id, 9.0]]);

    $carried = \App\Models\Production\JobOrder::where('data->carried_from', $jobOrder->id)->firstOrFail();
    CloseManufactureTaskSession::make()->action(
        StartManufactureTaskSession::make()->action($user, $carried->jobOrderItems()->first()->tasks()->first()),
        ['quantity_made' => 8, 'outcome' => 'complete']
    );

    /* The SK line was cut at 3.333 SKOs: it still owes ten units, not 9.999 and a crumb for stock. */
    expect($outstanding($jobOrder))->toBe([[$buyers[0]['bay']->id, 10.0]])
        ->and($outstanding($carried))->toBe([[$buyers[1]['bay']->id, 6.0], [$buyers[0]['bay']->id, 2.0]]);

    $jobOrderIds = [$jobOrder->id, $carried->id];

    /* The small walk first: it must not be taken off the other bay's share. */
    \App\Actions\Dispatching\ProductionOutput\PutAwayFinishedJobOrder::make()->action($warehouse, $jobOrderIds, 'THRD-SKBUY');

    expect($outstanding($jobOrder))->toBe([])
        ->and($outstanding($carried))->toBe([[$buyers[1]['bay']->id, 6.0]])
        ->and($jobOrder->refresh()->state)->toBe(JobOrderStateEnum::RECEIVED)
        ->and($carried->refresh()->state)->toBe(JobOrderStateEnum::CONFIRMED);

    \App\Actions\Dispatching\ProductionOutput\PutAwayFinishedJobOrder::make()->action($warehouse, $jobOrderIds, 'THRD-ESBUY');

    $inBay = fn (array $buyer) => (float) \App\Models\Inventory\LocationOrgStock::where('location_id', $buyer['bay']->id)->where('org_stock_id', $orgStock->id)->value('quantity');

    expect($carried->refresh()->state)->toBe(JobOrderStateEnum::RECEIVED)
        ->and((float) $carried->jobOrderItems()->first()->quantity_received)->toBe(8.0)
        ->and($inBay($buyers[0]))->toBe(4.0)
        ->and($inBay($buyers[1]))->toBe(2.0);
});

test('goods made for a partner order that has already shipped go to stock, not to the bay', function () {
    $this->artefact->manufactureTasks()->sync([$this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1]]);
    $user = $this->guest->getUser();
    ManufactureTaskSession::where('user_id', $user->id)->where('state', ManufactureTaskSessionStateEnum::OPEN)->delete();

    $stock    = createStocks($this->group)[0];
    $orgStock = createOrgStocks($this->organisation, [$stock])[0];
    \App\Models\Procurement\PartnerShoppingListItem::where('org_stock_id', $orgStock->id)->forceDelete();
    Artefact::where('production_id', $this->production->id)->where('org_stock_id', $orgStock->id)->where('id', '!=', $this->artefact->id)->update(['org_stock_id' => null]);
    $this->artefact->update(['org_stock_id' => $orgStock->id]);
    $orgStock->update(['packed_in' => 1]);

    $warehouse = \App\Actions\Inventory\Warehouse\StoreWarehouse::make()->action($this->organisation, ['code' => 'WH-SHIP', 'name' => 'Shipped warehouse']);
    $area      = \App\Actions\Inventory\WarehouseArea\StoreWarehouseArea::make()->action($warehouse, ['code' => 'A-SHIP', 'name' => 'Shipped area']);
    $bay       = \App\Actions\Inventory\Location\StoreLocation::make()->action($area, ['code' => 'SHIP-BAY', 'name' => 'Shipped bay'] + \App\Models\Inventory\Location::factory()->definition());
    $buyer     = \App\Models\SysAdmin\Organisation::where('code', 'SKBUY')->first()
        ?? \App\Actions\SysAdmin\Organisation\StoreOrganisation::make()->action($this->group, [
            'code' => 'SKBUY',
            'name' => 'SKBUY',
            'type' => \App\Enums\SysAdmin\Organisation\OrganisationTypeEnum::SHOP,
        ] + \App\Models\SysAdmin\Organisation::factory()->definition());
    \App\Models\Procurement\OrgPartner::firstOrCreate(
        ['group_id' => $this->group->id, 'organisation_id' => $this->organisation->id, 'partner_id' => $buyer->id],
        ['status' => true],
    )->update(['goods_out_location_id' => $bay->id, 'cosmetic_goods_out_location_id' => null, 'gb_goods_out_location_id' => null]);

    [, , $shop]  = createShop();
    [, $product] = createProduct($shop);
    $transaction = createOrder(createCustomer($shop), $product)->transactions()->firstOrFail();

    $line = \App\Models\Procurement\PartnerShoppingListItem::create([
        'group_id'                => $this->group->id,
        'organisation_id'         => $buyer->id,
        'partner_organisation_id' => $this->organisation->id,
        'stock_id'                => $stock->id,
        'org_stock_id'            => createOrgStocks($buyer, [$stock])[0]->id,
        'quantity'                => 5,
        'transaction_id'          => $transaction->id,
    ]);

    $jobOrder = StoreJobOrder::make()->action($this->production, []);
    $item     = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 5]);
    ConfirmJobOrder::make()->action($jobOrder);
    $line->update(['job_order_id' => $jobOrder->id]);
    CloseManufactureTaskSession::make()->action(StartManufactureTaskSession::make()->action($user, $item->tasks()->first()), ['quantity_made' => 5, 'outcome' => 'complete']);

    $destinations = fn () => collect(\App\Actions\Production\JobOrder\GetJobOrderDestinationAllocation::make()->outstanding($jobOrder->refresh()))->pluck('location_id')->all();

    $state = $transaction->state;
    $transaction->update(['state' => \App\Enums\Ordering\Transaction\TransactionStateEnum::HANDLING]);
    expect($destinations())->toBe([$bay->id]);

    $transaction->update(['state' => \App\Enums\Ordering\Transaction\TransactionStateEnum::DISPATCHED]);
    expect($destinations())->toBe([null]);

    $transaction->update(['state' => $state]);
});

test('a recipe change does not ask again for a step whose replaced step is already finished', function () {
    $pouring   = stepTestManufactureTask($this->production, 'POUR', 'Pouring');
    $labelling = stepTestManufactureTask($this->production, 'LABEL', 'Labelling');
    $this->artefact->manufactureTasks()->sync([$this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1]]);

    $jobOrder = StoreJobOrder::make()->action($this->production, []);
    $madeItem = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 5]);
    $newItem  = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 3]);
    ConfirmJobOrder::make()->action($jobOrder);
    $making = $madeItem->tasks()->first();
    CloseManufactureTaskSession::make()->action(StartManufactureTaskSession::make()->action($this->guest->getUser(), $making), ['quantity_made' => 5]);

    $newRecipe = [
        'artefacts' => [$this->artefact->id],
        'steps'     => [
            ['manufacture_task_id' => $pouring->id, 'position' => 1, 'units_per_artefact' => 1],
            ['manufacture_task_id' => $labelling->id, 'position' => 2, 'units_per_artefact' => 1],
        ],
    ];
    SetArtefactsRecipe::make()->action($this->production, $newRecipe);

    $steps = $madeItem->tasks()->get();
    expect($steps->pluck('manufacture_task_id')->all())->toBe([$this->manufactureTask->id, $labelling->id])
        ->and($steps->last()->blockingStep($steps))->toBeNull()
        ->and($newItem->tasks()->pluck('manufacture_task_id')->all())->toBe([$pouring->id, $labelling->id]);

    SetArtefactsRecipe::make()->action($this->production, [
        'artefacts' => [$this->artefact->id],
        'steps'     => [
            ['manufacture_task_id' => $labelling->id, 'position' => 1, 'units_per_artefact' => 1],
            ['manufacture_task_id' => $this->manufactureTask->id, 'position' => 2, 'units_per_artefact' => 1],
        ],
    ]);
    expect($madeItem->tasks()->where('manufacture_task_id', $labelling->id)->exists())->toBeTrue();

    $singleStepItem = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 2]);
    $this->artefact->manufactureTasks()->sync([$this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1]]);
    $singleStepItem->tasks()->where('manufacture_task_id', '!=', $this->manufactureTask->id)->delete();
    CloseManufactureTaskSession::make()->action(StartManufactureTaskSession::make()->action($this->guest->getUser(), $singleStepItem->tasks()->first()), ['quantity_made' => 2]);
    SetArtefactsRecipe::make()->action($this->production, [
        'artefacts' => [$this->artefact->id],
        'steps'     => [['manufacture_task_id' => $pouring->id, 'position' => 1, 'units_per_artefact' => 1]],
    ]);
    expect($singleStepItem->tasks()->where('manufacture_task_id', $pouring->id)->exists())->toBeTrue();

    $this->artefact->manufactureTasks()->sync([$this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1]]);
});

test('a step added to the recipe is not added to an open job whose later step already has work', function () {
    $packing   = stepTestManufactureTask($this->production, 'PACKL', 'Packing later');
    $shrinking = stepTestManufactureTask($this->production, 'SHRINKN', 'Shrinking new');
    $this->artefact->manufactureTasks()->sync([
        $this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1],
        $packing->id               => ['position' => 2, 'units_per_artefact' => 1],
    ]);

    $jobOrder   = StoreJobOrder::make()->action($this->production, []);
    $packedItem = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 4]);
    $madeItem   = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 3]);
    ConfirmJobOrder::make()->action($jobOrder);
    foreach ([$this->manufactureTask->id, $packing->id] as $taskId) {
        CloseManufactureTaskSession::make()->action(StartManufactureTaskSession::make()->action($this->guest->getUser(), $packedItem->tasks()->where('manufacture_task_id', $taskId)->first()), ['quantity_made' => 4]);
    }
    CloseManufactureTaskSession::make()->action(StartManufactureTaskSession::make()->action($this->guest->getUser(), $madeItem->tasks()->where('manufacture_task_id', $this->manufactureTask->id)->first()), ['quantity_made' => 3]);

    SetArtefactsRecipe::make()->action($this->production, [
        'artefacts' => [$this->artefact->id],
        'steps'     => [
            ['manufacture_task_id' => $this->manufactureTask->id, 'position' => 1, 'units_per_artefact' => 1],
            ['manufacture_task_id' => $shrinking->id, 'position' => 2, 'units_per_artefact' => 1],
            ['manufacture_task_id' => $packing->id, 'position' => 3, 'units_per_artefact' => 1],
        ],
    ]);

    expect($packedItem->tasks()->where('manufacture_task_id', $shrinking->id)->exists())->toBeFalse()
        ->and($madeItem->tasks()->orderBy('position')->pluck('manufacture_task_id')->all())->toBe([$this->manufactureTask->id, $shrinking->id, $packing->id]);

    $this->artefact->manufactureTasks()->sync([$this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1]]);
});

test('a step dropped from the recipe while a session was open on it leaves the job when that session closes with nothing made', function () {
    $mixing  = stepTestManufactureTask($this->production, 'MIXL', 'Mixing leftover');
    $filling = stepTestManufactureTask($this->production, 'FILLL', 'Filling leftover');
    $this->artefact->manufactureTasks()->sync([$this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1]]);

    $jobOrder    = StoreJobOrder::make()->action($this->production, []);
    $idleItem    = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 4]);
    ConfirmJobOrder::make()->action($jobOrder);
    $idleSession   = StartManufactureTaskSession::make()->action($this->guest->getUser(), $idleItem->tasks()->first());
    $idleTaskId    = $idleSession->job_order_item_task_id;

    SetArtefactsRecipe::make()->action($this->production, [
        'artefacts' => [$this->artefact->id],
        'steps'     => [
            ['manufacture_task_id' => $mixing->id, 'position' => 1, 'units_per_artefact' => 1],
            ['manufacture_task_id' => $filling->id, 'position' => 2, 'units_per_artefact' => 1],
        ],
    ]);
    expect($idleItem->tasks()->whereKey($idleTaskId)->exists())->toBeTrue();

    CloseManufactureTaskSession::make()->action($idleSession, ['quantity_made' => 0]);

    expect($idleItem->tasks()->pluck('manufacture_task_id')->all())->toBe([$mixing->id, $filling->id])
        ->and(ManufactureTaskSession::whereKey($idleSession->id)->exists())->toBeFalse();

    $this->artefact->manufactureTasks()->sync([$this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1]]);
    $workedJobOrder = StoreJobOrder::make()->action($this->production, []);
    $workedItem     = StoreJobOrderItem::make()->action($workedJobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 4]);
    ConfirmJobOrder::make()->action($workedJobOrder);
    $workedTask    = $workedItem->tasks()->where('manufacture_task_id', $this->manufactureTask->id)->first();
    $workedSession = StartManufactureTaskSession::make()->action($this->guest->getUser(), $workedTask);
    $this->artefact->manufactureTasks()->sync([$mixing->id => ['position' => 1, 'units_per_artefact' => 1]]);
    CloseManufactureTaskSession::make()->action($workedSession, ['quantity_made' => 2]);

    expect($workedTask->fresh())->not->toBeNull()
        ->and(ManufactureTaskSession::whereKey($workedSession->id)->exists())->toBeTrue();

    $this->artefact->manufactureTasks()->sync([$this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1]]);
});

test('a change of a step units per artefact reaches open job steps with nothing recorded, started or not, and leaves worked ones alone', function () {
    $this->artefact->manufactureTasks()->sync([$this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1]]);

    $jobOrder      = StoreJobOrder::make()->action($this->production, []);
    $untouchedItem = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 6]);
    $workedItem    = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 6]);
    $startedItem   = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 6]);
    ConfirmJobOrder::make()->action($jobOrder);
    CloseManufactureTaskSession::make()->action(StartManufactureTaskSession::make()->action($this->guest->getUser(), $workedItem->tasks()->first()), ['quantity_made' => 2]);
    $startedItem->tasks()->first()->update(['state' => \App\Enums\Production\JobOrderItemTask\JobOrderItemTaskStateEnum::IN_PROGRESS]);

    SetArtefactsRecipe::make()->action($this->production, [
        'artefacts' => [$this->artefact->id],
        'steps'     => [['manufacture_task_id' => $this->manufactureTask->id, 'position' => 1, 'units_per_artefact' => 0.5]],
    ]);

    expect((float) $untouchedItem->tasks()->first()->quantity_required)->toBe(3.0)
        ->and((float) $startedItem->tasks()->first()->quantity_required)->toBe(3.0)
        ->and((float) $workedItem->tasks()->first()->quantity_required)->toBe(6.0);

    expect(\App\Actions\Production\JobOrderItemTask\GenerateJobOrderItemTasks::make()->jobsKeepingTheirCount($this->artefact, [$this->manufactureTask->id => 1])->all())
        ->toContain($jobOrder->reference.' '.$this->artefact->code)
        ->and(\App\Actions\Production\JobOrderItemTask\GenerateJobOrderItemTasks::make()->jobsKeepingTheirCount($this->artefact, [$this->manufactureTask->id => 0.5])->all())
        ->toBe([]);

    \App\Actions\Production\Artefact\AttachManufactureTaskToArtefact::make()->action($this->artefact, [
        'manufacture_task_id' => $this->manufactureTask->id,
        'units_per_artefact'  => 0.25,
    ]);

    expect((float) $untouchedItem->tasks()->first()->quantity_required)->toBe(1.5)
        ->and((float) $workedItem->tasks()->first()->quantity_required)->toBe(6.0);

    $this->artefact->manufactureTasks()->sync([$this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1]]);
});

test('a recipe change that reaches open job orders names them, and those with work recorded, before it is saved', function () {
    $this->artefact->manufactureTasks()->sync([$this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1]]);

    $jobOrder   = StoreJobOrder::make()->action($this->production, []);
    $workedItem = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 6]);
    ConfirmJobOrder::make()->action($jobOrder);
    CloseManufactureTaskSession::make()->action(StartManufactureTaskSession::make()->action($this->guest->getUser(), $workedItem->tasks()->first()), ['quantity_made' => 2]);

    $generate = \App\Actions\Production\JobOrderItemTask\GenerateJobOrderItemTasks::make();
    $label    = $jobOrder->reference.' '.$this->artefact->code;

    expect($generate->openJobsAffectedBy($this->artefact, [$this->manufactureTask->id => 1]))->toBeNull();

    $affected = $generate->openJobsAffectedBy($this->artefact, [$this->manufactureTask->id => 12]);
    expect($affected['open']->all())->toContain($label)
        ->and($affected['keeping']->all())->toContain($label)
        ->and(\App\Actions\Production\JobOrderItemTask\GenerateJobOrderItemTasks::openJobsConfirmationMessage([$affected, null]))
        ->toContain($label)->toContain('keep their old count')
        ->and(\App\Actions\Production\JobOrderItemTask\GenerateJobOrderItemTasks::openJobsConfirmationMessage([null]))->toBeNull();
});

test('a step counted in packs typed as a rounded fraction asks for whole packs', function () {
    expect(ArtefactManufactureTask::stepQuantity(60, '0.166700'))->toBe(10.0)
        ->and(ArtefactManufactureTask::stepQuantity(240, 0.3333))->toBe(80.0)
        ->and(ArtefactManufactureTask::artefactsIn(10, 0.1667))->toBe(60.0)
        ->and(ArtefactManufactureTask::artefactsIn(9, 0.1667))->toBe(54.0)
        ->and(ArtefactManufactureTask::exactUnits(0.4))->toBe(0.4)
        ->and(ArtefactManufactureTask::stepQuantity(5, 6))->toBe(30.0);

    $this->artefact->manufactureTasks()->sync([$this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 0.1667]]);

    $jobOrder = StoreJobOrder::make()->action($this->production, []);
    $item     = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 60]);
    ConfirmJobOrder::make()->action($jobOrder);
    $step = $item->tasks()->first();

    expect((float) $step->quantity_required)->toBe(10.0);

    CloseManufactureTaskSession::make()->action(StartManufactureTaskSession::make()->action($this->guest->getUser(), $step), ['quantity_made' => 10]);

    expect($step->refresh()->state)->toBe(JobOrderItemTaskStateEnum::DONE);

    $this->artefact->manufactureTasks()->sync([$this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1]]);
});

test('jobs planned for a day name no operator, and a step given to an operator is theirs alone on the floor', function () {
    $packingTask = stepTestManufactureTask($this->production);
    $this->artefact->manufactureTasks()->sync([
        $this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1],
        $packingTask->id           => ['position' => 2, 'units_per_artefact' => 1],
    ]);

    $maker  = dayPlanOperator($this->organisation);
    $packer = dayPlanOperator($this->organisation);
    $today  = now()->toDateString();

    $nextPosition       = JobOrder::nextPlanPosition($this->production->id, $today);
    [$planned, $second] = \App\Actions\Production\JobOrder\StoreJobOrdersGroupedByArtisan::run($this->production, [
        ['artefact' => $this->artefact, 'quantity' => 2],
        ['artefact' => $this->artefact, 'quantity' => 3],
    ], null, $today);

    expect($planned->employee_id)->toBeNull()
        ->and($planned->planned_for->toDateString())->toBe($today)
        ->and([$planned->plan_position, $second->plan_position])->toBe([$nextPosition, $nextPosition + 1]);

    [$making, $packing] = $planned->jobOrderItems()->first()->tasks;

    patch(route('grp.models.job-order-item-task.assign', ['jobOrderItemTask' => $making->id]), ['employee_id' => $maker->id])->assertRedirect();
    patch(route('grp.models.job-order-item-task.assign', ['jobOrderItemTask' => $packing->id]), ['employee_id' => $packer->id])->assertRedirect();

    $operator = \App\Models\SysAdmin\User::factory()->create(['group_id' => $this->group->id]);
    $operator->employees()->attach($maker->id, ['group_id' => $this->group->id, 'organisation_id' => $this->organisation->id]);
    $operator->givePermissionTo("productions_operations.{$this->production->id}.view");

    actingAs($operator);
    $operatorFloor = get(route('grp.org.productions.show.floor', [$this->organisation->slug, $this->production->slug]))->viewData('page')['props'];
    actingAs($this->guest->getUser());

    expect(collect($operatorFloor['tasks'])->pluck('id')->all())->toBe([$making->id])
        ->and($operatorFloor['tasks'][0]['is_mine'])->toBeTrue();

    expect(fn () => StartManufactureTaskSession::make()->action($operator, $packing->refresh()))
        ->toThrow(ValidationException::class, 'This job is not addressed to you');

    $session = StartManufactureTaskSession::make()->action($operator, $making->refresh());
    expect($planned->refresh()->state)->toBe(JobOrderStateEnum::CONFIRMED);
    CloseManufactureTaskSession::make()->action($session, ['quantity_made' => (float) $making->quantity_required]);

    $floor = collect(get(route('grp.org.productions.show.floor', [$this->organisation->slug, $this->production->slug]))->viewData('page')['props']['tasks']);
    expect($floor->firstWhere('id', $packing->id)['artisan'])->toBe($packer->contact_name);

    $plan = get(route('grp.org.productions.show.operations.day_plan', [$this->organisation->slug, $this->production->slug]))->viewData('page')['props'];
    $rows = collect($plan['data']['data']);
    $row  = $rows->firstWhere('job_order_id', $planned->id);

    expect(array_column($row['steps'], 'operator'))->toBe([$maker->contact_name, $packer->contact_name])
        ->and($row['steps'][0]['state'])->toBe(JobOrderItemTaskStateEnum::DONE->value)
        ->and($row['is_carried_over'])->toBeFalse()
        ->and(array_column($rows->firstWhere('job_order_id', $second->id)['steps'], 'operator'))->toBe([null, null])
        ->and($plan['summary']['steps_unassigned'])->toBeGreaterThanOrEqual(2)
        ->and($plan['summary']['steps_done'])->toBeGreaterThanOrEqual(1);

    patch(route('grp.models.job-order-item-task.assign', ['jobOrderItemTask' => $packing->id]), ['employee_id' => null])->assertRedirect();
    expect($packing->refresh()->employee_id)->toBeNull();

    $this->artefact->manufactureTasks()->sync([$this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1]]);
});

test('a planned job not finished on its day stays on the plan until it is confirmed onto a day, and a carried job keeps its plan and operators', function () {
    $this->artefact->manufactureTasks()->sync([$this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1]]);

    $maker     = dayPlanOperator($this->organisation);
    $today     = now()->toDateString();
    $yesterday = now()->subDay()->toDateString();

    $jobOrder = StoreJobOrder::make()->action($this->production, ['planned_for' => $yesterday]);
    $item     = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 10]);
    $task     = $item->tasks()->first();
    \App\Actions\Production\JobOrderItemTask\AssignJobOrderItemTask::make()->action($task, $maker->id);
    ConfirmJobOrder::make()->action($jobOrder);

    $planRow = fn (string $date, int $jobOrderId) => collect(
        get(route('grp.org.productions.show.operations.day_plan', [$this->organisation->slug, $this->production->slug, 'date' => $date]))->viewData('page')['props']['data']['data']
    )->firstWhere('job_order_id', $jobOrderId);

    expect($planRow($today, $jobOrder->id)['is_carried_over'])->toBeTrue()
        ->and($planRow($yesterday, $jobOrder->id))->not->toBeNull();

    $session = StartManufactureTaskSession::make()->action($this->guest->getUser(), $task->refresh());
    CloseManufactureTaskSession::make()->action($session, ['quantity_made' => 4, 'outcome' => 'carry_over']);

    $carried = JobOrder::find($item->refresh()->data['carried_to']);
    expect($carried->planned_for->toDateString())->toBe($yesterday)
        ->and($carried->plan_position)->toBe($jobOrder->plan_position)
        ->and($carried->jobOrderItems()->first()->tasks()->first()->employee_id)->toBe($maker->id)
        ->and($planRow($today, $jobOrder->id))->toBeNull()
        ->and($planRow($today, $carried->id)['is_carried_over'])->toBeTrue();

    patch(route('grp.models.job-order.update', ['jobOrder' => $carried->id]), ['planned_for' => $today])->assertRedirect();
    expect($carried->refresh()->planned_for->toDateString())->toBe($today)
        ->and($carried->plan_position)->toBeGreaterThanOrEqual(1)
        ->and($planRow($today, $carried->id)['is_carried_over'])->toBeFalse();

    patch(route('grp.models.job-order.update', ['jobOrder' => $carried->id]), ['planned_for' => null])->assertRedirect();
    expect($carried->refresh()->planned_for)->toBeNull()
        ->and($carried->plan_position)->toBeNull()
        ->and($planRow($today, $carried->id))->toBeNull();
});

test('an hourly mixing step is for its named artisans only, is recorded in kg, pays the base rate with no target and opens forming once some mix is made', function () {
    $user = $this->guest->getUser();
    ManufactureTaskSession::where('user_id', $user->id)->where('state', ManufactureTaskSessionStateEnum::OPEN)
        ->update(['state' => ManufactureTaskSessionStateEnum::CLOSED, 'ended_at' => now()]);
    $bandAttributes = ['group_id' => $this->production->group_id, 'organisation_id' => $this->production->organisation_id, 'effective_from' => now()->subYear()];
    $band0 = \App\Models\Production\ManufacturePayBand::query()->firstOrCreate(['production_id' => $this->production->id, 'code' => '0'], ['name' => 'Band 0', 'hourly_rate' => 12.71] + $bandAttributes);
    \App\Models\Production\ManufacturePayBand::query()->firstOrCreate(['production_id' => $this->production->id, 'code' => '1'], ['name' => 'Band 1', 'hourly_rate' => 13, 'target_multiplier' => 0.0001] + $bandAttributes);

    $suffix  = rand(10000, 99999);
    $mixTask = StoreManufactureTask::make()->action($this->production, [
        'code'              => 'MIX'.$suffix,
        'name'              => 'Mixing '.$suffix,
        'is_piece_rate'     => false,
        'is_recorded_in_kg' => true,
    ]);
    $artefact = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'BOMB'.$suffix, 'name' => 'Bath bomb '.$suffix]);
    $artefact->manufactureTasks()->sync([
        $mixTask->id               => ['position' => 1, 'units_per_artefact' => 0.1, 'standard_rate' => 5],
        $this->manufactureTask->id => ['position' => 2, 'units_per_artefact' => 1],
    ]);

    $floorUser = function () {
        $employee  = dayPlanOperator($this->organisation);
        $floorUser = \App\Models\SysAdmin\User::factory()->create(['group_id' => $this->group->id]);
        $floorUser->employees()->attach($employee->id, ['group_id' => $this->group->id, 'organisation_id' => $this->organisation->id]);
        $floorUser->givePermissionTo("productions_operations.{$this->production->id}.view");

        return [$employee, $floorUser];
    };
    [$mixer, $mixerUser]       = $floorUser();
    [$pieceWorker, $pieceUser] = $floorUser();

    \Pest\Laravel\post(route('grp.models.manufacture_task.artisans.attach', [$mixTask->id]), ['employee_id' => $mixer->id])->assertRedirect();
    expect($mixTask->artisans()->pluck('employees.id')->all())->toBe([$mixer->id]);

    $steps = collect([300, 200, 100])->map(function (int $quantity) use ($artefact, $pieceWorker) {
        $jobOrder = StoreJobOrder::make()->action($this->production, ['employee_id' => $pieceWorker->id]);
        $item     = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $artefact->id, 'quantity' => $quantity]);
        ConfirmJobOrder::make()->action($jobOrder);

        return $item->tasks()->orderBy('position')->get();
    });
    [[$mixA, $formingA], [$mixB], [$mixC, $formingC]] = $steps->map->all()->all();

    expect((float) $mixA->quantity_required)->toBe(30.0)
        ->and($mixA->packSize())->toBeNull()
        ->and($mixA->assigneeId())->toBeNull();

    $floorOf = function (\App\Models\SysAdmin\User $floorUser) use ($user) {
        actingAs($floorUser);
        $tasks = collect(get(route('grp.org.productions.show.floor', [$this->organisation->slug, $this->production->slug]))->viewData('page')['props']['tasks']);
        actingAs($user);

        return $tasks;
    };

    $mixIds = [$mixA->id, $mixB->id, $mixC->id];
    expect($floorOf($pieceUser)->whereIn('id', $mixIds))->toBeEmpty()
        ->and($floorOf($pieceUser)->pluck('id'))->toContain($formingA->id);

    $mixerFloor = $floorOf($mixerUser);
    expect($mixerFloor->whereIn('id', $mixIds)->pluck('id')->sort()->values()->all())->toBe($mixIds)
        ->and($mixerFloor->pluck('id'))->not->toContain($formingA->id)
        ->and($mixerFloor->firstWhere('id', $mixA->id))->toMatchArray(['unit' => 'kg', 'kg_per_unit' => 0.1, 'pack_size' => null, 'can_start' => true, 'only_for' => [$mixer->contact_name]]);

    expect(fn () => StartManufactureTaskSession::make()->action($pieceUser, $mixA->refresh()))->toThrow(ValidationException::class, 'Only')
        ->and(fn () => StartManufactureTaskSession::make()->action($user, $formingA->refresh()))->toThrow(ValidationException::class, 'Finish');
    patch(route('grp.models.job-order-item-task.assign', ['jobOrderItemTask' => $mixA->id]), ['employee_id' => $pieceWorker->id])->assertSessionHasErrors('employee_id');

    \Pest\Laravel\post(route('grp.models.production.combined_task.store', ['production' => $this->production->id]), ['job_order_item_task_ids' => [$mixA->id, $mixB->id]])->assertSessionHasNoErrors();

    $session = StartManufactureTaskSession::make()->action($mixerUser, $mixA->refresh());
    $session->update(['started_at' => now()->subHours(2)]);
    $closed = CloseManufactureTaskSession::make()->action($session, ['quantity_made' => 50.5])->refresh();

    expect($closed->standard_rate)->toBeNull()
        ->and($closed->is_under_target)->toBeFalse()
        ->and($closed->band_code)->toBe('0')
        ->and((float) $closed->bonus)->toBe(0.0)
        ->and((float) $closed->task_work_cost)->toBe(0.0)
        ->and((float) $closed->pay)->toBe(round(2 * (float) $band0->hourly_rate, 2))
        ->and((float) $mixA->refresh()->quantity_made)->toBe(30.3)
        ->and((float) $mixB->refresh()->quantity_made)->toBe(20.2)
        ->and($mixA->state)->toBe(JobOrderItemTaskStateEnum::DONE)
        ->and((int) $mixA->jobOrderItem->quantity)->toBe(300);

    $partial = StartManufactureTaskSession::make()->action($mixerUser, $mixC->refresh());
    CloseManufactureTaskSession::make()->action($partial, ['quantity_made' => 4]);
    expect($mixC->refresh()->state)->toBe(JobOrderItemTaskStateEnum::IN_PROGRESS);

    $forming = StartManufactureTaskSession::make()->action($user, $formingC->refresh());
    expect($forming->job_order_item_task_id)->toBe($formingC->id);
    CloseManufactureTaskSession::make()->action($forming, ['quantity_made' => 1]);

    $planned     = StoreJobOrder::make()->action($this->production, ['employee_id' => $pieceWorker->id]);
    $plannedItem = StoreJobOrderItem::make()->action($planned, ['artefact_id' => $artefact->id, 'quantity' => 10]);
    $plannedMix  = $plannedItem->tasks()->orderBy('position')->first();
    expect($planned->refresh()->state)->toBe(JobOrderStateEnum::IN_PROCESS)
        ->and($floorOf($mixerUser)->firstWhere('id', $plannedMix->id)['is_mine'] ?? null)->toBeTrue()
        ->and($floorOf($pieceUser)->pluck('id'))->not->toContain($plannedMix->id);
    $plannedSession = StartManufactureTaskSession::make()->action($mixerUser, $plannedMix);
    expect($planned->refresh()->state)->toBe(JobOrderStateEnum::CONFIRMED);
    CloseManufactureTaskSession::make()->action($plannedSession, ['quantity_made' => 1]);

    \Pest\Laravel\delete(route('grp.models.manufacture_task.artisans.detach', [$mixTask->id, $mixer->id]))->assertRedirect();
    expect($mixTask->artisans()->count())->toBe(0)
        ->and($mixA->refresh()->assigneeId())->toBe($pieceWorker->id);
});

test('a mix with a batch size is asked and recorded in whole batches, rounded on the combined total, without growing the job', function () {
    $user = $this->guest->getUser();
    ManufactureTaskSession::where('user_id', $user->id)->where('state', ManufactureTaskSessionStateEnum::OPEN)
        ->update(['state' => ManufactureTaskSessionStateEnum::CLOSED, 'ended_at' => now()]);

    $suffix  = rand(10000, 99999);
    $mixTask = StoreManufactureTask::make()->action($this->production, [
        'code'              => 'MXB'.$suffix,
        'name'              => 'Mixing '.$suffix,
        'is_piece_rate'     => false,
        'is_recorded_in_kg' => true,
    ]);
    $artefact = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'BB'.$suffix, 'name' => 'Bath bomb '.$suffix]);
    $artefact->manufactureTasks()->sync([$mixTask->id => ['position' => 1, 'units_per_artefact' => 0.1]]);

    \Pest\Laravel\post(route('grp.models.artefact.manufacture-task.attach', ['artefact' => $artefact->id]), ['manufacture_task_id' => $mixTask->id, 'position' => 1, 'units_per_artefact' => 0.1, 'mix_batch_kg' => 30])
        ->assertSessionHasErrors('mix_batch_kg');
    \Pest\Laravel\post(route('grp.models.artefact.manufacture-task.attach', ['artefact' => $artefact->id]), ['manufacture_task_id' => $mixTask->id, 'position' => 1, 'units_per_artefact' => 0.1, 'mix_batch_kg' => 50])
        ->assertSessionHasNoErrors();
    expect($artefact->manufactureTasks()->first()->pivot->mix_batch_kg)->toBe(50.0);

    [$mixA, $mixB] = collect([541, 400])->map(function (int $quantity) use ($artefact) {
        $jobOrder = StoreJobOrder::make()->action($this->production, []);
        $item     = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $artefact->id, 'quantity' => $quantity]);
        ConfirmJobOrder::make()->action($jobOrder);

        return $item->tasks()->first();
    })->all();
    expect((float) $mixA->quantity_required)->toBe(54.1)
        ->and($mixA->mixBatchKg())->toBe(50.0);

    \Pest\Laravel\post(route('grp.models.production.combined_task.store', ['production' => $this->production->id]), ['job_order_item_task_ids' => [$mixA->id, $mixB->id]])->assertSessionHasNoErrors();
    $floorTask = collect(get(route('grp.org.productions.show.floor', [$this->organisation->slug, $this->production->slug]))->viewData('page')['props']['tasks'])
        ->first(fn (array $task) => in_array($mixA->id, array_column($task['combined'] ?? [], 'id')));
    expect($floorTask)->toMatchArray(['mix_batch_kg' => 50, 'quantity_required' => 94.1])
        ->and($floorTask['steps'][0])->toMatchArray(['unit' => 'kg', 'mix_batch_kg' => 50]);

    $jobOrderMix = collect(get(route('grp.org.productions.show.operations.job-orders.show', [$this->organisation->slug, $this->production->slug, $mixA->jobOrder->slug]))
        ->viewData('page')['props']['items'])->first()['tasks'][0];
    expect($jobOrderMix)->toMatchArray(['quantity_required' => 54.1, 'unit' => 'kg', 'mix_batch_kg' => 50]);

    $session = StartManufactureTaskSession::make()->action($user, $mixA->refresh());
    expect(fn () => CloseManufactureTaskSession::make()->action($session, ['quantity_made' => 94.1]))->toThrow(ValidationException::class, 'whole batches of 50 kg');
    CloseManufactureTaskSession::make()->action($session, ['quantity_made' => 100]);

    expect((float) $mixA->refresh()->quantity_made + (float) $mixB->refresh()->quantity_made)->toBe(100.0)
        ->and($mixA->state)->toBe(JobOrderItemTaskStateEnum::DONE)
        ->and($mixB->state)->toBe(JobOrderItemTaskStateEnum::DONE)
        ->and((int) $mixA->jobOrderItem->quantity)->toBe(541)
        ->and((int) $mixB->jobOrderItem->quantity)->toBe(400);

    $soloOrder = StoreJobOrder::make()->action($this->production, []);
    $soloItem  = StoreJobOrderItem::make()->action($soloOrder, ['artefact_id' => $artefact->id, 'quantity' => 900]);
    ConfirmJobOrder::make()->action($soloOrder);
    $solo        = StartManufactureTaskSession::make()->action($user, $soloItem->tasks()->first());
    $soloSession = CloseManufactureTaskSession::make()->action($solo, ['quantity_made' => 100]);
    expect(fn () => \App\Actions\Production\ManufactureTaskSession\CorrectManufactureTaskSessionQuantity::make()->action($soloSession, ['quantity_made' => 75], 'Supervisor'))->toThrow(ValidationException::class, 'whole batches of 50 kg');
    expect((float) \App\Actions\Production\ManufactureTaskSession\CorrectManufactureTaskSessionQuantity::make()->action($soloSession, ['quantity_made' => 50], 'Supervisor')->quantity_made)->toBe(50.0);
});

test('HELP-4232 a mix batch size is set on the kg steps of many artefacts at once, and the floor counts a pack step in packs', function () {
    $suffix   = rand(10000, 99999);
    $mixTask  = StoreManufactureTask::make()->action($this->production, ['code' => 'MXS'.$suffix, 'name' => 'Mixing '.$suffix, 'is_piece_rate' => false, 'is_recorded_in_kg' => true]);
    $packTask = StoreManufactureTask::make()->action($this->production, ['code' => 'PKS'.$suffix, 'name' => 'Packing '.$suffix, 'is_piece_rate' => true]);
    $artefacts = collect(['A', 'B'])->map(function (string $letter) use ($suffix, $mixTask, $packTask) {
        $artefact = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'MB'.$letter.$suffix, 'name' => 'Bath bomb '.$letter.$suffix]);
        $artefact->manufactureTasks()->sync([
            $mixTask->id  => ['position' => 1, 'units_per_artefact' => 0.141],
            $packTask->id => ['position' => 2, 'units_per_artefact' => 0.1667],
        ]);

        return $artefact;
    });
    $mixBatchOf = fn ($artefact, $task) => ArtefactManufactureTask::where('artefact_id', $artefact->id)->where('manufacture_task_id', $task->id)->value('mix_batch_kg');

    \Pest\Laravel\post(route('grp.models.production.artefacts.set_mix_batch', [$this->production->id]), ['artefacts' => $artefacts->pluck('id')->all(), 'mix_batch_kg' => 30])
        ->assertSessionHasErrors('mix_batch_kg');
    \Pest\Laravel\post(route('grp.models.production.artefacts.set_mix_batch', [$this->production->id]), ['artefacts' => $artefacts->pluck('id')->all(), 'mix_batch_kg' => 50])
        ->assertSessionHasNoErrors();
    expect($mixBatchOf($artefacts[0], $mixTask))->toBe(50.0)
        ->and($mixBatchOf($artefacts[1], $mixTask))->toBe(50.0)
        ->and($mixBatchOf($artefacts[0], $packTask))->toBeNull();

    $jobOrder = StoreJobOrder::make()->action($this->production, []);
    $item     = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $artefacts[0]->id, 'quantity' => 80]);
    ConfirmJobOrder::make()->action($jobOrder);
    $pack = $item->tasks()->where('manufacture_task_id', $packTask->id)->first();
    expect((float) $pack->quantity_required)->toBe(13.333);

    $floorTask = collect(get(route('grp.org.productions.show.floor', [$this->organisation->slug, $this->production->slug]))->viewData('page')['props']['tasks'])
        ->first(fn (array $task) => in_array($pack->id, array_column($task['steps'], 'id')));
    expect(collect($floorTask['steps'])->firstWhere('id', $pack->id)['pack_size'])->toBe(6)
        ->and(collect($floorTask['steps'])->firstWhere('id', $item->tasks()->where('manufacture_task_id', $mixTask->id)->value('id'))['mix_batch_kg'])->toBe(50.0);

    \Pest\Laravel\post(route('grp.models.production.artefacts.set_mix_batch', [$this->production->id]), ['artefacts' => [$artefacts[1]->id], 'mix_batch_kg' => null])
        ->assertSessionHasNoErrors();
    expect($mixBatchOf($artefacts[1], $mixTask))->toBeNull()
        ->and($mixBatchOf($artefacts[0], $mixTask))->toBe(50.0);
});

test('HELP-4279 a mix is made in 56.4 kg batches, and the production manager keeps the list of batch sizes', function () {
    $user = $this->guest->getUser();
    ManufactureTaskSession::where('user_id', $user->id)->where('state', ManufactureTaskSessionStateEnum::OPEN)
        ->update(['state' => ManufactureTaskSessionStateEnum::CLOSED, 'ended_at' => now()]);

    $suffix   = rand(10000, 99999);
    $mixTask  = StoreManufactureTask::make()->action($this->production, ['code' => 'MXD'.$suffix, 'name' => 'Mixing '.$suffix, 'is_piece_rate' => false, 'is_recorded_in_kg' => true]);
    $artefact = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'BD'.$suffix, 'name' => 'Bath bomb '.$suffix]);
    $artefact->manufactureTasks()->sync([$mixTask->id => ['position' => 1, 'units_per_artefact' => 0.1]]);

    expect($this->production->refresh()->mixBatchSizesKg())->toContain(56.4);
    \Pest\Laravel\post(route('grp.models.production.artefacts.set_mix_batch', [$this->production->id]), ['artefacts' => [$artefact->id], 'mix_batch_kg' => 56.4])
        ->assertSessionHasNoErrors();
    expect($artefact->manufactureTasks()->first()->pivot->mix_batch_kg)->toBe(56.4);

    $jobOrder = StoreJobOrder::make()->action($this->production, []);
    $item     = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $artefact->id, 'quantity' => 1500]);
    ConfirmJobOrder::make()->action($jobOrder);
    $mix = $item->tasks()->first();
    expect($mix->mixBatchKg())->toBe(56.4);

    $session = StartManufactureTaskSession::make()->action($user, $mix);
    expect(fn () => CloseManufactureTaskSession::make()->action($session, ['quantity_made' => 150]))->toThrow(ValidationException::class, 'whole batches of 56.4 kg, enter 56.4, 112.8, 169.2');
    expect((float) CloseManufactureTaskSession::make()->action($session, ['quantity_made' => 169.2])->quantity_made)->toBe(169.2);

    \Pest\Laravel\patch(route('grp.models.production.mix_batch_sizes.update', [$this->production->id]), ['sizes' => [50, 0]])
        ->assertSessionHasErrors('sizes.1');
    \Pest\Laravel\patch(route('grp.models.production.mix_batch_sizes.update', [$this->production->id]), ['sizes' => [56.4, 30, 50]])
        ->assertSessionHasNoErrors();
    expect($this->production->refresh()->mixBatchSizesKg())->toBe([30.0, 50.0, 56.4]);

    \Pest\Laravel\post(route('grp.models.artefact.manufacture-task.attach', ['artefact' => $artefact->id]), ['manufacture_task_id' => $mixTask->id, 'position' => 1, 'units_per_artefact' => 0.1, 'mix_batch_kg' => 25, 'confirm_open_jobs' => true])
        ->assertSessionHasErrors('mix_batch_kg');
    \Pest\Laravel\post(route('grp.models.artefact.manufacture-task.attach', ['artefact' => $artefact->id]), ['manufacture_task_id' => $mixTask->id, 'position' => 1, 'units_per_artefact' => 0.1, 'mix_batch_kg' => 30, 'confirm_open_jobs' => true])
        ->assertSessionHasNoErrors();
    expect($artefact->manufactureTasks()->first()->pivot->mix_batch_kg)->toBe(30.0);

    \Pest\Laravel\patch(route('grp.models.production.mix_batch_sizes.update', [$this->production->id]), ['sizes' => [25, 50, 56.4]])
        ->assertSessionHasNoErrors();
});

test('a mix entered on Making by mistake is moved to the mix step and Making goes back to do', function () {
    $user = auth()->user();
    ManufactureTaskSession::where('user_id', $user->id)->where('state', ManufactureTaskSessionStateEnum::OPEN)
        ->update(['state' => ManufactureTaskSessionStateEnum::CLOSED, 'ended_at' => now()]);

    $suffix     = rand(10000, 99999);
    $makingTask = StoreManufactureTask::make()->action($this->production, ['code' => 'MK'.$suffix, 'name' => 'Making '.$suffix, 'is_piece_rate' => true]);
    $mixTask    = StoreManufactureTask::make()->action($this->production, ['code' => 'MX'.$suffix, 'name' => 'Mixing '.$suffix, 'is_piece_rate' => false, 'is_recorded_in_kg' => true]);
    $artefact   = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'SALT'.$suffix, 'name' => 'Salt '.$suffix]);
    $artefact->manufactureTasks()->sync([
        $makingTask->id => ['position' => 1, 'units_per_artefact' => 1],
        $mixTask->id    => ['position' => 2, 'units_per_artefact' => 0.56],
    ]);

    $jobOrder = StoreJobOrder::make()->action($this->production, []);
    $item     = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $artefact->id, 'quantity' => 180]);
    ConfirmJobOrder::make()->action($jobOrder);
    $making = $item->tasks()->where('manufacture_task_id', $makingTask->id)->first();
    $mix    = $item->tasks()->where('manufacture_task_id', $mixTask->id)->first();

    $floorTask = collect(get(route('grp.org.productions.show.floor', [$this->organisation->slug, $this->production->slug]))->viewData('page')['props']['tasks'])
        ->firstWhere('id', $making->id);
    expect($floorTask['is_piece_rate'])->toBeTrue();

    $session = CloseManufactureTaskSession::make()->action(StartManufactureTaskSession::make()->action($user, $making), ['quantity_made' => 180]);
    expect($making->refresh()->state)->toBe(JobOrderItemTaskStateEnum::DONE);

    $artisansSession = collect(get(route('grp.org.productions.show.artisans.index', [$this->organisation->slug, $this->production->slug]))->viewData('page')['props']['artisans'])
        ->flatMap(fn (array $artisan) => collect($artisan['jobs'] ?? [])->flatMap(fn (array $job) => collect($job['steps'] ?? [])->flatMap(fn (array $step) => $step['sessions'])))
        ->firstWhere('id', $session->id);
    expect($artisansSession['other_steps'])->toHaveCount(1)
        ->and($artisansSession['other_steps'][0])->toMatchArray(['id' => $mix->id, 'unit' => 'kg', 'quantity_left' => 100.8]);

    \App\Actions\Production\Artefact\AttachManufactureTaskToArtefact::make()->action($artefact, ['manufacture_task_id' => $makingTask->id, 'position' => 1, 'units_per_artefact' => 0.3333]);
    expect((float) $making->refresh()->quantity_required)->toBe(180.0);

    $correct = fn (array $data) => \App\Actions\Production\ManufactureTaskSession\CorrectManufactureTaskSessionQuantity::make()->action($session->refresh(), $data, 'Supervisor');
    $otherJobOrder = StoreJobOrder::make()->action($this->production, []);
    $otherStep     = StoreJobOrderItem::make()->action($otherJobOrder, ['artefact_id' => $artefact->id, 'quantity' => 10])->tasks()->first();
    expect(fn () => $correct(['quantity_made' => 100.8, 'job_order_item_task_id' => $otherStep->id]))->toThrow(ValidationException::class, 'same job line');

    $moved = $correct(['quantity_made' => 100.8, 'job_order_item_task_id' => $mix->id]);

    expect($moved->job_order_item_task_id)->toBe($mix->id)
        ->and($moved->manufacture_task_id)->toBe($mixTask->id)
        ->and((float) $moved->quantity_made)->toBe(100.8)
        ->and($moved->standard_rate)->toBeNull()
        ->and((float) $making->refresh()->quantity_made)->toBe(0.0)
        ->and((float) $making->quantity_required)->toBe(60.0)
        ->and($making->state)->toBe(JobOrderItemTaskStateEnum::TODO)
        ->and((float) $mix->refresh()->quantity_made)->toBe(100.8)
        ->and($mix->state)->toBe(JobOrderItemTaskStateEnum::DONE)
        ->and($jobOrder->refresh()->data['corrections'][0])->toMatchArray(['from_step' => 'Making '.$suffix, 'step' => 'Mixing '.$suffix, 'from' => 180.0, 'to' => 100.8, 'by' => 'Supervisor']);

    $making->update(['quantity_required' => 40]);
    expect((float) $making->followRecipeWhenNothingRecorded()->quantity_required)->toBe(40.0);
});

test('the day plan lists the materials its open steps need, the check asks the warehouse in whole boxes, and the warehouse walks them to the factory location', function () {
    $packingTask = stepTestManufactureTask($this->production);
    $warehouse   = \App\Actions\Inventory\Warehouse\StoreWarehouse::make()->action($this->organisation, ['code' => 'WH-MAT', 'name' => 'Materials warehouse']);
    $area        = \App\Actions\Inventory\WarehouseArea\StoreWarehouseArea::make()->action($warehouse, ['code' => 'A-MAT', 'name' => 'Materials area']);
    $newLocation = fn (string $code) => \App\Actions\Inventory\Location\StoreLocation::make()->action($area, ['code' => $code, 'name' => $code] + \App\Models\Inventory\Location::factory()->definition());
    $shelf       = $newLocation('MAT-SHELF');
    $factory     = $newLocation('MAT-FACTORY');
    $goodsIn     = $newLocation('MAT-GOODS-IN');

    $productOrgStock = labelTestOrgStock($this->organisation, $this->group);
    $artefact        = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'MATCHECK', 'name' => 'Checked artefact', 'org_stock_id' => $productOrgStock->id]);
    $artefact->manufactureTasks()->sync([
        $this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1],
        $packingTask->id           => ['position' => 2, 'units_per_artefact' => 1],
    ]);

    $material = function (string $code, ManufactureTask $step, int $packedIn, float $shelfSkos) use ($artefact, $shelf) {
        $orgStock = labelTestOrgStock($this->organisation, $this->group);
        $orgStock->update(['packed_in' => $packedIn]);
        $rawMaterial = StoreRawMaterial::make()->action($this->production, [
            'type'        => RawMaterialTypeEnum::CONSUMABLE->value,
            'state'       => RawMaterialStateEnum::ORPHAN->value,
            'code'        => $code,
            'description' => $code,
            'unit'        => RawMaterialUnitEnum::UNIT->value,
            'unit_cost'   => 1,
        ]);
        UpdateRawMaterial::make()->action($rawMaterial, ['org_stock_id' => $orgStock->id]);
        AttachRawMaterialToRecipeStep::make()->action(
            ArtefactManufactureTask::where('artefact_id', $artefact->id)->where('manufacture_task_id', $step->id)->first(),
            ['raw_material_id' => $rawMaterial->id, 'quantity_per_unit' => 1]
        );
        $shelfStock = \App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock::make()->action($orgStock, $shelf, ['type' => \App\Enums\Inventory\LocationStock\LocationStockTypeEnum::PICKING]);
        \App\Actions\Inventory\OrgStockMovement\StoreOrgStockMovement::make()->action($orgStock, $shelf, [
            'quantity' => $shelfSkos,
            'type'     => \App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum::PURCHASE,
        ]);

        return [$rawMaterial, $orgStock, $shelfStock->refresh()];
    };
    [$bottle, $bottleStock, $bottleShelf] = $material('MAT-BOTTLE', $this->manufactureTask, 1, 1000);
    [$box, $boxStock, $boxShelf]          = $material('MAT-BOX', $packingTask, 50, 10);

    $today = now()->toDateString();
    $this->production->update(['factory_location_id' => null]);
    expect(fn () => \App\Actions\Production\MaterialRequest\SubmitMaterialsCheck::make()->action($this->production, $today, []))
        ->toThrow(ValidationException::class, 'no factory location');
    \App\Actions\Production\Production\SetProductionFactoryLocation::make()->handle($this->production, $factory);

    [$jobOrder] = \App\Actions\Production\JobOrder\StoreJobOrdersGroupedByArtisan::run($this->production, [['artefact' => $artefact, 'quantity' => 120]], null, $today);
    $lines      = fn () => \App\Actions\Production\MaterialRequest\GetDayMaterialsChecklist::run($this->production, $today)->keyBy('id');

    expect((float) $lines()->get($bottle->id)->needed_units)->toBe(120.0)
        ->and((float) $lines()->get($box->id)->needed_units)->toBe(120.0)
        ->and($lines()->get($box->id)->job_orders)->toContain($jobOrder->reference)
        ->and((float) $lines()->get($box->id)->box_units)->toBe(50.0);

    $user = $this->guest->getUser();
    ConfirmJobOrder::make()->action($jobOrder);
    [$making, $packing] = $jobOrder->jobOrderItems()->first()->tasks;
    CloseManufactureTaskSession::make()->action(StartManufactureTaskSession::make()->action($user, $making), ['quantity_made' => 120]);

    expect($lines()->has($bottle->id))->toBeFalse()
        ->and((float) $lines()->get($box->id)->needed_units)->toBe(120.0);

    $checker  = dayPlanOperator($this->organisation);
    $operator = \App\Models\SysAdmin\User::factory()->create(['group_id' => $this->group->id]);
    $operator->employees()->attach($checker->id, ['group_id' => $this->group->id, 'organisation_id' => $this->organisation->id]);
    $operator->givePermissionTo("productions_operations.{$this->production->id}.view");

    actingAs($operator);
    \Pest\Laravel\post(route('grp.models.production.materials_check.submit', ['production' => $this->production->id]), [
        'date'  => $today,
        'lines' => [['raw_material_id' => $box->id, 'missing_units' => 120]],
    ])->assertForbidden();
    actingAs($user);

    patch(route('grp.models.production.materials_check.checker', ['production' => $this->production->id]), ['date' => $today, 'employee_id' => $checker->id])->assertRedirect();

    actingAs($operator);
    $floor = get(route('grp.org.productions.show.floor', [$this->organisation->slug, $this->production->slug]))->viewData('page')['props'];
    expect($floor['materials_check_route']['name'])->toBe('grp.org.productions.show.operations.materials_check');
    \Pest\Laravel\post(route('grp.models.production.materials_check.submit', ['production' => $this->production->id]), [
        'date'  => $today,
        'lines' => [['raw_material_id' => $box->id, 'missing_units' => 120]],
    ])->assertRedirect();
    actingAs($user);

    $request = \App\Models\Production\ProductionMaterialRequest::where('production_id', $this->production->id)->whereDate('plan_date', $today)->latest('id')->first();
    $item    = $request->items()->first();
    expect($request->state)->toBe(\App\Enums\Production\ProductionMaterialRequest\ProductionMaterialRequestStateEnum::SUBMITTED)
        ->and($request->checker_employee_id)->toBe($checker->id)
        ->and($request->warehouse_id)->toBe($warehouse->id)
        ->and($item->boxes)->toBe(3)
        ->and((float) $item->skos_to_bring)->toBe(3.0)
        ->and($item->job_orders)->toBe([$jobOrder->reference]);

    get(route('grp.org.productions.show.operations.materials_check', [$this->organisation->slug, $this->production->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Org/Production/DayMaterialsCheck')
            ->where('factory_location', 'MAT-FACTORY')
            ->where('production_id', $this->production->id)
            ->where('requests', fn ($requests) => collect($requests)->contains('id', $request->id)));
    expect((float) $lines()->get($box->id)->requested_units)->toBe(120.0);

    $pending = \App\Actions\Dispatching\ProductionMaterials\IndexProductionMaterialRequestItems::pendingItems($warehouse)->pluck('production_material_request_items.id');
    expect($pending->all())->toBe([$item->id]);
    $hub = get(route('grp.org.warehouses.show.dispatching.backlog', [$this->organisation->slug, $warehouse->slug]))->viewData('page')['props'];
    expect(collect($hub['production_materials']['data'])->firstWhere('id', $item->id))
        ->toMatchArray(['location_code' => 'MAT-SHELF', 'to_location' => 'MAT-FACTORY', 'boxes' => 3, 'warehouse_short' => false]);

    \App\Actions\Dispatching\ProductionMaterials\DeliverProductionMaterialRequestItem::make()->action($item, $boxShelf, 3, $user);

    $factoryStock = \App\Models\Inventory\LocationOrgStock::where('location_id', $factory->id)->where('org_stock_id', $boxStock->id)->first();
    expect((float) $factoryStock->quantity)->toBe(3.0)
        ->and((float) $boxShelf->refresh()->quantity)->toBe(7.0)
        ->and((float) $boxStock->refresh()->quantity_in_locations)->toBe(10.0)
        ->and($item->refresh()->state)->toBe(\App\Enums\Production\ProductionMaterialRequestItem\ProductionMaterialRequestItemStateEnum::DELIVERED)
        ->and($request->refresh()->state)->toBe(\App\Enums\Production\ProductionMaterialRequest\ProductionMaterialRequestStateEnum::DELIVERED);

    $pickingRank = \Illuminate\Support\Facades\DB::table('locations')->whereIn('id', [$shelf->id, $factory->id])
        ->selectRaw('id, '.\App\Actions\Dispatching\PartnerStaging\PartnerBayPickingOrder::sql('0').' as rank')
        ->pluck('rank', 'id');
    expect([$pickingRank[$shelf->id], $pickingRank[$factory->id]])->toBe([1, 2]);

    $short = \App\Actions\Production\MaterialRequest\SubmitMaterialsCheck::make()->action($this->production, $today, [$box->id => 10], $user);
    \App\Actions\Dispatching\ProductionMaterials\SetProductionMaterialRequestItemShort::make()->action($short->items()->first(), $user);
    expect($short->items()->first()->state)->toBe(\App\Enums\Production\ProductionMaterialRequestItem\ProductionMaterialRequestItemStateEnum::SHORT)
        ->and($short->refresh()->state)->toBe(\App\Enums\Production\ProductionMaterialRequest\ProductionMaterialRequestStateEnum::DELIVERED);

    CloseManufactureTaskSession::make()->action(StartManufactureTaskSession::make()->action($user, $packing->refresh()), ['quantity_made' => 120]);
    \App\Actions\Production\JobOrder\ReceiveJobOrderIntoStock::make()->action($jobOrder->refresh(), ['location_id' => $goodsIn->id]);

    expect((float) $factoryStock->refresh()->quantity)->toBe(0.6)
        ->and((float) $boxShelf->refresh()->quantity)->toBe(7.0)
        ->and((float) $bottleShelf->refresh()->quantity)->toBe(880.0);

    $this->production->update(['factory_location_id' => null]);
});

test('a line whose done mix made more kg than required can be given whole to another artisan', function () {
    [$artisanA, $artisanB, , $line, $tasks, $splitUrl] = stepOwnerFixture($this);
    [$mix, $making, $packing] = $tasks->all();

    $this->guest->getUser()->employees()->sync([$artisanA->id => ['group_id' => $this->group->id, 'organisation_id' => $this->organisation->id]]);
    $session = StartManufactureTaskSession::make()->action($this->guest->getUser(), $mix);
    CloseManufactureTaskSession::make()->action($session, ['quantity_made' => 200]);
    $mix->refresh()->update(['state' => \App\Enums\Production\JobOrderItemTask\JobOrderItemTaskStateEnum::DONE]);
    $packing->update(['employee_id' => $artisanA->id]);

    patch($splitUrl, ['assignments' => [['id' => $line->id, 'employee_id' => $artisanB->id, 'quantity' => 1344]]])->assertSessionHasNoErrors();

    expect($line->refresh()->employee_id)->toBe($artisanB->id)
        ->and($mix->refresh()->sessions()->count())->toBe(1)
        ->and($packing->refresh()->employee_id)->toBeNull()
        ->and($packing->assigneeId())->toBe($artisanB->id)
        ->and($mix->assigneeId())->toBe($artisanB->id);

    $this->guest->getUser()->employees()->detach($artisanA->id);
});

test('shrinking an artisan share below what they made on a non-mix step fails and names the step', function () {
    [$artisanA, $artisanB, , $line, $tasks, $splitUrl] = stepOwnerFixture($this);
    $making = $tasks[1];
    $making->update(['quantity_made' => 900, 'state' => \App\Enums\Production\JobOrderItemTask\JobOrderItemTaskStateEnum::IN_PROGRESS]);

    patch($splitUrl, ['assignments' => [
        ['id' => $line->id, 'employee_id' => $artisanA->id, 'quantity' => 500],
        ['employee_id' => $artisanB->id, 'quantity' => 844],
    ]])->assertSessionHasErrors('assignments');

    expect(session('errors')->first('assignments'))->toContain($making->manufactureTask->name)->toContain('900')
        ->and($line->refresh()->quantity)->toBe(1344);
});

test('the packing step alone can be given to another artisan once making is done', function () {
    [$artisanA, $artisanB, , $line, $tasks] = stepOwnerFixture($this);
    [$mix, $making, $packing] = $tasks->all();
    $making->update(['quantity_made' => 1344, 'state' => \App\Enums\Production\JobOrderItemTask\JobOrderItemTaskStateEnum::DONE]);

    expect($packing->assigneeId())->toBe($artisanA->id);

    patch(route('grp.models.job-order-item-task.assign', ['jobOrderItemTask' => $packing->id]), ['employee_id' => $artisanB->id])->assertSessionHasNoErrors();

    expect($packing->refresh()->assigneeId())->toBe($artisanB->id)
        ->and($making->refresh()->assigneeId())->toBe($artisanA->id);
});

test('the job order page gives the owner of every step', function () {
    [$artisanA, $artisanB, $jobOrder, $line, $tasks] = stepOwnerFixture($this);
    $tasks[2]->update(['employee_id' => $artisanB->id]);

    $item = collect(get(route('grp.org.productions.show.operations.job-orders.show', [$this->organisation->slug, $this->production->slug, $jobOrder->slug]))
        ->viewData('page')['props']['items'])->firstWhere('id', $line->id);

    expect(collect($item['tasks'])->map(fn (array $task) => $task['owners'][0]['owner_id'])->all())->toBe([$artisanA->id, $artisanA->id, $artisanB->id])
        ->and(collect($item['tasks'])->every(fn (array $task) => $task['owners'][0]['can_assign']))->toBeTrue();
});

test('a kg step is not snapped to a pack fraction and every site counts artefacts out the same way', function () {
    [, , , $line, $tasks] = stepOwnerFixture($this);
    [$mix, $making] = $tasks->all();

    $mix->jobOrderItem->artefact->manufactureTasks()->updateExistingPivot($mix->manufacture_task_id, ['units_per_artefact' => 0.0333]);
    $making->jobOrderItem->artefact->manufactureTasks()->updateExistingPivot($making->manufacture_task_id, ['units_per_artefact' => 0.3333]);
    $mix = $mix->fresh();
    $making = $making->fresh();

    expect($mix->unitsPerArtefact())->toBe(0.0333)
        ->and($mix->requiredFor(600))->toBe(19.98)
        ->and($mix->artefactsIn(19.98))->toBe(600.0)
        ->and($making->unitsPerArtefact())->toBe(1 / 3)
        ->and($making->requiredFor(600))->toBe(200.0)
        ->and($making->artefactsIn(10))->toBe(30.0)
        ->and($making->artefactsIn(10))->toBe(\App\Models\Production\ArtefactManufactureTask::artefactsIn(10, 0.3333));
});

test('every write of a required quantity recalculates the step, and a voided or empty session is not work', function () {
    [$artisanA, , , $line, $tasks] = stepOwnerFixture($this);
    [$mix] = $tasks->all();

    $this->guest->getUser()->employees()->sync([$artisanA->id => ['group_id' => $this->group->id, 'organisation_id' => $this->organisation->id]]);
    $session = StartManufactureTaskSession::make()->action($this->guest->getUser(), $mix);
    $session = CloseManufactureTaskSession::make()->action($session, ['quantity_made' => 200]);
    $state   = \App\Enums\Production\JobOrderItemTask\JobOrderItemTaskStateEnum::class;

    expect($mix->refresh()->state)->toBe($state::DONE);

    $mix->setRequiredQuantity(300);
    expect($mix->refresh()->state)->toBe($state::IN_PROGRESS);

    VoidManufactureTaskSession::make()->action($session->refresh());
    expect($mix->refresh()->state)->toBe($state::TODO);

    $session->update(['state' => \App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum::CLOSED, 'quantity_made' => 0]);
    \App\Actions\Production\JobOrderItemTask\CalculateJobOrderItemTaskQuantities::run($mix->refresh());
    expect($mix->refresh()->state)->toBe($state::TODO);

    $this->guest->getUser()->employees()->detach($artisanA->id);
});

test('a recipe change deletes an unworked step from an open job, keeps a worked one, and the final step ignores the orphan', function () {
    [, , $jobOrder, $line, $tasks] = stepOwnerFixture($this);
    [$mix, $making, $packing] = $tasks->all();
    $artefact = $line->artefact;

    $artefact->manufactureTasks()->detach($packing->manufacture_task_id);
    \App\Actions\Production\JobOrderItemTask\GenerateJobOrderItemTasks::run($line->refresh());
    expect(\App\Models\Production\JobOrderItemTask::find($packing->id))->toBeNull()
        ->and($line->refresh()->finalTask()->id)->toBe($making->id);

    $artefact->manufactureTasks()->attach($packing->manufacture_task_id, ['position' => 3, 'units_per_artefact' => 1]);
    \App\Actions\Production\JobOrderItemTask\GenerateJobOrderItemTasks::run($line->refresh());
    $packing = $line->refresh()->tasks->firstWhere('manufacture_task_id', $packing->manufacture_task_id);
    $packing->update(['quantity_made' => 10, 'state' => \App\Enums\Production\JobOrderItemTask\JobOrderItemTaskStateEnum::IN_PROGRESS]);
    $making->update(['quantity_made' => 700]);

    $artefact->manufactureTasks()->detach($packing->manufacture_task_id);
    \App\Actions\Production\JobOrderItemTask\GenerateJobOrderItemTasks::run($line->refresh());

    $line->refresh();
    expect($line->tasks->pluck('id')->contains($packing->id))->toBeTrue()
        ->and($line->finalTask()->id)->toBe($making->id)
        ->and(\App\Actions\Production\JobOrder\GetJobOrderDestinationAllocation::make()->producedArtefacts($line))->toBe(700.0);

    $item = collect(get(route('grp.org.productions.show.operations.job-orders.show', [$this->organisation->slug, $this->production->slug, $jobOrder->slug]))
        ->viewData('page')['props']['items'])->firstWhere('id', $line->id);
    expect($item['produced_quantity'])->toBe(700.0);
});

test('artefacts are uploaded only by organisation supervisors or production r&d editors', function () {
    $production = $this->production;
    $user       = \App\Models\SysAdmin\User::factory()->create(['group_id' => $production->group_id, 'status' => true]);

    actingAsUserWithOnlyPermissions($user, ["productions_rd.$production->id.view"]);

    \Pest\Laravel\post(route('grp.models.production.artefacts.upload', $production->id), [
        'file' => \Illuminate\Http\UploadedFile::fake()->createWithContent('artefacts.csv', "code,name\nART1,Artefact\n"),
    ])->assertForbidden();

    expect(\Illuminate\Support\Facades\Route::has('grp.models.production.manufacture_tasks.upload'))->toBeFalse();
});

test('preparation shorter than the threshold is added to the job step started straight after it, and can be kept as preparation again', function () {
    $this->artefact->manufactureTasks()->sync([
        $this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1, 'standard_rate' => null],
    ]);
    $user = $this->guest->getUser();
    ManufactureTaskSession::where('user_id', $user->id)->where('state', ManufactureTaskSessionStateEnum::OPEN)
        ->update(['state' => ManufactureTaskSessionStateEnum::CLOSED, 'ended_at' => now()]);
    \App\Models\Production\ManufactureBreak::where('user_id', $user->id)->where(fn ($query) => $query->whereNull('ended_at')->orWhere('ended_at', '>', now()->subMinute()))->delete();
    \App\Actions\Production\ManufactureTaskSession\SetManufactureShortPreparationMinutes::make()->action($this->production, ['minutes' => 10]);

    $jobOrder     = StoreJobOrder::make()->action($this->production, []);
    $jobOrderItem = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 100]);
    ConfirmJobOrder::make()->action($jobOrder);
    $task = $jobOrderItem->tasks()->first();

    $prepare = function (int $minutes, array $reason = []) use ($user) {
        $preparation = StartManufactureNonProductiveSession::make()->action($user, $this->production, ['activity_type' => 'preparation']);
        $this->travel($minutes)->minutes();

        return CloseManufactureTaskSession::make()->action($preparation, $reason)->refresh();
    };
    $work = function () use ($user, $task) {
        $session = StartManufactureTaskSession::make()->action($user, $task);
        $this->travel(30)->minutes();

        return CloseManufactureTaskSession::make()->action($session, ['quantity_made' => 1])->refresh();
    };

    $this->travelTo(now('UTC')->addDays(3)->setTime(9, 0));
    $preparation = $prepare(6);
    expect($preparation->preparation_reason_id)->toBeNull();
    $this->travel(30)->seconds();
    $job         = $work();
    $preparation->refresh();

    expect($preparation->state)->toBe(ManufactureTaskSessionStateEnum::VOIDED)
        ->and($preparation->moved_into_session_id)->toBe($job->id)
        ->and($preparation->moved_seconds)->toBe(390)
        ->and($job->started_at->equalTo($preparation->started_at))->toBeTrue()
        ->and(round($job->paidHours(), 4))->toEqual(round(36.5 / 60, 4));

    $artisan = collect(get(route('grp.org.productions.show.artisans.index', [$this->organisation->slug, $this->production->slug, 'from' => now()->toDateString(), 'to' => now()->toDateString()]))
        ->viewData('page')['props']['artisans'])->firstWhere('user_id', $user->id);
    $jobRow  = collect($artisan['jobs'])->flatMap(fn ($row) => collect($row['steps'])->flatMap(fn ($step) => $step['sessions']))->firstWhere('id', $job->id);
    expect(collect($artisan['non_productive']['sessions'])->firstWhere('id', $preparation->id))->toMatchArray(['moved_into' => $this->manufactureTask->name])
        ->and($jobRow['preparation_minutes'])->toBe(6)
        ->and($artisan['non_productive']['activities']['preparation'])->toEqual(0);

    \App\Actions\Production\ManufactureTaskSession\UndoManufacturePreparationMove::make()->action($preparation);
    $job->refresh();
    expect($preparation->refresh()->state)->toBe(ManufactureTaskSessionStateEnum::CLOSED)
        ->and($preparation->moved_into_session_id)->toBeNull()
        ->and($job->started_at->equalTo($preparation->started_at->copy()->addSeconds(390)))->toBeTrue()
        ->and(round($job->paidHours(), 4))->toEqual(0.5)
        ->and(fn () => \App\Actions\Production\ManufactureTaskSession\UndoManufacturePreparationMove::make()->action($preparation))->toThrow(ValidationException::class);

    $preparation = $prepare(6);
    $break       = \App\Actions\Production\ManufactureBreak\StartManufactureBreak::make()->action($user, $this->production, ['planned_minutes' => 20]);
    \App\Actions\Production\ManufactureBreak\EndManufactureBreak::make()->action($break);
    $work();
    expect($preparation->refresh()->state)->toBe(ManufactureTaskSessionStateEnum::CLOSED);

    $this->travel(5)->minutes();
    $cleaning = StartManufactureNonProductiveSession::make()->action($user, $this->production, ['activity_type' => 'cleaning']);
    $this->travel(2)->minutes();
    CloseManufactureTaskSession::make()->action($cleaning, []);
    $preparation = $prepare(6);
    $this->travel(3)->minutes();
    $work();
    expect($preparation->refresh()->state)->toBe(ManufactureTaskSessionStateEnum::CLOSED);

    $reasonId = \App\Models\Production\ManufacturePreparationReason::forProduction($this->production)->firstWhere('is_other', false)->id;
    $long     = StartManufactureNonProductiveSession::make()->action($user, $this->production, ['activity_type' => 'preparation']);
    $this->travel(12)->minutes();
    expect(fn () => CloseManufactureTaskSession::make()->action($long, []))->toThrow(ValidationException::class);
    $long = CloseManufactureTaskSession::make()->action($long, ['preparation_reason_id' => $reasonId]);
    $work();
    expect($long->refresh()->state)->toBe(ManufactureTaskSessionStateEnum::CLOSED);

    $this->manufactureTask->update(['is_quantity_only' => true]);
    $preparation = $prepare(6);
    $work();
    $this->manufactureTask->update(['is_quantity_only' => false]);
    expect($preparation->refresh()->state)->toBe(ManufactureTaskSessionStateEnum::CLOSED);

    $elsewhere   = Production::whereKeyNot($this->production->id)->first() ?? StoreProduction::make()->action($this->organisation, ['code' => 'PREP2', 'name' => 'Second floor']);
    $preparation = $prepare(6);
    $preparation->update(['production_id' => $elsewhere->id]);
    $work();
    expect($preparation->refresh()->state)->toBe(ManufactureTaskSessionStateEnum::CLOSED);

    $preparation = $prepare(6);
    $work();
    expect($preparation->refresh()->state)->toBe(ManufactureTaskSessionStateEnum::VOIDED);
    $this->travel(8)->days();
    expect(fn () => \App\Actions\Production\ManufactureTaskSession\UndoManufacturePreparationMove::make()->action($preparation))->toThrow(ValidationException::class);
    \App\Actions\Production\ManufactureTaskSession\UndoManufacturePreparationMove::make()->action($preparation, true);
    expect($preparation->refresh()->state)->toBe(ManufactureTaskSessionStateEnum::CLOSED);

    \App\Actions\Production\ManufactureTaskSession\SetManufactureShortPreparationMinutes::make()->action($this->production, ['minutes' => 0]);
    $this->production->refresh();
    $short = StartManufactureNonProductiveSession::make()->action($user, $this->production, ['activity_type' => 'preparation']);
    $this->travel(2)->minutes();
    expect(fn () => CloseManufactureTaskSession::make()->action($short->refresh(), []))->toThrow(ValidationException::class);
    CloseManufactureTaskSession::make()->action($short, ['preparation_reason_id' => $reasonId]);
    $work();
    expect($short->refresh()->state)->toBe(ManufactureTaskSessionStateEnum::CLOSED);

    \App\Actions\Production\ManufactureTaskSession\SetManufactureShortPreparationMinutes::make()->action($this->production, ['minutes' => 10]);
    $this->travelBack();
});
