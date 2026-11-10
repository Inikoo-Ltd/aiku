<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 29 Oct 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Feature;

use App\Actions\Production\Artefact\BranchArtefact;
use App\Actions\Production\Artefact\CancelArtefactDevelopment;
use App\Actions\Production\Artefact\LaunchArtefact;
use App\Actions\Production\Artefact\MergeArtefactBranch;
use App\Actions\Production\Artefact\SetArtefactsRecipe;
use App\Actions\Production\Artefact\SetArtefactState;
use App\Actions\Production\Artefact\SetArtefactsState;
use App\Actions\Production\Artefact\StoreArtefact;
use App\Actions\Production\JobOrder\ConfirmJobOrder;
use App\Actions\Production\JobOrder\ReceiveJobOrderIntoStock;
use App\Actions\Production\JobOrder\StoreJobOrder;
use App\Actions\Production\JobOrder\StoreTrialRunJobOrder;
use App\Actions\Production\JobOrderItem\StoreJobOrderItem;
use App\Actions\Production\ManufactureTaskSession\CloseManufactureTaskSession;
use App\Actions\Production\ManufactureTaskSession\StartManufactureTaskSession;
use App\Actions\Production\Production\StoreProduction;
use App\Actions\Production\RawMaterial\StoreRawMaterial;
use App\Enums\Production\Artefact\ArtefactStateEnum;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionActivityTypeEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum;
use App\Enums\Production\RawMaterial\RawMaterialStateEnum;
use App\Enums\Production\RawMaterial\RawMaterialTypeEnum;
use App\Enums\Production\RawMaterial\RawMaterialUnitEnum;
use App\Models\Inventory\OrgStockMovement;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Models\Production\Artefact;
use App\Models\Production\ManufactureTaskSession;
use App\Models\Production\RawMaterial;
use App\Models\Production\Production;
use Config;
use Illuminate\Support\Facades\DB;
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
    $this->guest = createAdminGuest($this->group);
    ManufactureTaskSession::where('user_id', $this->guest->getUser()->id)
        ->where('state', ManufactureTaskSessionStateEnum::OPEN)
        ->update(['state' => ManufactureTaskSessionStateEnum::VOIDED]);

    $this->production = Production::orderBy('id')->first()
        ?? StoreProduction::make()->action($this->organisation, ['code' => 'DEVP', 'name' => 'Development production']);

    $this->manufactureTask = stepTestManufactureTask($this->production, 'DEVPOUR', 'Pouring');

    $this->experiment = fn (string $code) => tap(
        StoreArtefact::make()->action($this->production, ['code' => $code.rand(1000, 9999), 'name' => 'Experiment '.$code]),
        fn (Artefact $artefact) => $artefact->manufactureTasks()->sync([
            $this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1],
        ])
    );

    $this->listedArtefactIds = function (): array {
        $ids = [];
        get(route('grp.org.productions.show.crafts.artefacts.index', [$this->organisation->slug, $this->production->slug, 'artefacts_perPage' => 1000]))
            ->assertInertia(function (AssertableInertia $page) use (&$ids) {
                $ids = collect($page->toArray()['props']['artefacts']['data'])->pluck('id')->all();
            });

        return $ids;
    };

    Config::set('inertia.pages.paths', [resource_path('js/Pages/Grp')]);
    actingAs($this->guest->getUser());
});

test('a new artefact is an experiment and stays out of the artefacts list', function () {
    $experiment = ($this->experiment)('EXP');
    $cancelled  = ($this->experiment)('EXPC');
    CancelArtefactDevelopment::make()->action($cancelled, ['reason' => 'Scent did not hold']);

    $listed = ($this->listedArtefactIds)();

    expect($experiment->state)->toBe(ArtefactStateEnum::IN_PROCESS)
        ->and($listed)->not->toContain($experiment->id)
        ->and($listed)->not->toContain($cancelled->id);
});

test('a trial run is a job order for the floor that never reaches the board', function () {
    $experiment = ($this->experiment)('TRIAL');
    $boardLines = PartnerShoppingListItem::count();

    $jobOrder = StoreTrialRunJobOrder::make()->action($this->production, [
        'artefact_id' => $experiment->id,
        'quantity'    => 6,
        'notes'       => 'Try 3% lavender',
    ]);

    $item = $jobOrder->jobOrderItems()->first();

    expect($jobOrder->isTrialRun())->toBeTrue()
        ->and($jobOrder->state)->toBe(JobOrderStateEnum::IN_PROCESS)
        ->and($jobOrder->internal_notes)->toContain('Try 3% lavender')
        ->and($item->artefact_id)->toBe($experiment->id)
        ->and((float) $item->quantity)->toBe(6.0)
        ->and($item->tasks()->count())->toBe(1)
        ->and(PartnerShoppingListItem::count())->toBe($boardLines);
});

