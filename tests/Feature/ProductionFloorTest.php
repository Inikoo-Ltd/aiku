<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 25 Nov 2024 11:48:10 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Feature;

use App\Actions\Production\Artefact\StoreArtefact;
use App\Actions\Production\Artisan\AttachArtisan;
use App\Actions\SysAdmin\Organisation\Seeders\SeedJobPositions;
use App\Actions\HumanResources\JobPosition\SyncEmployeeJobPositions;
use App\Models\HumanResources\JobPosition;
use App\Actions\HumanResources\Employee\StoreEmployee;
use App\Models\HumanResources\Employee;
use App\Actions\Production\JobOrder\ConfirmJobOrder;
use App\Actions\Production\JobOrder\StoreJobOrder;
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
use App\Enums\Production\Artisan\ArtisanPayTypeEnum;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
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
use App\Models\Production\JobOrder;
use App\Models\Production\JobOrderItemTask;
use App\Models\Production\ManufactureTaskSession;
use App\Models\Production\JobOrderItem;
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
        'inertia.pages.paths',
        [resource_path('js/Pages/Grp')]
    );
    actingAs($this->guest->getUser());

});

test('an artefact without a recipe still queues work through the default production task', function () {
    $this->artefact->manufactureTasks()->detach();

    $jobOrder     = StoreJobOrder::make()->action($this->production, []);
    $jobOrderItem = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 4]);

    $task = $jobOrderItem->tasks()->first();
    expect($jobOrderItem->tasks()->count())->toBe(1)
        ->and($task->manufactureTask->code)->toBe('PROD')
        ->and((float)$task->quantity_required)->toBe(4.0)
        ->and($this->artefact->refresh()->manufactureTasks()->count())->toBe(1);

    $this->artefact->manufactureTasks()->detach();
});

test('attaching a task to an artefact backfills the work queue of open job orders', function () {
    $this->artefact->manufactureTasks()->detach();

    $jobOrder     = StoreJobOrder::make()->action($this->production, []);
    $jobOrderItem = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 4]);

    AttachManufactureTaskToArtefact::make()->action($this->artefact, [
        'manufacture_task_id' => $this->manufactureTask->id,
        'units_per_artefact'  => 2,
    ]);

    $task = $jobOrderItem->tasks()->where('manufacture_task_id', $this->manufactureTask->id)->first();
    expect($jobOrderItem->tasks()->count())->toBe(2)
        ->and((float)$task->quantity_required)->toBe(8.0);

    $this->artefact->manufactureTasks()->detach();
});

test('work queue is generated from the artefact recipe and sessions pay the worker', function () {
    $this->artefact->manufactureTasks()->sync([
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

test('a step cannot be started before earlier steps in the job order are finished', function () {
    $secondTask = stepTestManufactureTask($this->production);

    $this->artefact->manufactureTasks()->sync([
        $this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1],
        $secondTask->id            => ['position' => 2, 'units_per_artefact' => 1],
    ]);

    $jobOrder     = StoreJobOrder::make()->action($this->production, ['date' => now()->subYear()]);
    $jobOrderItem = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 5]);
    ConfirmJobOrder::make()->action($jobOrder);

    [$firstTask, $secondJobTask] = $jobOrderItem->tasks()->orderBy('position')->get();
    $user = $this->guest->getUser();

    expect(fn () => StartManufactureTaskSession::make()->action($user, $secondJobTask))
        ->toThrow(ValidationException::class, 'Finish '.$this->manufactureTask->name.' first');

    $queue = collect(get(route('grp.org.productions.show.operations.dashboard', [$this->organisation->slug, $this->production->slug]))
        ->viewData('page')['props']['command_control']['queue']);
    expect($queue->firstWhere('id', $firstTask->id)['blocked_by_step'])->toBeNull()
        ->and($queue->firstWhere('id', $secondJobTask->id)['blocked_by_step'])->toBe($this->manufactureTask->name);

    CloseManufactureTaskSession::make()->action(
        StartManufactureTaskSession::make()->action($user, $firstTask),
        ['quantity_made' => 5]
    );

    $session = StartManufactureTaskSession::make()->action($user, $secondJobTask);
    expect($session->state)->toBe(ManufactureTaskSessionStateEnum::OPEN);
    CloseManufactureTaskSession::make()->action($session, ['quantity_made' => 5]);
});

test('a second operator cannot start a step another operator already has open', function () {
    $this->artefact->manufactureTasks()->sync([
        $this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1],
    ]);

    $jobOrder     = StoreJobOrder::make()->action($this->production, []);
    $jobOrderItem = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 5]);
    ConfirmJobOrder::make()->action($jobOrder);
    $task = $jobOrderItem->tasks()->first();

    $firstUser  = $this->guest->getUser();
    $secondUser = stepTestSecondOperator($this->group);

    $session = StartManufactureTaskSession::make()->action($firstUser, $task);

    expect(fn () => StartManufactureTaskSession::make()->action($secondUser, $task))
        ->toThrow(ValidationException::class, 'Someone else is already working on this step');

    CloseManufactureTaskSession::make()->action($session, ['quantity_made' => 5]);
});

test('steps sharing a position still run one after the other, in the order they were queued', function () {
    $secondTask = stepTestManufactureTask($this->production);
    $this->artefact->manufactureTasks()->sync([
        $this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1],
        $secondTask->id            => ['position' => 1, 'units_per_artefact' => 1],
    ]);

    $jobOrder     = StoreJobOrder::make()->action($this->production, []);
    $jobOrderItem = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 5]);
    ConfirmJobOrder::make()->action($jobOrder);

    [$earlier, $later] = $jobOrderItem->tasks()->orderBy('id')->get();
    $user = $this->guest->getUser();
    expect($earlier->manufacture_task_id)->toBe($this->manufactureTask->id);

    $floorTasks = collect(get(route('grp.org.productions.show.floor', [$this->organisation->slug, $this->production->slug]))
        ->viewData('page')['props']['tasks']);
    expect($floorTasks->firstWhere('id', $later->id)['blocked_by_step'])->toBe($earlier->manufactureTask->name)
        ->and($floorTasks->firstWhere('id', $earlier->id)['can_start'])->toBeTrue();

    expect(fn () => StartManufactureTaskSession::make()->action($user, $later))
        ->toThrow(ValidationException::class, 'Finish '.$earlier->manufactureTask->name.' first');

    CloseManufactureTaskSession::make()->action(StartManufactureTaskSession::make()->action($user, $earlier), ['quantity_made' => 5]);
    CloseManufactureTaskSession::make()->action(StartManufactureTaskSession::make()->action($user, $later), ['quantity_made' => 5]);

    expect($later->refresh()->state)->toBe(JobOrderItemTaskStateEnum::DONE);
});

test('someone the job is not addressed to, or a job not on the floor, is refused before anything about its steps', function () {
    $secondTask = stepTestManufactureTask($this->production);
    $this->artefact->manufactureTasks()->sync([
        $this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1],
        $secondTask->id            => ['position' => 2, 'units_per_artefact' => 1],
    ]);

    $jobOrder     = StoreJobOrder::make()->action($this->production, []);
    $jobOrderItem = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 5]);
    $blockedTask  = $jobOrderItem->tasks()->where('position', 2)->first();

    expect(fn () => StartManufactureTaskSession::make()->action($this->guest->getUser(), $blockedTask))
        ->toThrow(ValidationException::class, 'This job order has not been released to the floor');

    ConfirmJobOrder::make()->action($jobOrder);
    $outsider = \App\Models\SysAdmin\User::factory()->create(['group_id' => $this->group->id]);

    expect(fn () => StartManufactureTaskSession::make()->action($outsider, $blockedTask))
        ->toThrow(ValidationException::class, 'This job is not addressed to you');
});

