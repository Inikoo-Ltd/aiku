<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 25 Nov 2024 11:48:10 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Feature;

use App\Actions\Production\Artefact\StoreArtefact;
use App\Actions\Production\Artefact\SetArtefactsBatchSize;
use App\Actions\Production\Artisan\AttachArtisan;
use App\Actions\Production\PartnerShippingList\GetMixesToPrepare;
use App\Actions\Production\PartnerShippingList\GetMixJobOrders;
use App\Actions\Production\Artefact\SetArtefactAsMix;
use App\Actions\Production\JobOrderItem\GetJobOrderItemMissingMixes;
use App\Actions\Production\PartnerShippingList\StoreJobOrdersForMixes;
use App\Actions\HumanResources\Employee\StoreEmployee;
use App\Models\HumanResources\Employee;
use App\Actions\Production\JobOrder\ConfirmJobOrder;
use App\Actions\Production\JobOrder\StoreJobOrder;
use App\Actions\Production\ManufactureTask\StoreManufactureTask;
use App\Actions\Production\Artefact\AttachRawMaterialToRecipeStep;
use App\Models\Production\ArtefactManufactureTask;
use App\Actions\Production\JobOrderItem\StoreJobOrderItem;
use App\Actions\Production\ManufactureTaskSession\CloseManufactureTaskSession;
use App\Actions\Production\ManufactureTaskSession\StartManufactureTaskSession;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
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
use App\Models\Production\ManufactureTaskSession;
use App\Models\Production\JobOrderItem;
use App\Models\Production\ManufactureTask;
use App\Models\Production\Production;
use App\Models\Production\RawMaterial;
use Config;
use Illuminate\Validation\ValidationException;

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
    $mixArtefact = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'MIX-BASE', 'name' => 'Bath bomb base mix']);
    $mix         = UpdateRawMaterial::make()->action($this->rawMaterial, ['artefact_id' => $mixArtefact->id, 'quantity_on_location' => 2]);
    $mix->update(['org_stock_id' => null]);

    $this->artefact->manufactureTasks()->sync([
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
        ->and($created[0]->jobOrderItems()->first()->quantity)->toBe((int) ceil($shortfall))
        ->and($created[0]->jobOrderItems()->first()->data['batch_code'])->toBe($created[0]->reference.'-'.$mixArtefact->code)
        ->and($created[0]->jobOrderItems()->first()->data['expiry_date'])->toBeNull();

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

test('assigning a line to an artisan refreshes the manufacture floor tablets', function () {
    $modelData                    = Employee::factory()->make(['organisation_id' => $this->organisation->id])->toArray();
    $modelData['worker_number']   = 'W'.rand(1000, 9999);
    $modelData['alias']           = 'Alias '.rand(1000, 9999);
    $modelData['type']            = \App\Enums\HumanResources\Employee\EmployeeTypeEnum::EMPLOYEE;
    $modelData['employment_type'] = \App\Enums\HumanResources\Employee\EmploymentTypeEnum::FULL_TIME;
    $modelData['state']           = \App\Enums\HumanResources\Employee\EmployeeStateEnum::WORKING;
    $artisan                      = StoreEmployee::make()->action($this->organisation, $modelData);

    $jobOrder = StoreJobOrder::make()->action($this->production, []);
    $line     = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 100]);

    \Illuminate\Support\Facades\Event::fake([\App\Events\BroadcastManufactureFloorChanged::class]);

    actingAs($this->guest->getUser());
    patch(route('grp.models.job-order-item.split', ['jobOrderItem' => $line->id]), ['assignments' => [
        ['id' => $line->id, 'employee_id' => $artisan->id, 'quantity' => 100],
    ]])->assertSessionHasNoErrors();

    \Illuminate\Support\Facades\Event::assertDispatched(\App\Events\BroadcastManufactureFloorChanged::class, fn ($event) => $event->productionId == $this->production->id);
});

test('to produce board lists each artisan and their share of a split job order', function () {
    $stocks   = createStocks($this->group);
    $orgStock = createOrgStocks($this->organisation, [$stocks[0]])[0];
    \App\Models\Production\Artefact::where('production_id', $this->production->id)->where('org_stock_id', $orgStock->id)->update(['org_stock_id' => null]);
    $artefact = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'SPLIT-01', 'name' => 'Shared between artisans']);
    $artefact->update(['org_stock_id' => $orgStock->id]);
    $packedIn = $orgStock->packed_in;
    $orgStock->update(['packed_in' => 100]);

    [$artisanA, $artisanB] = collect(range(1, 2))->map(function () {
        $modelData = Employee::factory()->make(['organisation_id' => $this->organisation->id])->toArray();
        $modelData['worker_number']   = 'W'.rand(1000, 9999);
        $modelData['alias']           = 'Alias '.rand(1000, 9999);
        $modelData['type']            = \App\Enums\HumanResources\Employee\EmployeeTypeEnum::EMPLOYEE;
        $modelData['employment_type'] = \App\Enums\HumanResources\Employee\EmploymentTypeEnum::FULL_TIME;
        $modelData['state']           = \App\Enums\HumanResources\Employee\EmployeeStateEnum::WORKING;

        return StoreEmployee::make()->action($this->organisation, $modelData);
    })->all();

    $jobOrder = StoreJobOrder::make()->action($this->production, []);
    $line     = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $artefact->id, 'quantity' => 1000]);

    actingAs($this->guest->getUser());
    patch(route('grp.models.job-order-item.split', ['jobOrderItem' => $line->id]), ['assignments' => [
        ['id' => $line->id, 'employee_id' => $artisanA->id, 'quantity' => 650],
        ['employee_id' => $artisanB->id, 'quantity' => 350],
    ]])->assertSessionHasErrors('assignments');

    patch(route('grp.models.job-order-item.split', ['jobOrderItem' => $line->id]), ['assignments' => [
        ['id' => $line->id, 'employee_id' => $artisanA->id, 'quantity' => 600],
        ['employee_id' => $artisanB->id, 'quantity' => 400],
    ]])->assertSessionHasNoErrors();
    $orgStock->update(['packed_in' => $packedIn]);

    $boardLine = \App\Models\Procurement\PartnerShoppingListItem::create([
        'group_id'        => $this->group->id,
        'organisation_id' => $this->organisation->id,
        'stock_id'        => $orgStock->stock_id,
        'org_stock_id'    => $orgStock->id,
        'quantity'        => 5,
        'job_order_id'    => $jobOrder->id,
    ]);

    $card = collect(get(route('grp.org.productions.show.to_produce.index', [$this->organisation->slug, $this->production->slug]))
        ->assertOk()->viewData('page')['props']['groups'])
        ->flatMap(fn ($lane) => $lane['items'])
        ->first(fn ($item) => data_get($item, 'job_order_id') === $jobOrder->id);

    expect(collect(data_get($card, 'job_order_sub_jobs'))->map(fn ($subJob) => [$subJob['reference'], $subJob['artisan'], $subJob['quantity']])->all())->toBe([
        [$jobOrder->reference.'-A', $artisanA->contact_name, 600.0],
        [$jobOrder->reference.'-B', $artisanB->contact_name, 400.0],
    ]);

    $boardLine->forceDelete();
    $artefact->update(['org_stock_id' => null]);
});