test('a trial run a product development asks for waits off the floor, unassigned, until a production manager releases it', function () {
    $experiment = ($this->experiment)('ASKED');
    $worker     = \App\Actions\HumanResources\Employee\StoreEmployee::make()->action($this->organisation, [
        ...\App\Models\HumanResources\Employee::factory()->make(['organisation_id' => $this->organisation->id])->toArray(),
        'worker_number'   => 'W'.rand(1000, 9999),
        'alias'           => 'Alias '.rand(1000, 9999),
        'type'            => \App\Enums\HumanResources\Employee\EmployeeTypeEnum::EMPLOYEE,
        'employment_type' => \App\Enums\HumanResources\Employee\EmploymentTypeEnum::FULL_TIME,
        'state'           => \App\Enums\HumanResources\Employee\EmployeeStateEnum::WORKING,
    ]);
    $this->guest->getUser()->employees()->attach($worker->id, ['group_id' => $this->group->id, 'organisation_id' => $this->organisation->id]);

    $asked     = StoreTrialRunJobOrder::make()->action($this->production, ['artefact_id' => $experiment->id, 'quantity' => 3, 'employee_id' => $worker->id, 'product_development' => 'DEV-9999']);
    $own       = StoreTrialRunJobOrder::make()->action($this->production, ['artefact_id' => $experiment->id, 'quantity' => 3, 'employee_id' => $worker->id]);
    $askedTask = $asked->jobOrderItems()->first()->tasks()->first();
    expect($asked->employee_id)->toBeNull();
    $asked->update(['employee_id' => $worker->id]);
    $floor      = fn () => collect(get(route('grp.org.productions.show.floor', [$this->organisation->slug, $this->production->slug]))->viewData('page')['props']['tasks'])->pluck('id');
    $showProps  = fn () => get(route('grp.org.productions.show.operations.job-orders.show', [$this->organisation->slug, $this->production->slug, $asked->slug]))->assertOk()->viewData('page')['props'];

    expect($asked->isAwaitingRelease())->toBeTrue()
        ->and($own->isAwaitingRelease())->toBeFalse()
        ->and($floor())->not->toContain($askedTask->id)
        ->and($floor())->toContain($own->jobOrderItems()->first()->tasks()->first()->id)
        ->and(fn () => StartManufactureTaskSession::make()->action($this->guest->getUser(), $askedTask))
        ->toThrow(ValidationException::class, 'This job order has not been released to the floor');

    $developer = \App\Models\SysAdmin\User::factory()->create(['group_id' => $this->group->id, 'status' => true]);
    actingAsUserWithOnlyPermissions($developer, ["productions_rd.{$this->production->id}.edit"]);
    expect($showProps()['confirm_route'])->toBeNull()
        ->and($showProps()['update_route'])->toBeNull();
    patch(route('grp.models.job-order.confirm', ['jobOrder' => $asked->id]))->assertForbidden();

    actingAs($this->guest->getUser());
    expect($showProps()['confirm_route'])->not->toBeNull();
    patch(route('grp.models.job-order.confirm', ['jobOrder' => $asked->id]))->assertRedirect();

    expect($asked->refresh()->state)->toBe(JobOrderStateEnum::CONFIRMED)
        ->and($asked->isAwaitingRelease())->toBeFalse()
        ->and($floor())->toContain($askedTask->id);

    $this->guest->getUser()->employees()->detach($worker->id);
});

test('an experiment can not go into a normal job order, nor a launched artefact into a trial run', function () {
    $experiment = ($this->experiment)('NOJO');
    $launched   = StoreArtefact::make()->action($this->production, ['code' => 'LIVE'.rand(1000, 9999), 'name' => 'Launched', 'state' => ArtefactStateEnum::ACTIVE->value]);

    $jobOrder = StoreJobOrder::make()->action($this->production, []);
    expect(fn () => StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $experiment->id, 'quantity' => 5]))
        ->toThrow(ValidationException::class, 'is not launched yet');

    $trialRun = StoreTrialRunJobOrder::make()->action($this->production, ['artefact_id' => $experiment->id, 'quantity' => 2]);
    expect(fn () => StoreJobOrderItem::make()->action($trialRun, ['artefact_id' => $launched->id, 'quantity' => 5]))
        ->toThrow(ValidationException::class, 'is launched')
        ->and(fn () => StoreTrialRunJobOrder::make()->action($this->production, ['artefact_id' => $launched->id, 'quantity' => 2]))
        ->toThrow(ValidationException::class);
});

test('a trial run is paid as development and received at the development office, not into stock', function () {
    $experiment = ($this->experiment)('OFFICE');
    $jobOrder   = StoreTrialRunJobOrder::make()->action($this->production, ['artefact_id' => $experiment->id, 'quantity' => 4]);
    ConfirmJobOrder::make()->action($jobOrder);

    $user    = $this->guest->getUser();
    $session = StartManufactureTaskSession::make()->action($user, $jobOrder->jobOrderItems()->first()->tasks()->first());
    CloseManufactureTaskSession::make()->action($session, ['quantity_made' => 4]);

    expect($session->refresh()->activity_type)->toBe(ManufactureTaskSessionActivityTypeEnum::DEVELOPMENT);

    $movements = OrgStockMovement::count();
    $jobOrder  = ReceiveJobOrderIntoStock::make()->action($jobOrder->refresh(), []);
    $item      = $jobOrder->jobOrderItems()->first();

    expect($jobOrder->refresh()->state)->toBe(JobOrderStateEnum::RECEIVED)
        ->and((float) $item->quantity_received)->toBe(4.0)
        ->and($item->data['put_away'][ReceiveJobOrderIntoStock::DEVELOPMENT_OFFICE])->toEqual(4)
        ->and(OrgStockMovement::count())->toBe($movements);
});

test('an experiment launches only once it can be sold', function () {
    $experiment = ($this->experiment)('LAUNCH');

    expect(fn () => LaunchArtefact::make()->action($experiment))
        ->toThrow(ValidationException::class, 'Not ready to launch');

    [, , $shop]  = createShop();
    [, $product] = createProduct($shop);
    $orgStock    = labelTestOrgStock($this->organisation, $this->group);
    DB::table('product_has_org_stocks')->insert(['product_id' => $product->id, 'org_stock_id' => $orgStock->id, 'quantity' => 1, 'created_at' => now(), 'updated_at' => now()]);
    $product->update(['is_for_sale' => true]);
    $experiment->update(['org_stock_id' => $orgStock->id, 'is_in_development' => true]);

    $launched = LaunchArtefact::make()->action($experiment->refresh(), $this->guest->getUser());

    expect($launched->state)->toBe(ArtefactStateEnum::ACTIVE)
        ->and($launched->is_in_development)->toBeFalse()
        ->and($launched->data['launch']['user_id'])->toBe($this->guest->getUser()->id)
        ->and(($this->listedArtefactIds)())->toContain($launched->id)
        ->and(fn () => SetArtefactState::make()->action($launched, ArtefactStateEnum::IN_PROCESS))
        ->toThrow(ValidationException::class, 'can not go back to development');
});