test('a step already under way keeps going when an earlier step is reopened by a void', function () {
    $secondTask = stepTestManufactureTask($this->production);
    $this->artefact->manufactureTasks()->sync([
        $this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1],
        $secondTask->id            => ['position' => 2, 'units_per_artefact' => 1],
    ]);

    $jobOrder     = StoreJobOrder::make()->action($this->production, []);
    $jobOrderItem = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 5]);
    ConfirmJobOrder::make()->action($jobOrder);
    [$firstTask, $secondJobTask] = $jobOrderItem->tasks()->orderBy('position')->get();
    $user = $this->guest->getUser();

    $firstSession = CloseManufactureTaskSession::make()->action(StartManufactureTaskSession::make()->action($user, $firstTask), ['quantity_made' => 5]);
    CloseManufactureTaskSession::make()->action(StartManufactureTaskSession::make()->action($user, $secondJobTask), ['quantity_made' => 2]);

    VoidManufactureTaskSession::make()->action($firstSession);
    expect($firstTask->refresh()->state)->not->toBe(JobOrderItemTaskStateEnum::DONE)
        ->and($secondJobTask->refresh()->state)->toBe(JobOrderItemTaskStateEnum::IN_PROGRESS);

    $onTheFloor = collect(get(route('grp.org.productions.show.floor', [$this->organisation->slug, $this->production->slug]))
        ->viewData('page')['props']['tasks'])->firstWhere('id', $secondJobTask->id);
    expect($onTheFloor['blocked_by_step'])->toBeNull()
        ->and($onTheFloor['can_start'])->toBeTrue();

    CloseManufactureTaskSession::make()->action(StartManufactureTaskSession::make()->action($user, $secondJobTask), ['quantity_made' => 3]);
    expect($secondJobTask->refresh()->state)->toBe(JobOrderItemTaskStateEnum::DONE);
});

test('recipe edits reorder and remove steps nobody has started on open job orders', function () {
    $secondTask = stepTestManufactureTask($this->production);
    $this->artefact->manufactureTasks()->sync([
        $this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1],
        $secondTask->id            => ['position' => 2, 'units_per_artefact' => 1],
    ]);

    $jobOrder     = StoreJobOrder::make()->action($this->production, []);
    $jobOrderItem = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 5]);
    ConfirmJobOrder::make()->action($jobOrder);
    $firstStep = $jobOrderItem->tasks()->where('manufacture_task_id', $this->manufactureTask->id)->first();
    $boxStep   = $jobOrderItem->tasks()->where('manufacture_task_id', $secondTask->id)->first();

    AttachManufactureTaskToArtefact::make()->action($this->artefact, ['manufacture_task_id' => $this->manufactureTask->id, 'position' => 3]);
    expect($firstStep->refresh()->position)->toBe(3);

    CloseManufactureTaskSession::make()->action(StartManufactureTaskSession::make()->action($this->guest->getUser(), $boxStep), ['quantity_made' => 5]);

    DetachManufactureTaskFromArtefact::make()->action($this->artefact, $this->manufactureTask);
    expect(JobOrderItemTask::find($firstStep->id))->toBeNull()
        ->and(JobOrderItemTask::find($boxStep->id))->not->toBeNull();

    $singleStepArtefact = Artefact::where('production_id', $this->production->id)->where('code', 'ONE-STEP')->first()
        ?? StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'ONE-STEP', 'name' => 'Single step artefact']);
    $singleStepArtefact->manufactureTasks()->sync([$this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1]]);
    $singleStepJobOrder = StoreJobOrder::make()->action($this->production, []);
    $onlyStep           = StoreJobOrderItem::make()->action($singleStepJobOrder, ['artefact_id' => $singleStepArtefact->id, 'quantity' => 2])->tasks()->first();

    DetachManufactureTaskFromArtefact::make()->action($singleStepArtefact, $this->manufactureTask);
    expect(JobOrderItemTask::find($onlyStep->id))->not->toBeNull();

    $this->artefact->manufactureTasks()->sync([$this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1]]);
});

test('a step taken out of the recipe no longer holds up the steps after it', function () {
    $secondTask = stepTestManufactureTask($this->production);
    $this->artefact->manufactureTasks()->sync([
        $this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1],
        $secondTask->id            => ['position' => 2, 'units_per_artefact' => 1],
    ]);

    $jobOrder     = StoreJobOrder::make()->action($this->production, []);
    $jobOrderItem = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 5]);
    ConfirmJobOrder::make()->action($jobOrder);
    [$removedStep, $laterStep] = $jobOrderItem->tasks()->orderBy('position')->get();
    $user = $this->guest->getUser();

    VoidManufactureTaskSession::make()->action(
        CloseManufactureTaskSession::make()->action(StartManufactureTaskSession::make()->action($user, $removedStep), ['quantity_made' => 5])
    );
    DetachManufactureTaskFromArtefact::make()->action($this->artefact, $this->manufactureTask);
    expect(JobOrderItemTask::find($removedStep->id))->not->toBeNull();

    $onTheFloor = collect(get(route('grp.org.productions.show.floor', [$this->organisation->slug, $this->production->slug]))
        ->viewData('page')['props']['tasks'])->firstWhere('id', $laterStep->id);
    expect($onTheFloor['blocked_by_step'])->toBeNull();

    CloseManufactureTaskSession::make()->action(StartManufactureTaskSession::make()->action($user, $laterStep), ['quantity_made' => 5]);
    expect($laterStep->refresh()->state)->toBe(JobOrderItemTaskStateEnum::DONE);

    $this->artefact->manufactureTasks()->sync([$this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1]]);
});

test('closing short can finish the job or carry the shortfall to a new job order', function () {
    $this->artefact->manufactureTasks()->sync([
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
        ->and($carried->reference)->toBe($jobOrder->reference.'a')
        ->and((float)$carried->jobOrderItems()->first()->tasks()->first()->quantity_required)->toBe(15.0);

    $carriedTask    = $carried->jobOrderItems()->first()->tasks()->first();
    $carriedSession = StartManufactureTaskSession::make()->action($user, $carriedTask);
    CloseManufactureTaskSession::make()->action($carriedSession, ['quantity_made' => 5, 'outcome' => 'carry_over']);
    $carriedAgain = \App\Models\Production\JobOrder::where('production_id', $this->production->id)->orderByDesc('id')->first();

    expect($carriedAgain->reference)->toBe($jobOrder->reference.'b');

    actingAs($user);
    $this->patch(route('grp.models.job-order-item.update', [$item->id]), ['quantity' => 25])->assertSessionHasErrors('quantity');
    $this->patch(route('grp.models.job-order-item.update', [$item->id]), ['quantity' => 10])->assertSessionHasErrors('quantity');
    $this->patch(route('grp.models.job-order-item.update', [$carried->jobOrderItems()->first()->id]), ['quantity' => 15])->assertSessionHasErrors('quantity');
    expect($item->refresh()->quantity)->toBe(10)
        ->and($item->data['carried_to'])->toBe($carried->id)
        ->and((float) $task->refresh()->quantity_required)->toBe(10.0);

    $this->patch(route('grp.models.job-order-item.update', [$carriedAgain->jobOrderItems()->first()->id]), ['quantity' => 12])->assertSessionHasNoErrors();
});

test('pausing keeps the job and its count, and the floor links a carried job to the one it came from', function () {
    $this->artefact->manufactureTasks()->sync([
        $this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1],
    ]);
    $user  = auth()->user();
    $floor = fn () => get(route('grp.org.productions.show.floor', [$this->organisation->slug, $this->production->slug]));

    $jobOrder = StoreJobOrder::make()->action($this->production, []);
    $item     = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 25]);
    ConfirmJobOrder::make()->action($jobOrder);
    $task      = $item->tasks()->first();
    $jobOrders = \App\Models\Production\JobOrder::where('production_id', $this->production->id)->count();

    CloseManufactureTaskSession::make()->action(StartManufactureTaskSession::make()->action($user, $task), ['quantity_made' => 10]);
    expect($task->refresh()->state)->toBe(JobOrderItemTaskStateEnum::IN_PROGRESS)
        ->and((float) $task->quantity_made)->toBe(10.0)
        ->and((float) $task->quantity_required)->toBe(25.0)
        ->and($item->refresh()->quantity)->toBe(25)
        ->and(\App\Models\Production\JobOrder::where('production_id', $this->production->id)->count())->toBe($jobOrders);

    StartManufactureTaskSession::make()->action($user, $task);
    $floor()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('open_session.task.quantity_made', 10)
        ->where('open_session.task.continued_from', null)
        ->where('open_session.carry', ['reference' => $jobOrder->reference.'a', 'units_per_artefact' => 1, 'quantity' => 25])
        ->etc());
    get(route('grp.org.productions.show.operations.dashboard', [$this->organisation->slug, $this->production->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('command_control.open_session.carry.reference', $jobOrder->reference.'a')->where('command_control.open_session.task.continued_from', null)->etc());

    $session = \App\Models\Production\ManufactureTaskSession::where('user_id', $user->id)->where('state', ManufactureTaskSessionStateEnum::OPEN)->first();
    CloseManufactureTaskSession::make()->action($session, ['quantity_made' => 5, 'outcome' => 'carry_over']);
    $carried     = \App\Models\Production\JobOrder::find($item->refresh()->data['carried_to']);
    $carriedTask = $carried->jobOrderItems()->first()->tasks()->first();
    expect($carried->reference)->toBe($jobOrder->reference.'a')
        ->and((float) $carriedTask->quantity_required)->toBe(10.0);

    $task->update(['state' => JobOrderItemTaskStateEnum::TODO]);
    $cards = collect($floor()->viewData('page')['props']['tasks'])->keyBy('id');
    expect($cards[$carriedTask->id]['continued_from'])->toBe($jobOrder->reference)
        ->and($cards[$carriedTask->id]['continued_in'])->toBeNull()
        ->and($cards[$task->id]['continued_in'])->toBe($carried->reference)
        ->and($cards[$task->id]['continued_from'])->toBeNull();
    $task->update(['state' => JobOrderItemTaskStateEnum::DONE]);
});

