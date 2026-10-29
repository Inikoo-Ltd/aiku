<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 29 Oct 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Feature;

use App\Actions\Production\Artefact\CancelArtefactDevelopment;
use App\Actions\Production\Artefact\LaunchArtefact;
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
use App\Enums\Production\Artefact\ArtefactStateEnum;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionActivityTypeEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum;
use App\Models\Inventory\OrgStockMovement;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Models\Production\Artefact;
use App\Models\Production\ManufactureTaskSession;
use App\Models\Production\Production;
use Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

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

    Config::set('inertia.testing.page_paths', [resource_path('js/Pages/Grp')]);
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