test('to produce board shows the artisan and name of the step a job order is on', function () {
    $stock    = \App\Actions\Goods\Stock\StoreStock::make()->action($this->group, array_merge(\App\Models\Goods\Stock::factory()->definition(), ['state' => \App\Enums\Goods\Stock\StockStateEnum::ACTIVE]));
    $orgStock = createOrgStocks($this->organisation, [$stock])[0];
    $artefact = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'STEP-'.rand(1000, 9999), 'name' => 'Made in two steps']);
    $artefact->update(['org_stock_id' => $orgStock->id]);
    $secondStep = stepTestManufactureTask($this->production);
    $artefact->manufactureTasks()->sync([
        $this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1],
        $secondStep->id            => ['position' => 2, 'units_per_artefact' => 1],
    ]);

    [$maker, $packer] = collect(range(1, 2))->map(function () {
        $modelData = Employee::factory()->make(['organisation_id' => $this->organisation->id])->toArray();
        $modelData['worker_number']   = 'W'.rand(1000, 9999);
        $modelData['alias']           = 'Alias '.rand(1000, 9999);
        $modelData['type']            = \App\Enums\HumanResources\Employee\EmployeeTypeEnum::EMPLOYEE;
        $modelData['employment_type'] = \App\Enums\HumanResources\Employee\EmploymentTypeEnum::FULL_TIME;
        $modelData['state']           = \App\Enums\HumanResources\Employee\EmployeeStateEnum::WORKING;

        return StoreEmployee::make()->action($this->organisation, $modelData);
    })->all();

    $jobOrder = StoreJobOrder::make()->action($this->production, []);
    $jobOrder->update(['employee_id' => $maker->id]);
    $line = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $artefact->id, 'quantity' => 5]);

    actingAs($this->guest->getUser());
    patch(route('grp.models.job-order-item.split', ['jobOrderItem' => $line->id]), ['assignments' => [
        ['id' => $line->id, 'employee_id' => $maker->id, 'quantity' => 3],
        ['employee_id' => $packer->id, 'quantity' => 2],
    ]])->assertSessionHasNoErrors();
    ConfirmJobOrder::make()->action($jobOrder);

    $boardLine = \App\Models\Procurement\PartnerShoppingListItem::create([
        'group_id'        => $this->group->id,
        'organisation_id' => $this->organisation->id,
        'stock_id'        => $orgStock->stock_id,
        'org_stock_id'    => $orgStock->id,
        'quantity'        => 5,
        'job_order_id'    => $jobOrder->id,
    ]);

    $card = function () use ($jobOrder) {
        foreach (get(route('grp.org.productions.show.to_produce.index', [$this->organisation->slug, $this->production->slug]))->assertOk()->viewData('page')['props']['groups'] as $lane) {
            foreach ($lane['items'] as $item) {
                if (data_get($item, 'job_order_id') === $jobOrder->id) {
                    return [$lane['label'], data_get($item, 'current_steps')];
                }
            }
        }

        return null;
    };
    $firstStep = $this->manufactureTask->name;
    $everyone  = collect([$maker->contact_name, $packer->contact_name])->sort()->values()->all();

    expect($card())->toBe(['Assigned', [['name' => $firstStep, 'artisans' => $everyone]]]);

    $firstTask = $line->tasks()->orderBy('position')->first();
    $session   = StartManufactureTaskSession::make()->action($this->guest->getUser(), $firstTask);
    CloseManufactureTaskSession::make()->action($session, ['quantity_made' => $firstTask->quantity_required]);
    expect($card())->toBe(['Producing', [
        ['name' => $firstStep, 'artisans' => [$packer->contact_name]],
        ['name' => $secondStep->name, 'artisans' => [$maker->contact_name]],
    ]]);

    $line->refresh()->update(['employee_id' => null]);
    $jobOrder->update(['employee_id' => null]);
    expect($card())->toBe(['Producing', [
        ['name' => $firstStep, 'artisans' => [$packer->contact_name]],
        ['name' => $secondStep->name, 'artisans' => [null]],
    ]]);

    $boardLine->forceDelete();
    $taskIds = \App\Models\Production\JobOrderItemTask::where('job_order_id', $jobOrder->id)->pluck('id');
    \App\Models\Production\ManufactureTaskSession::whereIn('job_order_item_task_id', $taskIds)->delete();
    \App\Models\Production\JobOrderItemTask::whereIn('id', $taskIds)->delete();
    $jobOrder->jobOrderItems()->forceDelete();
    $jobOrder->forceDelete();
    $artefact->update(['org_stock_id' => null]);
});

test('to produce queue only shows lines with an artefact in this factory', function () {
    $stocks    = createStocks($this->group);
    $orgStocks = createOrgStocks($this->organisation, [$stocks[0], $stocks[1]]);

    \App\Models\Production\Artefact::where('production_id', $this->production->id)
        ->whereIn('org_stock_id', [$orgStocks[0]->id, $orgStocks[1]->id])
        ->update(['org_stock_id' => null]);

    $made = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'GATE-01', 'name' => 'Made here']);
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
    $elsewhere = StoreArtefact::make()->action($otherProduction, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'GATE-02', 'name' => 'Made in the other factory']);
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

    $jobOrder = \App\Models\Production\JobOrder::find($covered->refresh()->job_order_id);
    $jobOrderItem = $jobOrder->jobOrderItems()->where('artefact_id', $made->id)->first();
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
    $hubRows   = fn () => collect($hubProps()['production_output']['data']);
    $hubOutput = fn () => $hubRows()->pluck('job_order_reference')->all();
    expect($hubOutput())->toContain($jobOrder->reference)
        ->and($hubProps()['tabs']['navigation']['production_output']['number'])->toBe(count(\App\Actions\Dispatching\ProductionOutput\GetFinishedProductionJobOrders::run($warehouse)));

    $stockItem        = $hubRows()->firstWhere('job_order_reference', $jobOrder->reference);
    $stockDestination = collect($stockItem['destinations'])->firstWhere('type', 'stock');
    expect($stockItem['job_order_id'])->toBe($jobOrder->id)
        ->and(array_column($stockDestination['locations'], 'code'))->toBe(array_filter([$stockDestination['location_code']]));
    expect(fn () => \App\Actions\Dispatching\ProductionOutput\PutAwayFinishedJobOrder::make()->action($warehouse, [$jobOrder->id], [$stockItem['id'] => 'L-BRD']))
        ->toThrow(ValidationException::class, 'is not kept in L-BRD yet');

    \App\Actions\Dispatching\ProductionOutput\PutAwayFinishedJobOrder::make()->action($warehouse, [$jobOrder->id], [$stockItem['id'] => 'L-BRD'], true, [$stockItem['id'] => 1]);
    $stillWaiting = collect($hubRows()->firstWhere('id', $stockItem['id'])['destinations'])->firstWhere('type', 'stock');
    expect($stillWaiting['quantity'])->toEqual(round($stockDestination['quantity'] - 1, 3))
        ->and($jobOrder->refresh()->state)->toBe(JobOrderStateEnum::CONFIRMED);

    \App\Actions\Dispatching\ProductionOutput\PutAwayFinishedJobOrder::make()->action($warehouse, [$jobOrder->id], [$stockItem['id'] => 'l-brd'], true);
    expect(\App\Models\Inventory\LocationOrgStock::where('location_id', $location->id)->exists())->toBeTrue()
        ->and($jobOrder->refresh()->state)->toBe(JobOrderStateEnum::RECEIVED)
        ->and($hubOutput())->not->toContain($jobOrder->reference)
        ->and($laneOf()->flatten()->all())->not->toContain($stocks[0]->code);

    $byArtisan = get(route('grp.org.productions.show.to_produce.by_artisan', $routeParameters))
        ->assertOk()->viewData('page')['props'];
    expect(collect($byArtisan['groups'])->pluck('items')->flatten(1)->pluck('stock_code')->all())->not->toContain($stocks[0]->code);

    $all = get(route('grp.org.productions.show.to_produce.list', $routeParameters))
        ->assertOk()->viewData('page')['props'];
    expect(collect($all['data']['data'])->pluck('stock_code')->intersect([$stocks[0]->code, $stocks[1]->code])->values()->all())
        ->toBe([$stocks[1]->code]);
});

test('a partner line the factory has stock for belongs on pre-pick, not the to produce board', function () {
    $stocks    = createStocks($this->group);
    $orgStocks = createOrgStocks($this->organisation, [$stocks[0]]);
    \App\Models\Production\Artefact::where('production_id', $this->production->id)->where('org_stock_id', $orgStocks[0]->id)->update(['org_stock_id' => null]);
    $made = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'COVER-01', 'name' => 'Covered by stock']);
    $made->update(['org_stock_id' => $orgStocks[0]->id]);
    $orgStocks[0]->update(['quantity_in_locations' => 40, 'quantity_available' => 40]);

    $buyer = \App\Actions\SysAdmin\Organisation\StoreOrganisation::make()->action($this->group, [
            'code' => 'CVR',
            'name' => 'Covered buyer',
            'type' => \App\Enums\SysAdmin\Organisation\OrganisationTypeEnum::SHOP,
        ] + \App\Models\SysAdmin\Organisation::factory()->definition());
    $counts = fn () => \App\Actions\Production\PartnerShippingList\UI\GetProductionQueueCounts::run($this->organisation, $this->production);
    $before = $counts();
    $line   = \App\Models\Procurement\PartnerShoppingListItem::create([
        'group_id'                => $this->group->id,
        'organisation_id'         => $buyer->id,
        'partner_organisation_id' => $this->organisation->id,
        'stock_id'                => $stocks[0]->id,
        'org_stock_id'            => $orgStocks[0]->id,
        'quantity'                => 6,
    ]);

    actingAs($this->guest->getUser());
    $routeParameters = [$this->organisation->slug, $this->production->slug];
    $backlog = fn () => collect(get(route('grp.org.productions.show.to_produce.index', $routeParameters))
        ->assertOk()->viewData('page')['props']['groups'])
        ->firstWhere('label', 'Backlog')['items'];

    expect(collect($backlog())->pluck('id')->all())->not->toContain($line->id)
        ->and($counts()['to_produce'])->toBe($before['to_produce'])
        ->and($counts()['pre_pick'])->toBe($before['pre_pick'] + 1);

    $orgStocks[0]->update(['quantity_in_locations' => 2, 'quantity_available' => 2]);
    expect(collect($backlog())->pluck('id')->all())->not->toContain($line->id);

    $orgStocks[0]->update(['quantity_in_locations' => 0, 'quantity_available' => 0]);
    expect(collect($backlog())->pluck('id')->all())->toContain($line->id)
        ->and($counts()['to_produce'])->toBe($before['to_produce'] + 1);

    $orgStocks[0]->update(['state' => \App\Enums\Inventory\OrgStock\OrgStockStateEnum::DISCONTINUED]);
    expect(collect($backlog())->pluck('id')->all())->not->toContain($line->id)
        ->and($counts()['to_produce'])->toBe($before['to_produce']);

    $orgStocks[0]->update(['state' => \App\Enums\Inventory\OrgStock\OrgStockStateEnum::ACTIVE]);
});