test('making more than the job asked for needs a manager badge or pin and grows the job to what was made', function () {
    $boxing = stepTestManufactureTask($this->production);
    $this->artefact->manufactureTasks()->sync([
        $this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1],
        $boxing->id                => ['position' => 2, 'units_per_artefact' => 1],
    ]);
    SeedJobPositions::make()->handle($this->organisation);
    $user = $this->guest->getUser();
    \Illuminate\Support\Facades\RateLimiter::clear('overproduction-manager-code:'.$user->id);

    $employeeWithPin = function (string $positionCode, string $pin) {
        $modelData                    = Employee::factory()->make(['organisation_id' => $this->organisation->id])->toArray();
        $modelData['worker_number']   = 'W'.rand(10000, 99999);
        $modelData['alias']           = 'Alias '.rand(10000, 99999);
        $modelData['type']            = \App\Enums\HumanResources\Employee\EmployeeTypeEnum::EMPLOYEE;
        $modelData['employment_type'] = \App\Enums\HumanResources\Employee\EmploymentTypeEnum::FULL_TIME;
        $modelData['state']           = \App\Enums\HumanResources\Employee\EmployeeStateEnum::WORKING;
        $modelData['username']        = 'overprod'.rand(10000, 99999);
        $modelData['password']        = 'secret-password';
        $employee                     = StoreEmployee::make()->action($this->organisation, $modelData);
        SyncEmployeeJobPositions::make()->handle($employee, [
            JobPosition::where('organisation_id', $this->organisation->id)->where('code', $positionCode)->first()->id => ['Production' => [$this->production->id]],
        ]);
        $employee->update(['pin' => $this->organisation->id.':'.$pin]);

        return $employee;
    };
    $operativePin = 'OP'.rand(100000, 999999);
    $managerPin   = 'MG'.rand(100000, 999999);
    $employeeWithPin('prod-c', $operativePin);
    $manager = $employeeWithPin('prod-m', $managerPin);

    $jobOrder = StoreJobOrder::make()->action($this->production, []);
    $item     = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 20]);
    ConfirmJobOrder::make()->action($jobOrder);
    $mixing = $item->tasks()->orderBy('position')->first();

    $session = StartManufactureTaskSession::make()->action($user, $mixing);
    expect(fn () => CloseManufactureTaskSession::make()->action($session, ['quantity_made' => 25]))
        ->toThrow(\Illuminate\Validation\ValidationException::class)
        ->and(fn () => CloseManufactureTaskSession::make()->action($session->refresh(), ['quantity_made' => 25, 'manager_code' => $operativePin]))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    $session->update(['employee_id' => $manager->id]);
    expect(fn () => CloseManufactureTaskSession::make()->action($session->refresh(), ['quantity_made' => 25, 'manager_code' => $managerPin]))
        ->toThrow(\Illuminate\Validation\ValidationException::class, 'Someone else must authorise your overproduction');
    $session->update(['employee_id' => null]);

    CloseManufactureTaskSession::make()->action($session->refresh(), ['quantity_made' => 25, 'manager_code' => $managerPin, 'manager_method' => 'qr']);

    $tasks = $item->tasks()->orderBy('position')->get();
    expect($item->refresh()->quantity)->toBe(25)
        ->and((float) $tasks[0]->quantity_required)->toBe(25.0)
        ->and($tasks[0]->state)->toBe(JobOrderItemTaskStateEnum::DONE)
        ->and((float) $tasks[1]->quantity_required)->toBe(25.0)
        ->and($jobOrder->refresh()->data['overproductions'][0])->toMatchArray([
            'quantity' => 5,
            'manager'  => $manager->contact_name,
            'method'   => 'qr',
        ]);
});

