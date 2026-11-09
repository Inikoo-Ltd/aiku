<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 25 Nov 2024 11:48:10 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Feature;

use App\Actions\Inventory\OrgStock\UI\GetOrgStockShowcase;
use App\Actions\Production\Artefact\StoreArtefact;
use App\Actions\Production\Artefact\UI\GetArtefactShowcase;
use App\Actions\Production\ArtefactDepartment\StoreArtefactDepartment;
use App\Actions\Production\ArtefactFamily\StoreArtefactFamily;
use App\Actions\Production\JobOrder\ConfirmJobOrder;
use App\Actions\Production\JobOrder\StoreJobOrder;
use App\Actions\Production\ManufactureTask\StoreManufactureTask;
use App\Actions\Production\Artefact\AttachRawMaterialToRecipeStep;
use App\Actions\Production\Artefact\DetachRawMaterialFromRecipeStep;
use App\Models\Production\ArtefactManufactureTask;
use App\Models\Production\RecipeStepRawMaterial;
use App\Actions\Production\JobOrderItem\StoreJobOrderItem;
use App\Actions\Production\ManufactureTaskSession\CloseManufactureTaskSession;
use App\Actions\Production\ManufactureTaskSession\StartManufactureTaskSession;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Models\Production\JobOrderItem;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum;
use App\Actions\Production\Production\StoreProduction;
use App\Actions\Production\RawMaterial\StoreRawMaterial;
use App\Actions\Production\RawMaterial\UpdateRawMaterial;
use App\Enums\Production\ManufactureTask\ManufactureTaskOperativeRewardAllowanceTypeEnum;
use App\Enums\Production\ManufactureTask\ManufactureTaskOperativeRewardTermsEnum;
use App\Enums\Production\RawMaterial\RawMaterialStateEnum;
use App\Enums\Production\RawMaterial\RawMaterialStockStatusEnum;
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

    $artefact = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value,
        'code'                    => 'LINKEDART1',
        'name'                    => 'Linked artefact',
        'trade_unit_id'           => $tradeUnit?->id,
        'org_stock_id'            => $orgStock->id,
        'recommended_batch_size'  => 250,
    ]);

    expect($artefact->org_stock_id)->toBe($orgStock->id)
        ->and($artefact->trade_unit_id)->toBe($tradeUnit?->id)
        ->and($artefact->recommended_batch_size)->toBe(250);

    $artefactWithoutBatchSize = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value,
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

    $artefact = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value,
        'code'            => 'RECEIVEART1',
        'name'            => 'Receivable artefact',
        'org_stock_id'    => $orgStock->id,
        'shelf_life_days' => 365,
    ]);
    $artefact->manufactureTasks()->sync([
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

    $boardLine = fn (array $extra) => \App\Models\Procurement\PartnerShoppingListItem::create([
        'group_id'        => $this->organisation->group_id,
        'organisation_id' => $this->organisation->id,
        'stock_id'        => $stock->id,
        'org_stock_id'    => $orgStock->id,
        'quantity'        => 10,
        'state'           => \App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum::OPEN,
        'job_order_id'    => $jobOrder->id,
    ] + $extra);
    $restockLine = $boardLine([]);
    $partnerLine = $boardLine(['partner_organisation_id' => $this->organisation->id]);

    ConfirmJobOrder::make()->action($jobOrder);

    $task = $jobOrderItem->tasks()->first();
    $user = $this->guest->getUser();
    $session = StartManufactureTaskSession::make()->action($user, $task);
    CloseManufactureTaskSession::make()->action($session, ['quantity_made' => 10]);

    \App\Actions\Production\JobOrder\HoldJobOrder::make()->action($jobOrder, ['on_hold' => true, 'kind' => 'missing_label', 'reason' => 'Labels out of stock'], $user);

    expect(fn () => \App\Actions\Production\JobOrder\ReceiveJobOrderIntoStock::make()->action($jobOrder->refresh(), ['location_id' => $location->id]))
        ->toThrow(ValidationException::class, 'On hold: Labels out of stock. Clear the hold before receiving.')
        ->and(\App\Actions\Production\JobOrder\UI\IndexJobOrders::make()->getElementGroups($this->production)['hold']['elements']['on_hold'][1])->toBeGreaterThanOrEqual(1);

    \App\Actions\Production\JobOrder\HoldJobOrder::make()->action($jobOrder->refresh(), ['on_hold' => false], $user);

    expect($jobOrder->refresh()->data)->not->toHaveKey('hold')
        ->and($jobOrder->data['holds'])->toHaveCount(1)
        ->and($jobOrder->data['holds'][0])->toMatchArray(['reason' => 'Labels out of stock', 'kind' => 'missing_label', 'cleared_user_id' => $user->id]);

    $jobOrder = \App\Actions\Production\JobOrder\ReceiveJobOrderIntoStock::make()->action($jobOrder, [
        'location_id' => $location->id,
    ]);

    expect($restockLine->refresh()->state)->toBe(\App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum::DISMISSED)
        ->and($partnerLine->refresh()->state)->toBe(\App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum::OPEN);

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
        ->and($batchCode->code)->toBe($jobOrder->reference.'-'.$artefact->code)
        ->and($batchCode->expiry_date->toDateString())->toBe(now()->addDays(365)->toDateString())
        ->and(\App\Models\Inventory\OrgStockMovementBatch::where('org_stock_movement_id', $movement->id)->get(['batch_code_id', 'quantity'])->toArray())
        ->toEqual([['batch_code_id' => $batchCode->id, 'quantity' => '10.000000']]);

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

    $artefact = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value,
        'code'         => 'RECEIVEART2',
        'name'         => 'Receivable artefact with recipe',
        'org_stock_id' => $orgStock->id,
    ]);
    $artefact->manufactureTasks()->sync([
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
    /* The recipe counts raw material units, the stock counts SKOs of five. */
    $inputOrgStock->update(['packed_in' => 5]);

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
        ->and((float) $deductionMovement->quantity)->toBe(-1.0);

    $inputLocationOrgStock->refresh();
    expect((float) $inputLocationOrgStock->quantity)->toBe(99.0);
});

test('raw material with no stock location is deducted at the factory location instead of blocking the receive', function () {
    $orgStock = \App\Actions\Inventory\OrgStock\StoreOrgStock::make()->action($this->organisation, \App\Actions\Goods\Stock\StoreStock::make()->action(
        $this->group,
        array_merge(\App\Models\Goods\Stock::factory()->definition(), ['state' => \App\Enums\Goods\Stock\StockStateEnum::ACTIVE])
    ));
    $inputOrgStock = \App\Actions\Inventory\OrgStock\StoreOrgStock::make()->action($this->organisation, \App\Actions\Goods\Stock\StoreStock::make()->action(
        $this->group,
        array_merge(\App\Models\Goods\Stock::factory()->definition(), ['state' => \App\Enums\Goods\Stock\StockStateEnum::ACTIVE])
    ));

    $artefact = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value,
        'code'         => 'RECEIVEART3',
        'name'         => 'Artefact whose raw material has no location',
        'org_stock_id' => $orgStock->id,
    ]);
    $artefact->manufactureTasks()->sync([
        $this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1],
    ]);

    $rawMaterial = StoreRawMaterial::make()->action($this->production, [
        'type'        => RawMaterialTypeEnum::CONSUMABLE->value,
        'state'       => RawMaterialStateEnum::ORPHAN->value,
        'code'        => 'RECIPERM3',
        'description' => 'raw material kept nowhere',
        'unit'        => RawMaterialUnitEnum::KILOGRAM->value,
        'unit_cost'   => 10,
    ]);
    UpdateRawMaterial::make()->action($rawMaterial, ['org_stock_id' => $inputOrgStock->id]);
    AttachRawMaterialToRecipeStep::make()->action(
        ArtefactManufactureTask::where('artefact_id', $artefact->id)->where('manufacture_task_id', $this->manufactureTask->id)->first(),
        ['raw_material_id' => $rawMaterial->id, 'quantity_per_unit' => 0.5]
    );

    $warehouse = \App\Actions\Inventory\Warehouse\StoreWarehouse::make()->action($this->organisation, [
        'code' => 'WH-REC3',
        'name' => 'Warehouse for receiving 3',
    ]);
    $area = \App\Actions\Inventory\WarehouseArea\StoreWarehouseArea::make()->action($warehouse, [
        'code' => 'A-REC3',
        'name' => 'Area receiving 3',
    ]);
    $location = \App\Actions\Inventory\Location\StoreLocation::make()->action($area, ['code' => 'L-REC3', 'name' => 'Loc receiving 3'] + \App\Models\Inventory\Location::factory()->definition());
    $factoryLocation = \App\Actions\Inventory\Location\StoreLocation::make()->action($area, ['code' => 'L-FACT3', 'name' => 'Factory 3'] + \App\Models\Inventory\Location::factory()->definition());

    $previousFactoryLocationId = $this->production->factory_location_id;
    $this->production->update(['factory_location_id' => $factoryLocation->id]);

    try {
        $jobOrder = StoreJobOrder::make()->action($this->production, []);
        $jobOrderItem = StoreJobOrderItem::make()->action($jobOrder, [
            'artefact_id' => $artefact->id,
            'quantity'    => 4,
        ]);
        ConfirmJobOrder::make()->action($jobOrder);

        $session = StartManufactureTaskSession::make()->action($this->guest->getUser(), $jobOrderItem->tasks()->first());
        CloseManufactureTaskSession::make()->action($session, ['quantity_made' => 4]);

        $jobOrder = \App\Actions\Production\JobOrder\ReceiveJobOrderIntoStock::make()->action($jobOrder->refresh(), [
            'location_id' => $location->id,
        ]);
    } finally {
        $this->production->update(['factory_location_id' => $previousFactoryLocationId]);
    }

    expect($jobOrder->state)->toBe(JobOrderStateEnum::RECEIVED)
        ->and((float) \App\Models\Inventory\LocationOrgStock::where('location_id', $factoryLocation->id)->where('org_stock_id', $inputOrgStock->id)->value('quantity'))->toBe(-2.0);
});