test('pre-pick releases only whole lines the shelf more than covers, and leaves the next one in pre-pick without raising anything to produce', function () {
    $stocks    = [\App\Actions\Goods\Stock\StoreStock::make()->action($this->group, array_merge(\App\Models\Goods\Stock::factory()->definition(), ['state' => \App\Enums\Goods\Stock\StockStateEnum::ACTIVE]))];
    $orgStocks = createOrgStocks($this->organisation, $stocks);
    \App\Models\Production\Artefact::where('production_id', $this->production->id)->where('org_stock_id', $orgStocks[0]->id)->update(['org_stock_id' => null]);
    StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'AUTO-01', 'name' => 'Auto released'])->update(['org_stock_id' => $orgStocks[0]->id]);
    $orgStocks[0]->update(['quantity_in_locations' => 40, 'quantity_available' => 40]);

    $buyer = \App\Actions\SysAdmin\Organisation\StoreOrganisation::make()->action($this->group, [
            'code' => 'AUT',
            'name' => 'Auto buyer',
            'type' => \App\Enums\SysAdmin\Organisation\OrganisationTypeEnum::SHOP,
        ] + \App\Models\SysAdmin\Organisation::factory()->definition());
    $line = fn (float $quantity, string $createdAt) => \App\Models\Procurement\PartnerShoppingListItem::create([
        'group_id'                => $this->group->id,
        'organisation_id'         => $buyer->id,
        'partner_organisation_id' => $this->organisation->id,
        'stock_id'                => $stocks[0]->id,
        'org_stock_id'            => $orgStocks[0]->id,
        'quantity'                => $quantity,
        'created_at'              => $createdAt,
    ]);
    $otherStock = \App\Actions\Goods\Stock\StoreStock::make()->action($this->group, array_merge(\App\Models\Goods\Stock::factory()->definition(), ['state' => \App\Enums\Goods\Stock\StockStateEnum::ACTIVE]));
    $otherOrgStock = createOrgStocks($this->organisation, [$otherStock])[0];
    \App\Models\Procurement\PartnerShoppingListItem::create([
        'group_id'                => $this->group->id,
        'organisation_id'         => $buyer->id,
        'partner_organisation_id' => $this->organisation->id,
        'stock_id'                => $otherStock->id,
        'org_stock_id'            => $otherOrgStock->id,
        'quantity'                => 1000,
        'pre_picked_at'           => now(),
    ]);
    $covered  = $line(6, '2026-10-01 08:00:00');
    $boundary = $line(50, '2026-10-01 09:00:00');
    $behind   = $line(10, '2026-10-01 10:00:00');

    $wasHub = $this->organisation->is_manufacturing_hub;
    $this->organisation->update(['is_manufacturing_hub' => true]);
    $this->artisan('production:release_pre_pick --dry-run')
        ->expectsOutputToContain('1 lines would be released (6 SKOs)')
        ->assertSuccessful();
    expect($covered->refresh()->pre_picked_at)->toBeNull()
        ->and(\App\Models\Procurement\PartnerShoppingListItem::where('stock_id', $stocks[0]->id)->count())->toBe(3);

    $result = \App\Actions\Production\PartnerShippingList\ReleaseFullyStockedPrePickLines::run($this->organisation);

    $restock = \App\Models\Procurement\PartnerShoppingListItem::where('organisation_id', $this->organisation->id)
        ->whereNull('partner_organisation_id')
        ->where('stock_id', $stocks[0]->id)
        ->get();
    expect($result)->toBe(1)
        ->and($covered->refresh()->pre_picked_at)->not->toBeNull()
        ->and((float) $covered->quantity)->toEqual(6.0)
        ->and($boundary->refresh()->pre_picked_at)->toBeNull()
        ->and((float) $boundary->quantity)->toEqual(50.0)
        ->and($restock)->toBeEmpty();

    actingAs($this->guest->getUser());
    $routeParameters = [$this->organisation->slug, $this->production->slug];
    $backlogCards = collect(collect(get(route('grp.org.productions.show.to_produce.index', $routeParameters))
        ->assertOk()->viewData('page')['props']['groups'])
        ->firstWhere('label', 'Backlog')['items'])->keyBy('id');
    expect($backlogCards->keys()->all())->toContain($behind->id)
        ->not->toContain($boundary->id)
        ->and((float) $backlogCards[$behind->id]['stock_promised'])->toEqual(6.0);

    $prePick = collect(get(route('grp.org.productions.show.pre_pick.index', $routeParameters))
        ->assertOk()->viewData('page')['props']['data']['data'])->keyBy('id');
    expect($prePick[$boundary->id]['automation_status'])->toBe('awaiting_full_stock')
        ->and((float) $prePick[$boundary->id]['shortfall'])->toEqual(16.0)
        ->and($prePick[$behind->id]['automation_status'])->toBe('to_produce');

    expect(\App\Actions\Production\PartnerShippingList\ReleaseFullyStockedPrePickLines::run($this->organisation))->toBe(0)
        ->and(\App\Actions\Production\PartnerShippingList\UI\IndexPrePickList::automationStatus(40, 40, 40, 0))->toBe('held_buffer');

    $this->organisation->update(['is_manufacturing_hub' => $wasHub]);
    \App\Models\Procurement\PartnerShoppingListItem::whereIn('stock_id', [$stocks[0]->id, $otherStock->id])->delete();
    \App\Models\Production\Artefact::where('org_stock_id', $orgStocks[0]->id)->delete();
});

test('the to produce board shows every open line, not only the newest page of the table', function () {
    $stocks    = createStocks($this->group);
    $orgStocks = createOrgStocks($this->organisation, [$stocks[0]]);
    \App\Models\Production\Artefact::where('production_id', $this->production->id)->where('org_stock_id', $orgStocks[0]->id)->update(['org_stock_id' => null]);
    StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'ALL-01', 'name' => 'Every line'])->update(['org_stock_id' => $orgStocks[0]->id]);
    $orgStocks[0]->update(['quantity_in_locations' => 0, 'quantity_available' => 0]);

    $lines = collect(range(1, 3))->map(fn ($day) => \App\Models\Procurement\PartnerShoppingListItem::create([
        'group_id'        => $this->group->id,
        'organisation_id' => $this->organisation->id,
        'stock_id'        => $stocks[0]->id,
        'org_stock_id'    => $orgStocks[0]->id,
        'quantity'        => 5,
        'created_at'      => now()->subDays($day),
    ]));

    config(['ui.table.max_records_per_page' => 2, 'ui.table.min_records_per_page' => 1]);
    actingAs($this->guest->getUser());
    $backlogIds = collect(collect(get(route('grp.org.productions.show.to_produce.index', [$this->organisation->slug, $this->production->slug]))
        ->assertOk()->viewData('page')['props']['groups'])
        ->firstWhere('label', 'Backlog')['items'])->pluck('id');

    expect($backlogIds->intersect($lines->pluck('id'))->count())->toBe(3);
});