test('closing a step at ten times its target rate needs a manager badge or pin', function () {
    $forming = stepTestManufactureTask($this->production, 'FORM', 'Forming');
    $forming->update(['is_piece_rate' => true, 'standard_rate' => 92]);
    $this->artefact->manufactureTasks()->sync([$forming->id => ['position' => 1, 'units_per_artefact' => 1]]);
    SeedJobPositions::make()->handle($this->organisation);
    $user = $this->guest->getUser();
    \Illuminate\Support\Facades\RateLimiter::clear('overproduction-manager-code:'.$user->id);

    $modelData                    = Employee::factory()->make(['organisation_id' => $this->organisation->id])->toArray();
    $modelData['worker_number']   = 'W'.rand(10000, 99999);
    $modelData['alias']           = 'Alias '.rand(10000, 99999);
    $modelData['type']            = \App\Enums\HumanResources\Employee\EmployeeTypeEnum::EMPLOYEE;
    $modelData['employment_type'] = \App\Enums\HumanResources\Employee\EmploymentTypeEnum::FULL_TIME;
    $modelData['state']           = \App\Enums\HumanResources\Employee\EmployeeStateEnum::WORKING;
    $modelData['username']        = 'ratemgr'.rand(10000, 99999);
    $modelData['password']        = 'secret-password';
    $manager                      = StoreEmployee::make()->action($this->organisation, $modelData);
    SyncEmployeeJobPositions::make()->handle($manager, [
        JobPosition::where('organisation_id', $this->organisation->id)->where('code', 'prod-m')->first()->id => ['Production' => [$this->production->id]],
    ]);
    $managerPin = 'MG'.rand(100000, 999999);
    $manager->update(['pin' => $this->organisation->id.':'.$managerPin]);

    $jobOrder = StoreJobOrder::make()->action($this->production, []);
    $item     = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 640]);
    ConfirmJobOrder::make()->action($jobOrder);
    $task = $item->tasks()->first();

    $session = StartManufactureTaskSession::make()->action($user, $task);
    $session->update(['started_at' => now()->subSeconds(6)]);
    expect(fn () => CloseManufactureTaskSession::make()->action($session->refresh(), ['quantity_made' => 640]))
        ->toThrow(ValidationException::class, 'against a target of 92, a manager must confirm it')
        ->and($session->refresh()->state)->toBe(ManufactureTaskSessionStateEnum::OPEN)
        ->and((float) $task->refresh()->quantity_made)->toBe(0.0);

    $session->update(['started_at' => now()->subHour()]);
    CloseManufactureTaskSession::make()->action($session->refresh(), ['quantity_made' => 300]);
    expect((float) $task->refresh()->quantity_made)->toBe(300.0);

    $session = StartManufactureTaskSession::make()->action($user, $task);
    $session->update(['started_at' => now()->subSeconds(6)]);
    CloseManufactureTaskSession::make()->action($session->refresh(), ['quantity_made' => 340, 'manager_code' => $managerPin]);
    expect($task->refresh()->state)->toBe(JobOrderItemTaskStateEnum::DONE);

    $mixing = stepTestManufactureTask($this->production, 'KGFAST', 'Mixing by weight');
    $mixing->update(['is_piece_rate' => true, 'is_recorded_in_kg' => true, 'standard_rate' => 50]);
    $this->artefact->manufactureTasks()->sync([$mixing->id => ['position' => 1, 'units_per_artefact' => 1]]);
    $jobOrder = StoreJobOrder::make()->action($this->production, []);
    $item     = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 10]);
    ConfirmJobOrder::make()->action($jobOrder);

    $session = StartManufactureTaskSession::make()->action($user, $item->tasks()->first());
    $session->update(['started_at' => now()->subSeconds(6)]);
    CloseManufactureTaskSession::make()->action($session->refresh(), ['quantity_made' => 12, 'manager_code' => $managerPin]);
    expect($item->refresh()->quantity)->toBe(10)
        ->and($jobOrder->refresh()->data['overproductions'] ?? [])->toBe([]);

    $modelData['worker_number'] = 'W'.rand(10000, 99999);
    $modelData['alias']         = 'Alias '.rand(10000, 99999);
    $modelData['username']      = 'hourly'.rand(10000, 99999);
    $hourly                     = StoreEmployee::make()->action($this->organisation, $modelData);
    $hourly->update(['artisan_pay_type' => \App\Enums\Production\Artisan\ArtisanPayTypeEnum::HOURLY]);

    $untargeted = stepTestManufactureTask($this->production, 'NOTGT', 'Making without target');
    $untargeted->update(['is_piece_rate' => true, 'is_recorded_in_kg' => false, 'standard_rate' => null]);
    $this->artefact->manufactureTasks()->sync([$untargeted->id => ['position' => 1, 'units_per_artefact' => 1]]);
    $jobOrder = StoreJobOrder::make()->action($this->production, []);
    $item     = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 360]);
    ConfirmJobOrder::make()->action($jobOrder);
    $task = $item->tasks()->first();

    $session = StartManufactureTaskSession::make()->action($user, $task);
    $session->update(['employee_id' => $hourly->id, 'started_at' => now()->subMinutes(40)]);
    expect(fn () => CloseManufactureTaskSession::make()->action($session->refresh(), ['quantity_made' => 180]))
        ->toThrow(ValidationException::class, 'a step with no target, by someone paid by the hour: a manager must confirm it')
        ->and($session->refresh()->state)->toBe(ManufactureTaskSessionStateEnum::OPEN);
    CloseManufactureTaskSession::make()->action($session->refresh(), ['quantity_made' => 180, 'manager_code' => $managerPin]);
    expect((float) $task->refresh()->quantity_made)->toBe(180.0);

    $session = StartManufactureTaskSession::make()->action($user, $task);
    $session->update(['employee_id' => null, 'started_at' => now()->subMinutes(40)]);
    CloseManufactureTaskSession::make()->action($session->refresh(), ['quantity_made' => 180]);
    expect($task->refresh()->state)->toBe(JobOrderItemTaskStateEnum::DONE);
});

test('a quantity only step records who made it and when with no hours and no rate prompt, an hourly step without target never asks a manager', function () {
    $user = $this->guest->getUser();

    $modelData                  = Employee::factory()->make(['organisation_id' => $this->organisation->id])->toArray();
    $modelData['worker_number'] = 'W'.rand(10000, 99999);
    $modelData['alias']         = 'Alias '.rand(10000, 99999);
    $hourly                     = StoreEmployee::make()->action($this->organisation, $modelData);
    $hourly->update(['artisan_pay_type' => \App\Enums\Production\Artisan\ArtisanPayTypeEnum::HOURLY]);

    $filling = stepTestManufactureTask($this->production, 'FILLQ', 'Filling to order');
    $filling->update(['is_piece_rate' => true, 'is_quantity_only' => true, 'standard_rate' => 5]);
    $this->artefact->manufactureTasks()->sync([$filling->id => ['position' => 1, 'units_per_artefact' => 1]]);
    $jobOrder = StoreJobOrder::make()->action($this->production, []);
    $item     = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 2]);
    ConfirmJobOrder::make()->action($jobOrder);
    $task = $item->tasks()->first();

    $session = StartManufactureTaskSession::make()->action($user, $task);
    $session->update(['employee_id' => $hourly->id, 'started_at' => now()->subMinutes(40)]);
    get(route('grp.org.productions.show.floor', [$this->organisation->slug, $this->production->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('open_session.task.is_quantity_only', true)->etc());

    expect(fn () => CloseManufactureTaskSession::make()->action($session->refresh(), ['quantity_made' => 3]))
        ->toThrow(ValidationException::class, 'a manager must authorise the extra 1');
    $session = CloseManufactureTaskSession::make()->action($session->refresh(), ['quantity_made' => 2])->refresh();
    expect($session->state)->toBe(ManufactureTaskSessionStateEnum::CLOSED)
        ->and($session->user_id)->toBe($user->id)
        ->and($session->started_at->equalTo($session->ended_at))->toBeTrue()
        ->and((float) $session->hours)->toBe(0.0)
        ->and($task->refresh()->state)->toBe(JobOrderItemTaskStateEnum::DONE);

    $mixing = stepTestManufactureTask($this->production, 'MIXH', 'Mixing hourly');
    $mixing->update(['is_piece_rate' => false, 'standard_rate' => null]);
    $this->artefact->manufactureTasks()->sync([$mixing->id => ['position' => 1, 'units_per_artefact' => 1]]);
    $jobOrder = StoreJobOrder::make()->action($this->production, []);
    $item     = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 180]);
    ConfirmJobOrder::make()->action($jobOrder);

    $session = StartManufactureTaskSession::make()->action($user, $item->tasks()->first());
    $session->update(['employee_id' => $hourly->id, 'started_at' => now()->subMinutes(40)]);
    CloseManufactureTaskSession::make()->action($session->refresh(), ['quantity_made' => 180]);
    expect($item->tasks()->first()->state)->toBe(JobOrderItemTaskStateEnum::DONE);
});

test('a step counted in packs says so on the floor and takes whole packs only', function () {
    $this->artefact->manufactureTasks()->sync([
        $this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 0.125],
    ]);
    $user = auth()->user();

    $jobOrder = StoreJobOrder::make()->action($this->production, []);
    $item     = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 16]);
    ConfirmJobOrder::make()->action($jobOrder);
    $task = $item->tasks()->first();

    expect((float) $task->quantity_required)->toBe(2.0)
        ->and($task->packSize())->toBe(8);

    $session = StartManufactureTaskSession::make()->action($user, $task);
    get(route('grp.org.productions.show.floor', [$this->organisation->slug, $this->production->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('open_session.task.pack_size', 8)->etc());

    expect(fn () => CloseManufactureTaskSession::make()->action($session, ['quantity_made' => 1.5]))
        ->toThrow(\Illuminate\Validation\ValidationException::class, 'whole packs of 8');

    CloseManufactureTaskSession::make()->action($session->refresh(), ['quantity_made' => 1]);
    $item->update(['quantity' => 18]);
    $task->update(['quantity_required' => 2.25]);
    CloseManufactureTaskSession::make()->action(StartManufactureTaskSession::make()->action($user, $task->refresh()), ['quantity_made' => 1.25]);
    expect($task->refresh()->state)->toBe(JobOrderItemTaskStateEnum::DONE)
        ->and($item->refresh()->quantity)->toBe(18);

    $this->artefact->manufactureTasks()->sync([
        $this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1],
    ]);
    expect($task->refresh()->packSize())->toBeNull();
});