test('artefact compliance status reflects its items', function () {
    $status = \App\Actions\Production\Artefact\GetArtefactComplianceStatus::run($this->artefact);
    expect($status['status'])->toBe(\App\Enums\Production\Artefact\ArtefactComplianceStatusEnum::NOT_CONFIGURED);

    $item = \App\Models\Production\ArtefactComplianceItem::create([
        'group_id'        => $this->artefact->group_id,
        'organisation_id' => $this->artefact->organisation_id,
        'artefact_id'     => $this->artefact->id,
        'org_stock_id'    => $this->artefact->org_stock_id ?? labelTestOrgStock($this->organisation, $this->group)->id,
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
        'org_stock_id'    => $this->artefact->org_stock_id ?? labelTestOrgStock($this->organisation, $this->group)->id,
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
        'org_stock_id'    => $this->artefact->org_stock_id ?? labelTestOrgStock($this->organisation, $this->group)->id,
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

test('costings import resolves materials, creates artefacts and writes per-unit recipes once', function () {
    $dir = sys_get_temp_dir().'/costings-'.uniqid();
    mkdir($dir);
    $existingArtefact = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'CST-EXIST', 'name' => 'Existing product']);
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

    $auroraArtefact = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'CST-AURORA2', 'name' => 'Aurora product', 'source_id' => '4:999']);
    $auroraArtefact->manufactureTasks()->sync([$this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1]]);
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
    $artefact = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'CST-AURORA', 'name' => 'Aurora product', 'source_id' => '4:99999', 'recommended_batch_size' => 10]);
    $artefact->manufactureTasks()->sync([$this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1]]);
    $step = ArtefactManufactureTask::where('artefact_id', $artefact->id)->first();
    AttachRawMaterialToRecipeStep::make()->action($step, ['raw_material_id' => $this->rawMaterial->id, 'quantity_per_unit' => 10]);

    $this->artisan('manufacture:normalise-aurora-recipes', ['production' => $this->production->slug])->assertExitCode(0);
    expect((float)$step->rawMaterials()->first()->quantity_per_unit)->toBe(10.0);

    $this->artisan('manufacture:normalise-aurora-recipes', ['production' => $this->production->slug, '--write' => true])->assertExitCode(0);
    expect((float)$step->rawMaterials()->first()->quantity_per_unit)->toBe(1.0);

    $this->artisan('manufacture:normalise-aurora-recipes', ['production' => $this->production->slug, '--write' => true])->assertExitCode(0);
    expect((float)$step->rawMaterials()->first()->quantity_per_unit)->toBe(1.0)
        ->and($artefact->refresh()->data['recipe_quantities_normalised_at'])->not->toBeNull();

    $costed = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'CST-AURORA3', 'name' => 'Costed product', 'source_id' => '4:99998', 'recommended_batch_size' => 10]);
    $costed->update(['data' => ['costings_recipe' => ['imported_at' => now()->toDateTimeString()]]]);
    $costed->manufactureTasks()->sync([$this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1]]);
    $costedStep = ArtefactManufactureTask::where('artefact_id', $costed->id)->first();
    AttachRawMaterialToRecipeStep::make()->action($costedStep, ['raw_material_id' => $this->rawMaterial->id, 'quantity_per_unit' => 0.4]);

    $this->artisan('manufacture:normalise-aurora-recipes', ['production' => $this->production->slug, '--write' => true])->assertExitCode(0);
    expect((float)$costedStep->rawMaterials()->first()->quantity_per_unit)->toBe(0.4);
});

test('artefact labels can be saved, updated and deleted', function () {
    $orgStock = labelTestOrgStock($this->organisation, $this->group);
    $this->artefact = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'LABEL-'.$orgStock->id, 'name' => 'Labelled']);
    $this->artefact->update(['org_stock_id' => $orgStock->id]);

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
    expect($label->org_stock_id)->toBe($orgStock->id)
        ->and($label->artefact_id)->toBe($this->artefact->id)
        ->and($orgStock->labels()->pluck('id')->all())->toBe([$labelId])
        ->and($label->name)->toBe('Front')
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
        'org_stock_id'    => labelTestOrgStock($this->organisation, $this->group)->id,
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

test('SKO labels are the compliance team\'s: workers draft, supervisors publish, and a label missing mandatory information cannot be published', function () {
    \App\Actions\SysAdmin\Group\Seeders\SeedGroupPermissions::run($this->group);
    $orgStock = labelTestOrgStock($this->organisation, $this->group);
    $layout   = [
        'orientation' => 'portrait',
        'columns'     => 2,
        'rows'        => 4,
        'page_margin' => 8,
        'gap'         => 3,
        'fields'      => [['source' => 'cpnp_number', 'text' => 'CPNP 123', 'x' => 0.1, 'y' => 0.2, 'font_size' => 8, 'color' => '#111827']],
    ];

    $userWithRole = function (string $role) {
        $user = \App\Models\SysAdmin\User::factory()->create(['group_id' => $this->group->id]);
        $user->syncRoles([$role]);

        return $user->fresh();
    };

    actingAs($userWithRole('compliance-worker'));
    $labelId = \Pest\Laravel\postJson(route('grp.models.org_stock.labels.store', $orgStock->id), array_merge($layout, ['name' => 'Imported']))
        ->assertCreated()
        ->json('data.id');
    $label = \App\Models\Production\ArtefactLabel::find($labelId);

    expect($label->org_stock_id)->toBe($orgStock->id)
        ->and($label->stock_id)->toBe($orgStock->stock_id)
        ->and($orgStock->stock->labels->pluck('id')->all())->toBe([$labelId])
        ->and($label->artefact_id)->toBeNull();

    \Pest\Laravel\patchJson(route('grp.models.stock.label_mandatory_information.update', $orgStock->stock_id), ['label_mandatory_information' => ['cpnp_number', 'ingredients']])
        ->assertForbidden();
    \Pest\Laravel\postJson(route('grp.models.org_stock.labels.publish', [$orgStock->id, $labelId]))
        ->assertForbidden();

    actingAs($userWithRole('compliance-manager'));
    \Pest\Laravel\patchJson(route('grp.models.stock.label_mandatory_information.update', $orgStock->stock_id), ['label_mandatory_information' => ['cpnp_number', 'ingredients']])
        ->assertOk();
    expect($label->refresh()->missingMandatoryInformation())->toBe(['ingredients']);

    actingAs($userWithRole('compliance-supervisor'));
    \Pest\Laravel\postJson(route('grp.models.org_stock.labels.publish', [$orgStock->id, $labelId]))
        ->assertUnprocessable();

    \Pest\Laravel\postJson(route('grp.models.org_stock.labels.on_artwork', [$orgStock->id, $labelId]), ['on_artwork' => ['ingredients']])
        ->assertOk();
    \Pest\Laravel\postJson(route('grp.models.org_stock.labels.publish', [$orgStock->id, $labelId]))
        ->assertOk();

    expect($label->refresh()->state)->toBe(\App\Enums\Production\Artefact\ArtefactLabelStateEnum::PUBLISHED)
        ->and($label->missingMandatoryInformation())->toBe([]);

    $otherOrgStock = labelTestOrgStock($this->organisation, $this->group);
    \Pest\Laravel\postJson(route('grp.models.org_stock.labels.unpublish', [$otherOrgStock->id, $labelId]))
        ->assertNotFound();
});

test('compliance labels place icons, wrapped texts, free texts and a text per language taken from the product record', function () {
    \App\Actions\SysAdmin\Group\Seeders\SeedGroupPermissions::run($this->group);
    $orgStock  = labelTestOrgStock($this->organisation, $this->group);
    $tradeUnit = \App\Actions\Goods\TradeUnit\StoreTradeUnit::make()->action($this->group, \App\Models\Goods\TradeUnit::factory()->definition());
    $tradeUnit->update([
        'pictogram_flammable' => true,
        'label_info'          => [
            'markets'                  => ['uk'],
            'languages'                => ['de'],
            'ce_marking'                    => true,
            'best_before'                   => 'pao_12m',
            'sorting_recycling_information' => true,
            'packaging_material_codes'      => ['show' => true, 'value' => ['pet_1', 'pap_21']],
        ],
    ]);
    $orgStock->tradeUnits()->sync([$tradeUnit->id => ['quantity' => 1]]);
    $this->organisation->update(['settings' => array_merge($this->organisation->settings ?? [], ['gpsr' => ['operator' => [
        'code'     => 'SK',
        'position' => 3,
        'role'     => 'Dovozca / EU Responsible Person:',
        'name'     => 'Ancient Wisdom s.r.o.',
        'short'    => 'SK & EU RP Ancient Wisdom s.r.o.',
        'address'  => 'Prílohy 583/57, 919 26 Zavar, Slovensko',
        'email'    => 'contact@awgifts.eu',
    ]]])]);
    $this->group->update(['settings' => array_merge($this->group->settings ?? [], ['gpsr' => ['eu_responsible_organisation_id' => $this->organisation->id]])]);

    $information = \App\Actions\Inventory\OrgStock\UI\GetOrgStockLabelInformation::run($orgStock->refresh());
    expect($information['packaging_materials'])->toBe('pet_1,pap_21')
        ->and($information['hazard_pictograms'])->toBe('flammable')
        ->and($information['ce_marking'])->toBe('ce')
        ->and($information['ukca_marking'])->toBe('')
        ->and($information['eu_responsible_person'])->toBe("RP in EU: Ancient Wisdom s.r.o.\nPrílohy 583/57, 919 26 Zavar, Slovensko\ncontact@awgifts.eu")
        ->and($information['warnings:de'])->toBe('')
        ->and($information['directions_for_use:de'])->toBe('')
        ->and($information['product_name:de'])->toBe('')
        ->and($information['period_after_opening'])->toBe('pao_12m')
        ->and($information['sorting_information'])->toBe('triman,info_tri')
        ->and($information['free_text'])->toBe('');

    foreach (['pao_12m', 'triman', 'info_tri'] as $icon) {
        expect(\App\Actions\Production\Artefact\Label\GetArtefactLabelIconSource::run($icon))->toStartWith('data:image/svg+xml;base64,');
    }

    $userWithRole = function (string $role) {
        $user = \App\Models\SysAdmin\User::factory()->create(['group_id' => $this->group->id]);
        $user->syncRoles([$role]);

        return $user->fresh();
    };

    actingAs($userWithRole('compliance-manager'));
    \Pest\Laravel\patchJson(route('grp.models.stock.label_mandatory_information.update', $orgStock->stock_id), ['label_mandatory_information' => ['ingredients:de']])
        ->assertUnprocessable();
    \Pest\Laravel\patchJson(route('grp.models.stock.label_mandatory_information.update', $orgStock->stock_id), ['label_mandatory_information' => ['warnings:de', 'packaging_materials', 'product_name:de', 'period_after_opening']])
        ->assertOk();

    $layout = [
        'orientation' => 'portrait',
        'columns'     => 2,
        'rows'        => 3,
        'page_margin' => 8,
        'gap'         => 3,
        'fields'      => [
            ['source' => 'packaging_materials', 'text' => 'pet_1,pap_21', 'x' => 0.05, 'y' => 0.05, 'font_size' => 8, 'color' => '#000000', 'icon_size' => 0.25],
            ['source' => 'warnings:de', 'text' => "Nicht verschlucken.\nAußer Reichweite von Kindern aufbewahren.", 'x' => 0.05, 'y' => 0.5, 'font_size' => 6, 'color' => '#000000', 'box_width' => 0.8, 'height' => 6.2, 'rotation' => 90],
            ['source' => 'product_name:de', 'text' => 'Magnesium-Badesalz', 'x' => 0.05, 'y' => 0.3, 'font_size' => 7, 'color' => '#000000'],
            ['source' => 'period_after_opening', 'text' => 'pao_12m', 'x' => 0.6, 'y' => 0.05, 'font_size' => 8, 'color' => '#000000', 'icon_size' => 0.2],
            ['source' => 'sorting_information', 'text' => 'triman,info_tri', 'x' => 0.6, 'y' => 0.3, 'font_size' => 8, 'color' => '#000000', 'icon_size' => 0.2],
            ['source' => 'free_text', 'text' => 'Weight / Peso / Gewicht', 'x' => 0.05, 'y' => 0.9, 'font_size' => 6, 'color' => '#000000'],
        ],
    ];

    actingAs($userWithRole('compliance-worker'));
    $labelId = \Pest\Laravel\postJson(route('grp.models.org_stock.labels.store', $orgStock->id), array_merge($layout, ['name' => 'Compliance']))
        ->assertCreated()
        ->json('data.id');
    $label = \App\Models\Production\ArtefactLabel::find($labelId);

    expect($label->layout['fields'][0]['icon_size'])->toBe(0.25)
        ->and($label->layout['fields'][5]['text'])->toBe('Weight / Peso / Gewicht')
        ->and($label->layout['fields'][1]['box_width'])->toBe(0.8)
        ->and($label->layout['fields'][1]['height'])->toBe(6.2)
        ->and($label->missingMandatoryInformation())->toBe([]);

    \Pest\Laravel\post(route('grp.models.org_stock.label_sheet', $orgStock->id), $layout)
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

test('a batch made for stock is never offered a goods out bay as its shelf', function () {
    $this->artefact->manufactureTasks()->sync([
        $this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1],
    ]);
    $user = $this->guest->getUser();
    \App\Models\Production\ManufactureTaskSession::where('user_id', $user->id)
        ->where('state', \App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum::OPEN)->delete();

    $orgStock = createOrgStocks($this->organisation, [createStocks($this->group)[0]])[0];
    $this->artefact->update(['org_stock_id' => $orgStock->id]);

    $warehouse  = \App\Actions\Inventory\Warehouse\StoreWarehouse::make()->action($this->organisation, ['code' => 'WH-SHELF', 'name' => 'Shelf warehouse']);
    $area       = \App\Actions\Inventory\WarehouseArea\StoreWarehouseArea::make()->action($warehouse, ['code' => 'A-SHELF', 'name' => 'Shelf area']);
    $locationOf = fn (string $code) => \App\Actions\Inventory\Location\StoreLocation::make()->action($area, ['code' => $code, 'name' => $code] + \App\Models\Inventory\Location::factory()->definition());

    $bay = $locationOf('BAY-SHELF');
    $bay->update(['is_goods_out' => true]);
    $shelf = $locationOf('SHELF-01');

    $orgStock->locations()->detach();
    foreach ([$bay, $shelf] as $location) {
        \App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock::make()->action($orgStock, $location, [
            'type' => \App\Enums\Inventory\LocationStock\LocationStockTypeEnum::PICKING,
        ]);
    }

    $jobOrder = StoreJobOrder::make()->action($this->production, []);
    $item     = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $this->artefact->id, 'quantity' => 12]);
    ConfirmJobOrder::make()->action($jobOrder);
    CloseManufactureTaskSession::make()->action(
        StartManufactureTaskSession::make()->action($user, $item->tasks()->first()),
        ['quantity_made' => 12, 'outcome' => 'complete']
    );

    $row = collect(\App\Actions\Dispatching\ProductionOutput\GetFinishedProductionJobOrders::run($warehouse))
        ->where('destination.type', 'stock')
        ->flatMap(fn (array $trip) => collect($trip['jobs'])->flatMap(fn (array $job) => $job['items']))
        ->firstWhere('item_id', $item->id);

    expect($row['location_code'])->toBe('SHELF-01')
        ->and(collect($row['locations'])->pluck('code')->all())->toBe(['SHELF-01']);
});