test('a labelled run is prepared only with its batch code, and keeping the expiry date writes it onto the published label of a partner line made for another organisation', function () {
    $stocks       = createStocks($this->group);
    $makerOrgStock = createOrgStocks($this->organisation, [$stocks[0]])[0];
    \App\Models\Production\Artefact::where('production_id', $this->production->id)->where('org_stock_id', $makerOrgStock->id)->update(['org_stock_id' => null]);
    $made = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'EXPIRY-01', 'name' => 'Dated for a partner']);
    $made->update(['org_stock_id' => $makerOrgStock->id]);

    $buyer = \App\Models\SysAdmin\Organisation::where('code', 'EXPBUY')->first()
        ?? \App\Actions\SysAdmin\Organisation\StoreOrganisation::make()->action($this->group, [
            'code' => 'EXPBUY',
            'name' => 'Expiry buyer',
            'type' => \App\Enums\SysAdmin\Organisation\OrganisationTypeEnum::SHOP,
        ] + \App\Models\SysAdmin\Organisation::factory()->definition());
    $orgPartner = \App\Models\Procurement\OrgPartner::where('organisation_id', $buyer->id)->where('partner_id', $this->organisation->id)->first()
        ?? \App\Actions\Procurement\OrgPartner\StoreOrgPartner::make()->action($buyer, $this->organisation);

    $line = submittedPartnerShoppingListItem($orgPartner, $makerOrgStock, ['quantity' => 4]);
    expect($line->org_stock_id)->not->toBe($makerOrgStock->id)
        ->and($line->stock_id)->toBe($stocks[0]->id);

    $label = \App\Models\Production\ArtefactLabel::create([
        'group_id'        => $made->group_id,
        'organisation_id' => $made->organisation_id,
        'artefact_id'     => $made->id,
        'org_stock_id'    => $makerOrgStock->id,
        'name'            => 'Dated',
        'state'           => \App\Enums\Production\Artefact\ArtefactLabelStateEnum::PUBLISHED,
        'layout'          => ['fields' => [['source' => 'name', 'text' => 'Kept'], ['source' => 'expiry_date', 'text' => ''], ['source' => 'batch_code', 'text' => 'EXAMPLE-1']]],
    ]);

    actingAs($this->guest->getUser());
    \Pest\Laravel\post(route('grp.org.productions.show.to_produce.items.preparing', [$this->organisation->slug, $this->production->slug]), [
        'preparing' => true,
        'lines'     => [['id' => $line->id, 'expiry_date' => '2027-03-09']],
    ])->assertSessionHasErrors('lines.0.batch_code');
    expect($line->refresh()->preparing_at)->toBeNull();

    \Pest\Laravel\post(route('grp.org.productions.show.to_produce.items.preparing', [$this->organisation->slug, $this->production->slug]), [
        'preparing' => true,
        'lines'     => [['id' => $line->id, 'batch_code' => 'B-0309', 'expiry_date' => '2027-03-09', 'expiry_applies_to_label' => true]],
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($line->refresh()->preparing_at)->not->toBeNull()
        ->and($line->batch_code)->toBe('B-0309')
        ->and($label->refresh()->layout['fields'][1]['text'])->toBe('09/03/2027')
        ->and($label->layout['fields'][0]['text'])->toBe('Kept');
});

test('an own customer line leaves the to produce board once its order is dispatched', function () {
    $stocks    = createStocks($this->group);
    $orgStocks = createOrgStocks($this->organisation, [$stocks[0]]);
    \App\Models\Production\Artefact::where('production_id', $this->production->id)->where('org_stock_id', $orgStocks[0]->id)->update(['org_stock_id' => null]);
    $made = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'DISP-01', 'name' => 'Dispatched line']);
    $made->update(['org_stock_id' => $orgStocks[0]->id]);

    $line = \App\Models\Procurement\PartnerShoppingListItem::create([
        'group_id'        => $this->group->id,
        'organisation_id' => $this->organisation->id,
        'stock_id'        => $stocks[0]->id,
        'org_stock_id'    => $orgStocks[0]->id,
        'quantity'        => 2,
    ]);

    actingAs($this->guest->getUser());
    $routeParameters = [$this->organisation->slug, $this->production->slug];
    $backlog = fn () => collect(collect(get(route('grp.org.productions.show.to_produce.index', $routeParameters))
        ->assertOk()->viewData('page')['props']['groups'])
        ->firstWhere('label', 'Backlog')['items'])->pluck('id')->all();

    expect($backlog())->toContain($line->id);

    $line->update(['state' => \App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum::ORDERED]);
    expect($backlog())->not->toContain($line->id);
});

test('to restock bands rank artefacts by cover and queue them onto the to produce board', function () {
    $stocks    = createStocks($this->group);
    $orgStocks = createOrgStocks($this->organisation, [$stocks[0], $stocks[1]]);

    \App\Models\Production\Artefact::where('production_id', $this->production->id)
        ->whereIn('org_stock_id', [$orgStocks[0]->id, $orgStocks[1]->id])
        ->update(['org_stock_id' => null]);

    /* createStocks hands back the suite's shared stocks, so clear any board line an earlier test left. */
    \App\Models\Procurement\PartnerShoppingListItem::whereIn('stock_id', collect($orgStocks)->pluck('stock_id'))->forceDelete();

    $empty   = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'RES-01', 'name' => 'Sold out here']);
    $covered = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'RES-02', 'name' => 'Plenty here']);
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
    $toDo = collect($props['data']['data'])->pluck('stock_code');

    expect($toDo)->toContain($orgStocks[0]->code)
        ->and($toDo)->not->toContain($orgStocks[1]->code)
        ->and($props['leadTime']['days'])->toBeGreaterThan(0)
        ->and((int) collect($props['data']['data'])->firstWhere('stock_code', $orgStocks[0]->code)['job_units'])->toBe(9);

    \App\Actions\Production\Restock\QueueArtefactsToProduce::make()
        ->action($this->organisation, $this->production, [['artefact_id' => $empty->id, 'quantity' => 9]]);

    $queued = \App\Models\Procurement\PartnerShoppingListItem::where('org_stock_id', $orgStocks[0]->id)->first();
    expect((float) $queued->quantity)->toBe(9.0)
        ->and($queued->partner_organisation_id)->toBeNull()
        ->and($queued->organisation_id)->toBe($this->organisation->id);

    $after = get(route('grp.org.productions.show.to_restock.index', $routeParameters))
        ->assertOk()->viewData('page')['props'];

    expect(collect($after['data']['data'])->pluck('stock_code'))->not->toContain($orgStocks[0]->code)
        ->and($after)->not->toHaveKey('candidates')
        ->and(collect($after['cover'])->pluck('bucket')->all())->toContain('out', 'w1', 'w2', 'ok');
});

test('to restock leaves out stocks whose only products are exclusive to a private customer', function () {
    $stocks    = createStocks($this->group);
    $orgStocks = createOrgStocks($this->organisation, [$stocks[0]]);

    \App\Models\Production\Artefact::where('production_id', $this->production->id)
        ->where('org_stock_id', $orgStocks[0]->id)
        ->update(['org_stock_id' => null]);
    \App\Models\Procurement\PartnerShoppingListItem::where('stock_id', $orgStocks[0]->stock_id)->forceDelete();

    $artefact = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'RES-PRIV', 'name' => 'Private product']);
    $artefact->update(['org_stock_id' => $orgStocks[0]->id]);
    $orgStocks[0]->update(['quantity_available' => 0]);
    $orgStocks[0]->stats()->update(['recommended_order_quantity' => 9, 'days_of_cover' => 0]);

    list(, , $shop) = createShop();
    [, $product] = createProduct($shop);
    $customer    = createCustomer($shop);
    \DB::table('product_has_org_stocks')->where('org_stock_id', $orgStocks[0]->id)->delete();
    \DB::table('product_has_org_stocks')->insert(['product_id' => $product->id, 'org_stock_id' => $orgStocks[0]->id, 'quantity' => 1]);
    $product->update(['state' => \App\Enums\Catalogue\Product\ProductStateEnum::ACTIVE, 'exclusive_for_customer_id' => $customer->id]);

    actingAs($this->guest->getUser());
    $url   = route('grp.org.productions.show.to_restock.index', [$this->organisation->slug, $this->production->slug, 'perPage' => 1000]);
    $codes = fn () => collect(get($url)->assertOk()->viewData('page')['props']['data']['data'])->pluck('stock_code');

    expect($codes())->not->toContain($orgStocks[0]->code);

    \App\Models\Procurement\OrgPartner::updateOrCreate(
        ['group_id' => $this->group->id, 'organisation_id' => $this->organisation->id, 'partner_id' => $this->organisation->id],
        ['status' => true, 'customer_id' => $customer->id],
    );

    expect($codes())->toContain($orgStocks[0]->code);

    \App\Models\Procurement\OrgPartner::where('organisation_id', $this->organisation->id)->where('partner_id', $this->organisation->id)->update(['customer_id' => null]);
});