test('a supervisor corrects an entry typed too high and the grown job shrinks back', function () {
    $this->artefact->manufactureTasks()->sync([
        $this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 0.125],
    ]);
    $user = auth()->user();

    $jobOrder = StoreJobOrder::make()->action($this->production, []);
    $item     = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 56]);
    ConfirmJobOrder::make()->action($jobOrder);
    $task = $item->tasks()->first();

    $session = StartManufactureTaskSession::make()->action($user, $task);
    $session->update(['quantity_made' => 254, 'state' => ManufactureTaskSessionStateEnum::CLOSED, 'ended_at' => now()]);
    $task->update(['quantity_required' => 254]);
    $item->update(['quantity' => 2032]);
    $jobOrder->update(['data' => array_merge($jobOrder->data ?? [], ['overproductions' => [['session_id' => $session->id, 'quantity' => 247]]])]);
    \App\Actions\Production\JobOrderItemTask\CalculateJobOrderItemTaskQuantities::run($task);

    $correct = fn (float $quantity) => \App\Actions\Production\ManufactureTaskSession\CorrectManufactureTaskSessionQuantity::make()->action($session->refresh(), ['quantity_made' => $quantity], 'Supervisor');

    expect(fn () => $correct(300))->toThrow(\Illuminate\Validation\ValidationException::class, 'Say why')
        ->and(fn () => $correct(6.5))->toThrow(\Illuminate\Validation\ValidationException::class, 'whole packs of 8');

    $correct(31);
    expect($item->refresh()->quantity)->toBe(248)
        ->and((float) $task->refresh()->quantity_required)->toBe(31.0)
        ->and((float) $task->quantity_made)->toBe(31.0)
        ->and($task->state)->toBe(JobOrderItemTaskStateEnum::DONE)
        ->and($jobOrder->refresh()->data['overproductions'][0]['quantity'])->toEqual(24)
        ->and($jobOrder->data['corrections'][0])->toMatchArray(['from' => 254.0, 'to' => 31.0, 'by' => 'Supervisor', 'pack_size' => 8]);

    $correct(6);
    expect($item->refresh()->quantity)->toBe(56)
        ->and((float) $task->refresh()->quantity_required)->toBe(7.0)
        ->and($task->state)->toBe(JobOrderItemTaskStateEnum::IN_PROGRESS)
        ->and($jobOrder->refresh()->data['overproductions'])->toBe([]);

    $raise = fn (float $quantity, ?string $reason) => \App\Actions\Production\ManufactureTaskSession\CorrectManufactureTaskSessionQuantity::make()->action($session->refresh(), ['quantity_made' => $quantity, 'reason' => $reason], 'Supervisor');
    expect(fn () => $raise(7, null))->toThrow(\Illuminate\Validation\ValidationException::class, 'Say why')
        ->and(fn () => $raise(8, 'Entered on the wrong session'))->toThrow(\Illuminate\Validation\ValidationException::class, 'Only 1 left');

    $raise(7, 'Entered on the wrong session');
    expect((float) $session->refresh()->quantity_made)->toBe(7.0)
        ->and((float) $task->refresh()->quantity_made)->toBe(7.0)
        ->and($task->state)->toBe(JobOrderItemTaskStateEnum::DONE)
        ->and($item->refresh()->quantity)->toBe(56)
        ->and(collect($jobOrder->refresh()->data['corrections'])->last())->toMatchArray(['from' => 6.0, 'to' => 7.0, 'reason' => 'Entered on the wrong session']);

    get(route('grp.org.productions.show.artisans.index', [$this->organisation->slug, $this->production->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('artisans')->etc());

    $this->artefact->manufactureTasks()->sync([
        $this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1],
    ]);
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
    $this->artefact->manufactureTasks()->sync([
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
        'quantity_per_unit' => 0.0000738,
    ]);

    expect($step->rawMaterials()->count())->toBe(1)
        ->and((float)$step->rawMaterials()->first()->quantity_per_unit)->toBe(0.0000738);

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
            ->has('breadcrumbs', 3)
            ->where('breadcrumbs.2.simple.label', 'Manufacture floor')
            ->has('tasks')
            ->where('server_time', fn (string $serverTime) => abs(now()->diffInSeconds($serverTime)) < 60)
            ->has('today', fn (AssertableInertia $page) => $page
                ->has('sessions')
                ->has('quantity_made')
                ->has('earned'))
            ->where('open_session', null);
    });
});

test('every app database connection talks to the database in UTC so start times do not shift with the server timezone', function () {
    foreach (['aiku', 'aiku_no_sticky', 'aiku_read_only', 'archive'] as $connection) {
        expect(config("database.connections.$connection.timezone"))->toBe('UTC');
    }
});

test('floor marks a later step as blocked until the earlier step is done, then flags who is working it', function () {
    $secondTask = stepTestManufactureTask($this->production);

    $this->artefact->manufactureTasks()->sync([
        $this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1],
        $secondTask->id            => ['position' => 2, 'units_per_artefact' => 1],
    ]);

    $olderJobOrder = StoreJobOrder::make()->action($this->production, ['date' => now()->subWeek()]);
    StoreJobOrderItem::make()->action($olderJobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 5]);
    ConfirmJobOrder::make()->action($olderJobOrder);

    $jobOrder     = StoreJobOrder::make()->action($this->production, []);
    $jobOrderItem = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 5]);
    ConfirmJobOrder::make()->action($jobOrder);

    [$firstTask, $secondJobTask] = $jobOrderItem->tasks()->orderBy('position')->get();
    $firstUser  = $this->guest->getUser();
    $secondUser = stepTestSecondOperator($this->group);

    $props = get(route('grp.org.productions.show.floor', [$this->organisation->slug, $this->production->slug]))
        ->viewData('page')['props'];
    $blockedTask = collect($props['tasks'])->firstWhere('id', $secondJobTask->id);
    expect($blockedTask['blocked_by_step'])->toBe($this->manufactureTask->name)
        ->and($blockedTask['can_start'])->toBeFalse()
        ->and(collect($blockedTask['steps'])->pluck('task_name')->all())->toBe([$this->manufactureTask->name, $secondTask->name])
        ->and(collect($blockedTask['steps'])->pluck('blocked_by_step')->all())->toBe([null, $this->manufactureTask->name]);

    $notReady = collect($props['tasks'])->map(fn (array $task) => !$task['can_start'] || count($task['waiting_for']) > 0)->values();
    expect($notReady->all())->toBe($notReady->sort()->values()->all());

    CloseManufactureTaskSession::make()->action(
        StartManufactureTaskSession::make()->action($firstUser, $firstTask),
        ['quantity_made' => 5]
    );
    $secondSession = StartManufactureTaskSession::make()->action($secondUser, $secondJobTask);

    $seenByFirstUser = collect(get(route('grp.org.productions.show.floor', [$this->organisation->slug, $this->production->slug]))
        ->viewData('page')['props']['tasks'])->firstWhere('id', $secondJobTask->id);
    expect($seenByFirstUser['working_on_by'])->toBe([$secondUser->contact_name ?: $secondUser->username])
        ->and($seenByFirstUser['blocked_by_step'])->toBeNull()
        ->and($seenByFirstUser['can_start'])->toBeFalse()
        ->and($seenByFirstUser['steps'][0]['worked_by'])->toBe([$firstUser->contact_name ?: $firstUser->username])
        ->and($seenByFirstUser['steps'][0]['quantity_made'])->toEqual(5)
        ->and($seenByFirstUser['steps'][1]['working_on_by'])->toBe([$secondUser->contact_name ?: $secondUser->username]);

    actingAs($secondUser);
    $props = get(route('grp.org.productions.show.floor', [$this->organisation->slug, $this->production->slug]))
        ->viewData('page')['props'];
    expect($props['open_session']['task']['id'])->toBe($secondJobTask->id);

    CloseManufactureTaskSession::make()->action($secondSession, ['quantity_made' => 5]);
    actingAs($this->guest->getUser());
});