test('a finished product goes to goods in while another product of the same job order is still being made', function () {
    $user = $this->guest->getUser();
    \App\Models\Production\ManufactureTaskSession::where('user_id', $user->id)
        ->where('state', \App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum::OPEN)->delete();

    $artefactFor = function (string $code) {
        $stock    = \App\Actions\Goods\Stock\StoreStock::make()->action($this->group, array_merge(\App\Models\Goods\Stock::factory()->definition(), ['state' => \App\Enums\Goods\Stock\StockStateEnum::ACTIVE]));
        $orgStock = \App\Actions\Inventory\OrgStock\StoreOrgStock::make()->action($this->organisation, $stock);
        $orgStock->update(['packed_in' => 1]);
        $artefact = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => $code, 'name' => $code, 'org_stock_id' => $orgStock->id]);
        $artefact->manufactureTasks()->sync([$this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1]]);

        return $artefact;
    };

    $warehouse = \App\Actions\Inventory\Warehouse\StoreWarehouse::make()->action($this->organisation, ['code' => 'WH-PART', 'name' => 'Partial warehouse']);
    $area      = \App\Actions\Inventory\WarehouseArea\StoreWarehouseArea::make()->action($warehouse, ['code' => 'A-PART', 'name' => 'Partial area']);
    \App\Actions\Inventory\Location\StoreLocation::make()->action($area, ['code' => 'SHELF-PART', 'name' => 'Partial shelf'] + \App\Models\Inventory\Location::factory()->definition());

    $jobOrder = StoreJobOrder::make()->action($this->production, []);
    $soap     = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $artefactFor('PART-SOAP')->id, 'quantity' => 12]);
    $slices   = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $artefactFor('PART-SLICE')->id, 'quantity' => 5]);
    ConfirmJobOrder::make()->action($jobOrder);

    $make = fn (JobOrderItem $item) => CloseManufactureTaskSession::make()->action(
        StartManufactureTaskSession::make()->action($user, $item->tasks()->first()),
        ['quantity_made' => $item->quantity, 'outcome' => 'complete']
    );
    $waitingItemIds = fn () => collect(\App\Actions\Dispatching\ProductionOutput\GetFinishedProductionJobOrders::run($warehouse))
        ->flatMap(fn (array $trip) => collect($trip['jobs'])->flatMap(fn (array $job) => $job['items']))
        ->pluck('item_id')->intersect([$soap->id, $slices->id])->values()->all();

    $make($soap);
    expect($waitingItemIds())->toBe([$soap->id]);

    \App\Actions\Dispatching\ProductionOutput\PutAwayFinishedJobOrder::make()->action($warehouse, [$jobOrder->id], 'SHELF-PART', true);
    expect((float) $soap->refresh()->quantity_received)->toEqual(12.0)
        ->and($jobOrder->refresh()->state)->toBe(JobOrderStateEnum::CONFIRMED)
        ->and($waitingItemIds())->toBe([]);

    $make($slices);
    expect($waitingItemIds())->toBe([$slices->id]);

    \App\Actions\Dispatching\ProductionOutput\PutAwayFinishedJobOrder::make()->action($warehouse, [$jobOrder->id], 'SHELF-PART', true);
    expect($jobOrder->refresh()->state)->toBe(JobOrderStateEnum::RECEIVED);
});

test('production for stock is flagged on the backlog and booking it in fulfils the oldest lines', function () {
    $stock = \App\Actions\Goods\Stock\StoreStock::make()->action(
        $this->group,
        array_merge(\App\Models\Goods\Stock::factory()->definition(), [
            'state' => \App\Enums\Goods\Stock\StockStateEnum::ACTIVE
        ])
    );
    $orgStock = \App\Actions\Inventory\OrgStock\StoreOrgStock::make()->action($this->organisation, $stock);
    $orgStock->update(['packed_in' => 10, 'quantity_in_locations' => 0, 'quantity_available' => 0]);

    $artefact = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value,
        'code'         => 'SURPLUS1',
        'name'         => 'Surplus artefact',
        'org_stock_id' => $orgStock->id,
    ]);
    $artefact->manufactureTasks()->sync([
        $this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1],
    ]);

    $area = \App\Actions\Inventory\WarehouseArea\StoreWarehouseArea::make()->action(
        \App\Actions\Inventory\Warehouse\StoreWarehouse::make()->action($this->organisation, ['code' => 'WH-SUR', 'name' => 'Surplus warehouse']),
        ['code' => 'A-SUR', 'name' => 'Surplus area']
    );
    $location = \App\Actions\Inventory\Location\StoreLocation::make()->action(
        $area,
        ['code' => 'L-SUR', 'name' => 'Surplus location'] + \App\Models\Inventory\Location::factory()->definition()
    );

    $line = fn (float $quantity, array $extra = []) => \App\Models\Procurement\PartnerShoppingListItem::create([
        'group_id'        => $this->group->id,
        'organisation_id' => $this->organisation->id,
        'stock_id'        => $stock->id,
        'org_stock_id'    => $orgStock->id,
        'quantity'        => $quantity,
    ] + $extra);

    $jobOrder = StoreJobOrder::make()->action($this->production, []);
    $jobOrderItem = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $artefact->id, 'quantity' => 100]);
    $line(1, ['job_order_id' => $jobOrder->id, 'quantity_to_produce' => 10, 'state' => \App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum::ORDERED]);
    $older = $line(1, ['created_at' => now()->subDay()]);
    $newer = $line(12);

    ConfirmJobOrder::make()->action($jobOrder);

    actingAs($this->guest->getUser());
    $routeParameters = [$this->organisation->slug, $this->production->slug];
    $backlog = fn (array $query = []) => collect(get(route('grp.org.productions.show.to_produce.index', $routeParameters + $query))
        ->assertOk()->viewData('page')['props']['groups'])->firstWhere('label', 'Backlog')['items'];

    expect(collect($backlog())->firstWhere('id', $older->id)['pipeline'] ?? null)->toBe(['pending_booking' => 0.0, 'in_production' => 1.0, 'job_orders' => [$jobOrder->reference]]);
    expect(collect(GetArtefactShowcase::run($artefact->refresh())['todo_batches'])->pluck('id'))->toContain($jobOrderItem->id);
    get(route('grp.org.productions.show.crafts.artefacts.show', $routeParameters + [2 => $artefact->slug, 'tab' => 'current_orders']))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('current_orders.data.0.id', $jobOrderItem->id));

    $session = StartManufactureTaskSession::make()->action($this->guest->getUser(), $jobOrderItem->tasks()->first());
    CloseManufactureTaskSession::make()->action($session, ['quantity_made' => 100]);

    $items = collect($backlog())->keyBy('id');
    expect($items[$older->id]['pipeline']['pending_booking'])->toBe(1.0)
        ->and($items[$newer->id]['pipeline']['pending_booking'])->toBe(9.0)
        ->and(collect($backlog(['ignore_pipeline' => 1]))->pluck('pipeline')->filter()->all())->toBe([]);

    \App\Actions\Production\JobOrder\ReceiveJobOrderIntoStock::make()->action($jobOrder->refresh(), ['location_id' => $location->id]);

    expect($older->refresh()->state)->toBe(\App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum::DISMISSED)
        ->and($newer->refresh()->state)->toBe(\App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum::OPEN)
        ->and((float) $newer->quantity)->toBe(3.0);

    $showcase = GetArtefactShowcase::run($artefact);
    expect(collect($showcase['todo_batches'])->pluck('id'))->not->toContain($jobOrderItem->id)
        ->and(collect($showcase['recent_batches'])->firstWhere('id', $jobOrderItem->id))->toMatchArray(['reference' => $jobOrder->reference, 'quantity_made' => 100.0, 'quantity_received' => 100.0]);

    get(route('grp.org.productions.show.crafts.artefacts.show', $routeParameters + [2 => $artefact->slug, 'tab' => 'batches']))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('batches.data.0.id', $jobOrderItem->id)
            ->where('batches.data.0.status', 'received')
            ->where('batches.data.0.batch_code', $jobOrder->reference.'-'.$artefact->code)
            ->where('batches.data.0.quantity_made', 100)
            ->where('batches.data.0.started_at', fn ($startedAt) => $startedAt !== null)
            ->where('batches.data.0.finished_at', fn ($finishedAt) => $finishedAt !== null)
            ->where('batches.data.0.steps.0.quantity_made', 100)
            ->where('batches.totals.all_time', 100)
            ->where('batches.totals.last_30_days', 100)
            ->where('tabs.navigation.batches.number', 1)
            ->where('tabs.navigation.current_orders.number', 0));

    $cancelledJobOrder = StoreJobOrder::make()->action($this->production, []);
    StoreJobOrderItem::make()->action($cancelledJobOrder, ['artefact_id' => $artefact->id, 'quantity' => 50])->delete();
    $cancelledJobOrder->delete();

    get(route('grp.org.productions.show.crafts.artefacts.show', $routeParameters + [2 => $artefact->slug, 'tab' => 'batches']))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('batches.data', 1)->where('tabs.navigation.batches.number', 1));
    get(route('grp.org.productions.show.crafts.artefacts.show', $routeParameters + [2 => $artefact->slug, 'tab' => 'batches', 'batches_elements' => ['cancelled' => 'cancelled']]))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('batches.data', 1)->where('batches.data.0.status', 'cancelled'));

    $isHub = $this->organisation->is_manufacturing_hub;
    $this->organisation->update(['is_manufacturing_hub' => true]);
    get(route('grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.show', [$this->organisation->slug, $location->warehouse->slug, $orgStock->slug, 'tab' => 'artefact_batches']))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('artefact_batches.data.0.id', $jobOrderItem->id)
            ->where('tabs.navigation.artefact_batches.number', 1)
            ->where('tabs.navigation.artefact_batches.type', 'icon'));
    expect(GetOrgStockShowcase::run($location->warehouse, $orgStock->refresh())['production'])->toMatchArray(['code' => $artefact->code, 'open_jobs' => 0, 'jobs_done' => 1]);
    $this->organisation->update(['is_manufacturing_hub' => false]);
    get(route('grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.show', [$this->organisation->slug, $location->warehouse->slug, $orgStock->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page->missing('tabs.navigation.artefact_batches'));
    expect(GetOrgStockShowcase::run($location->warehouse, $orgStock->refresh())['production'])->toBeNull();
    $this->organisation->update(['is_manufacturing_hub' => $isHub]);
});