test('an experiment is cancelled with a reason, never while a trial run is open, and never launched after', function () {
    $experiment = ($this->experiment)('CANCEL');
    $jobOrder   = StoreTrialRunJobOrder::make()->action($this->production, ['artefact_id' => $experiment->id, 'quantity' => 1]);

    expect(fn () => CancelArtefactDevelopment::make()->action($experiment, ['reason' => 'Too expensive']))
        ->toThrow(ValidationException::class, $jobOrder->reference);

    $jobOrder->update(['state' => JobOrderStateEnum::NOT_RECEIVED]);
    $cancelled = CancelArtefactDevelopment::make()->action($experiment->refresh(), ['reason' => 'Too expensive'], $this->guest->getUser());

    expect($cancelled->state)->toBe(ArtefactStateEnum::CANCELLED)
        ->and($cancelled->data['cancellation']['reason'])->toBe('Too expensive')
        ->and(fn () => LaunchArtefact::make()->action($cancelled))->toThrow(ValidationException::class)
        ->and(SetArtefactsState::make()->action($this->production, ['artefacts' => [$cancelled->id], 'state' => ArtefactStateEnum::ACTIVE->value]))->toBe(0)
        ->and(fn () => SetArtefactState::make()->action($cancelled, ArtefactStateEnum::ACTIVE))
        ->toThrow(ValidationException::class, 'stays cancelled');
});

test('UI development room', function () {
    $experiment = ($this->experiment)('ROOM');

    get(route('grp.org.productions.show.crafts.development', [$this->organisation->slug, $this->production->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Org/Production/DevelopmentRoom')
            ->where('tabs.current', 'experiments')
            ->has('stats', 4)
            ->has('actions.trial_run_route')
            ->has('experiments.data', fn (AssertableInertia $rows) => $rows->etc())
            ->etc());

    expect($experiment->state)->toBe(ArtefactStateEnum::IN_PROCESS);
});

test('a trial run carried over to the next shift stays a trial run', function () {
    $experiment = ($this->experiment)('CARRY');
    $jobOrder   = StoreTrialRunJobOrder::make()->action($this->production, ['artefact_id' => $experiment->id, 'quantity' => 10]);
    ConfirmJobOrder::make()->action($jobOrder);

    $item    = $jobOrder->jobOrderItems()->first();
    $session = StartManufactureTaskSession::make()->action($this->guest->getUser(), $item->tasks()->first());
    CloseManufactureTaskSession::make()->action($session, ['quantity_made' => 4, 'outcome' => 'carry_over']);

    $carried = \App\Models\Production\JobOrder::find($item->refresh()->data['carried_to']);

    expect($session->refresh()->state)->toBe(ManufactureTaskSessionStateEnum::CLOSED)
        ->and($carried->isTrialRun())->toBeTrue()
        ->and($carried->jobOrderItems()->first()->artefact_id)->toBe($experiment->id);
});

test('a cancelled experiment is never revived or moved by a plain change of state', function () {
    $experiment = ($this->experiment)('REVIVE');

    expect(fn () => SetArtefactState::make()->action($experiment, ArtefactStateEnum::CANCELLED))
        ->toThrow(ValidationException::class, 'is cancelled from the Development room');

    $cancelled = CancelArtefactDevelopment::make()->action($experiment, ['reason' => 'Formula unstable']);

    expect(fn () => SetArtefactState::make()->action($cancelled, ArtefactStateEnum::IN_PROCESS))
        ->toThrow(ValidationException::class, 'stays cancelled');
});

test('the board and shortfall never raise a job order for an experiment', function () {
    $organisation = $this->production->organisation;
    $orgStock     = labelTestOrgStock($organisation, $this->group);
    $experiment   = ($this->experiment)('BOARD');
    $experiment->update(['org_stock_id' => $orgStock->id]);

    $result = \App\Actions\Dispatching\FulfilmentGate\StoreJobOrderFromShortfall::make()->handle($organisation, [
        ['org_stock_id' => $orgStock->id, 'quantity' => 5],
    ]);

    expect($experiment->jobOrderItems()->count())->toBe(0)
        ->and(collect($result)->flatten()->implode(' '))->toContain('not manufacturable here');
});

test('a family whose artefacts were all cancelled in development is cancelled', function () {
    $hydrator = \App\Actions\Production\ArtefactFamily\Hydrators\ArtefactFamilyHydrateArtefacts::make();

    expect($hydrator->state(['cancelled' => 3]))->toBe(ArtefactStateEnum::CANCELLED)
        ->and($hydrator->state(['cancelled' => 2, 'in_process' => 1]))->toBe(ArtefactStateEnum::IN_PROCESS)
        ->and($hydrator->state(['cancelled' => 2, 'discontinued' => 1]))->toBe(ArtefactStateEnum::DISCONTINUED)
        ->and($hydrator->state([]))->toBe(ArtefactStateEnum::IN_PROCESS);
});

test('an artefact for an SKO already in stock is launched, not an experiment, and goes into a normal job order', function () {
    $orgStock = labelTestOrgStock($this->production->organisation, $this->group);
    $orgStock->update(['quantity_in_locations' => 10710]);
    $fresh = labelTestOrgStock($this->production->organisation, $this->group);

    $live       = StoreArtefact::make()->action($this->production, ['code' => 'LIVE'.rand(1000, 9999), 'name' => 'Live', 'org_stock_id' => $orgStock->id]);
    $experiment = StoreArtefact::make()->action($this->production, ['code' => 'NEW'.rand(1000, 9999), 'name' => 'New', 'org_stock_id' => $fresh->id]);
    $jobOrder   = StoreJobOrder::make()->action($this->production, []);

    expect($live->state)->toBe(ArtefactStateEnum::ACTIVE)
        ->and($experiment->state)->toBe(ArtefactStateEnum::IN_PROCESS)
        ->and(StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $live->id, 'quantity' => 5])->artefact_id)->toBe($live->id);
});