test('floor skips tasks left behind by a deleted job order item or job order', function () {
    $this->artefact->manufactureTasks()->sync([
        $this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1],
    ]);

    $orphanedItem = StoreJobOrder::make()->action($this->production, []);
    $deletedItem  = StoreJobOrderItem::make()->action($orphanedItem, ['artefact_id' => $this->artefact->id, 'quantity' => 2]);
    ConfirmJobOrder::make()->action($orphanedItem);
    $deletedItem->delete();

    $orphanedOrder = StoreJobOrder::make()->action($this->production, []);
    StoreJobOrderItem::make()->action($orphanedOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 2]);
    ConfirmJobOrder::make()->action($orphanedOrder);
    $orphanedOrder->delete();

    $references = collect(get(route('grp.org.productions.show.floor', [$this->organisation->slug, $this->production->slug]))
        ->assertOk()
        ->viewData('page')['props']['tasks'])->pluck('job_order_reference');

    expect($references)->not->toContain($orphanedItem->reference)
        ->and($references)->not->toContain($orphanedOrder->reference);

    $queue = collect(get(route('grp.org.productions.show.operations.dashboard', [$this->organisation->slug, $this->production->slug]))
        ->assertOk()
        ->viewData('page')['props']['command_control']['queue'])->pluck('job_order_reference');

    expect($queue)->not->toContain($orphanedItem->reference)
        ->and($queue)->not->toContain($orphanedOrder->reference);

    $orphanedTask = JobOrderItemTask::where('job_order_id', $orphanedOrder->id)->first();
    expect(fn () => StartManufactureTaskSession::make()->action($this->guest->getUser(), $orphanedTask))
        ->toThrow(ValidationException::class, __('This job is no longer on the floor'));
});

test('a finished task cannot be started, a stale session on it closes with nothing made, and a session closes once', function () {
    $this->artefact->manufactureTasks()->sync([
        $this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1],
    ]);
    $user = $this->guest->getUser();

    $jobOrder = StoreJobOrder::make()->action($this->production, []);
    $item     = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 4]);
    ConfirmJobOrder::make()->action($jobOrder);
    $task = $item->tasks()->first();

    $session = StartManufactureTaskSession::make()->action($user, $task);
    CloseManufactureTaskSession::make()->action($session, ['quantity_made' => 4]);
    expect($task->refresh()->state)->toBe(JobOrderItemTaskStateEnum::DONE);

    expect(fn () => CloseManufactureTaskSession::make()->action($session, ['quantity_made' => 0]))
        ->toThrow(ValidationException::class, __('This task session is already closed'));

    expect(fn () => StartManufactureTaskSession::make()->action($user, $task))
        ->toThrow(ValidationException::class, __('This task is already finished'));

    $stale = ManufactureTaskSession::create([
        ...$session->only(['group_id', 'organisation_id', 'production_id', 'job_order_item_task_id', 'manufacture_task_id', 'user_id', 'employee_id']),
        'state'      => ManufactureTaskSessionStateEnum::OPEN,
        'started_at' => now(),
    ]);

    expect(fn () => CloseManufactureTaskSession::make()->action($stale, ['quantity_made' => 1]))
        ->toThrow(ValidationException::class);
    expect(CloseManufactureTaskSession::make()->action($stale, ['quantity_made' => 0])->state)
        ->toBe(ManufactureTaskSessionStateEnum::CLOSED);
});