test('SKO made in-house without an artefact can get one from the trade unit composition', function () {
    $organisation = $this->production->organisation;
    $stock        = \App\Actions\Goods\Stock\StoreStock::make()->action(
        $this->group,
        array_merge(\App\Models\Goods\Stock::factory()->definition(), [
            'state' => \App\Enums\Goods\Stock\StockStateEnum::ACTIVE
        ])
    );
    $orgStock  = \App\Actions\Inventory\OrgStock\StoreOrgStock::make()->action($organisation, $stock);
    $tradeUnit = \App\Actions\Goods\TradeUnit\StoreTradeUnit::make()->action($this->group, \App\Models\Goods\TradeUnit::factory()->definition());
    $orgStock->tradeUnits()->syncWithoutDetaching([$tradeUnit->id => ['quantity' => 1]]);

    $compositionRow = fn () => collect(\App\Actions\Goods\TradeUnit\UI\GetTradeUnitComposition::run($tradeUnit)['org_stocks'])->firstWhere('id', $orgStock->id);

    expect($orgStock->refresh()->is_made_in_house)->toBeFalse()
        ->and($compositionRow()['missing_artefact'])->toBeNull();

    \App\Actions\Inventory\OrgStock\UpdateOrgStock::make()->action($orgStock, ['is_made_in_house' => true]);

    $department = StoreArtefactDepartment::make()->action($this->production, ['code' => 'INHDEP', 'name' => 'In-house department']);
    $family     = StoreArtefactFamily::make()->action($department, ['code' => 'INHFAM', 'name' => 'In-house family']);
    $stockFamily    = \App\Actions\Goods\StockFamily\StoreStockFamily::make()->action($this->group, array_merge(\App\Models\Goods\StockFamily::factory()->definition(), ['code' => 'INHSF']));
    $orgStockFamily = \App\Actions\Inventory\OrgStockFamily\StoreOrgStockFamily::make()->action($organisation, $stockFamily, []);
    $orgStock->update(['org_stock_family_id' => $orgStockFamily->id]);
    $family->update(['org_stock_family_id' => $orgStockFamily->id]);
    StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'INHSIB', 'name' => 'Sibling', 'artefact_family_id' => $family->id, 'recommended_batch_size' => 48, 'shelf_life_days' => 730]);

    $missing = $compositionRow()['missing_artefact'];
    expect($missing)->not->toBeNull()
        ->and($missing['create_route']['parameters']['org_stock'])->toBe($orgStock->id)
        ->and($missing['link_data'])->toBe(['org_stock_id' => $orgStock->id, 'trade_unit_id' => $tradeUnit->id]);

    get(route($missing['create_route']['name'], $missing['create_route']['parameters']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('CreateModel')
            ->where('formData.blueprint.0.fields.code.value', $orgStock->code)
            ->where('formData.blueprint.0.fields.org_stock_id.value', $orgStock->id)
            ->where('formData.blueprint.0.fields.trade_unit_id.value', $tradeUnit->id)
            ->where('formData.blueprint.0.fields.artefact_family_id.value', $family->id)
            ->where('formData.blueprint.0.fields.recommended_batch_size.value', 48)
            ->where('formData.blueprint.0.fields.shelf_life_days.value', 730));

    expect(StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'INHFAMART', 'name' => 'Family only', 'artefact_family_id' => $family->id])->artefact_department_id)
        ->toBe($department->id);

    $missingArtefactCodes = function () use ($organisation) {
        request()->merge(['elements' => ['artefact' => 'missing_artefact'], 'perPage' => 500]);

        return \App\Actions\Inventory\OrgStock\UI\IndexOrgStocks::make()
            ->initialisation($organisation, [])
            ->handle($organisation, bucket: 'current')
            ->pluck('code');
    };

    expect($missingArtefactCodes())->toContain($orgStock->code);

    $artefact = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'INHOUSE-LINK', 'name' => 'Link me']);

    $this->getJson(route($missing['artefacts_route']['name'], $missing['artefacts_route']['parameters']).'?filter[global]=INHOUSE-LINK')
        ->assertOk()
        ->assertJsonPath('data.0.id', $artefact->id);

    $this->patchJson(route($missing['link_route']['name'], [...$missing['link_route']['parameters'], 'artefact' => $artefact->id]), $missing['link_data'])
        ->assertOk();

    expect($missingArtefactCodes())->not->toContain($orgStock->code);

    $row = $compositionRow();
    expect($row['missing_artefact'])->toBeNull()
        ->and($row['artefacts'][0]['code'])->toBe('INHOUSE-LINK')
        ->and($artefact->refresh()->trade_unit_id)->toBe($tradeUnit->id);

    $otherStock = \App\Actions\Inventory\OrgStock\StoreOrgStock::make()->action($organisation, \App\Actions\Goods\Stock\StoreStock::make()->action(
        $this->group,
        array_merge(\App\Models\Goods\Stock::factory()->definition(), ['state' => \App\Enums\Goods\Stock\StockStateEnum::ACTIVE])
    ));
    StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'INHOUSE-NEW', 'name' => 'New', 'org_stock_id' => $otherStock->id]);

    expect($otherStock->refresh()->is_made_in_house)->toBeTrue();
});