test('to restock hides stocks one private customer took almost all of, once hydrated', function () {
    $orgStocks = collect([0, 1])->map(fn () => \App\Actions\Inventory\OrgStock\StoreOrgStock::make()->action(
        $this->organisation,
        \App\Actions\Goods\Stock\StoreStock::make()->action($this->group, array_merge(\App\Models\Goods\Stock::factory()->definition(), ['state' => \App\Enums\Goods\Stock\StockStateEnum::ACTIVE]))
    ))->all();

    foreach ([0, 1] as $index) {
        $artefact = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'RES-TOP'.$index, 'name' => 'Top customer '.$index]);
        $artefact->update(['org_stock_id' => $orgStocks[$index]->id]);
        $orgStocks[$index]->update(['quantity_available' => 0]);
        $orgStocks[$index]->stats()->update(['recommended_order_quantity' => 9, 'days_of_cover' => 0]);
        \DB::table('delivery_note_items')->whereIn('org_stock_id', [$orgStocks[$index]->id])->delete();
    }

    list(, , $shop) = createShop();
    $big   = createCustomer($shop);
    $small = \App\Actions\CRM\Customer\StoreCustomer::make()->action($shop, \App\Models\CRM\Customer::factory()->definition());
    $warehouseId = createWarehouse()->id;

    $dispatch = function (int $orgStockId, int $customerId, int $quantity) use ($shop, $warehouseId) {
        $noteId = \DB::table('delivery_notes')->insertGetId([
            'group_id' => $shop->group_id, 'organisation_id' => $shop->organisation_id, 'slug' => 'dn-'.\Illuminate\Support\Str::random(10),
            'warehouse_id' => $warehouseId, 'shop_id' => $shop->id, 'customer_id' => $customerId,
            'reference' => 'DN'.\Illuminate\Support\Str::random(8), 'date' => now(), 'data' => '{}',
        ]);
        \DB::table('delivery_note_items')->insert([
            'group_id' => $shop->group_id, 'organisation_id' => $this->organisation->id, 'shop_id' => $shop->id, 'delivery_note_id' => $noteId,
            'org_stock_id' => $orgStockId, 'quantity_dispatched' => $quantity, 'data' => '{}', 'created_at' => now(), 'updated_at' => now(),
        ]);
    };

    $dispatch($orgStocks[0]->id, $big->id, 95);
    $dispatch($orgStocks[0]->id, $small->id, 5);
    $dispatch($orgStocks[1]->id, $big->id, 60);
    $dispatch($orgStocks[1]->id, $small->id, 40);

    actingAs($this->guest->getUser());
    $url   = route('grp.org.productions.show.to_restock.index', [$this->organisation->slug, $this->production->slug, 'perPage' => 1000]);
    $codes = fn () => collect(get($url)->assertOk()->viewData('page')['props']['data']['data'])->pluck('stock_code');

    \App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateTopCustomerShare::run($this->organisation);

    expect($codes())->not->toContain($orgStocks[0]->code)
        ->and($codes())->toContain($orgStocks[1]->code);
});

test('to restock keeps a stock partly covered by production, asking only for what is missing, and hides a fully covered one', function () {
    $stocks    = createStocks($this->group);
    $orgStocks = createOrgStocks($this->organisation, [$stocks[0]]);

    \App\Models\Production\Artefact::where('production_id', $this->production->id)
        ->where('org_stock_id', $orgStocks[0]->id)
        ->update(['org_stock_id' => null]);
    \App\Models\Procurement\PartnerShoppingListItem::where('stock_id', $orgStocks[0]->stock_id)->forceDelete();

    \DB::table('product_has_org_stocks')->where('org_stock_id', $orgStocks[0]->id)->delete();
    $orgStocks[0]->stats()->update(['top_customer_id' => null, 'top_customer_dispatch_share' => null]);

    $artefact = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'RES-PART', 'name' => 'Partly covered', 'recommended_batch_size' => 10]);
    $artefact->update(['org_stock_id' => $orgStocks[0]->id]);
    $orgStocks[0]->update(['quantity_available' => 0, 'packed_in' => 1]);
    $orgStocks[0]->stats()->update(['recommended_order_quantity' => 95, 'days_of_cover' => 0]);

    actingAs($this->guest->getUser());
    $url = route('grp.org.productions.show.to_restock.index', [$this->organisation->slug, $this->production->slug, 'perPage' => 500]);
    $row = fn () => collect(get($url)->assertOk()->viewData('page')['props']['data']['data'])->firstWhere('stock_code', $orgStocks[0]->code);

    expect((int) $row()['job_units'])->toBe(100)
        ->and((int) $row()['in_production'])->toBe(0);

    $line = \App\Models\Procurement\PartnerShoppingListItem::create([
        'group_id'         => $this->group->id,
        'organisation_id'  => $this->organisation->id,
        'stock_id'         => $orgStocks[0]->stock_id,
        'org_stock_id'     => $orgStocks[0]->id,
        'quantity'         => 40,
        'priority'         => \App\Enums\Procurement\ShoppingListItem\ShoppingListItemPriorityEnum::NORMAL,
        'state'            => \App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum::OPEN,
    ]);

    expect((int) $row()['in_production'])->toBe(40)
        ->and((int) $row()['job_units'])->toBe(60);

    $line->update(['quantity' => 95]);

    expect($row())->toBeNull();
});

test('sending one product to the to produce board queues only that line', function () {
    $stocks    = createStocks($this->group);
    $orgStocks = createOrgStocks($this->organisation, [$stocks[0], $stocks[1]]);

    \App\Models\Production\Artefact::where('production_id', $this->production->id)
        ->whereIn('org_stock_id', [$orgStocks[0]->id, $orgStocks[1]->id])
        ->update(['org_stock_id' => null]);
    \App\Models\Procurement\PartnerShoppingListItem::whereIn('stock_id', collect($orgStocks)->pluck('stock_id'))->forceDelete();

    $first  = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'RES-ONE', 'name' => 'First', 'recommended_batch_size' => 10]);
    $second = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'RES-TWO', 'name' => 'Second', 'recommended_batch_size' => 10]);
    $first->update(['org_stock_id' => $orgStocks[0]->id]);
    $second->update(['org_stock_id' => $orgStocks[1]->id]);

    $result = \App\Actions\Production\Restock\QueueArtefactsToProduce::make()
        ->action($this->organisation, $this->production, [['artefact_id' => $second->id, 'units' => 25]]);

    expect($result['queued'])->toBe(1)
        ->and(\App\Models\Procurement\PartnerShoppingListItem::where('org_stock_id', $orgStocks[0]->id)->exists())->toBeFalse()
        ->and((float) \App\Models\Procurement\PartnerShoppingListItem::where('org_stock_id', $orgStocks[1]->id)->value('quantity'))->toBe(30.0 / max(1, (int) $orgStocks[1]->packed_in));

    $line = \App\Models\Procurement\PartnerShoppingListItem::where('org_stock_id', $orgStocks[1]->id)->first();
    expect($line->preparing_at)->not->toBeNull()
        ->and($result['artefact_ids'])->toBe([$second->id]);

    actingAs($this->guest->getUser());
    $page = get(route('grp.org.productions.show.to_restock.index', [$this->organisation->slug, $this->production->slug, 'sent' => $second->id]))->assertOk()->viewData('page');
    $partial = $this->withHeaders([
        'X-Inertia'                   => 'true',
        'X-Inertia-Version'           => $page['version'],
        'X-Inertia-Partial-Component' => 'Org/Production/ToRestock',
        'X-Inertia-Partial-Data'      => 'sent',
    ])->get(route('grp.org.productions.show.to_restock.index', [$this->organisation->slug, $this->production->slug, 'sent' => $second->id]))->assertOk()->json('props.sent');

    expect($partial['cards'][$second->id]['id'])->toBe($line->id)
        ->and($partial['artisans'])->toBeArray();
});

test('to restock suggests and queues whole SKOs, never a fraction', function () {
    $stocks    = createStocks($this->group);
    $orgStocks = createOrgStocks($this->organisation, [$stocks[0]]);

    \App\Models\Production\Artefact::where('production_id', $this->production->id)
        ->where('org_stock_id', $orgStocks[0]->id)
        ->update(['org_stock_id' => null]);
    \App\Models\Procurement\PartnerShoppingListItem::where('stock_id', $orgStocks[0]->stock_id)->forceDelete();
    \DB::table('product_has_org_stocks')->where('org_stock_id', $orgStocks[0]->id)->delete();
    $orgStocks[0]->stats()->update(['top_customer_id' => null, 'top_customer_dispatch_share' => null]);

    $artefact = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'RES-WHOLE', 'name' => 'Whole SKOs']);
    $artefact->update(['org_stock_id' => $orgStocks[0]->id]);
    $orgStocks[0]->update(['quantity_available' => 0, 'packed_in' => 12]);
    $orgStocks[0]->stats()->update(['recommended_order_quantity' => 6.9167, 'days_of_cover' => 0]);

    actingAs($this->guest->getUser());
    $url = route('grp.org.productions.show.to_restock.index', [$this->organisation->slug, $this->production->slug, 'perPage' => 500]);
    $row = collect(get($url)->assertOk()->viewData('page')['props']['data']['data'])->firstWhere('stock_code', $orgStocks[0]->code);

    expect((int) $row['job_units'] % 12)->toBe(0)
        ->and((int) $row['quantum_units'])->toBe(12);

    \App\Actions\Production\Restock\QueueArtefactsToProduce::make()
        ->action($this->organisation, $this->production, [['artefact_id' => $artefact->id, 'units' => 83]]);

    $quantity = (float) \App\Models\Procurement\PartnerShoppingListItem::where('org_stock_id', $orgStocks[0]->id)->value('quantity');

    expect($quantity)->toBe(7.0);
});