test('closing with nothing made keeps the job when continued later and refuses to call it finished', function () {
    $this->artefact->manufactureTasks()->sync([
        $this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1],
    ]);
    $user = $this->guest->getUser();

    $jobOrder = StoreJobOrder::make()->action($this->production, []);
    $item     = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 6]);
    ConfirmJobOrder::make()->action($jobOrder);
    $task          = $item->tasks()->first();
    $jobOrderCount = JobOrder::where('production_id', $this->production->id)->count();

    $session = StartManufactureTaskSession::make()->action($user, $task);
    expect(fn () => CloseManufactureTaskSession::make()->action($session, ['quantity_made' => 0, 'outcome' => 'complete']))
        ->toThrow(ValidationException::class);
    expect($session->refresh()->state)->toBe(ManufactureTaskSessionStateEnum::OPEN);

    CloseManufactureTaskSession::make()->action($session, ['quantity_made' => 0, 'outcome' => 'carry_over']);
    expect($session->refresh()->state)->toBe(ManufactureTaskSessionStateEnum::CLOSED)
        ->and($item->refresh()->quantity)->toBe(6)
        ->and((float)$task->refresh()->quantity_required)->toBe(6.0)
        ->and(JobOrder::where('production_id', $this->production->id)->count())->toBe($jobOrderCount);
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

    $this->artefact->manufactureTasks()->sync([
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

test('a job order line is split between artisans who each work, and are paid for, their own part', function () {
    [$artisanA, $artisanB, $artisanC] = collect(range(1, 3))->map(function () {
        $modelData = Employee::factory()->make(['organisation_id' => $this->organisation->id])->toArray();
        $modelData['worker_number']   = 'W'.rand(1000, 9999);
        $modelData['alias']           = 'Alias '.rand(1000, 9999);
        $modelData['type']            = \App\Enums\HumanResources\Employee\EmployeeTypeEnum::EMPLOYEE;
        $modelData['employment_type'] = \App\Enums\HumanResources\Employee\EmploymentTypeEnum::FULL_TIME;
        $modelData['state']           = \App\Enums\HumanResources\Employee\EmployeeStateEnum::WORKING;

        return StoreEmployee::make()->action($this->organisation, $modelData);
    })->all();

    $this->guest->getUser()->employees()->sync([$artisanA->id => [
        'group_id'        => $this->group->id,
        'organisation_id' => $this->organisation->id,
    ]]);
    $this->artefact->manufactureTasks()->sync([
        $this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1],
    ]);

    $packedIn = $this->artefact->orgStock?->packed_in;
    $this->artefact->orgStock?->update(['packed_in' => 100]);

    $jobOrder = StoreJobOrder::make()->action($this->production, []);
    $line     = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 1000, 'data' => ['demand_skos' => 1000, 'batch_code' => 'B-1']]);
    $splitUrl = route('grp.models.job-order-item.split', ['jobOrderItem' => $line->id]);

    patch($splitUrl, ['assignments' => [
        ['id' => $line->id, 'employee_id' => $artisanA->id, 'quantity' => 400],
        ['employee_id' => $artisanB->id, 'quantity' => 300],
    ]])->assertSessionHasErrors('assignments');

    patch($splitUrl, ['assignments' => [
        ['id' => $line->id, 'employee_id' => $artisanA->id, 'quantity' => 400],
        ['employee_id' => $artisanB->id, 'quantity' => 300],
        ['employee_id' => $artisanC->id, 'quantity' => 300],
    ]])->assertSessionHasNoErrors();
    $this->artefact->orgStock?->update(['packed_in' => $packedIn]);

    $subJobs = JobOrderItem::where('id', $line->id)->orWhere('split_from_id', $line->id)->orderBy('id')->get();
    expect($subJobs->pluck('employee_id')->all())->toBe([$artisanA->id, $artisanB->id, $artisanC->id])
        ->and($subJobs->pluck('quantity')->map(fn ($quantity) => (int) $quantity)->all())->toBe([400, 300, 300])
        ->and($subJobs->map(fn (JobOrderItem $subJob) => (float) $subJob->tasks()->first()->quantity_required)->all())->toBe([400.0, 300.0, 300.0])
        ->and($subJobs->map(fn (JobOrderItem $subJob) => data_get($subJob->data, 'batch_code'))->unique()->all())->toBe(['B-1'])
        ->and(data_get($subJobs->first()->data, 'demand_skos'))->toBeNull();

    ConfirmJobOrder::make()->action($jobOrder);

    $floorTasks = collect(get(route('grp.org.productions.show.floor', [$this->organisation->slug, $this->production->slug]))
        ->viewData('page')['props']['tasks'])->where('job_order_reference', $jobOrder->reference);
    expect($floorTasks->where('is_mine', true)->pluck('quantity_required')->all())->toBe([400.0])
        ->and($floorTasks->pluck('artisan')->sort()->values()->all())->toBe(collect([$artisanA, $artisanB, $artisanC])->pluck('contact_name')->sort()->values()->all());

    $session = StartManufactureTaskSession::make()->action($this->guest->getUser(), $subJobs[0]->tasks()->first());
    $session = CloseManufactureTaskSession::make()->action($session, ['quantity_made' => 400]);
    expect($session->employee_id)->toBe($artisanA->id);

    $item = collect(get(route('grp.org.productions.show.operations.job-orders.show', [$this->organisation->slug, $this->production->slug, $jobOrder->slug]))
        ->viewData('page')['props']['items'])->firstWhere('id', $line->id);
    expect($item['quantity'])->toBe(1000)
        ->and($item['produced_quantity'])->toBe(400.0)
        ->and($item['update_route'])->toBeNull()
        ->and($item['tasks'][0]['quantity_made'])->toBe(400.0)
        ->and($item['tasks'][0]['quantity_required'])->toBe(1000.0)
        ->and(collect($item['sub_jobs'])->pluck('reference')->all())->toBe([$jobOrder->reference.'-A', $jobOrder->reference.'-B', $jobOrder->reference.'-C'])
        ->and(collect($item['sub_jobs'])->pluck('state')->all())->toBe(['complete', 'assigned', 'assigned'])
        ->and(collect($item['sub_jobs'])->pluck('can_remove')->all())->toBe([false, true, true]);

    $artisans = collect(get(route('grp.org.productions.show.artisans.dashboard', [$this->organisation->slug, $this->production->slug]))
        ->viewData('page')['props']['artisans']);
    expect($artisans->firstWhere('id', $artisanB->id)['queued'])->toBe(1);

    patch($splitUrl, ['assignments' => [
        ['id' => $subJobs[0]->id, 'employee_id' => $artisanA->id, 'quantity' => 300],
        ['id' => $subJobs[1]->id, 'employee_id' => $artisanB->id, 'quantity' => 700],
    ]])->assertSessionHasErrors('assignments');

    patch($splitUrl, ['assignments' => [
        ['id' => $subJobs[1]->id, 'employee_id' => $artisanB->id, 'quantity' => 1000],
    ]])->assertSessionHasErrors('assignments');

    patch($splitUrl, ['assignments' => [
        ['id' => $subJobs[0]->id, 'employee_id' => $artisanA->id, 'quantity' => 400],
        ['id' => $subJobs[1]->id, 'employee_id' => $artisanB->id, 'quantity' => 600],
    ]])->assertSessionHasNoErrors();

    expect(JobOrderItem::find($subJobs[2]->id))->toBeNull()
        ->and((float) $subJobs[1]->refresh()->tasks()->first()->quantity_required)->toBe(600.0)
        ->and((int) $jobOrder->jobOrderItems()->sum('quantity'))->toBe(1000);

    $taskB    = $subJobs[1]->tasks()->first();
    $sessionB = StartManufactureTaskSession::make()->action($this->guest->getUser(), $taskB);
    CloseManufactureTaskSession::make()->action($sessionB, ['quantity_made' => 100]);
    $carried = \App\Actions\Production\JobOrderItemTask\SettleShortJobOrderItemTask::run($taskB->refresh(), true);
    expect($carried->employee_id)->toBe($artisanB->id)
        ->and((int) $carried->jobOrderItems()->first()->quantity)->toBe(500)
        ->and((int) $subJobs[1]->refresh()->quantity)->toBe(100);

    patch($splitUrl, ['assignments' => [
        ['id' => $subJobs[1]->id, 'employee_id' => $artisanB->id, 'quantity' => 200],
        ['id' => $subJobs[0]->id, 'employee_id' => $artisanA->id, 'quantity' => 300],
    ]])->assertSessionHasErrors(['assignments' => 'The rest of this job was carried to '.$carried->reference.', change the quantity there.']);

    patch($splitUrl, ['assignments' => [
        ['id' => $subJobs[0]->id, 'employee_id' => $artisanA->id, 'quantity' => 400],
        ['id' => $subJobs[1]->id, 'employee_id' => $artisanB->id, 'quantity' => 100],
    ]])->assertSessionHasNoErrors();
    expect($subJobs[1]->refresh()->data['carried_to'])->toBe($carried->id)
        ->and($subJobs[0]->refresh()->data)->not->toHaveKey('carried_to');

    $this->guest->getUser()->employees()->detach($artisanA->id);
});

test('my performance shows the artisan only their own finished work, with targets and no money', function () {
    $this->artefact->manufactureTasks()->sync([$this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1]]);
    $jobOrder     = StoreJobOrder::make()->action($this->production, []);
    $jobOrderItem = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 10]);
    ConfirmJobOrder::make()->action($jobOrder);
    $task = $jobOrderItem->tasks()->first();

    $mine   = CloseManufactureTaskSession::make()->action(StartManufactureTaskSession::make()->action($this->guest->getUser(), $task), ['quantity_made' => 4]);
    $theirs = CloseManufactureTaskSession::make()->action(StartManufactureTaskSession::make()->action(stepTestSecondOperator($this->group), $task), ['quantity_made' => 3]);

    $url   = route('grp.org.productions.show.floor.my_performance', [$this->organisation->slug, $this->production->slug]);
    $props = get($url)->assertOk()->viewData('page')['props'];
    $rows  = collect($props['data']['data']);

    expect($props['period']['key'])->toBe('this_week')
        ->and($rows->pluck('id'))->toContain($mine->id)->not->toContain($theirs->id)
        ->and($props['totals']['quantity_made'])->toBeGreaterThanOrEqual(4.0)
        ->and(array_keys($props['totals']))->toContain('preparation_hours', 'cleaning_hours', 'on_target')
        ->and(collect(array_keys($rows->firstWhere('id', $mine->id)))->intersect(['pay', 'earned', 'amount', 'band', 'correct_route', 'void_route', 'times_route']))->toBeEmpty()
        ->and(json_encode($props))->not->toContain('earned')->not->toContain('"pay"');

    $mine->update(['artisan_pay_type' => ArtisanPayTypeEnum::HOURLY, 'standard_rate' => 999999, 'is_under_target' => false, 'started_at' => $mine->ended_at->copy()->subHour()]);
    expect(collect(get($url)->viewData('page')['props']['data']['data'])->firstWhere('id', $mine->id)['is_under_target'])->toBeTrue();

    expect(collect(get($url.'?period=last_week')->viewData('page')['props']['data']['data'])->pluck('id'))->not->toContain($mine->id);
    get($url.'?period=custom')->assertSessionHasErrors(['from', 'to']);

    get(route('grp.org.productions.show.floor', [$this->organisation->slug, $this->production->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('pageHead.actions.0.route.name', 'grp.org.productions.show.floor.my_performance'));
});