test('the ai assistant sets up artefacts, raw materials, tasks and recipes only when enrolled, logged and revertible', function () {
    $user = $this->guest->getUser();
    $user->update(['can_use_mcp' => true, 'can_use_mcp_production' => false]);
    $suffix = strtoupper(\Illuminate\Support\Str::random(5));
    $tool   = fn (string $class, array $arguments) => \App\Mcp\Servers\AikuServer::actingAs($user)->tool($class, ['production' => $this->production->slug, ...$arguments]);

    $tool(\App\Mcp\Tools\ProductionRecordsTool::class, ['kind' => 'manufacture_task'])->assertHasErrors(['not enabled for this user']);

    [, , $shop] = createShop();
    expect($shop->organisation_id)->toBe($this->production->organisation_id);
    $shopkeeper = \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($this->production->group, array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => []]))->getUser();
    expect($this->production->canBeSetUpBy($shopkeeper))->toBeFalse();
    setPermissionsTeamId($shopkeeper->group_id);
    $shopkeeper->givePermissionTo('products.'.$shop->id);
    expect($this->production->canBeSetUpBy($shopkeeper->fresh()))->toBeTrue();
    $user->update(['can_use_mcp_production' => true]);

    $tool(\App\Mcp\Tools\ProductionRecordsTool::class, ['kind' => 'artefact', 'code' => 'AI-'.$suffix, 'create' => true, 'fields' => ['name' => 'Lip balm'], 'request_text' => 'create it'])
        ->assertHasErrors(['needs its SKO']);
    $tool(\App\Mcp\Tools\ProductionRecordsTool::class, ['kind' => 'artefact', 'code' => 'AI-'.$suffix, 'fields' => ['name' => 'Lip balm'], 'request_text' => 'create it'])
        ->assertHasErrors(['create=true']);

    $tool(\App\Mcp\Tools\ProductionRecordsTool::class, ['kind' => 'artefact', 'code' => 'AI-'.$suffix, 'create' => true, 'fields' => ['name' => 'Lip balm'], 'new_sko' => ['units' => 1], 'request_text' => 'create it with its sko'])
        ->assertOk()->assertSee(['"sko":"AI-'.$suffix.'"', '"trade_unit":"AI-'.$suffix.'"']);
    $artefact = Artefact::where('production_id', $this->production->id)->where('code', 'AI-'.$suffix)->firstOrFail();
    expect($artefact->orgStock->stock->code)->toBe('AI-'.$suffix)
        ->and($artefact->orgStock->is_made_in_house)->toBeTrue();

    $tool(\App\Mcp\Tools\ProductionRecordsTool::class, ['kind' => 'raw_material', 'code' => 'AIRM-'.$suffix, 'create' => true, 'fields' => ['type' => 'stock', 'description' => 'Beeswax', 'unit' => 'kilogram', 'unit_cost' => 8], 'request_text' => 'add beeswax'])->assertOk();
    foreach (['POUR', 'PACK'] as $code) {
        $tool(\App\Mcp\Tools\ProductionRecordsTool::class, ['kind' => 'manufacture_task', 'code' => $code.$suffix, 'create' => true, 'fields' => ['name' => $code], 'request_text' => 'add the tasks'])->assertOk();
    }

    $tool(\App\Mcp\Tools\ProductionRecordsTool::class, ['kind' => 'raw_material', 'code' => 'AIRM-'.$suffix, 'fields' => ['unit_cost' => 80], 'request_text' => 'beeswax is 80 now'])->assertHasErrors(['cost_jump']);
    $tool(\App\Mcp\Tools\ProductionRecordsTool::class, ['kind' => 'raw_material', 'code' => 'AIRM-'.$suffix, 'fields' => ['unit_cost' => 10], 'request_text' => 'beeswax is 10 now'])->assertOk();
    $costChange = \App\Models\SysAdmin\McpChange::latest('id')->first();
    expect($costChange->type)->toBe(\App\Enums\SysAdmin\McpChange\McpChangeTypeEnum::PRODUCTION_RECORD);
    \App\Actions\SysAdmin\McpChange\RevertMcpChange::run($costChange, $user);
    expect((float) RawMaterial::where('code', 'AIRM-'.$suffix)->value('unit_cost'))->toBe(8.0);

    $recipe = fn (array $steps) => $tool(\App\Mcp\Tools\ProductionRecipeTool::class, ['artefacts' => ['ai-'.$suffix], 'steps' => $steps, 'request_text' => 'set the steps']);
    $recipe([['task' => 'POUR'.$suffix]])->assertHasErrors(['units_per_artefact']);
    $recipe([['task' => 'POUR'.$suffix, 'units_per_artefact' => 1, 'target_per_hour' => 216, 'raw_materials' => [['code' => 'AIRM-'.$suffix, 'quantity' => 0.01]]], ['task' => 'PACK'.$suffix, 'units_per_artefact' => 0.1667, 'target_per_hour' => 11]])
        ->assertOk()->assertSee(['"task":"POUR'.$suffix.'"', '"target_per_hour":216', '"units_per_artefact":0.1667', '"materials_cost":0.08']);
    expect($artefact->manufactureTasks()->pluck('code')->all())->toBe(['POUR'.$suffix, 'PACK'.$suffix]);
    $recipe([['task' => 'PACK'.$suffix, 'units_per_artefact' => 1, 'target_per_hour' => null]])->assertHasErrors(['drop_raw_materials', 'AIRM-'.$suffix]);
    expect($artefact->manufactureTasks()->count())->toBe(2);

    \App\Actions\SysAdmin\McpChange\RevertMcpChange::run(\App\Models\SysAdmin\McpChange::latest('id')->first(), $user);
    expect($artefact->manufactureTasks()->pluck('code')->all())->toBe(['PROD']);

    $prodRate = $artefact->manufactureTasks()->first()->pivot->standard_rate;
    $prodStep = fn (string $targetFrom) => [['task' => 'PROD', 'units_per_artefact' => 1, 'target_per_hour' => 30, 'target_from' => $targetFrom]];
    $firstOpenDay = \App\Actions\Production\Artefact\SetArtefactStepTarget::firstOpenDay($artefact);
    $recipe($prodStep(now()->addDays(2)->toDateString()))->assertHasErrors(['today or an earlier day']);
    $recipe($prodStep(\Illuminate\Support\Carbon::parse($firstOpenDay)->subDay()->toDateString()))->assertHasErrors(['closed']);
    $recipe($prodStep($firstOpenDay))->assertOk();
    $datedBack = \App\Models\Production\ArtefactStepTarget::where('artefact_id', $artefact->id)->latest('id')->first();
    expect((float) $datedBack->standard_rate)->toBe(30.0)
        ->and($datedBack->user_id)->toBe($user->id)
        ->and($datedBack->effective_from->setTimezone(\App\Actions\Production\Artefact\SetArtefactStepTarget::timezone($artefact))->toDateString())->toBe($firstOpenDay);
    \App\Actions\SysAdmin\McpChange\RevertMcpChange::run(\App\Models\SysAdmin\McpChange::latest('id')->first(), $user);
    $reverted = \App\Models\Production\ArtefactStepTarget::where('artefact_id', $artefact->id)->latest('id')->first();
    expect($reverted->standard_rate)->toBe($prodRate)
        ->and($reverted->effective_from->equalTo($datedBack->effective_from))->toBeTrue();

    $recipe([
        ['task' => 'POUR'.$suffix, 'label' => 'Red', 'waits_for' => [], 'units_per_artefact' => 1, 'target_per_hour' => null],
        ['task' => 'POUR'.$suffix, 'label' => 'Blue', 'waits_for' => [], 'units_per_artefact' => 1, 'target_per_hour' => null],
        ['task' => 'PACK'.$suffix, 'waits_for' => [1, 2], 'units_per_artefact' => 1, 'target_per_hour' => null],
    ])->assertOk()->assertSee(['"label":"Red"', '"label":"Blue"', '"waits_for":[1,2]']);
    $strands = $artefact->manufactureTasks()->get()->pluck('pivot');
    expect($strands->pluck('label')->all())->toBe(['Red', 'Blue', null])
        ->and($strands[0]->waits_for)->toBe([])
        ->and($strands[2]->waits_for)->toBe([$strands[0]->id, $strands[1]->id]);
    \App\Actions\SysAdmin\McpChange\RevertMcpChange::run(\App\Models\SysAdmin\McpChange::latest('id')->first(), $user);
    expect($artefact->manufactureTasks()->pluck('code')->all())->toBe(['PROD']);

    $department = StoreArtefactDepartment::make()->action($this->production, ['code' => 'AID'.$suffix, 'name' => 'AI department']);
    $family     = StoreArtefactFamily::make()->action($department, ['code' => 'AIF'.$suffix, 'name' => 'AI family']);
    $artefact->update(['artefact_family_id' => $family->id]);
    $tool(\App\Mcp\Tools\ProductionRecipeTool::class, ['families' => ['AIF'.$suffix], 'steps' => [['task' => 'POUR'.$suffix, 'units_per_artefact' => 1, 'target_per_hour' => null,
        'raw_materials'          => [['code' => 'AIRM-'.$suffix, 'quantity' => 0.01]],
        'artefact_raw_materials' => [['artefact' => 'AI-'.$suffix, 'raw_materials' => [['code' => 'AIRM-'.$suffix, 'quantity' => 0.02]]]]]], 'request_text' => 'the whole family', 'accept' => ['change_1_artefacts', 'drop_raw_materials']])
        ->assertOk()->assertSee(['"code":"AI-'.$suffix.'"', '"quantity":0.02']);
    $tool(\App\Mcp\Tools\ProductionRecipeTool::class, ['families' => ['AIF'.$suffix], 'except' => ['AI-'.$suffix]])->assertHasErrors(['No artefacts left']);

    $artefact->orgStock->update(['packed_in' => 10]);
    $recipe([['task' => 'POUR'.$suffix, 'units_per_artefact' => 10, 'target_per_hour' => null]])->assertHasErrors(['pack_counted_twice', 'AI-'.$suffix.' (10 per SKO)']);

    $pourTask = ManufactureTask::where('production_id', $this->production->id)->where('code', 'POUR'.$suffix)->firstOrFail();
    \Pest\Laravel\post(route('grp.models.artefact.manufacture-task.attach', ['artefact' => $artefact->id]), ['manufacture_task_id' => $pourTask->id, 'position' => 1, 'units_per_artefact' => 10])
        ->assertSessionHasErrors(['units_per_artefact' => ArtefactManufactureTask::skoPackCountedAgainMessage(collect([$artefact->refresh()]))]);
    \Pest\Laravel\post(route('grp.models.production.artefacts.set_recipe', ['production' => $this->production->id]), ['artefacts' => [$artefact->id], 'steps' => [['manufacture_task_id' => $pourTask->id, 'position' => 1, 'units_per_artefact' => 10]]])
        ->assertSessionHasErrors('steps');
    expect((float) $artefact->manufactureTasks()->find($pourTask->id)->pivot->units_per_artefact)->toBe(1.0);

    $artefact->manufactureTasks()->updateExistingPivot($pourTask->id, ['units_per_artefact' => 10]);
    \Pest\Laravel\post(route('grp.models.artefact.manufacture-task.attach', ['artefact' => $artefact->id]), ['manufacture_task_id' => $pourTask->id, 'position' => 1, 'units_per_artefact' => 10, 'standard_rate' => 50])
        ->assertSessionHasNoErrors();

    $recipe([['task' => 'NOPE'.$suffix, 'units_per_artefact' => 1, 'target_per_hour' => null]])->assertHasErrors(['task NOPE'.$suffix]);
    expect(fn () => \App\Actions\SysAdmin\McpChange\RevertMcpChange::run(\App\Models\SysAdmin\McpChange::where('label', 'like', 'Create artefact AI-'.$suffix.'%')->firstOrFail(), $user))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('an artefact made in-house is an intermediate raw material of another recipe, costed from its own recipe', function () {
    $user = $this->guest->getUser();
    $user->update(['can_use_mcp' => true, 'can_use_mcp_production' => true]);
    $suffix = strtoupper(\Illuminate\Support\Str::random(5));
    $tool   = fn (string $class, array $arguments) => \App\Mcp\Servers\AikuServer::actingAs($user)->tool($class, ['production' => $this->production->slug, ...$arguments]);

    $tool(\App\Mcp\Tools\ProductionRecordsTool::class, ['kind' => 'artefact', 'code' => 'DIL-'.$suffix, 'create' => true, 'fields' => ['name' => 'Dilute'], 'new_sko' => ['units' => 1], 'request_text' => 'create the dilute'])->assertOk();
    $dilute = Artefact::where('production_id', $this->production->id)->where('code', 'DIL-'.$suffix)->firstOrFail();

    $oil = StoreRawMaterial::make()->action($this->production, ['type' => RawMaterialTypeEnum::STOCK->value, 'code' => 'OIL-'.$suffix, 'description' => 'Oil', 'unit' => RawMaterialUnitEnum::KILOGRAM->value, 'unit_cost' => 8]);
    $tool(\App\Mcp\Tools\ProductionRecipeTool::class, ['artefacts' => ['DIL-'.$suffix], 'steps' => [['task' => 'PROD', 'units_per_artefact' => 1, 'target_per_hour' => null, 'raw_materials' => [['code' => 'OIL-'.$suffix, 'quantity' => 0.5]]]], 'request_text' => 'half a kilo of oil'])
        ->assertOk();

    $tool(\App\Mcp\Tools\ProductionRecordsTool::class, ['kind' => 'artefact', 'code' => 'TWIN-'.$suffix, 'create' => true, 'fields' => ['name' => 'Twin', 'sko' => 'DIL-'.$suffix], 'request_text' => 'second artefact on the same sko'])
        ->assertHasErrors(['already the SKO of artefact DIL-'.$suffix]);

    $tool(\App\Mcp\Tools\ProductionRecordsTool::class, ['kind' => 'raw_material', 'code' => 'RM-DIL-'.$suffix, 'create' => true, 'fields' => ['type' => 'intermediate', 'description' => 'Dilute', 'unit' => 'kilogram', 'sko' => 'DIL-'.$suffix], 'request_text' => 'add the dilute as a raw material'])
        ->assertOk();
    $intermediate = RawMaterial::where('code', 'RM-DIL-'.$suffix)->firstOrFail();

    expect($intermediate->artefact_id)->toBe($dilute->id)
        ->and($intermediate->org_stock_id)->toBe($dilute->org_stock_id)
        ->and((float) $intermediate->unit_cost)->toBe(4.0);

    UpdateRawMaterial::make()->action($oil, ['unit_cost' => 10]);
    expect((float) $intermediate->refresh()->unit_cost)->toBe(5.0);

    $step = ArtefactManufactureTask::where('artefact_id', $dilute->id)->firstOrFail();
    AttachRawMaterialToRecipeStep::make()->action($step, ['raw_material_id' => $oil->id, 'quantity_per_unit' => 0.2]);
    expect((float) $intermediate->refresh()->unit_cost)->toBe(2.0);

    DetachRawMaterialFromRecipeStep::make()->action($step, $oil);
    expect((float) $intermediate->refresh()->unit_cost)->toBe(2.0);
});