test('sending a product over http redirects to a page whose sent prop carries its preparing card', function () {
    $stocks    = createStocks($this->group);
    $orgStocks = createOrgStocks($this->organisation, [$stocks[0]]);

    \App\Models\Production\Artefact::where('production_id', $this->production->id)
        ->where('org_stock_id', $orgStocks[0]->id)
        ->update(['org_stock_id' => null]);
    \App\Models\Procurement\PartnerShoppingListItem::where('stock_id', $orgStocks[0]->stock_id)->forceDelete();

    $artefact = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'RES-HTTP', 'name' => 'Http product']);
    $artefact->update(['org_stock_id' => $orgStocks[0]->id]);
    $orgStocks[0]->update(['packed_in' => 12]);

    actingAs($this->guest->getUser());
    $parameters = [$this->organisation->slug, $this->production->slug];
    $page       = get(route('grp.org.productions.show.to_restock.index', $parameters))->assertOk()->viewData('page');
    $inertia    = fn (string $only) => [
        'X-Inertia'                   => 'true',
        'X-Inertia-Version'           => $page['version'],
        'X-Inertia-Partial-Component' => 'Org/Production/ToRestock',
        'X-Inertia-Partial-Data'      => $only,
    ];

    $this->withHeaders($inertia('sent,flash') + ['Referer' => route('grp.org.productions.show.to_restock.index', $parameters)])
        ->post(route('grp.org.productions.show.to_restock.queue', $parameters), ['lines' => [['artefact_id' => $artefact->id, 'units' => 83]]])
        ->assertRedirect();

    $cards = $this->withHeaders($inertia('sent'))->get(route('grp.org.productions.show.to_restock.index', [...$parameters, 'sent' => $artefact->id]))->assertOk()->json('props.sent.cards');

    expect($cards)->toHaveKey((string) $artefact->id)
        ->and((float) $cards[$artefact->id]['quantity'])->toBe(7.0)
        ->and($cards[$artefact->id]['preparing_at'])->not->toBeNull();
});

test('queueing to produce rounds a per-line unit quantity up to whole batches', function () {
    $stocks    = createStocks($this->group);
    $orgStocks = createOrgStocks($this->organisation, [$stocks[0]]);

    \App\Models\Production\Artefact::where('production_id', $this->production->id)
        ->where('org_stock_id', $orgStocks[0]->id)
        ->update(['org_stock_id' => null]);
    \App\Models\Procurement\PartnerShoppingListItem::where('stock_id', $orgStocks[0]->stock_id)->forceDelete();

    $artefact = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'RES-UNITS', 'name' => 'Units product', 'recommended_batch_size' => 10]);
    $artefact->update(['org_stock_id' => $orgStocks[0]->id]);
    $orgStocks[0]->update(['packed_in' => 5]);

    $result = \App\Actions\Production\Restock\QueueArtefactsToProduce::make()
        ->action($this->organisation, $this->production, [['artefact_id' => $artefact->id, 'units' => 12]]);

    $line = \App\Models\Procurement\PartnerShoppingListItem::where('org_stock_id', $orgStocks[0]->id)->first();

    expect($result['queued'])->toBe(1)
        ->and((float) $line->quantity)->toBe(4.0);
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

    $artefact = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value,
        'code'                   => 'PACKEDART1',
        'name'                   => 'Artefact sold in tens',
        'org_stock_id'           => $orgStock->id,
        'recommended_batch_size' => 16,
    ]);
    $artefact->manufactureTasks()->sync([
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

test('a job order is raised in whole outers, unless the batch itself is not whole outers', function () {
    $batched = \App\Actions\Production\JobOrder\BatchedUnitsForDemand::make();

    expect($batched->handle(62.5, 8, null))->toBe(504)
        ->and($batched->handle(21.813, 16, 352))->toBe(352)
        ->and($batched->handle(0, 8, null))->toBe(8)
        ->and($batched->wholeOuters(500, 8, null))->toBe(504)
        ->and($batched->wholeOuters(496, 8, null))->toBe(496)
        ->and($batched->wholeOuters(349, 16, 352))->toBe(352)
        ->and($batched->wholeOuters(430, 24, 215))->toBe(430)
        ->and($batched->wholeOuters(7, null, null))->toBe(7);
});

test('the partner order quantum is one batch of units rounded down to whole SKOs', function () {
    $quantum = fn (?int $packedIn, ?int $batchSize) => \App\Actions\Production\JobOrder\BatchedUnitsForDemand::make()->quantumInSkos($packedIn, $batchSize);

    expect($quantum(10, 16))->toBe(1)
        ->and($quantum(10, 20))->toBe(2)
        ->and($quantum(10, 10))->toBe(1)
        ->and($quantum(1, 16))->toBe(16)
        ->and($quantum(6, 4))->toBe(1)
        ->and($quantum(16, 277))->toBe(17)
        ->and($quantum(8, 625))->toBe(78)
        ->and($quantum(16, 272))->toBe(17)
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

    $artefact = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value,
        'code'                   => 'HITCH-01',
        'name'                   => 'Made in batches of eighty',
        'recommended_batch_size' => 80,
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
    $this->artefact->manufactureTasks()->sync([
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
        'org_stock_id'            => createOrgStocks($buyer['organisation'], [$stock])[0]->id,
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

test('a batch rounded up over what the partner asked for sends only the asked quantity to the partner bay', function () {
    $this->artefact->manufactureTasks()->detach();
    $this->artefact->manufactureTasks()->sync([
        $this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1],
    ]);

    $stocks   = createStocks($this->group);
    $stock    = $stocks[0];
    $orgStock = createOrgStocks($this->organisation, [$stock])[0];
    \App\Models\Procurement\PartnerShoppingListItem::where('org_stock_id', $orgStock->id)->forceDelete();
    \App\Models\Production\Artefact::where('production_id', $this->production->id)
        ->where('org_stock_id', $orgStock->id)
        ->where('id', '!=', $this->artefact->id)
        ->update(['org_stock_id' => null]);
    $this->artefact->update(['org_stock_id' => $orgStock->id]);
    $orgStock->update(['packed_in' => 1]);

    $warehouse = \App\Actions\Inventory\Warehouse\StoreWarehouse::make()->action($this->organisation, ['code' => 'WH-BATCH', 'name' => 'Batch warehouse']);
    $area      = \App\Actions\Inventory\WarehouseArea\StoreWarehouseArea::make()->action($warehouse, ['code' => 'A-BATCH', 'name' => 'Batch area']);
    $bay       = \App\Actions\Inventory\Location\StoreLocation::make()->action($area, ['code' => 'BAY-BATCH', 'name' => 'BAY-BATCH'] + \App\Models\Inventory\Location::factory()->definition());

    $buyer = \App\Models\SysAdmin\Organisation::where('code', 'ESBUY')->first()
        ?? \App\Actions\SysAdmin\Organisation\StoreOrganisation::make()->action($this->group, [
            'code' => 'ESBUY',
            'name' => 'ESBUY',
            'type' => \App\Enums\SysAdmin\Organisation\OrganisationTypeEnum::SHOP,
        ] + \App\Models\SysAdmin\Organisation::factory()->definition());
    \App\Models\Procurement\OrgPartner::firstOrCreate(
        ['group_id' => $this->group->id, 'organisation_id' => $this->organisation->id, 'partner_id' => $buyer->id],
        ['status' => true],
    )->update(['goods_out_location_id' => $bay->id]);

    $jobOrder = StoreJobOrder::make()->action($this->production, []);
    $item     = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 280]);
    ConfirmJobOrder::make()->action($jobOrder);

    $line = \App\Models\Procurement\PartnerShoppingListItem::create([
        'group_id'                => $this->group->id,
        'organisation_id'         => $buyer->id,
        'partner_organisation_id' => $this->organisation->id,
        'stock_id'                => $stock->id,
        'org_stock_id'            => createOrgStocks($buyer, [$stock])[0]->id,
        'quantity'                => 50,
        'quantity_to_produce'     => 280,
        'job_order_id'            => $jobOrder->id,
    ]);

    $session = StartManufactureTaskSession::make()->action($this->guest->getUser(), $item->tasks()->first());
    CloseManufactureTaskSession::make()->action($session, ['quantity_made' => 280]);

    $allocation = collect(\App\Actions\Production\JobOrder\GetJobOrderDestinationAllocation::run($jobOrder->refresh()))
        ->map(fn (array $row) => [$row['location_id'], $row['quantity']]);

    expect($line->claimedSkos())->toBe(50.0)
        ->and($allocation->all())->toBe([
            [$bay->id, 50.0],
            [null, 230.0],
        ]);
});

test('a batch size change flags open jobs raised with the old one and their quantity can be corrected', function () {
    $stocks   = createStocks($this->group);
    $orgStock = createOrgStocks($this->organisation, [$stocks[0]])[0];

    \App\Models\Production\Artefact::where('production_id', $this->production->id)
        ->where('org_stock_id', $orgStock->id)
        ->update(['org_stock_id' => null]);
    $orgStock->update(['packed_in' => 12]);

    $artefact = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value,
        'code'                   => 'OFFBATCH-01',
        'name'                   => 'Raised at three hundred and sixty',
        'recommended_batch_size' => 360,
    ]);
    $artefact->update(['org_stock_id' => $orgStock->id]);
    $artefact->refresh();

    $jobOrder = \App\Actions\Production\JobOrder\StoreJobOrdersGroupedByArtisan::run($this->production, [
        ['artefact' => $artefact, 'quantity' => 40, 'batch_code' => null, 'expiry_date' => null],
    ])[0];
    $item = $jobOrder->jobOrderItems()->first();

    expect($item->quantity)->toBe(720)
        ->and((float) $item->data['demand_skos'])->toBe(40.0)
        ->and(\App\Actions\Production\JobOrderItem\GetOpenJobOrderItemsOffBatch::run([$artefact->id]))->toBeEmpty();

    SetArtefactsBatchSize::make()->action($this->production, [
        'artefacts'              => [$artefact->id],
        'recommended_batch_size' => 480,
    ]);

    $offBatch = \App\Actions\Production\JobOrderItem\GetOpenJobOrderItemsOffBatch::run([$artefact->id]);

    expect($offBatch)->toHaveCount(1)
        ->and($offBatch[0]['id'])->toBe($item->id)
        ->and($offBatch[0]['suggested_quantity'])->toBe(480);

    actingAs($this->guest->getUser());
    $this->patch(route('grp.models.job-order-item.update', [$item->id]), ['quantity' => 480])->assertSessionHasNoErrors();

    expect($item->refresh()->quantity)->toBe(480)
        ->and((float) $item->tasks()->first()->quantity_required)->toBe(480.0)
        ->and(\App\Actions\Production\JobOrderItem\GetOpenJobOrderItemsOffBatch::run([$artefact->id]))->toBeEmpty();

    $item->tasks()->first()->update(['quantity_made' => 100]);

    $this->patch(route('grp.models.job-order-item.update', [$item->id]), ['quantity' => 50])->assertSessionHasErrors('quantity');

    expect($item->refresh()->quantity)->toBe(480);

    $this->patch(route('grp.models.job-order-item.update', [$item->id]), ['quantity' => 485])->assertSessionHasNoErrors();

    expect($item->refresh()->quantity)->toBe(492);

    $this->post(route('grp.models.job-order.item.store', [$item->job_order_id]), ['artefact_id' => $artefact->id, 'quantity' => 13])->assertSessionHasNoErrors();

    expect(JobOrderItem::where('job_order_id', $item->job_order_id)->where('id', '!=', $item->id)->value('quantity'))->toBe(24);
});

test('a discontinued SKO leaves the to produce board unless a job order already carries it', function () {
    $stocks    = [\App\Actions\Goods\Stock\StoreStock::make()->action($this->group, array_merge(\App\Models\Goods\Stock::factory()->definition(), ['state' => \App\Enums\Goods\Stock\StockStateEnum::ACTIVE]))];
    $orgStocks = createOrgStocks($this->organisation, [$stocks[0]]);
    \App\Models\Production\Artefact::where('production_id', $this->production->id)->where('org_stock_id', $orgStocks[0]->id)->update(['org_stock_id' => null]);
    $made = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'DISC-SKO-01', 'name' => 'Discontinued']);
    $made->update(['org_stock_id' => $orgStocks[0]->id]);
    $orgStocks[0]->update(['quantity_in_locations' => 0, 'quantity_available' => 0]);

    $line = \App\Models\Procurement\PartnerShoppingListItem::create([
        'group_id'        => $this->group->id,
        'organisation_id' => $this->organisation->id,
        'stock_id'        => $orgStocks[0]->stock_id,
        'org_stock_id'    => $orgStocks[0]->id,
        'quantity'        => 5,
    ]);

    actingAs($this->guest->getUser());
    $routeParameters = [$this->organisation->slug, $this->production->slug];
    $backlog = fn () => collect(get(route('grp.org.productions.show.to_produce.index', $routeParameters))
        ->assertOk()->viewData('page')['props']['groups'])->firstWhere('label', 'Backlog')['items'];
    $listed = fn () => collect(get(route('grp.org.productions.show.to_produce.list', $routeParameters))
        ->assertOk()->viewData('page')['props']['data']['data'])->pluck('stock_code')->all();

    expect(collect($backlog())->pluck('stock_code')->all())->toBe([$stocks[0]->code]);

    $orgStocks[0]->update(['state' => \App\Enums\Inventory\OrgStock\OrgStockStateEnum::DISCONTINUED]);
    expect($backlog())->toBe([])
        ->and($listed())->not->toContain($stocks[0]->code);

    $orgStocks[0]->update(['state' => \App\Enums\Inventory\OrgStock\OrgStockStateEnum::ACTIVE]);
    \App\Actions\Production\PartnerShippingList\StoreJobOrdersFromToProduceItems::make()->action($this->production, [$line->id]);
    $orgStocks[0]->update(['state' => \App\Enums\Inventory\OrgStock\OrgStockStateEnum::DISCONTINUED]);
    expect($listed())->toContain($stocks[0]->code);
});