test('a job that cannot be made goes back to the backlog or is cancelled, keeping only what was finished', function () {
    $this->artefact->manufactureTasks()->sync([$this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1]]);
    $user = $this->guest->getUser();
    ManufactureTaskSession::where('user_id', $user->id)->where('state', ManufactureTaskSessionStateEnum::OPEN)->delete();

    $stock    = createStocks($this->group)[0];
    $orgStock = createOrgStocks($this->organisation, [$stock])[0];
    \App\Models\Procurement\PartnerShoppingListItem::where('stock_id', $stock->id)->forceDelete();
    Artefact::where('production_id', $this->production->id)->where('org_stock_id', $orgStock->id)->where('id', '!=', $this->artefact->id)->update(['org_stock_id' => null]);
    $this->artefact->update(['org_stock_id' => $orgStock->id]);
    $orgStock->update(['packed_in' => 1]);

    $jobWithLines = function () use ($stock, $orgStock) {
        $jobOrder = StoreJobOrder::make()->action($this->production, []);
        $item     = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 10]);
        ConfirmJobOrder::make()->action($jobOrder);
        $lines = collect([6, 4])->map(fn (int $quantity) => \App\Models\Procurement\PartnerShoppingListItem::create([
            'group_id'        => $this->group->id,
            'organisation_id' => $this->organisation->id,
            'stock_id'        => $stock->id,
            'org_stock_id'    => $orgStock->id,
            'quantity'        => $quantity,
            'job_order_id'    => $jobOrder->id,
            'preparing_at'    => now(),
            'batch_code'      => 'RUN-1',
        ]));

        return [$jobOrder, $item, $lines];
    };
    $cancelUrl = fn (JobOrder $jobOrder) => route('grp.models.job-order.cancel', ['jobOrder' => $jobOrder->id]);

    [$untouched, , $untouchedLines] = $jobWithLines();
    patch($cancelUrl($untouched), ['mode' => 'backlog', 'kind' => 'other'])->assertSessionHasErrors('reason');
    patch($cancelUrl($untouched), ['mode' => 'backlog', 'kind' => 'missing_label', 'reason' => 'Labels coming from China'])->assertSessionHasNoErrors();

    $untouched->refresh();
    $floorReferences = collect(get(route('grp.org.productions.show.floor', [$this->organisation->slug, $this->production->slug]))->viewData('page')['props']['tasks'])->pluck('job_order_reference');
    expect($untouched->state)->toBe(JobOrderStateEnum::NOT_RECEIVED)
        ->and($untouched->data['cancellation'])->toMatchArray(['mode' => 'backlog', 'kind' => 'missing_label', 'reason' => 'Labels coming from China', 'user_id' => $user->id, 'units_kept' => 0, 'units_returned' => 10])
        ->and($untouchedLines->map(fn ($line) => $line->refresh()->only(['job_order_id', 'preparing_at', 'batch_code']))->all())->toBe([['job_order_id' => null, 'preparing_at' => null, 'batch_code' => null], ['job_order_id' => null, 'preparing_at' => null, 'batch_code' => null]])
        ->and($floorReferences)->not->toContain($untouched->reference);

    $props = get(route('grp.org.productions.show.operations.job-orders.show', [$this->organisation->slug, $this->production->slug, $untouched->slug]))->viewData('page')['props'];
    expect($props['cancel_route'])->toBeNull()
        ->and($props['job_order']['cancellation']['kind_label'])->toBe('Missing label');

    [$started, $startedItem, $startedLines] = $jobWithLines();
    $session = StartManufactureTaskSession::make()->action($user, $startedItem->tasks()->first());
    expect(fn () => \App\Actions\Production\JobOrder\CancelJobOrder::make()->action($started, ['mode' => 'cancel', 'kind' => 'missing_packaging'], $user))
        ->toThrow(ValidationException::class);
    CloseManufactureTaskSession::make()->action($session, ['quantity_made' => 7]);

    \App\Actions\Production\JobOrder\CancelJobOrder::make()->action($started->refresh(), ['mode' => 'cancel', 'kind' => 'missing_packaging'], $user);

    $returned = \App\Models\Procurement\PartnerShoppingListItem::where('parent_id', $startedLines[1]->id)->firstOrFail();
    expect($started->refresh()->state)->toBe(JobOrderStateEnum::CONFIRMED)
        ->and($started->data['cancellation'])->toMatchArray(['mode' => 'cancel', 'units_kept' => 7, 'units_returned' => 3])
        ->and((int) $startedItem->refresh()->quantity)->toBe(7)
        ->and($startedItem->tasks()->first()->state)->toBe(JobOrderItemTaskStateEnum::DONE)
        ->and($startedLines[0]->refresh()->job_order_id)->toBe($started->id)
        ->and((float) $startedLines[1]->refresh()->quantity)->toBe(1.0)
        ->and($startedLines[1]->job_order_id)->toBe($started->id)
        ->and((float) $returned->quantity)->toBe(3.0)
        ->and($returned->job_order_id)->toBeNull();

    expect(fn () => \App\Actions\Production\JobOrder\CancelJobOrder::make()->action($untouched->refresh(), ['mode' => 'cancel', 'kind' => 'other', 'reason' => 'again'], $user))
        ->toThrow(ValidationException::class);
    expect(fn () => \App\Actions\Production\JobOrder\CancelJobOrder::make()->action($started->refresh(), ['mode' => 'cancel', 'kind' => 'other', 'reason' => 'again'], $user))
        ->toThrow(ValidationException::class);
});

test('a step of a carried job cannot go past what the step before it made there, even with a manager pin', function () {
    $labelling = stepTestManufactureTask($this->production);
    $this->artefact->manufactureTasks()->detach();
    $this->artefact->manufactureTasks()->sync([
        $this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1],
        $labelling->id             => ['position' => 2, 'units_per_artefact' => 1],
    ]);
    SeedJobPositions::make()->handle($this->organisation);
    $user = $this->guest->getUser();
    \Illuminate\Support\Facades\RateLimiter::clear('overproduction-manager-code:'.$user->id);

    $modelData                    = Employee::factory()->make(['organisation_id' => $this->organisation->id])->toArray();
    $modelData['worker_number']   = 'W'.rand(10000, 99999);
    $modelData['alias']           = 'Alias '.rand(10000, 99999);
    $modelData['type']            = \App\Enums\HumanResources\Employee\EmployeeTypeEnum::EMPLOYEE;
    $modelData['employment_type'] = \App\Enums\HumanResources\Employee\EmploymentTypeEnum::FULL_TIME;
    $modelData['state']           = \App\Enums\HumanResources\Employee\EmployeeStateEnum::WORKING;
    $modelData['username']        = 'carrymgr'.rand(10000, 99999);
    $modelData['password']        = 'secret-password';
    $manager                      = StoreEmployee::make()->action($this->organisation, $modelData);
    SyncEmployeeJobPositions::make()->handle($manager, [
        JobPosition::where('organisation_id', $this->organisation->id)->where('code', 'prod-m')->first()->id => ['Production' => [$this->production->id]],
    ]);
    $managerPin = 'MG'.rand(100000, 999999);
    $manager->update(['pin' => $this->organisation->id.':'.$managerPin]);

    $jobOrder = StoreJobOrder::make()->action($this->production, []);
    $item     = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 10]);
    ConfirmJobOrder::make()->action($jobOrder);
    [$making, $labellingTask] = $item->tasks()->orderBy('position')->get()->all();

    CloseManufactureTaskSession::make()->action(StartManufactureTaskSession::make()->action($user, $making), ['quantity_made' => 6, 'outcome' => 'carry_over']);
    $carried = \App\Models\Production\JobOrder::where('data->carried_from', $jobOrder->id)->firstOrFail();

    $session = StartManufactureTaskSession::make()->action($user, $labellingTask->refresh());
    expect(fn () => CloseManufactureTaskSession::make()->action($session, ['quantity_made' => 9, 'manager_code' => $managerPin]))
        ->toThrow(ValidationException::class, $this->manufactureTask->name.' made 6 on '.$jobOrder->reference.', this step cannot go past that. Enter the rest on '.$carried->reference.', where it was made')
        ->and($session->refresh()->state)->toBe(ManufactureTaskSessionStateEnum::OPEN)
        ->and($item->refresh()->quantity)->toBe(6);

    CloseManufactureTaskSession::make()->action($session->refresh(), ['quantity_made' => 6]);
    expect((float) $labellingTask->refresh()->quantity_made)->toBe(6.0)
        ->and($jobOrder->refresh()->data['overproductions'] ?? [])->toBe([]);
});