test('a custom product is made for one customer from an artefact waiting for its trade unit', function () {
    list($organisation, , $shop) = createShop();
    $customer = \App\Actions\CRM\Customer\StoreCustomer::make()->action($shop, \App\Models\CRM\Customer::factory()->definition());
    $other    = \App\Actions\CRM\Customer\StoreCustomer::make()->action($shop, \App\Models\CRM\Customer::factory()->definition());

    $production = $organisation->productions()->first()
        ?? StoreProduction::make()->action($organisation, ['code' => 'CUSPRD', 'name' => 'Custom production']);

    $suffix   = strtoupper(\Illuminate\Support\Str::random(6));
    $artefact = StoreArtefact::make()->action($production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'CUS-'.$suffix, 'name' => 'Engraved plaque '.$suffix]);

    expect(\App\Actions\CRM\Customer\StoreCustomerProductFromArtefact::artefactOptions($customer)->pluck('id'))->toContain($artefact->id);

    get(route('grp.org.productions.show.crafts.artefacts.show', [$organisation->slug, $production->slug, $artefact->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('customer_product.artefact_id', $artefact->id)
            ->where('customer_product.shops', fn ($shops) => collect($shops)->contains('id', $shop->id)));

    get(route('grp.json.shop.customers', ['shop' => $shop->id, 'filter[global]' => $customer->reference]))
        ->assertOk()
        ->assertJsonFragment(['slug' => $customer->slug]);

    get(route('grp.org.shops.show.crm.customers.show', [$organisation->slug, $shop->slug, $customer->slug, 'custom_product_artefact' => $artefact->id]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('can_make_custom_product', true)
            ->where('custom_product_artefact_id', $artefact->id));

    $product = \App\Actions\CRM\Customer\StoreCustomerProductFromArtefact::make()->action($customer, [
        'artefact_id' => $artefact->id,
        'code'        => 'CUS-'.$suffix,
        'name'        => 'Engraved plaque '.$suffix,
        'price'       => 1200,
        'units'       => 2,
    ]);
    $artefact->refresh();

    expect($artefact->trade_unit_id)->not->toBeNull()
        ->and($artefact->orgStock->organisation_id)->toBe($organisation->id)
        ->and($artefact->orgStock->stock->code)->toBe('CUS-'.$suffix)
        ->and($product->shop_id)->toBe($shop->id)
        ->and($product->exclusive_for_customer_id)->toBe($customer->id)
        ->and($product->tradeUnits()->pluck('trade_units.id')->all())->toBe([$artefact->trade_unit_id])
        ->and((float) $product->tradeUnits()->first()->pivot->quantity)->toBe(2.0)
        ->and($product->orgStocks()->pluck('org_stocks.id')->all())->toContain($artefact->org_stock_id)
        ->and(\App\Models\Catalogue\Product::whereKey($product->id)->sellableToCustomer($customer->id)->exists())->toBeTrue()
        ->and(\App\Models\Catalogue\Product::whereKey($product->id)->sellableToCustomer($other->id)->exists())->toBeFalse()
        ->and(\App\Actions\CRM\Customer\StoreCustomerProductFromArtefact::artefactOptions($customer)->pluck('id'))->not->toContain($artefact->id);

    expect(fn () => \App\Actions\CRM\Customer\StoreCustomerProductFromArtefact::make()->action($customer, [
        'artefact_id' => $artefact->id,
        'code'        => 'CUS-'.$suffix.'B',
        'name'        => 'Again',
        'price'       => 1,
        'units'       => 1,
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);

    \App\Actions\Goods\Stock\StoreStock::make()->action($organisation->group, ['code' => 'OLD-'.$suffix, 'name' => 'Old part', 'units' => 1, 'trade_unit' => ['description' => 'Old part']]);
    $oldPart = StoreArtefact::make()->action($production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'old-'.$suffix, 'name' => 'Old part never linked']);

    expect(\App\Actions\CRM\Customer\StoreCustomerProductFromArtefact::artefactOptions($customer)->pluck('id'))->not->toContain($oldPart->id)
        ->and(fn () => \App\Actions\CRM\Customer\StoreCustomerProductFromArtefact::make()->action($customer, [
            'artefact_id' => $oldPart->id,
            'code'        => 'CUS-OLD-'.$suffix,
            'name'        => 'Old part',
            'price'       => 1,
            'units'       => 1,
        ]))->toThrow(\Illuminate\Validation\ValidationException::class)
        ->and($oldPart->refresh()->trade_unit_id)->toBeNull()
        ->and(\App\Models\Catalogue\Product::where('shop_id', $shop->id)->where('code', 'CUS-OLD-'.$suffix)->exists())->toBeFalse();

    get(route('grp.org.productions.show.crafts.artefacts.show', [$organisation->slug, $production->slug, $artefact->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('customer_product', null)
            ->where('exclusive_products.0.code', 'CUS-'.$suffix)
            ->where('exclusive_products.0.customer', $customer->name));
});

test('the ai assistant puts SKOs on locations and sets up the factory location and work areas, logged and revertible', function () {
    $user = $this->guest->getUser();
    $user->update(['can_use_mcp' => true, 'can_use_mcp_locations' => false, 'can_use_mcp_production' => true]);
    $organisation = $this->production->organisation;
    $suffix       = strtoupper(\Illuminate\Support\Str::random(5));
    $warehouse    = \App\Models\Inventory\Warehouse::where('organisation_id', $organisation->id)->orderBy('id')->first() ?? createWarehouse();
    expect($warehouse->organisation_id)->toBe($organisation->id);
    $location     = \App\Actions\Inventory\Location\StoreLocation::make()->action($warehouse, ['code' => 'MCPL'.$suffix]);
    $orgStock     = labelTestOrgStock($organisation, $this->group);
    $locations    = fn (array $arguments) => \App\Mcp\Servers\AikuServer::actingAs($user)->tool(\App\Mcp\Tools\StockLocationsTool::class, ['organisation' => $organisation->slug, ...$arguments]);
    $pairs        = [['sko' => strtolower($orgStock->code), 'location' => 'mcpl'.strtolower($suffix)], ['sko' => 'NOPE'.$suffix, 'location' => 'MCPL'.$suffix]];

    $locations(['pairs' => $pairs])->assertHasErrors(['not enabled for this user']);
    $user->update(['can_use_mcp_locations' => true]);

    $locations(['pairs' => $pairs])->assertOk()->assertSee([$orgStock->code, 'NOPE'.$suffix.': not a SKO']);
    expect(\App\Models\Inventory\LocationOrgStock::where('org_stock_id', $orgStock->id)->where('location_id', $location->id)->exists())->toBeFalse();

    $locations(['pairs' => $pairs, 'confirm' => true, 'request_text' => 'yes put it there'])->assertOk()->assertSee($orgStock->code.' @ MCPL'.$suffix);
    expect(\App\Models\Inventory\LocationOrgStock::where('org_stock_id', $orgStock->id)->where('location_id', $location->id)->value('quantity'))->toEqual(0);
    $locations(['pairs' => $pairs])->assertOk()->assertSee('already_there');
    $locations(['skos' => [$orgStock->code]])->assertOk()->assertSee('MCPL'.$suffix);

    $change   = \App\Models\SysAdmin\McpChange::where('type', \App\Enums\SysAdmin\McpChange\McpChangeTypeEnum::STOCK_LOCATIONS)->latest('id')->firstOrFail();
    $outsider = \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($this->production->group, array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => []]))->getUser();
    $outsider->update(['can_use_mcp' => true, 'can_use_mcp_locations' => true, 'can_use_mcp_production' => true]);
    expect($change->request_text)->toBe('yes put it there')
        ->and($change->canBeRevertedBy($user))->toBeTrue()
        ->and($change->canBeRevertedBy($outsider->fresh()))->toBeFalse();
    \App\Actions\SysAdmin\McpChange\RevertMcpChange::run($change, $user);
    expect(\App\Models\Inventory\LocationOrgStock::where('org_stock_id', $orgStock->id)->where('location_id', $location->id)->exists())->toBeFalse();

    $department = StoreArtefactDepartment::make()->action($this->production, ['code' => 'MCPD'.$suffix, 'name' => 'Oils '.$suffix]);
    $worker     = dayPlanOperator($organisation);
    $colleague  = dayPlanOperator($organisation);
    $setup      = fn (array $arguments) => \App\Mcp\Servers\AikuServer::actingAs($user)->tool(\App\Mcp\Tools\ProductionSetupTool::class, ['production' => $this->production->slug, ...$arguments]);
    $factoryLocationId = $this->production->factory_location_id;

    $setup([])->assertOk()->assertSee(['MCPD'.$suffix, $worker->contact_name]);
    $setup(['work_areas' => [['name' => 'Oils '.$suffix, 'departments' => ['NOPE']]], 'request_text' => 'make it'])->assertHasErrors(['no department NOPE']);
    expect(\App\Models\Production\WorkArea::where('production_id', $this->production->id)->where('name', 'Oils '.$suffix)->exists())->toBeFalse();

    $setup([
        'factory_location' => 'MCPL'.$suffix,
        'work_areas'       => [['name' => 'Oils '.$suffix, 'departments' => ['mcpd'.strtolower($suffix)], 'artisans' => [$worker->worker_number, $colleague->alias]]],
        'request_text'     => 'set it up',
    ])->assertOk()->assertSee('Oils '.$suffix);

    $workArea = \App\Models\Production\WorkArea::where('production_id', $this->production->id)->where('name', 'Oils '.$suffix)->firstOrFail();
    $positionOfWorker = fn () => $workArea->artisans()->where('employees.id', $worker->id)->value('artisan_assignments.position');
    $firstPosition    = $positionOfWorker();
    expect($this->production->fresh()->factory_location_id)->toBe($location->id)
        ->and($department->fresh()->work_area_id)->toBe($workArea->id)
        ->and($workArea->artisans()->pluck('employees.id')->sort()->values()->all())->toBe(collect([$worker->id, $colleague->id])->sort()->values()->all());

    $setup(['work_areas' => [['name' => 'Oils '.$suffix, 'artisans' => [$worker->worker_number]]], 'request_text' => 'only the first one'])->assertOk();
    expect($workArea->artisans()->pluck('employees.id')->all())->toBe([$worker->id])
        ->and($positionOfWorker())->toBe($firstPosition);

    $change = \App\Models\SysAdmin\McpChange::where('type', \App\Enums\SysAdmin\McpChange\McpChangeTypeEnum::PRODUCTION_SETUP)->latest('id')->firstOrFail();
    expect($change->canBeRevertedBy($outsider->fresh()))->toBeFalse();
    \App\Actions\SysAdmin\McpChange\RevertMcpChange::run($change, $user);
    expect($workArea->artisans()->pluck('employees.id')->sort()->values()->all())->toBe(collect([$worker->id, $colleague->id])->sort()->values()->all());
    $change = \App\Models\SysAdmin\McpChange::where('type', \App\Enums\SysAdmin\McpChange\McpChangeTypeEnum::PRODUCTION_SETUP)->whereNull('reverted_at')->latest('id')->firstOrFail();
    \App\Actions\SysAdmin\McpChange\RevertMcpChange::run($change, $user);
    expect($this->production->fresh()->factory_location_id)->toBe($factoryLocationId)
        ->and(\App\Models\Production\WorkArea::find($workArea->id))->toBeNull()
        ->and($department->fresh()->work_area_id)->toBeNull();
});

test('HELP-4562 one mix and its Making cover every open line of any product that uses the strand, extra pieces go to the next jobs and raw materials leave stock per mix', function () {
    $user   = $this->guest->getUser();
    $suffix = rand(10000, 99999);
    $newOrgStock = fn () => \App\Actions\Inventory\OrgStock\StoreOrgStock::make()->action($this->organisation, \App\Actions\Goods\Stock\StoreStock::make()->action(
        $this->group,
        array_merge(\App\Models\Goods\Stock::factory()->definition(), ['state' => \App\Enums\Goods\Stock\StockStateEnum::ACTIVE])
    ));

    $powderStock = $newOrgStock();
    $powder      = StoreRawMaterial::make()->action($this->production, [
        'type'        => RawMaterialTypeEnum::CONSUMABLE->value,
        'state'       => RawMaterialStateEnum::ORPHAN->value,
        'code'        => 'PWD'.$suffix,
        'description' => 'Fizzer powder',
        'unit'        => RawMaterialUnitEnum::KILOGRAM->value,
        'unit_cost'   => 1,
    ]);
    UpdateRawMaterial::make()->action($powder, ['org_stock_id' => $powderStock->id]);

    $warehouse = \App\Actions\Inventory\Warehouse\StoreWarehouse::make()->action($this->organisation, ['code' => 'WMX'.$suffix, 'name' => 'Mix warehouse']);
    $area      = \App\Actions\Inventory\WarehouseArea\StoreWarehouseArea::make()->action($warehouse, ['code' => 'AMX'.$suffix, 'name' => 'Mix area']);
    $location  = fn (string $code) => \App\Actions\Inventory\Location\StoreLocation::make()->action($area, ['code' => $code.$suffix, 'name' => $code] + \App\Models\Inventory\Location::factory()->definition());
    $shelf     = $location('LMX');
    $goodsIn   = $location('LGI');
    $powderAt  = \App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock::make()->action($powderStock, $shelf, ['type' => \App\Enums\Inventory\LocationStock\LocationStockTypeEnum::PICKING]);
    \App\Actions\Inventory\OrgStockMovement\StoreOrgStockMovement::make()->action($powderStock, $shelf, ['quantity' => 1000, 'type' => \App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum::PRODUCTION]);
    $powderLeft = fn () => (float) $powderAt->refresh()->quantity;

    $mixing = StoreManufactureTask::make()->action($this->production, ['code' => 'MXP'.$suffix, 'name' => 'Mixing '.$suffix, 'is_piece_rate' => false, 'is_recorded_in_kg' => true]);
    $making = StoreManufactureTask::make()->action($this->production, ['code' => 'MKP'.$suffix, 'name' => 'Making '.$suffix, 'is_piece_rate' => true]);
    $boxing = StoreManufactureTask::make()->action($this->production, ['code' => 'BXP'.$suffix, 'name' => 'Boxing '.$suffix, 'is_piece_rate' => true]);

    $box = function (string $code, float $kgPerBox, float $powderPerKg, ?\App\Models\Inventory\OrgStock $orgStock = null) use ($mixing, $making, $boxing, $powder, $suffix) {
        $artefact = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => $code.$suffix, 'name' => $code, 'org_stock_id' => $orgStock?->id]);
        $artefact->manufactureTasks()->sync([
            $mixing->id => ['position' => 1, 'units_per_artefact' => $kgPerBox, 'mix_batch_kg' => 50, 'label' => 'Light Blue'],
            $making->id => ['position' => 2, 'units_per_artefact' => 1, 'label' => 'Light Blue'],
            $boxing->id => ['position' => 3, 'units_per_artefact' => 1],
        ]);
        AttachRawMaterialToRecipeStep::make()->action($artefact->recipeSteps()->where('manufacture_task_id', $mixing->id)->first(), ['raw_material_id' => $powder->id, 'quantity_per_unit' => $kgPerBox * $powderPerKg]);

        return $artefact;
    };
    $plainMix = fn (Artefact $artefact) => $artefact->recipeSteps()->where('manufacture_task_id', $mixing->id)->first();
    $plainOne = $box('ABP01', 0.05, 1);
    $plainTwo = $box('FBB08', 0.05, 1);
    $plainOne->recipeSteps()->where('manufacture_task_id', $mixing->id)->update(['label' => null]);
    $plainTwo->recipeSteps()->where('manufacture_task_id', $mixing->id)->update(['label' => null]);
    expect($plainMix($plainOne)->mixPoolKey())->not->toBe($plainMix($plainTwo)->mixPoolKey());

    $boxOne   = $box('CBF01', 0.05, 1, $newOrgStock());
    $boxTwo   = $box('CBF02', 0.05, 1);
    $otherMix = $box('CBF09', 0.1, 2);

    $line = function (Artefact $artefact, int $quantity) {
        $jobOrder = StoreJobOrder::make()->action($this->production, []);
        $item     = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $artefact->id, 'quantity' => $quantity]);
        ConfirmJobOrder::make()->action($jobOrder);

        return $item->refresh();
    };
    $mixOf    = fn (JobOrderItem $item) => $item->tasks()->where('manufacture_task_id', $mixing->id)->first();
    $makingOf = fn (JobOrderItem $item) => $item->tasks()->where('manufacture_task_id', $making->id)->first();
    $boxIt    = fn (JobOrderItem $item, int $boxes) => CloseManufactureTaskSession::make()->action(StartManufactureTaskSession::make()->action($user, $item->tasks()->where('manufacture_task_id', $boxing->id)->first()), ['quantity_made' => $boxes]);
    $done     = \App\Enums\Production\JobOrderItemTask\JobOrderItemTaskStateEnum::DONE;
    $floor    = fn () => collect(get(route('grp.org.productions.show.floor', [$this->organisation->slug, $this->production->slug]))->viewData('page')['props']['tasks']);

    $lineOne   = $line($boxOne, 300);
    $lineTwo   = $line($boxTwo, 200);
    $lineOther = $line($otherMix, 100);

    $floorTasks = $floor();
    $floorMix   = $floorTasks->firstWhere('id', $mixOf($lineOne)->id);
    expect($floorTasks->pluck('id'))->not->toContain($mixOf($lineTwo)->id)
        ->and($floorTasks->pluck('id'))->toContain($mixOf($lineOther)->id)
        ->and(array_column($floorMix['combined'], 'id'))->toBe([$mixOf($lineOne)->id, $mixOf($lineTwo)->id])
        ->and($floorMix)->toMatchArray(['artefact_name' => 'Shared mix', 'separate_route' => null, 'quantity_required' => 25])
        ->and($floorMix['mix_pool'])->toMatchArray(['unit' => 'kg', 'needed' => 25, 'in_stock' => null, 'mixes' => 1, 'left_after_kg' => 25])
        ->and(array_column($floorMix['mix_pool']['lines'], 'artefact_code'))->toBe(['CBF01'.$suffix, 'CBF02'.$suffix]);

    collect([$lineOne, $lineTwo, $lineOther])->each(fn (JobOrderItem $item) => $item->jobOrder->update(['planned_for' => now()]));
    $powderToBring = fn () => (float) (\App\Actions\Production\MaterialRequest\GetDayMaterialsChecklist::run($this->production, now()->toDateString())->firstWhere('id', $powder->id)?->needed_units ?? 0);
    expect($powderToBring())->toBe(45.0);

    $mixSession = CloseManufactureTaskSession::make()->action(StartManufactureTaskSession::make()->action($user, $mixOf($lineOne)), ['quantity_made' => 50]);
    $pool       = \App\Actions\Production\JobOrderItemTask\ShareMixPool::make();
    expect($powderToBring())->toBe(20.0)
        ->and((float) $mixOf($lineOne)->quantity_made)->toBe(15.0)
        ->and($mixOf($lineOne)->state)->toBe($done)
        ->and((float) $mixOf($lineTwo)->quantity_made)->toBe(10.0)
        ->and((float) $mixOf($lineOther)->quantity_made)->toBe(0.0)
        ->and($powderLeft())->toBe(950.0);

    $lineMixFresh = $line($boxOne, 100);
    expect((float) $mixOf($lineMixFresh)->quantity_made)->toBe(0.0);
    \App\Actions\Production\JobOrder\CancelJobOrder::make()->action($lineMixFresh->jobOrder, ['mode' => 'cancel', 'kind' => 'other', 'reason' => 'test']);

    $floorMaking = $floor()->firstWhere('id', $makingOf($lineOne)->id);
    expect(array_column($floorMaking['combined'], 'id'))->toBe([$makingOf($lineOne)->id, $makingOf($lineTwo)->id])
        ->and($floorMaking['mix_pool'])->toMatchArray(['unit' => null, 'needed' => 500, 'in_stock' => 0, 'mixes' => null]);

    $makingSession = CloseManufactureTaskSession::make()->action(StartManufactureTaskSession::make()->action($user, $makingOf($lineOne)), ['quantity_made' => 1000]);
    $inStock       = fn () => (float) $pool->leftovers($this->production->id, $makingSession->mix_pool_key)->sum('leftover_kg');
    expect((float) $makingOf($lineOne)->quantity_made)->toBe(300.0)
        ->and((float) $makingOf($lineTwo)->quantity_made)->toBe(200.0)
        ->and((int) $lineOne->refresh()->quantity)->toBe(300)
        ->and($inStock())->toBe(500.0);

    $lineThree = $line($boxOne, 200);
    expect((float) $makingOf($lineThree)->quantity_made)->toBe(200.0)
        ->and($makingOf($lineThree)->state)->toBe($done)
        ->and((float) $mixOf($lineThree)->quantity_made)->toBe(10.0)
        ->and($mixOf($lineThree)->state)->toBe($done)
        ->and($inStock())->toBe(300.0);

    \App\Actions\Production\JobOrderItem\UpdateJobOrderItem::make()->action($lineThree, ['quantity' => 100]);
    expect((float) $makingOf($lineThree)->quantity_made)->toBe(100.0)
        ->and($inStock())->toBe(400.0);

    \App\Actions\Production\JobOrder\CancelJobOrder::make()->action($lineTwo->jobOrder, ['mode' => 'cancel', 'kind' => 'other', 'reason' => 'test']);
    expect((float) $makingOf($lineTwo)->quantity_made)->toBe(0.0)
        ->and($inStock())->toBe(600.0);

    $packedLine = $line($boxTwo, 100);
    $boxIt($packedLine, 100);
    \App\Actions\Production\JobOrder\CancelJobOrder::make()->action($packedLine->jobOrder, ['mode' => 'cancel', 'kind' => 'other', 'reason' => 'test']);
    expect((float) $makingOf($packedLine)->quantity_made)->toBe(100.0)
        ->and($inStock())->toBe(500.0);

    $backlogLine = StoreJobOrderItem::make()->action($lineThree->jobOrder, ['artefact_id' => $boxTwo->id, 'quantity' => 100]);
    expect((float) $makingOf($backlogLine)->quantity_made)->toBe(100.0)
        ->and($inStock())->toBe(400.0);
    \App\Actions\Production\JobOrderItem\DeleteJobOrderItem::make()->handle($backlogLine->refresh(), ['kind' => 'other', 'reason' => 'test']);
    expect(JobOrderItem::find($backlogLine->id))->toBeNull()
        ->and($inStock())->toBe(500.0);

    $partlyBoxed = $line($boxTwo, 300);
    expect($inStock())->toBe(200.0);
    $boxIt($partlyBoxed, 100);
    \App\Actions\Production\JobOrder\CancelJobOrder::make()->action($partlyBoxed->jobOrder, ['mode' => 'cancel', 'kind' => 'other', 'reason' => 'test']);
    expect((float) $makingOf($partlyBoxed)->quantity_made)->toBe(100.0)
        ->and($inStock())->toBe(400.0);

    $boxIt($lineOne, 300);
    \App\Actions\Production\JobOrder\ReceiveJobOrderIntoStock::make()->action($lineOne->jobOrder->refresh(), ['location_id' => $goodsIn->id]);
    expect($powderLeft())->toBe(950.0);

    $otherMixSession = CloseManufactureTaskSession::make()->action(StartManufactureTaskSession::make()->action($user, $mixOf($lineOther)), ['quantity_made' => 50]);
    expect($otherMixSession->mix_pool_key)->not->toBe($mixSession->mix_pool_key)
        ->and((float) $mixOf($lineOther)->quantity_made)->toBe(10.0)
        ->and($powderLeft())->toBe(850.0);

    expect(fn () => \App\Actions\Production\ManufactureTaskSession\VoidManufactureTaskSession::make()->action($mixSession->refresh()))->toThrow(ValidationException::class, 'already received or closed');
    \App\Actions\Production\ManufactureTaskSession\VoidManufactureTaskSession::make()->action($otherMixSession->refresh());
    expect($powderLeft())->toBe(950.0)
        ->and((float) $mixOf($lineOther)->quantity_made)->toBe(0.0);

    $lineFour = $line($boxTwo, 1100);
    expect((float) $makingOf($lineFour)->quantity_made)->toBe(400.0)
        ->and((float) $mixOf($lineFour)->quantity_made)->toBe(20.0)
        ->and($inStock())->toBe(0.0);
    $secondMix = CloseManufactureTaskSession::make()->action(StartManufactureTaskSession::make()->action($user, $mixOf($lineFour)), ['quantity_made' => 50]);
    expect((float) $mixOf($lineFour)->quantity_made)->toBe(55.0)
        ->and((float) $makingOf($lineFour)->quantity_made)->toBe(400.0)
        ->and($powderLeft())->toBe(900.0);

    \App\Actions\Production\ManufactureTaskSession\CorrectManufactureTaskSessionQuantity::make()->action($secondMix, ['quantity_made' => 100, 'reason' => 'second batch'], 'Supervisor');
    expect((float) $mixOf($lineFour)->quantity_made)->toBe(55.0)
        ->and($powderLeft())->toBe(850.0);

    $carryLine = $line($boxOne, 200);
    expect((float) $mixOf($carryLine)->quantity_made)->toBe(0.0);
    CloseManufactureTaskSession::make()->action(StartManufactureTaskSession::make()->action($user, $mixOf($carryLine)), ['quantity_made' => 50]);
    $powderBefore = $powderLeft();
    CloseManufactureTaskSession::make()->action(StartManufactureTaskSession::make()->action($user, $makingOf($carryLine)), ['quantity_made' => 100, 'outcome' => 'carry_over']);
    $carried = \App\Models\Production\JobOrder::where('data->carried_from', $carryLine->job_order_id)->firstOrFail()->jobOrderItems()->first();
    expect($mixOf($carried)->state)->toBe($done);
    CloseManufactureTaskSession::make()->action(StartManufactureTaskSession::make()->action($user, $makingOf($carried)), ['quantity_made' => 100]);
    $boxIt($carryLine->refresh(), 100);
    $boxIt($carried->refresh(), 100);
    \App\Actions\Production\JobOrder\ReceiveJobOrderIntoStock::make()->action($carryLine->jobOrder->refresh(), ['location_id' => $goodsIn->id]);
    \App\Actions\Production\JobOrder\ReceiveJobOrderIntoStock::make()->action($carried->jobOrder->refresh(), ['location_id' => $goodsIn->id]);
    expect($powderLeft())->toBe($powderBefore);
});