test('a job carried to another day is one row on the board, with the amount the whole job asks for', function () {
    $this->artefact->manufactureTasks()->sync([
        $this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1],
    ]);
    $user      = $this->guest->getUser();
    $warehouse = \App\Actions\Inventory\Warehouse\StoreWarehouse::make()->action($this->organisation, ['code' => 'WH-CRY', 'name' => 'Carry warehouse']);
    \App\Models\Production\ManufactureTaskSession::where('user_id', $user->id)
        ->where('state', \App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum::OPEN)->delete();

    $jobOrder = StoreJobOrder::make()->action($this->production, []);
    $item     = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 40]);
    ConfirmJobOrder::make()->action($jobOrder);

    CloseManufactureTaskSession::make()->action(
        StartManufactureTaskSession::make()->action($user, $item->tasks()->first()),
        ['quantity_made' => 23, 'outcome' => 'carry_over']
    );

    $rowsOf = fn () => collect(\App\Actions\Dispatching\ProductionOutput\GetFinishedProductionJobOrders::run($warehouse))
        ->flatMap(fn (array $trip) => collect($trip['jobs'])->flatMap(fn (array $job) => $job['items']))
        ->filter(fn (array $row) => str_starts_with((string) $row['reference'], $jobOrder->reference))
        ->values();

    /* The board counts SKOs, the artisan artefact units; packed_in is whatever this fixture left. */
    $skos = fn (float $units) => round($units / max(1, (int) $this->artefact->orgStock?->packed_in), 3);

    $rows = $rowsOf();
    expect($rows)->toHaveCount(1)
        ->and($rows[0]['reference'])->toBe($jobOrder->reference)
        ->and($rows[0]['quantity'])->toEqual($skos(23))
        ->and($rows[0]['quantity_made'])->toEqual($skos(23))
        ->and($rows[0]['quantity_total'])->toEqual($skos(40))
        ->and($rows[0]['in_progress'])->toBeTrue();

    $carried = \App\Models\Production\JobOrder::where('production_id', $this->production->id)->orderByDesc('id')->first();
    CloseManufactureTaskSession::make()->action(
        StartManufactureTaskSession::make()->action($user, $carried->jobOrderItems()->first()->tasks()->first()),
        ['quantity_made' => 17, 'outcome' => 'complete']
    );

    $rows = $rowsOf();
    expect($rows)->toHaveCount(1)
        ->and($rows[0]['reference'])->toBe($jobOrder->reference)
        ->and($rows[0]['item_ids'])->toHaveCount(2)
        ->and($rows[0]['job_order_ids'])->toHaveCount(2)
        ->and($rows[0]['quantity'])->toEqual($skos(40))
        ->and($rows[0]['quantity_made'])->toEqual($skos(40))
        ->and($rows[0]['quantity_total'])->toEqual($skos(40))
        ->and($rows[0]['in_progress'])->toBeFalse();
});