test('an experiment raised from the development room stays an experiment even on an SKO already in stock', function () {
    $orgStock = labelTestOrgStock($this->production->organisation, $this->group);
    $orgStock->update(['quantity_in_locations' => 500]);
    $code = 'DEV'.rand(1000, 9999);

    $this->post(route('grp.models.production.artefacts.store', [$this->production->id, 'development' => 1]), ['code' => $code, 'name' => 'Reformulation', 'org_stock_id' => $orgStock->id])
        ->assertRedirect();

    expect(Artefact::where('code', $code)->first()->state)->toBe(ArtefactStateEnum::IN_PROCESS);
});

/**
 * A launched artefact with a two step recipe, oil on the first step.
 *
 * @return array{0: Artefact, 1: RawMaterial, 2: \App\Models\Production\ManufactureTask}
 */
function branchTestOriginal(object $test): array
{
    $boxing = stepTestManufactureTask($test->production, 'DEVBOX', 'Boxing');
    $oil    = RawMaterial::where('code', 'BRANCH-OIL')->first() ?? StoreRawMaterial::make()->action($test->production, [
        'type'        => RawMaterialTypeEnum::CONSUMABLE->value,
        'state'       => RawMaterialStateEnum::ORPHAN->value,
        'code'        => 'BRANCH-OIL',
        'description' => 'Branch oil',
        'unit'        => RawMaterialUnitEnum::KILOGRAM->value,
        'unit_cost'   => 4,
    ]);

    $original = StoreArtefact::make()->action($test->production, [
        'code'                   => 'ORIG'.rand(1000, 9999),
        'name'                   => 'Lavender soap',
        'state'                  => ArtefactStateEnum::ACTIVE->value,
        'recommended_batch_size' => 24,
        'shelf_life_days'        => 365,
    ]);

    SetArtefactsRecipe::make()->action($test->production, [
        'artefacts' => [$original->id],
        'steps'     => [
            ['manufacture_task_id' => $test->manufactureTask->id, 'position' => 1, 'units_per_artefact' => 1, 'standard_rate' => 40, 'raw_materials' => [['raw_material_id' => $oil->id, 'quantity_per_unit' => 0.2]]],
            ['manufacture_task_id' => $boxing->id, 'position' => 2, 'units_per_artefact' => 1, 'blocks_later_steps' => false],
        ],
    ]);

    return [$original->refresh(), $oil, $boxing];
}

test('a branch copies the launched artefact recipe and the original is left alone', function () {
    [$original, $oil, $boxing] = branchTestOriginal($this);
    $recipe = unifiedRecipeSteps($original);

    $branch = BranchArtefact::make()->action($original, $this->guest->getUser());

    expect($branch->state)->toBe(ArtefactStateEnum::IN_PROCESS)
        ->and($branch->branched_from_id)->toBe($original->id)
        ->and($branch->code)->toBe($original->code.'-B1')
        ->and($branch->org_stock_id)->toBeNull()
        ->and($branch->recommended_batch_size)->toBe(24)
        ->and($branch->shelf_life_days)->toBe(365)
        ->and(unifiedRecipeSteps($branch))->toBe($recipe)
        ->and((float) $branch->manufactureTasks()->first()->pivot->standard_rate)->toBe(40.0)
        ->and((bool) $branch->manufactureTasks()->get()->last()->pivot->blocks_later_steps)->toBeFalse()
        ->and(BranchArtefact::make()->action($original)->code)->toBe($original->code.'-B2');

    SetArtefactsRecipe::make()->action($this->production, [
        'artefacts' => [$branch->id],
        'steps'     => [['manufacture_task_id' => $boxing->id, 'position' => 1, 'units_per_artefact' => 1, 'raw_materials' => [['raw_material_id' => $oil->id, 'quantity_per_unit' => 0.5]]]],
    ]);

    expect(unifiedRecipeSteps($original->refresh()))->toBe($recipe)
        ->and($original->state)->toBe(ArtefactStateEnum::ACTIVE)
        ->and(fn () => BranchArtefact::make()->action($branch))->toThrow(ValidationException::class, 'is not launched');
});