test('INI-221 putting away a job order values every movement at FIFO in a bounded number of queries', function () {
    $user = $this->guest->getUser();
    ManufactureTaskSession::where('user_id', $user->id)->where('state', ManufactureTaskSessionStateEnum::OPEN)->delete();
    $wacStartDate = $this->organisation->wac_calculations_start_date;
    $this->organisation->update(['wac_calculations_start_date' => now()->subYear()->toDateString()]);
    $this->beforeApplicationDestroyed(fn () => $this->organisation->update(['wac_calculations_start_date' => $wacStartDate]));

    $newOrgStock = function (array $attributes = []) {
        $stock    = \App\Actions\Goods\Stock\StoreStock::make()->action($this->group, array_merge(\App\Models\Goods\Stock::factory()->definition(), ['state' => \App\Enums\Goods\Stock\StockStateEnum::ACTIVE]));
        $orgStock = \App\Actions\Inventory\OrgStock\StoreOrgStock::make()->action($this->organisation, $stock);
        $orgStock->update(['packed_in' => 1] + $attributes);

        return $orgStock;
    };

    $warehouse = \App\Actions\Inventory\Warehouse\StoreWarehouse::make()->action($this->organisation, ['code' => 'WH-I221', 'name' => 'INI-221 warehouse']);
    $area      = \App\Actions\Inventory\WarehouseArea\StoreWarehouseArea::make()->action($warehouse, ['code' => 'A-I221', 'name' => 'INI-221 area']);
    $shelf     = \App\Actions\Inventory\Location\StoreLocation::make()->action($area, ['code' => 'SHELF-I221', 'name' => 'INI-221 shelf'] + \App\Models\Inventory\Location::factory()->definition());
    $store     = \App\Actions\Inventory\Location\StoreLocation::make()->action($area, ['code' => 'RM-I221', 'name' => 'INI-221 raw materials'] + \App\Models\Inventory\Location::factory()->definition());

    $rawOrgStocks = collect(range(1, 4))->map(function (int $n) use ($newOrgStock, $store) {
        $orgStock = $newOrgStock();
        \App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock::make()->action($orgStock, $store, []);
        foreach ([[10, 2 * $n], [10, 3 * $n]] as [$quantity, $costPerSku]) {
            \App\Actions\Inventory\OrgStockMovement\StoreOrgStockMovement::make()->action($orgStock, $store, [
                'quantity'   => $quantity,
                'org_amount' => $quantity * $costPerSku,
                'type'       => \App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum::PURCHASE,
            ]);
        }

        $rawMaterial = StoreRawMaterial::make()->action($this->production, [
            'type'        => RawMaterialTypeEnum::CONSUMABLE->value,
            'state'       => RawMaterialStateEnum::ORPHAN->value,
            'code'        => 'I221RM'.$n,
            'description' => 'INI-221 raw material '.$n,
            'unit'        => RawMaterialUnitEnum::KILOGRAM->value,
            'unit_cost'   => 1,
        ]);
        UpdateRawMaterial::make()->action($rawMaterial, ['org_stock_id' => $orgStock->id]);

        return [$orgStock, $rawMaterial];
    });

    $jobOrder = StoreJobOrder::make()->action($this->production, []);
    $finishedOrgStocks = [];
    foreach (['I221-SOAP', 'I221-BOMB'] as $code) {
        $finishedOrgStocks[$code] = $newOrgStock(['current_supplier_sku_cost' => 7]);
        $artefact = StoreArtefact::make()->action($this->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => $code, 'name' => $code, 'org_stock_id' => $finishedOrgStocks[$code]->id]);
        $artefact->manufactureTasks()->sync([$this->manufactureTask->id => ['position' => 1, 'units_per_artefact' => 1]]);
        $recipeStep = ArtefactManufactureTask::where('artefact_id', $artefact->id)->first();
        foreach ($rawOrgStocks as [, $rawMaterial]) {
            AttachRawMaterialToRecipeStep::make()->action($recipeStep, ['raw_material_id' => $rawMaterial->id, 'quantity_per_unit' => 0.5]);
        }
        StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $artefact->id, 'quantity' => 4]);
    }
    ConfirmJobOrder::make()->action($jobOrder);
    foreach ($jobOrder->jobOrderItems as $item) {
        CloseManufactureTaskSession::make()->action(StartManufactureTaskSession::make()->action($user, $item->tasks()->first()), ['quantity_made' => 4, 'outcome' => 'complete']);
    }

    $lastMovementId = \App\Models\Inventory\OrgStockMovement::max('id');
    $queries        = [];
    \Illuminate\Support\Facades\DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });
    \Illuminate\Support\Facades\Queue::fake();

    \App\Actions\Dispatching\ProductionOutput\PutAwayFinishedJobOrder::make()->action($warehouse, [$jobOrder->id], 'SHELF-I221', true);
    $queryCount = count($queries);

    $orgStockCodes = collect($rawOrgStocks)->mapWithKeys(fn ($pair, $index) => [$pair[0]->id => 'RM'.($index + 1)])
        ->union(collect($finishedOrgStocks)->mapWithKeys(fn ($orgStock, $code) => [$orgStock->id => $code]));
    $movements = \App\Models\Inventory\OrgStockMovement::where('id', '>', $lastMovementId)->orderBy('id')->get()
        ->map(fn ($movement) => implode(' ', [
            $orgStockCodes[$movement->org_stock_id],
            $movement->type->value,
            ...array_map(fn ($field) => $field.'='.($movement->{$field} === null ? 'null' : round((float) $movement->{$field}, 3)), ['quantity', 'org_amount', 'grp_amount', 'running_quantity', 'running_quantity_org_stock', 'running_lpp_value', 'running_wac_value', 'running_fifo_value']),
        ]))->all();

    expect($jobOrder->refresh()->state)->toBe(JobOrderStateEnum::RECEIVED)
        ->and($movements)->toBe([
            'I221-SOAP associate quantity=0 org_amount=0 grp_amount=0 running_quantity=null running_quantity_org_stock=null running_lpp_value=null running_wac_value=null running_fifo_value=null',
            'I221-SOAP production quantity=4 org_amount=28 grp_amount=28 running_quantity=4 running_quantity_org_stock=4 running_lpp_value=28 running_wac_value=28 running_fifo_value=28',
            'RM1 production quantity=-2 org_amount=-5 grp_amount=-5 running_quantity=18 running_quantity_org_stock=18 running_lpp_value=54 running_wac_value=45 running_fifo_value=46',
            'RM2 production quantity=-2 org_amount=-10 grp_amount=-10 running_quantity=18 running_quantity_org_stock=18 running_lpp_value=108 running_wac_value=90 running_fifo_value=92',
            'RM3 production quantity=-2 org_amount=-15 grp_amount=-15 running_quantity=18 running_quantity_org_stock=18 running_lpp_value=162 running_wac_value=135 running_fifo_value=138',
            'RM4 production quantity=-2 org_amount=-20 grp_amount=-20 running_quantity=18 running_quantity_org_stock=18 running_lpp_value=216 running_wac_value=180 running_fifo_value=184',
            'I221-BOMB associate quantity=0 org_amount=0 grp_amount=0 running_quantity=null running_quantity_org_stock=null running_lpp_value=null running_wac_value=null running_fifo_value=null',
            'I221-BOMB production quantity=4 org_amount=28 grp_amount=28 running_quantity=4 running_quantity_org_stock=4 running_lpp_value=28 running_wac_value=28 running_fifo_value=28',
            'RM1 production quantity=-2 org_amount=-5.111 grp_amount=-5.111 running_quantity=16 running_quantity_org_stock=16 running_lpp_value=48 running_wac_value=40 running_fifo_value=42',
            'RM2 production quantity=-2 org_amount=-10.222 grp_amount=-10.222 running_quantity=16 running_quantity_org_stock=16 running_lpp_value=96 running_wac_value=80 running_fifo_value=84',
            'RM3 production quantity=-2 org_amount=-15.333 grp_amount=-15.333 running_quantity=16 running_quantity_org_stock=16 running_lpp_value=144 running_wac_value=120 running_fifo_value=126',
            'RM4 production quantity=-2 org_amount=-20.444 grp_amount=-20.444 running_quantity=16 running_quantity_org_stock=16 running_lpp_value=192 running_wac_value=160 running_fifo_value=168',
        ])
        ->and($queryCount)->toBeLessThan(370);
});