test('a job order planned by hand is one job with its lines on the board, even for a product already there', function () {
    $newEmployee = function () {
        $modelData = Employee::factory()->make(['organisation_id' => $this->organisation->id])->toArray();
        $modelData['worker_number']   = 'W'.rand(1000, 9999);
        $modelData['alias']           = 'Alias '.rand(1000, 9999);
        $modelData['type']            = \App\Enums\HumanResources\Employee\EmployeeTypeEnum::EMPLOYEE;
        $modelData['employment_type'] = \App\Enums\HumanResources\Employee\EmploymentTypeEnum::FULL_TIME;
        $modelData['state']           = \App\Enums\HumanResources\Employee\EmployeeStateEnum::WORKING;

        return StoreEmployee::make()->action($this->organisation, $modelData);
    };

    $newArtefact = function (string $code) {
        $stock = \App\Actions\Goods\Stock\StoreStock::make()->action(
            $this->group,
            array_merge(\App\Models\Goods\Stock::factory()->definition(), ['state' => \App\Enums\Goods\Stock\StockStateEnum::ACTIVE])
        );
        $orgStock = \App\Actions\Inventory\OrgStock\StoreOrgStock::make()->action($this->organisation, $stock);

        return StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => $code, 'name' => $code.' name', 'org_stock_id' => $orgStock->id]);
    };

    $first  = $newArtefact('MANUALJO1');
    $second = $newArtefact('MANUALJO2');
    AttachArtisan::make()->action($first, ['employee_id' => $newEmployee()->id]);
    AttachArtisan::make()->action($second, ['employee_id' => $newEmployee()->id]);

    $alreadyOnBoard = \App\Models\Procurement\PartnerShoppingListItem::create([
        'group_id'        => $this->organisation->group_id,
        'organisation_id' => $this->organisation->id,
        'stock_id'        => $first->orgStock->stock_id,
        'org_stock_id'    => $first->org_stock_id,
        'quantity'        => 3,
        'state'           => \App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum::OPEN,
    ]);

    $jobOrder = \App\Actions\Production\JobOrder\StoreManualJobOrder::make()->action($this->production, [
        'reason'    => 'partner',
        'notes'     => 'ELEMENTS - PO85',
        'needed_by' => '2026-10-06',
        'lines'     => [
            ['artefact_id' => $first->id, 'quantity' => 2],
            ['artefact_id' => $second->id, 'quantity' => 1],
        ],
    ]);

    $lines = \App\Models\Procurement\PartnerShoppingListItem::where('job_order_id', $jobOrder->id)->get();

    expect($jobOrder->state)->toBe(JobOrderStateEnum::IN_PROCESS)
        ->and($jobOrder->jobOrderItems()->count())->toBe(2)
        ->and($jobOrder->employee_id)->toBe($first->artisans()->first()->id)
        ->and($jobOrder->internal_notes)->toBe('Partner: ELEMENTS - PO85')
        ->and($jobOrder->data['reason'])->toBe('partner')
        ->and($lines)->toHaveCount(2)
        ->and($lines->every(fn ($line) => $line->needed_by->format('Y-m-d') === '2026-10-06' && $line->notes === 'Partner: ELEMENTS - PO85'))->toBeTrue()
        ->and($alreadyOnBoard->refresh()->job_order_id)->toBeNull();

    $reversed = \App\Actions\Production\JobOrder\StoreManualJobOrder::make()->action($this->production, [
        'reason' => 'stock',
        'lines'  => [
            ['artefact_id' => $second->id, 'quantity' => 1],
            ['artefact_id' => $first->id, 'quantity' => 1],
        ],
    ]);
    expect($reversed->employee_id)->toBe($second->artisans()->first()->id);

    $second->update(['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::DORMANT]);
    $woken = \App\Actions\Production\JobOrder\StoreManualJobOrder::make()->action($this->production, [
        'reason' => 'stock',
        'lines'  => [['artefact_id' => $second->id, 'quantity' => 1]],
    ]);
    expect($woken->jobOrderItems()->count())->toBe(1)
        ->and($second->refresh()->state)->toBe(\App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE);

    expect(fn () => \App\Actions\Production\JobOrder\StoreManualJobOrder::make()->action($this->production, [
        'reason' => 'stock',
        'lines'  => [],
    ]))->toThrow(ValidationException::class);
});

test('creating job orders from a click event payload is a validation error, not a database error', function () {
    actingAs($this->guest->getUser());

    \Pest\Laravel\post(route('grp.org.productions.show.to_produce.job_orders.store', [$this->organisation->slug, $this->production->slug]), [
        'ids' => ['isTrusted' => true, '_vts' => 1792585086231],
    ])->assertSessionHasErrors('ids');
});

test('a job order stays on the to produce board once its line is ordered, until it is received', function () {
    $this->artefact->manufactureTasks()->sync([
        $this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1],
    ]);
    $stocks    = createStocks($this->group);
    $orgStocks = createOrgStocks($this->organisation, [$stocks[0]]);
    \App\Models\Procurement\PartnerShoppingListItem::where('org_stock_id', $orgStocks[0]->id)->forceDelete();
    Artefact::where('production_id', $this->production->id)
        ->where('org_stock_id', $orgStocks[0]->id)
        ->where('id', '!=', $this->artefact->id)
        ->update(['org_stock_id' => null]);
    $this->artefact->update(['org_stock_id' => $orgStocks[0]->id]);
    $orgStocks[0]->update(['packed_in' => 1]);

    $line = \App\Models\Procurement\PartnerShoppingListItem::create([
        'group_id'        => $this->group->id,
        'organisation_id' => $this->organisation->id,
        'stock_id'        => $stocks[0]->id,
        'org_stock_id'    => $orgStocks[0]->id,
        'quantity'        => 4,
    ]);
    $jobOrder = StoreJobOrder::make()->action($this->production, []);
    StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 4]);
    ConfirmJobOrder::make()->action($jobOrder);
    $line->update(['job_order_id' => $jobOrder->id]);

    actingAs($this->guest->getUser());
    $routeParameters = [$this->organisation->slug, $this->production->slug];
    $cardsOf = fn () => collect(get(route('grp.org.productions.show.to_produce.index', $routeParameters))->assertOk()->viewData('page')['props']['groups'])
        ->flatMap(fn (array $lane) => collect($lane['items'])->map(fn ($item) => ['lane' => $lane['label'], 'id' => data_get($item, 'id'), 'state' => data_get($item, 'state'), 'job_order_id' => data_get($item, 'job_order_id')]))
        ->where('job_order_id', $jobOrder->id)
        ->values();

    expect($cardsOf()->all())->toBe([['lane' => 'Assigned', 'id' => $line->id, 'state' => 'open', 'job_order_id' => $jobOrder->id]]);

    $line->update(['state' => \App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum::ORDERED]);
    expect($cardsOf()->all())->toBe([['lane' => 'Assigned', 'id' => $line->id, 'state' => 'ordered', 'job_order_id' => $jobOrder->id]]);

    \App\Actions\Production\PartnerShippingList\UnassignToProduceItems::make()->action($this->production, [$line->id]);
    expect($line->refresh()->job_order_id)->toBe($jobOrder->id)
        ->and($jobOrder->refresh()->trashed())->toBeFalse();

    $jobOrder->update(['state' => JobOrderStateEnum::RECEIVED]);
    expect($cardsOf()->all())->toBe([]);
});

test('both job orders of a carried job keep a card on the to produce board, whichever of them the line stays with', function () {
    $this->artefact->manufactureTasks()->detach();
    $this->artefact->manufactureTasks()->sync([
        $this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1],
    ]);
    $stocks    = createStocks($this->group);
    $orgStocks = createOrgStocks($this->organisation, [$stocks[0]]);
    \App\Models\Procurement\PartnerShoppingListItem::where('org_stock_id', $orgStocks[0]->id)->forceDelete();
    Artefact::where('production_id', $this->production->id)
        ->where('org_stock_id', $orgStocks[0]->id)
        ->where('id', '!=', $this->artefact->id)
        ->update(['org_stock_id' => null]);
    $this->artefact->update(['org_stock_id' => $orgStocks[0]->id]);
    $orgStocks[0]->update(['packed_in' => 1]);

    /* The first part already makes the 2 SKOs asked for, so the line stays and the rest is made for stock. */
    $line = \App\Models\Procurement\PartnerShoppingListItem::create([
        'group_id'        => $this->group->id,
        'organisation_id' => $this->organisation->id,
        'stock_id'        => $stocks[0]->id,
        'org_stock_id'    => $orgStocks[0]->id,
        'quantity'        => 2,
    ]);
    $jobOrder = StoreJobOrder::make()->action($this->production, []);
    $item     = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 10]);
    ConfirmJobOrder::make()->action($jobOrder);
    $line->update(['job_order_id' => $jobOrder->id]);

    CloseManufactureTaskSession::make()->action(
        StartManufactureTaskSession::make()->action($this->guest->getUser(), $item->tasks()->first()),
        ['quantity_made' => 6, 'outcome' => 'carry_over']
    );
    $carried = \App\Models\Production\JobOrder::where('data->carried_from', $jobOrder->id)->firstOrFail();

    actingAs($this->guest->getUser());
    $routeParameters = [$this->organisation->slug, $this->production->slug];
    $cardsOf = fn () => collect(get(route('grp.org.productions.show.to_produce.index', $routeParameters))->assertOk()->viewData('page')['props']['groups'])
        ->flatMap(fn (array $lane) => $lane['items'])
        ->filter(fn ($item) => in_array(data_get($item, 'job_order_id'), [$jobOrder->id, $carried->id]))
        ->map(fn ($item) => [data_get($item, 'job_order_id'), data_get($item, 'state'), (float) data_get($item, 'quantity'), data_get($item, 'notes')])
        ->sortBy(fn (array $card) => $card[0])
        ->values()
        ->all();

    expect($line->refresh()->job_order_id)->toBe($jobOrder->id)
        ->and($cardsOf())->toBe([
            [$jobOrder->id, 'open', 2.0, null],
            [$carried->id, 'stock', 4.0, 'For stock'],
        ]);

    /* A line that follows the carried job wholly leaves the first part for stock. */
    $line->update(['job_order_id' => $carried->id]);
    expect($cardsOf())->toBe([
        [$jobOrder->id, 'stock', 6.0, 'For stock'],
        [$carried->id, 'open', 2.0, null],
    ]);
});