test('merging a branch back replaces the original recipe, keeps the old one in its history and ends the branch', function () {
    [$original, $oil, $boxing] = branchTestOriginal($this);
    $previous = unifiedRecipeSteps($original);
    $branch   = BranchArtefact::make()->action($original);

    SetArtefactsRecipe::make()->action($this->production, [
        'artefacts' => [$branch->id],
        'steps'     => [
            ['manufacture_task_id' => $this->manufactureTask->id, 'position' => 1, 'units_per_artefact' => 1, 'raw_materials' => [['raw_material_id' => $oil->id, 'quantity_per_unit' => 0.3]]],
        ],
    ]);
    $redesigned = unifiedRecipeSteps($branch);

    $trialRun = StoreTrialRunJobOrder::make()->action($this->production, ['artefact_id' => $branch->id, 'quantity' => 2]);
    expect(fn () => MergeArtefactBranch::make()->action($branch->refresh()))->toThrow(ValidationException::class, $trialRun->reference);
    $trialRun->update(['state' => JobOrderStateEnum::NOT_RECEIVED]);

    $merged = MergeArtefactBranch::make()->action($branch->refresh(), $this->guest->getUser());

    expect($merged->id)->toBe($original->id)
        ->and(unifiedRecipeSteps($original->refresh()))->toBe($redesigned)
        ->and($original->state)->toBe(ArtefactStateEnum::ACTIVE)
        ->and($original->data['recipe_history'][0]['replaced_by_branch']['id'])->toBe($branch->id)
        ->and(collect($original->data['recipe_history'][0]['steps'])->pluck('manufacture_task_id')->all())->toBe(collect($previous)->pluck('manufacture_task_id')->all())
        ->and($branch->refresh()->state)->toBe(ArtefactStateEnum::MERGED)
        ->and($branch->data['merge']['into_id'])->toBe($original->id)
        ->and(fn () => SetArtefactState::make()->action($branch, ArtefactStateEnum::IN_PROCESS))->toThrow(ValidationException::class, 'stays merged')
        ->and(fn () => StoreTrialRunJobOrder::make()->action($this->production, ['artefact_id' => $branch->id, 'quantity' => 1]))->toThrow(ValidationException::class)
        ->and(fn () => MergeArtefactBranch::make()->action($branch))->toThrow(ValidationException::class)
        ->and(fn () => LaunchArtefact::make()->action($branch))->toThrow(ValidationException::class)
        ->and(($this->listedArtefactIds)())->not->toContain($branch->id);
});

test('merging back asks first when open job orders of the original change, then they follow the new recipe', function () {
    [$original, , $boxing] = branchTestOriginal($this);
    $branch  = BranchArtefact::make()->action($original);
    $filling = stepTestManufactureTask($this->production, 'DEVFILL', 'Filling');

    SetArtefactsRecipe::make()->action($this->production, [
        'artefacts' => [$branch->id],
        'steps'     => [
            ['manufacture_task_id' => $this->manufactureTask->id, 'position' => 1, 'units_per_artefact' => 1],
            ['manufacture_task_id' => $filling->id, 'position' => 2, 'units_per_artefact' => 1],
            ['manufacture_task_id' => $boxing->id, 'position' => 3, 'units_per_artefact' => 1],
        ],
    ]);

    $jobOrder = StoreJobOrder::make()->action($this->production, []);
    $item     = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $original->id, 'quantity' => 10]);

    $this->post(route('grp.models.production.artefacts.merge_back', [$this->production->id, $branch->id]))
        ->assertSessionHasErrors('confirm_open_jobs');
    expect($branch->refresh()->state)->toBe(ArtefactStateEnum::IN_PROCESS);

    $this->post(route('grp.models.production.artefacts.merge_back', [$this->production->id, $branch->id]), ['confirm_open_jobs' => true])
        ->assertSessionHasNoErrors();

    expect($branch->refresh()->state)->toBe(ArtefactStateEnum::MERGED)
        ->and($item->tasks()->pluck('manufacture_task_id')->all())->toContain($filling->id);
});

test('a branch launches as its own product only with its own SKO', function () {
    [$original] = branchTestOriginal($this);
    $recipe     = unifiedRecipeSteps($original);

    [, , $shop]  = createShop();
    [, $product] = createProduct($shop);
    $orgStock    = labelTestOrgStock($this->organisation, $this->group);
    DB::table('product_has_org_stocks')->insert(['product_id' => $product->id, 'org_stock_id' => $orgStock->id, 'quantity' => 1, 'created_at' => now(), 'updated_at' => now()]);
    $product->update(['is_for_sale' => true]);
    $original->update(['org_stock_id' => $orgStock->id]);

    $branch = BranchArtefact::make()->action($original);
    $branch->update(['org_stock_id' => $orgStock->id]);
    expect(fn () => LaunchArtefact::make()->action($branch->refresh()))->toThrow(ValidationException::class, 'its own SKO');

    [, $spinOffProduct] = createProduct($shop);
    $spinOffStock       = labelTestOrgStock($this->organisation, $this->group);
    DB::table('product_has_org_stocks')->insert(['product_id' => $spinOffProduct->id, 'org_stock_id' => $spinOffStock->id, 'quantity' => 1, 'created_at' => now(), 'updated_at' => now()]);
    $spinOffProduct->update(['is_for_sale' => true]);
    $branch->update(['org_stock_id' => $spinOffStock->id]);

    $launched = LaunchArtefact::make()->action($branch->refresh());

    expect($launched->state)->toBe(ArtefactStateEnum::ACTIVE)
        ->and($launched->branched_from_id)->toBe($original->id)
        ->and(unifiedRecipeSteps($original->refresh()))->toBe($recipe)
        ->and(fn () => MergeArtefactBranch::make()->action($launched))->toThrow(ValidationException::class, 'is not a branch in development');
});

test('a cancelled branch leaves the original unchanged', function () {
    [$original] = branchTestOriginal($this);
    $recipe     = unifiedRecipeSteps($original);
    $branch     = BranchArtefact::make()->action($original);

    CancelArtefactDevelopment::make()->action($branch, ['reason' => 'Scent did not hold']);

    expect($branch->refresh()->state)->toBe(ArtefactStateEnum::CANCELLED)
        ->and(unifiedRecipeSteps($original->refresh()))->toBe($recipe)
        ->and($original->state)->toBe(ArtefactStateEnum::ACTIVE)
        ->and(fn () => MergeArtefactBranch::make()->action($branch))->toThrow(ValidationException::class);
});

test('UI branch from the artefact page and merge back from the development room', function () {
    [$original] = branchTestOriginal($this);

    get(route('grp.org.productions.show.crafts.artefacts.show', [$this->organisation->slug, $this->production->slug, $original->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Org/Production/Artefact')
            ->where('pageHead.actions.0.route.name', 'grp.models.production.artefacts.branch')
            ->etc());

    $this->getJson(route('grp.json.production.artefacts.index', [$this->production->id, 'filter' => ['global' => $original->code]]))
        ->assertOk()
        ->assertJsonFragment(['id' => $original->id, 'code' => $original->code]);

    $this->post(route('grp.models.production.artefacts.branch', [$this->production->id, $original->id]))->assertRedirect();
    $branch = Artefact::where('branched_from_id', $original->id)->latest('id')->first();

    get(route('grp.org.productions.show.crafts.development', [$this->organisation->slug, $this->production->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Org/Production/DevelopmentRoom')
            ->has('actions.merge_route')
            ->has('actions.branch_route')
            ->etc());

    MergeArtefactBranch::make()->action($branch);

    get(route('grp.org.productions.show.crafts.development', [$this->organisation->slug, $this->production->slug, 'tab' => 'merged']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('tabs.current', 'merged')
            ->has('merged.data', fn (AssertableInertia $rows) => $rows->etc())
            ->etc());

    get(route('grp.org.productions.show.crafts.artefacts.show', [$this->organisation->slug, $this->production->slug, $branch->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('pageHead.model', 'Branch of '.$original->code)
            ->where('pageHead.actions.0', null)
            ->etc());
});

test('an experiment whose SKO sells only through a product exclusive to a customer can launch', function () {
    $experiment = ($this->experiment)('EXCL');

    [, , $shop]  = createShop();
    [, $product] = createProduct($shop);
    $orgStock    = labelTestOrgStock($this->organisation, $this->group);
    DB::table('product_has_org_stocks')->insert(['product_id' => $product->id, 'org_stock_id' => $orgStock->id, 'quantity' => 1, 'created_at' => now(), 'updated_at' => now()]);
    $product->updateQuietly(['is_for_sale' => false, 'state' => \App\Enums\Catalogue\Product\ProductStateEnum::ACTIVE, 'exclusive_for_customer_id' => createCustomer($shop)->id]);
    $experiment->update(['org_stock_id' => $orgStock->id, 'is_in_development' => true]);

    expect(\App\Actions\Production\Artefact\GetArtefactLaunchReadiness::run($experiment->refresh())['ready'])->toBeTrue()
        ->and(LaunchArtefact::make()->action($experiment, $this->guest->getUser())->state)->toBe(ArtefactStateEnum::ACTIVE);
});

test('raw materials download as numbers, only those matching the search', function () {
    [, $oil] = branchTestOriginal($this);
    $oil->update(['unit_cost' => 4.25]);

    $rows = \App\Actions\Production\RawMaterial\ExportRawMaterials::make()->rows($this->production, $oil->code);

    expect($rows)->toHaveCount(1)
        ->and($rows[0][0])->toBe($oil->code)
        ->and($rows[0][2])->toBe(RawMaterialUnitEnum::labels()[RawMaterialUnitEnum::KILOGRAM->value])
        ->and($rows[0][5])->toBe(4.25)
        ->and($rows[0][7])->toBeGreaterThanOrEqual(1)
        ->and(\App\Actions\Production\RawMaterial\ExportRawMaterials::make()->rows($this->production, 'NO-SUCH-RAW-MATERIAL'))->toBe([]);

    get(route('grp.org.productions.show.crafts.raw_materials.index', [$this->organisation->slug, $this->production->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('pageHead.actions.0.key', 'download')
            ->etc());

    get(route('grp.org.productions.show.crafts.raw_materials.export', [$this->organisation->slug, $this->production->slug, 'search' => $oil->code]))
        ->assertOk()
        ->assertDownload('raw-materials-'.$this->production->slug.'-'.now()->toDateString().'.xlsx');

    $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load(tap(tempnam(sys_get_temp_dir(), 'rm').'.xlsx', fn (string $path) => file_put_contents($path, \Maatwebsite\Excel\Facades\Excel::raw(
        new \App\Exports\Production\RawMaterialsExport([['RM-0', 'Zero', null, null, null, 0.0, 0.0, 0, null]], 'GBP'),
        \Maatwebsite\Excel\Excel::XLSX
    ))))->getActiveSheet();

    expect($sheet->getCell('F1')->getValue())->toBe('Unit cost GBP')
        ->and($sheet->getCell('F2')->getValue())->toBe(0.0)
        ->and($sheet->getCell('H2')->getValue())->toBe(0)
        ->and($sheet->getCell('C2')->getValue())->toBeNull();

    $accountant = \App\Models\SysAdmin\User::factory()->create(['group_id' => $this->group->id]);
    $accountant->givePermissionTo(["accounting.{$this->production->organisation_id}.view"]);
    $parameters = [$this->organisation->slug, $this->production->slug];
    $crafts     = \App\Actions\UI\Grp\Layout\GetProductionNavigation::run($this->production, $accountant);

    expect(collect($crafts['crafts']['topMenu']['subSections'])->pluck('label')->all())->toBe([__('Dashboard'), __('Raw materials')])
        ->and($crafts)->not->toHaveKey('operations');

    actingAs($accountant);
    get(route('grp.org.productions.show.crafts.dashboard', $parameters))->assertOk();
    get(route('grp.org.productions.show.crafts.raw_materials.index', $parameters))->assertOk();
    get(route('grp.org.productions.show.crafts.raw_materials.show', [...$parameters, $oil->slug]))->assertOk();
    get(route('grp.org.productions.show.crafts.raw_materials.export', $parameters))->assertOk();
    get(route('grp.org.productions.show.crafts.artefacts.index', $parameters))->assertForbidden();

    actingAs($this->guest->getUser());
});

function developmentSession(\App\Models\Production\JobOrderItemTask $task, \App\Models\HumanResources\Employee $employee, \Illuminate\Support\Carbon $startedAt, int $minutes, float $made, int $userId): ManufactureTaskSession
{
    return ManufactureTaskSession::create([
        'group_id'               => $task->group_id,
        'organisation_id'        => $task->organisation_id,
        'production_id'          => $task->production_id,
        'job_order_item_task_id' => $task->id,
        'manufacture_task_id'    => $task->manufacture_task_id,
        'user_id'                => $userId,
        'employee_id'            => $employee->id,
        'state'                  => ManufactureTaskSessionStateEnum::CLOSED,
        'started_at'             => $startedAt,
        'ended_at'               => $startedAt->copy()->addMinutes($minutes),
        'quantity_made'          => $made,
        'activity_type'          => ManufactureTaskSessionActivityTypeEnum::DEVELOPMENT,
    ]);
}

test('a development step leaves out first runs, short sessions and outliers and proposes the median per hour', function () {
    $session  = fn (int $id, int $employee, float $rate, float $hours) => ['id' => $id, 'employee_id' => $employee, 'rate' => $rate, 'hours' => $hours, 'excluded' => null];
    $settings = Production::DEFAULT_DEVELOPMENT_REVIEW;

    $classified = \App\Actions\Production\Artefact\GetArtefactDevelopmentProposal::make()->classify(collect([
        $session(1, 1, 100, 1),
        $session(2, 2, 90, 1),
        $session(3, 1, 120, 1),
        $session(4, 2, 125, 1),
        $session(5, 1, 300, 0.05),
        $session(6, 1, 130, 1),
        $session(7, 2, 128, 1),
        $session(8, 2, 400, 1),
    ]), $settings)->pluck('excluded', 'id')->all();

    expect($classified)->toBe([1 => 'first_run', 2 => 'first_run', 3 => null, 4 => null, 5 => 'too_short', 6 => null, 7 => null, 8 => 'outlier'])
        ->and(\App\Actions\Production\Artefact\GetArtefactDevelopmentProposal::quantile(collect([120.0, 125.0, 128.0, 130.0]), 0.5))->toBe(126.5);
});

function developmentFixture(object $test, string $code, ?int $familyId = null): array
{
    $artefact = ($test->experiment)($code);
    $artefact->update(['is_in_development' => true, 'artefact_family_id' => $familyId, 'recipe_changed_at' => now()->subDays(20)]);
    $jobOrder = StoreTrialRunJobOrder::make()->action($test->production, ['artefact_id' => $artefact->id, 'quantity' => 100]);

    return [$artefact->refresh(), $jobOrder->jobOrderItems()->first()->tasks()->first()];
}

test('a development product with enough runs is put on the review task, and accepting saves the targets and ends development', function () {
    [$experiment, $task] = developmentFixture($this, 'DEVTG');
    expect($experiment->development_started_at)->not->toBeNull();
    $anna   = dayPlanOperator($this->organisation);
    $boris  = dayPlanOperator($this->organisation);
    $userId = $this->guest->getUser()->id;
    $start  = now()->subDays(10)->startOfHour();

    foreach ([[$anna, 60], [$boris, 60], [$anna, 80], [$anna, 90], [$boris, 100], [$boris, 110], [$anna, 120]] as $index => [$artisan, $made]) {
        developmentSession($task, $artisan, $start->copy()->addHours($index * 2), 60, $made, $userId);
    }
    $waitingForTimes = developmentSession($task, $boris, $start->copy()->addDay(), 60, 500, $userId);
    $waitingForTimes->update(['left_open_after_shift_at' => now()->subDays(9)]);

    $proposal = \App\Actions\Production\Artefact\GetArtefactDevelopmentProposal::run($experiment);
    $step     = $proposal['steps'][0];

    expect($step['sessions_count'])->toBe(5)
        ->and($step['excluded_count'])->toBe(2)
        ->and($step['artisans_count'])->toBe(2)
        ->and($step['hours'])->toEqual(5)
        ->and($step['proposed_target'])->toEqual(100)
        ->and($step['is_ready'])->toBeTrue()
        ->and($proposal['is_overdue'])->toBeTrue();

    $reviewer = $this->guest->getUser();
    \App\Models\Tasks\StaffTask::where('data->kind', 'artefact_development_review')->where('data->production_id', $this->production->id)->update(['status' => \App\Enums\Tasks\StaffTaskStatusEnum::DONE]);
    \App\Actions\Production\Production\SetDevelopmentReviewSettings::make()->action($this->production, [...$this->production->developmentReviewSettings(), 'reviewer_ids' => [$reviewer->id]]);
    \App\Actions\Production\Artefact\AskToReviewArtefactDevelopment::run($this->production->refresh());
    \App\Actions\Production\Artefact\AskToReviewArtefactDevelopment::run($this->production);

    $reviewTask = fn () => \App\Actions\Production\Artefact\AskToReviewArtefactDevelopment::openTaskFor($this->production);
    $line       = fn () => collect($reviewTask()?->data['subtasks'] ?? [])->firstWhere('artefact_id', $experiment->id);
    expect($reviewTask()->assignee_id)->toBe($reviewer->id)
        ->and(collect($reviewTask()->data['subtasks'])->where('artefact_id', $experiment->id)->count())->toBe(1)
        ->and($line()['title'])->toContain($experiment->code);

    $taskId = $reviewTask()->id;
    \App\Actions\Production\Artefact\DecideArtefactDevelopmentReview::make()->action($experiment, ['decision' => 'accepted', 'targets' => [$step['id'] => 100]]);

    $experiment->refresh();
    $lineAfter = collect(\App\Models\Tasks\StaffTask::find($taskId)->data['subtasks'])->firstWhere('artefact_id', $experiment->id);
    expect((float) $experiment->recipeSteps()->first()->standard_rate)->toBe(100.0)
        ->and($experiment->is_in_development)->toBeFalse()
        ->and($experiment->data['development_review']['decision'])->toBe('accepted')
        ->and($lineAfter['status'])->toBe('done');
});

test('a development step pools the same step of its family, and development goes on while another step has no target', function () {
    $department = \App\Actions\Production\ArtefactDepartment\StoreArtefactDepartment::make()->action($this->production, ['code' => 'DVD'.rand(1000, 9999), 'name' => 'Development pool']);
    $family     = \App\Actions\Production\ArtefactFamily\StoreArtefactFamily::make()->action($department, ['code' => 'DVF'.rand(1000, 9999), 'name' => 'Development pool family']);
    [$first, $firstTask]   = developmentFixture($this, 'DEVPA', $family->id);
    [$second, $secondTask] = developmentFixture($this, 'DEVPB', $family->id);
    $artisans = [dayPlanOperator($this->organisation), dayPlanOperator($this->organisation)];
    $start    = now()->subDays(10)->startOfHour();

    foreach (range(0, 7) as $index) {
        developmentSession($index % 2 ? $secondTask : $firstTask, $artisans[intdiv($index, 4)], $start->copy()->addHours($index * 2), 60, 100, $this->guest->getUser()->id);
    }

    $packing = stepTestManufactureTask($this->production, 'DEVPK2', 'Packing');
    $packing->update(['is_piece_rate' => true]);
    \App\Actions\Production\Artefact\AttachManufactureTaskToArtefact::make()->action($second, ['manufacture_task_id' => $packing->id, 'position' => 2, 'units_per_artefact' => 1]);
    $second->refresh()->update(['recipe_changed_at' => now()->subDays(20)]);

    $proposal = \App\Actions\Production\Artefact\GetArtefactDevelopmentProposal::run($first->refresh());
    expect($proposal['steps'][0]['sessions_count'])->toBe(6)
        ->and($proposal['steps'][0]['is_ready'])->toBeTrue();

    $secondSteps = \App\Actions\Production\Artefact\GetArtefactDevelopmentProposal::run($second->refresh())['steps'];
    \App\Actions\Production\Artefact\DecideArtefactDevelopmentReview::make()->action($second, ['decision' => 'accepted', 'targets' => [$secondSteps[0]['id'] => 100]]);

    expect($second->refresh()->is_in_development)->toBeTrue();
});

test('a development product kept with a reason and one whose recipe changed are not ready again until new runs come in', function () {
    [$experiment, $task] = developmentFixture($this, 'DEVKP');
    $artisans = [dayPlanOperator($this->organisation), dayPlanOperator($this->organisation)];
    $start    = now()->subDays(10)->startOfHour();

    foreach (range(0, 7) as $index) {
        developmentSession($task, $artisans[$index % 2], $start->copy()->addHours($index * 2), 60, 100, $this->guest->getUser()->id);
    }
    expect(\App\Actions\Production\Artefact\GetArtefactDevelopmentProposal::run($experiment)['is_ready'])->toBeTrue();

    expect(fn () => \App\Actions\Production\Artefact\DecideArtefactDevelopmentReview::make()->action($experiment, ['decision' => 'kept']))->toThrow(ValidationException::class);

    \App\Actions\Production\Artefact\DecideArtefactDevelopmentReview::make()->action($experiment, ['decision' => 'kept', 'reason' => 'New mould next week']);
    $proposal = \App\Actions\Production\Artefact\GetArtefactDevelopmentProposal::run($experiment->refresh());
    expect($experiment->is_in_development)->toBeTrue()
        ->and($proposal['is_ready'])->toBeFalse()
        ->and($proposal['kept']['reason'])->toBe('New mould next week');

    $experiment->update(['data' => \Illuminate\Support\Arr::except($experiment->data, 'development_review')]);
    \App\Actions\Production\Artefact\AttachManufactureTaskToArtefact::make()->action($experiment, ['manufacture_task_id' => stepTestManufactureTask($this->production, 'DEVPACK', 'Packing')->id, 'position' => 2, 'units_per_artefact' => 1]);
    $proposal = \App\Actions\Production\Artefact\GetArtefactDevelopmentProposal::run($experiment->refresh());

    expect($experiment->recipe_changed_at->isToday())->toBeTrue()
        ->and($proposal['steps'][0]['sessions_count'])->toBe(0)
        ->and($proposal['is_ready'])->toBeFalse();
});

test('UI paid as development tab', function () {
    $experiment = ($this->experiment)('DEVUI');
    $experiment->update(['is_in_development' => true]);

    get(route('grp.org.productions.show.crafts.development', [$this->organisation->slug, $this->production->slug, 'tab' => 'targets']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Org/Production/DevelopmentRoom')
            ->where('tabs.current', 'targets')
            ->where('development_review.can_decide', true)
            ->where('targets.data', fn ($rows) => collect($rows)->contains('code', $experiment->code))
            ->etc());
});
