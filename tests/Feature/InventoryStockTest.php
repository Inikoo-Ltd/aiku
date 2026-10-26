<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Dec 2024 15:01:28 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Feature;

use App\Enums\Inventory\OrgStockMovement\OrgStockMovementReasonEnum;
use App\Actions\Goods\Stock\StoreStock;
use App\Actions\Goods\StockFamily\StoreStockFamily;
use App\Actions\Goods\StockFamily\UpdateStockFamily;
use App\Actions\Procurement\GetOrganisationStockCoverBuckets;
use App\Actions\Goods\TradeUnit\StoreTradeUnit;
use App\Actions\Inventory\Location\StoreLocation;
use App\Actions\Inventory\Location\UpdateLocation;
use App\Actions\Inventory\LocationOrgStock\HandleLowStockAuditLock;
use App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock;
use App\Actions\Inventory\LocationOrgStock\UpdateLocationOrgStock;
use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateOutOfStockForecast;
use App\Actions\Inventory\OrgStock\ForecastOrgStockDemand;
use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateQuantityInLocations;
use App\Actions\Inventory\OrgStock\StoreOrgStock;
use App\Enums\Goods\Stock\StockStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Enums\Inventory\LocationStock\LocationStockTypeEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementClassEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementFlowEnum;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Models\Goods\Stock;
use App\Models\Goods\StockFamily;
use App\Models\Goods\TradeUnit;
use App\Models\Inventory\Location;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockMovement;
use App\Actions\Dispatching\BackfillWarehouseTimestamps;
use App\Actions\Goods\Stock\UpdateStock;
use App\Models\Dispatching\Packing;
use App\Models\Dispatching\Picking;
use Config;
use App\Actions\Inventory\OrgStock\RedoOrgStockTimeSeries;
use App\Actions\Accounting\InvoiceTransaction\ProcessInvoiceTransactionTimeSeries;
use App\Actions\Inventory\OrgStock\ProcessOrgStockTimeSeriesRecords;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Accounting\InvoiceTransaction;
use Illuminate\Support\Facades\Queue;
use App\Actions\Accounting\InvoiceTransaction\DeleteInvoiceTransaction;
use App\Actions\Catalogue\CollectionTimeSeries\ProcessCollectionTimeSeriesRecords;
use App\Actions\Catalogue\Collection\StoreCollection;
use App\Actions\Catalogue\Collection\AttachModelToCollection;
use App\Actions\Catalogue\CollectionTimeSeries\RedoCollectionTimeSeries;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use App\Actions\Procurement\OrgSupplier\StoreOrgSupplier;
use App\Actions\Procurement\PurchaseOrder\StorePurchaseOrder;
use App\Actions\Procurement\PurchaseOrderTransaction\StorePurchaseOrderTransaction;
use App\Actions\SupplyChain\Supplier\StoreSupplier;
use App\Actions\SupplyChain\SupplierProduct\StoreSupplierProduct;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use App\Actions\Inventory\OrgStockMovement\StoreOrgStockMovement;
use App\Actions\Maintenance\Inventory\OrgStockMovement\RebuildMissingPickedOrgStockMovements;
use Illuminate\Support\Carbon;

use function Pest\Laravel\actingAs;

require_once __DIR__.'/../Support/InventoryHelpers.php';

beforeAll(function () {
    loadDB();
});

beforeEach(
    function () {
        $this->organisation = createOrganisation();
        $this->group        = group();
        setPermissionsTeamId($this->group->id);
        $this->guest = createAdminGuest($this->group);

        $this->artisan('warehouse:seed-permissions')->assertExitCode(0);

        Config::set("inertia.testing.page_paths", [resource_path("js/Pages/Grp")]);
        $this->user = $this->guest->getUser();
        actingAs($this->user);
    }
);

/*
 * Folded in from its own file so it stops paying for a database restore of its own. The tests below
 * are the only ones here that need a shop, so they build the rest of the chain themselves rather than
 * widening the beforeEach every other test in this file runs.
 *
 * Two of them move the shop's migrated_to_aiku_on to decide whether the backfill should touch the
 * work at all, and the afterEach puts it back: the shop is shared with every block in this file, and
 * a date left in the future would quietly switch off the backfill for anything appended after this.
 */
describe('warehouse instrumentation', function () {
    beforeEach(function () {
        list(, , $this->shop) = createShop();

        $this->warehouse = createWarehouse();
        $this->customer  = createCustomer($this->shop);

        list($this->tradeUnit, $this->product) = createProduct($this->shop);

        $this->migratedOn = $this->shop->migrated_to_aiku_on;
    });

    afterEach(function () {
        DB::table('shops')->where('id', $this->shop->id)->update(['migrated_to_aiku_on' => $this->migratedOn]);
    });

    test('picking and packing stamp the lifecycle timestamps as the work happens', function () {
        [$deliveryNote] = packedDeliveryNote($this);

        $picking = Picking::where('delivery_note_id', $deliveryNote->id)->first();
        $packing = Packing::where('delivery_note_id', $deliveryNote->id)->first();

        expect($picking->last_picked_at)->not->toBeNull()
            ->and($packing->queued_at)->not->toBeNull()
            ->and($packing->packing_at)->not->toBeNull()
            ->and($packing->done_at)->not->toBeNull()
            ->and($packing->packing_at->gte($packing->queued_at))->toBeTrue()
            ->and($packing->done_at->gte($packing->packing_at))->toBeTrue()
            ->and($packing->queued_at->eq($deliveryNote->picked_at))->toBeTrue()
            ->and($packing->packing_at->eq($deliveryNote->packing_at))->toBeTrue();
    });

    test('backfill refills the timestamps from what the application already recorded', function () {
        [$deliveryNote] = packedDeliveryNote($this);

        DB::table('shops')->where('id', $this->shop->id)->update(['migrated_to_aiku_on' => now()->subYear()]);
        DB::table('delivery_notes')->where('id', $deliveryNote->id)->update(['source_id' => null]);
        clearInstrumentation($deliveryNote->id);

        BackfillWarehouseTimestamps::run();

        $picking = Picking::where('delivery_note_id', $deliveryNote->id)->first();
        $packing = Packing::where('delivery_note_id', $deliveryNote->id)->first();

        expect($picking->last_picked_at->eq($picking->created_at))->toBeTrue()
            ->and($packing->done_at->eq($packing->created_at))->toBeTrue()
            ->and($packing->queued_at->eq($deliveryNote->picked_at))->toBeTrue()
            ->and($packing->packing_at->eq($deliveryNote->packing_at))->toBeTrue();
    });

    test('backfill leaves alone work the shop did before it moved to aiku', function () {
        [$deliveryNote] = packedDeliveryNote($this);

        DB::table('shops')->where('id', $this->shop->id)->update(['migrated_to_aiku_on' => now()->addYear()]);
        clearInstrumentation($deliveryNote->id);

        BackfillWarehouseTimestamps::run();

        expect(Picking::where('delivery_note_id', $deliveryNote->id)->first()->last_picked_at)->toBeNull()
            ->and(Packing::where('delivery_note_id', $deliveryNote->id)->first()->done_at)->toBeNull();
    });

    test('backfill leaves alone orders that came over from aurora', function () {
        [$deliveryNote] = packedDeliveryNote($this);

        DB::table('shops')->where('id', $this->shop->id)->update(['migrated_to_aiku_on' => now()->subYear()]);
        DB::table('delivery_notes')->where('id', $deliveryNote->id)->update(['source_id' => 'aurora:1']);
        clearInstrumentation($deliveryNote->id);

        BackfillWarehouseTimestamps::run();

        expect(Picking::where('delivery_note_id', $deliveryNote->id)->first()->last_picked_at)->toBeNull()
            ->and(Packing::where('delivery_note_id', $deliveryNote->id)->first()->done_at)->toBeNull();
    });

    test('stock on an open delivery note still to be picked is held back from what is available', function () {
        $isAikuStockControl = $this->organisation->is_aiku_stock_control;
        DB::table('organisations')->where('id', $this->organisation->id)->update(['is_aiku_stock_control' => true]);

        [$deliveryNote, $deliveryNoteItem] = packedDeliveryNote($this);
        $orgStock = $deliveryNoteItem->orgStock;

        expect((float)$orgStock->refresh()->quantity_to_be_picked)->toBe(0.0)
            ->and((float)$orgStock->quantity_available)->toBe(90.0);

        $deliveryNote->update(['state' => DeliveryNoteStateEnum::HANDLING]);
        $deliveryNoteItem->update(['state' => DeliveryNoteItemStateEnum::HANDLING, 'quantity_required' => 25]);

        expect((float)$orgStock->refresh()->quantity_to_be_picked)->toBe(15.0)
            ->and((float)$orgStock->quantity_available)->toBe(75.0);

        $deliveryNote->update(['state' => DeliveryNoteStateEnum::CANCELLED]);

        expect((float)$orgStock->refresh()->quantity_to_be_picked)->toBe(0.0)
            ->and((float)$orgStock->quantity_available)->toBe(90.0);

        DB::table('organisations')->where('id', $this->organisation->id)->update(['is_aiku_stock_control' => $isAikuStockControl]);
    });

    test('lines swept up by marking a whole note packed are flagged so rates can exclude them', function () {
        [$deliveryNote] = packedDeliveryNote($this, markWholeNotePacked: true);

        $packing = Packing::where('delivery_note_id', $deliveryNote->id)->first();

        expect($packing->data['auto_packed'] ?? null)->toBeTrue()
            ->and($packing->done_at)->not->toBeNull();
    });

    test('rebuild missing picks writes one picked movement per dispatched line with no picking, only under a later stock check', function () {
        [$deliveryNote, $deliveryNoteItem] = packedDeliveryNote($this);
        $orgStock         = $deliveryNoteItem->orgStock;
        $locationOrgStock = LocationOrgStock::where('org_stock_id', $orgStock->id)->first();

        DB::table('pickings')->where('delivery_note_id', $deliveryNote->id)->delete();
        DB::table('org_stock_movements')->where('org_stock_id', $orgStock->id)->delete();
        DB::table('delivery_notes')->where('id', $deliveryNote->id)->update(['dispatched_at' => '2026-05-10 10:00:00']);
        DB::table('delivery_note_items')->where('id', $deliveryNoteItem->id)->update(['quantity_dispatched' => 10]);

        $rebuild = RebuildMissingPickedOrgStockMovements::make();
        $from    = Carbon::parse('2026-04-01');
        $to      = Carbon::parse('2026-08-01');

        expect($rebuild->handle($this->organisation, $from, $to, false)['2026-05']['no_later_stock_check'])->toBe(1)
            ->and(OrgStockMovement::where('org_stock_id', $orgStock->id)->count())->toBe(0);

        $audit = StoreOrgStockMovement::make()->action($orgStock, $locationOrgStock->location, [
            'type'             => OrgStockMovementTypeEnum::AUDIT->value,
            'quantity'         => 0,
            'audited_quantity' => 100,
        ], strict: false);
        DB::table('org_stock_movements')->where('id', $audit->id)->update(['date' => '2026-07-16', 'class' => OrgStockMovementClassEnum::HELPER->value]);
        $quantityBefore = (float)$locationOrgStock->refresh()->quantity;

        $dryRun = $rebuild->handle($this->organisation, $from, $to, true);
        expect($dryRun['2026-05']['written'])->toBe(1)
            ->and(OrgStockMovement::where('org_stock_id', $orgStock->id)->where('type', OrgStockMovementTypeEnum::PICKED)->count())->toBe(0);

        $rebuild->handle($this->organisation, $from, $to, false);
        $picked = OrgStockMovement::where('org_stock_id', $orgStock->id)->where('type', OrgStockMovementTypeEnum::PICKED)->sole();

        expect((float)$picked->quantity)->toBe(-10.0)
            ->and($picked->parent_id)->toBe($deliveryNote->id)
            ->and($picked->location_id)->toBe($locationOrgStock->location_id)
            ->and($picked->date->format('Y-m-d'))->toBe('2026-05-10')
            ->and((float)$locationOrgStock->refresh()->quantity)->toBe($quantityBefore)
            ->and($rebuild->handle($this->organisation, $from, $to, false)['2026-05']['already_picked'])->toBe(1)
            ->and(OrgStockMovement::where('org_stock_id', $orgStock->id)->where('type', OrgStockMovementTypeEnum::PICKED)->count())->toBe(1);
    });
});

test('merging a duplicate stock moves its links to the stocked twin and retires the orphans', function () {
    ensureFirstWarehouseHasCountry($this->organisation);
    $group  = $this->organisation->group;
    $stocks = createStocks($group);
    $empty  = $stocks[0];
    $held   = $stocks[1];

    [$emptyOrgStock] = createOrgStocks($this->organisation, [$empty]);
    [$heldOrgStock] = createOrgStocks($this->organisation, [$held]);

    $supplier            = StoreSupplier::make()->action($group, Supplier::factory()->definition());
    StoreSupplierProduct::make()->action($supplier, array_merge(SupplierProduct::factory()->definition(), ['stock_id' => $empty->id]));
    $orgSupplier         = OrgSupplier::where('supplier_id', $supplier->id)->where('organisation_id', $this->organisation->id)->first()
        ?? StoreOrgSupplier::make()->action($this->organisation, $supplier);
    $purchaseOrder       = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());
    $openTransaction     = StorePurchaseOrderTransaction::make()->action($purchaseOrder, null, $emptyOrgStock, PurchaseOrderTransaction::factory()->definition());
    $openStockDelivery   = \App\Actions\GoodsIn\StockDelivery\StoreStockDelivery::make()->action($orgSupplier, ['reference' => 'SD-MERGE-'.uniqid(), 'date' => now()->toDateString()], strict: false);
    $openDeliveryItem    = \App\Actions\GoodsIn\StockDeliveryItem\StoreStockDeliveryItem::make()->action($openStockDelivery, null, $emptyOrgStock, ['unit_quantity' => 30], strict: false);

    // The merge only retires org stocks holding nothing, and the fixture stock is shared with earlier tests
    DB::table('location_org_stocks')->where('org_stock_id', $emptyOrgStock->id)->update(['quantity' => 0]);

    $tradeUnit = StoreTradeUnit::make()->action($group, TradeUnit::factory()->definition());
    DB::table('model_has_trade_units')->insert([
        'model_type' => 'Stock', 'model_id' => $empty->id, 'trade_unit_id' => $tradeUnit->id,
        'quantity' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);

    \App\Actions\Goods\Stock\MergeDuplicateStock::make()->handle($empty->refresh(), $held->refresh(), 'ArtTT-MERGE');

    expect(DB::table('model_has_trade_units')->where('model_type', 'Stock')->where('model_id', $empty->id)->count())->toBe(0)
        ->and(DB::table('model_has_trade_units')->where('model_type', 'Stock')->where('model_id', $held->id)->where('trade_unit_id', $tradeUnit->id)->exists())->toBeTrue()
        ->and($empty->refresh()->state)->toBe(\App\Enums\Goods\Stock\StockStateEnum::DISCONTINUED)
        ->and($emptyOrgStock->refresh()->state)->toBe(\App\Enums\Inventory\OrgStock\OrgStockStateEnum::DISCONTINUED)
        ->and($held->refresh()->code)->toBe('ArtTT-MERGE')
        ->and($held->slug)->toBe('arttt-merge')
        ->and($empty->refresh()->slug)->not->toBe('arttt-merge')
        ->and($openTransaction->refresh()->org_stock_id)->toBe($heldOrgStock->id)
        ->and($openDeliveryItem->refresh()->org_stock_id)->toBe($heldOrgStock->id);
});

describe('product available quantity resync', function () {
    test('hydrator resyncs an out-of-sync product even when quantity_available does not change', function () {
        list(, , $shop) = createShop();
        list(, $product) = createProduct($shop);

        $orgStock = $product->orgStocks()->first();
        if (!$orgStock) {
            $orgStock = OrgStock::first();
            $product->orgStocks()->attach($orgStock->id, ['quantity' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }

        $location         = StoreLocation::make()->action(createWarehouse(), Location::factory()->definition());
        $locationOrgStock = StoreLocationOrgStock::make()->action($orgStock, $location, []);
        UpdateLocationOrgStock::make()->action($locationOrgStock, ['quantity' => 50]);

        OrgStockHydrateQuantityInLocations::run($orgStock->id);
        $expected = (int) floor($orgStock->refresh()->quantity_available);
        expect($expected)->toBeGreaterThan(0);

        DB::table('products')->where('id', $product->id)->update(['available_quantity' => $expected + 7]);

        OrgStockHydrateQuantityInLocations::run($orgStock->id);

        expect($product->refresh()->available_quantity)->toBe($expected);
    });
});

describe('packing change guard', function () {
    test('pivot change with stock in locations requires a stock strategy, keep flags recount, convert rescales at zero value', function () {
        $stock     = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE]));
        $orgStock  = StoreOrgStock::make()->action($this->organisation, $stock);
        $tradeUnit = StoreTradeUnit::make()->action($this->group, TradeUnit::factory()->definition());
        $orgStock->tradeUnits()->sync([$tradeUnit->id => ['quantity' => 1]]);

        $location         = StoreLocation::make()->action(createWarehouse(), Location::factory()->definition());
        $locationOrgStock = StoreLocationOrgStock::make()->action($orgStock, $location, []);
        UpdateLocationOrgStock::make()->action($locationOrgStock, ['quantity' => 12]);
        $locationOrgStock->updateQuietly(['audited_at' => now(), 'is_low_stock_checked' => true]);

        $payload = ['trade_units' => [['id' => $tradeUnit->id, 'quantity' => 6]]];

        try {
            \App\Actions\Inventory\OrgStock\UpdateOrgStockTradeUnits::make()->handle($orgStock, $payload);
            $this->fail('Expected a stock strategy to be required');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            expect($exception->errors())->toHaveKeys(['stock_recount_required', 'stock_conversion_preview'])
                ->and($exception->errors()['stock_conversion_preview'][0])->toContain('12 → 2');
        }
        expect((float) $orgStock->tradeUnits()->first()->pivot->quantity)->toBe(1.0);

        \App\Actions\Inventory\OrgStock\UpdateOrgStockTradeUnits::make()->handle($orgStock, array_merge($payload, ['stock_strategy' => 'keep']));

        expect((float) $orgStock->refresh()->tradeUnits->first()->pivot->quantity)->toBe(6.0)
            ->and((float) $locationOrgStock->refresh()->quantity)->toBe(12.0)
            ->and($locationOrgStock->audited_at)->toBeNull()
            ->and($locationOrgStock->is_low_stock_checked)->toBeFalse();

        $locationOrgStock->updateQuietly(['audited_at' => now()]);

        \App\Actions\Inventory\OrgStock\UpdateOrgStockTradeUnits::make()->handle($orgStock, [
            'trade_units'    => [['id' => $tradeUnit->id, 'quantity' => 3]],
            'stock_strategy' => 'convert',
        ]);

        $conversionMovement = \App\Models\Inventory\OrgStockMovement::where('org_stock_id', $orgStock->id)
            ->where('reason', 'uom')->latest('id')->first();

        expect((float) $orgStock->refresh()->tradeUnits->first()->pivot->quantity)->toBe(3.0)
            ->and((float) $locationOrgStock->refresh()->quantity)->toBe(24.0)
            ->and($locationOrgStock->audited_at)->not->toBeNull()
            ->and($conversionMovement)->not->toBeNull()
            ->and((float) $conversionMovement->org_amount)->toBe(0.0)
            ->and((float) $conversionMovement->audited_quantity)->toBe(24.0);

        UpdateLocationOrgStock::make()->action($locationOrgStock, ['quantity' => 0]);
        \App\Actions\Inventory\OrgStock\UpdateOrgStockTradeUnits::make()->handle($orgStock, [
            'trade_units'    => [['id' => $tradeUnit->id, 'quantity' => 12]],
            'stock_strategy' => 'convert',
        ]);
        expect((float) $orgStock->refresh()->tradeUnits->first()->pivot->quantity)->toBe(12.0);
    });

    test('converting the count to a bigger pack keeps the stock value and rescales the sko value', function () {
        $this->organisation->update(['wac_calculations_start_date' => now()->subYear()->toDateString()]);

        $stock     = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE]));
        $orgStock  = StoreOrgStock::make()->action($this->organisation, $stock);
        $tradeUnit = StoreTradeUnit::make()->action($this->group, TradeUnit::factory()->definition());
        $orgStock->tradeUnits()->sync([$tradeUnit->id => ['quantity' => 6]]);
        $orgStock->refresh()->unsetRelation('organisation');

        $location         = StoreLocation::make()->action(createWarehouse(), Location::factory()->definition());
        $locationOrgStock = StoreLocationOrgStock::make()->action($orgStock, $location, []);

        \App\Actions\Inventory\OrgStockMovement\StoreOrgStockMovement::make()->action($orgStock, $location, [
            'type'     => OrgStockMovementTypeEnum::PURCHASE->value,
            'quantity' => 48,
        ])->update(['cost_per_sku' => 6.79, 'org_amount' => 325.92, 'date' => now()->subMinute()]);

        \App\Actions\Inventory\OrgStock\UpdateOrgStockTradeUnits::make()->handle($orgStock, [
            'trade_units'    => [['id' => $tradeUnit->id, 'quantity' => 8]],
            'stock_strategy' => 'convert',
        ]);

        $valuation = \App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateSkuValue::make()->getValuationPerSku($orgStock->refresh(), now());

        expect((float) $locationOrgStock->refresh()->quantity)->toBe(36.0)
            ->and($valuation['fifo'])->toEqualWithDelta(9.0533, 0.0001)
            ->and($valuation['wac'])->toEqualWithDelta(9.0533, 0.0001)
            ->and($valuation['fifo'] * 36)->toEqualWithDelta(325.92, 0.001)
            ->and((float) $orgStock->sku_value)->toBe(9.05);
    });

    test('group composition change asks the same question for every stocked warehouse and converts its counts', function () {
        $stock     = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE]));
        $orgStock  = StoreOrgStock::make()->action($this->organisation, $stock);
        $tradeUnit = StoreTradeUnit::make()->action($this->group, TradeUnit::factory()->definition());
        $stock->tradeUnits()->sync([$tradeUnit->id => ['quantity' => 1]]);
        $orgStock->tradeUnits()->sync([$tradeUnit->id => ['quantity' => 1]]);

        $warehouse        = createWarehouse();
        $location         = StoreLocation::make()->action($warehouse, Location::factory()->definition());
        $locationOrgStock = StoreLocationOrgStock::make()->action($orgStock, $location, []);
        UpdateLocationOrgStock::make()->action($locationOrgStock, ['quantity' => 20]);
        $locationOrgStock->updateQuietly(['audited_at' => now()]);

        $level = collect(\App\Actions\Goods\Stock\UI\EditStockComposition::make()->getStockLevels($stock))
            ->firstWhere('warehouse', $warehouse->name);
        expect($level['quantity'])->toBe(20.0)
            ->and($level['packing'])->toBe([$tradeUnit->id => 1.0]);

        $payload = ['trade_units' => [['id' => $tradeUnit->id, 'quantity' => 10]]];

        expect(fn () => UpdateStock::make()->handle($stock, $payload))->toThrow(ValidationException::class)
            ->and((float) $orgStock->tradeUnits()->first()->pivot->quantity)->toBe(1.0);

        UpdateStock::make()->handle($stock->refresh(), array_merge($payload, ['stock_strategy' => 'convert']));

        $level = collect(\App\Actions\Goods\Stock\UI\EditStockComposition::make()->getStockLevels($stock->refresh()))
            ->firstWhere('warehouse', $warehouse->name);

        expect((float) $orgStock->refresh()->tradeUnits->first()->pivot->quantity)->toBe(10.0)
            ->and((float) $locationOrgStock->refresh()->quantity)->toBe(2.0)
            ->and($locationOrgStock->audited_at)->not->toBeNull()
            ->and(OrgStockMovement::where('org_stock_id', $orgStock->id)->where('reason', 'uom')->exists())->toBeTrue()
            ->and($level['quantity'])->toBe(2.0)
            ->and($level['packing'])->toBe([$tradeUnit->id => 10.0])
            ->and(\App\Models\Tasks\StaffTask::where('model_type', 'OrgStock')->where('model_id', $orgStock->id)->exists())->toBeFalse();

        \App\Actions\Inventory\OrgStock\SyncOrgStockTradeUnits::run($orgStock, [$tradeUnit->id => ['quantity' => 5]], 'keep', $this->user->id);

        $recountTask = \App\Models\Tasks\StaffTask::where('model_type', 'OrgStock')->where('model_id', $orgStock->id)->sole();

        expect((float) $locationOrgStock->refresh()->quantity)->toBe(2.0)
            ->and($locationOrgStock->audited_at)->toBeNull()
            ->and($recountTask->department)->toBe('warehouse')
            ->and($recountTask->requester_id)->toBe($this->user->id)
            ->and($recountTask->data['organisation_id'])->toBe($warehouse->organisation_id)
            ->and($recountTask->subject)->toContain($warehouse->name)
            ->and($recountTask->description)->toContain($location->code.': 2');
    });
});

test('stock history archiver keeps a monthly snapshot and the readers read the archived days back', function () {
    config()->set(
        'database.connections.archive',
        array_merge(config('database.connections.'.config('database.default')), ['search_path' => 'archive'])
    );
    DB::purge('archive');
    DB::statement('create schema if not exists archive');
    DB::statement('drop table if exists archive.org_stock_histories, archive.location_org_stock_histories, archive.org_stocks, archive.locations, archive.organisation_stock_histories');

    $stocks    = createStocks($this->group);
    $orgStocks = createOrgStocks($this->organisation, $stocks);
    $orgStock  = $orgStocks[0];
    $warehouse = createWarehouse();
    $location  = StoreLocation::make()->action($warehouse, Location::factory()->definition());

    $retention = config('archive.stock_history_retention_months');

    $days = [
        'recent'   => now()->subMonth()->startOfDay(),
        'keeper'   => now()->subMonths($retention + 4)->startOfMonth()->addDays(27),
        'archived' => now()->subMonths($retention + 4)->startOfMonth()->addDays(6),
    ];

    foreach ($days as $key => $date) {
        $organisationStockHistoryId = DB::table('organisation_stock_histories')->insertGetId([
            'group_id'        => $this->group->id,
            'organisation_id' => $this->organisation->id,
            'date'            => $date->format('Y-m-d'),
            'number_org_stocks'              => 1,
            'number_out_of_stock_org_stocks' => 0,
            'number_location_org_stocks'     => 1,
        ]);

        $orgStockHistoryId = DB::table('org_stock_histories')->insertGetId([
            'organisation_stock_history_id' => $organisationStockHistoryId,
            'organisation_id'               => $this->organisation->id,
            'org_stock_id'                  => $orgStock->id,
            'date'                          => $date->format('Y-m-d'),
            'quantity_in_locations'         => 10,
            'org_stock_lpp_value'           => 100,
        ]);

        DB::table('location_org_stock_histories')->insert([
            'org_stock_history_id'          => $orgStockHistoryId,
            'organisation_stock_history_id' => $organisationStockHistoryId,
            'org_stock_id'                  => $orgStock->id,
            'location_id'                   => $location->id,
            'date'                          => $date->format('Y-m-d'),
            'actual_quantity_in_locations'  => 10,
            'quantity_in_locations'         => 10,
        ]);

        $days[$key] = ['date' => $date, 'organisation_stock_history_id' => $organisationStockHistoryId];
    }

    $archivedRows = \App\Actions\Inventory\OrgStock\Stock\ArchiveStockHistories::run();

    $liveOn   = fn (string $table, array $day) => DB::table($table)->where('date', $day['date']->format('Y-m-d'))->where('org_stock_id', $orgStock->id)->exists();
    $archiveOn = fn (string $table, array $day) => DB::connection('archive')->table($table)->where('date', $day['date']->format('Y-m-d'))->where('org_stock_id', $orgStock->id)->exists();

    expect($archivedRows)->toBe(2)
        ->and($liveOn('org_stock_histories', $days['recent']))->toBeTrue()
        ->and($liveOn('org_stock_histories', $days['keeper']))->toBeTrue()
        ->and($liveOn('org_stock_histories', $days['archived']))->toBeFalse()
        ->and($liveOn('location_org_stock_histories', $days['archived']))->toBeFalse()
        ->and($archiveOn('org_stock_histories', $days['archived']))->toBeTrue()
        ->and($archiveOn('location_org_stock_histories', $days['archived']))->toBeTrue()
        ->and($archiveOn('org_stock_histories', $days['keeper']))->toBeFalse()
        ->and(DB::connection('archive')->table('org_stocks')->where('id', $orgStock->id)->exists())->toBeTrue()
        ->and(DB::connection('archive')->table('locations')->where('id', $location->id)->exists())->toBeTrue();

    $archivedDayExport = new \App\Exports\Inventory\ShowOrganisationStockHistoryExport(
        \App\Models\Inventory\OrganisationStockHistory::find($days['archived']['organisation_stock_history_id'])
    );

    expect($archivedDayExport->query()->count())->toBe(1);

    $reader = new class () {
        use \App\Actions\Traits\WithStockHistoryArchiveRead;

        public function connectionFor(int $organisationStockHistoryId): ?string
        {
            return $this->stockHistoryDayConnection(\App\Models\Inventory\OrganisationStockHistory::find($organisationStockHistoryId));
        }

        public function datesNewestFirst(int $orgStockId, array $dates): array
        {
            $rows = $this->stockHistoryRowsNewestFirst(
                fn (?string $connection) => DB::connection($connection)->table('org_stock_histories')
                    ->where('org_stock_id', $orgStockId)
                    ->whereIn('date', $dates)
                    ->select(['date', 'quantity_in_locations'])
                    ->orderBy('date', 'desc'),
                null
            );

            return array_map(fn ($row) => \Illuminate\Support\Carbon::parse($row->date)->format('Y-m-d'), iterator_to_array($rows, false));
        }
    };

    expect(\App\Actions\Inventory\OrgStock\Stock\GetStockHistories::run(
        fn ($query) => $query->where('org_stock_id', $orgStock->id)->select(['date', 'quantity_in_locations'])
    )->pluck('date')->map(fn ($date) => \Illuminate\Support\Carbon::parse($date)->format('Y-m-d'))->all())
        ->toContain($days['archived']['date']->format('Y-m-d'), $days['keeper']['date']->format('Y-m-d'), $days['recent']['date']->format('Y-m-d'));

    DB::connection('archive')->table('org_stock_histories')
        ->where('organisation_stock_history_id', $days['archived']['organisation_stock_history_id'])
        ->update(['org_stock_wac_value' => 77, 'wac_per_sku' => 7.7, 'non_moving_1y' => 0]);

    \App\Actions\Inventory\OrganisationStockHistory\Hydrators\OrganisationStockHistoryHydrateFromOrgStockHistories::run(
        $days['archived']['organisation_stock_history_id']
    );

    $rolledUp = DB::table('organisation_stock_histories')->find($days['archived']['organisation_stock_history_id']);

    expect((float) $rolledUp->org_stock_wac_value)->toBe(77.0)
        ->and($rolledUp->number_org_stocks)->toBe(1)
        ->and($rolledUp->number_locations)->toBe(1);

    expect($reader->connectionFor($days['recent']['organisation_stock_history_id']))->toBeNull()
        ->and($reader->connectionFor($days['keeper']['organisation_stock_history_id']))->toBeNull()
        ->and($reader->connectionFor($days['archived']['organisation_stock_history_id']))->toBe('archive')
        ->and($reader->datesNewestFirst($orgStock->id, array_map(fn ($day) => $day['date']->format('Y-m-d'), $days)))->toBe([
            $days['recent']['date']->format('Y-m-d'),
            $days['keeper']['date']->format('Y-m-d'),
            $days['archived']['date']->format('Y-m-d'),
        ]);
});

test('stock history sweeps refuse to rewrite only the live years when the archive is unreachable', function () {
    config()->set('database.connections.archive', array_merge(
        config('database.connections.'.config('database.default')),
        ['host' => '127.0.0.1', 'port' => 1, 'database' => 'aiku_archive_unreachable', 'connect_timeout' => 1]
    ));
    DB::purge('archive');

    $sweep = new class () {
        use \App\Actions\Traits\WithStockHistoryArchiveWrite;

        public function connection(): ?string
        {
            return $this->stockHistoryWriteConnection();
        }
    };

    expect(fn () => $sweep->connection())->toThrow(\Exception::class, 'refusing to rewrite only the recent years');

    config()->set('database.connections.archive.database', null);
    DB::purge('archive');

    expect($sweep->connection())->toBeNull();
});

test('merging a duplicate stock keeps the links its twin already has', function () {
    $group  = $this->organisation->group;
    $stocks = createStocks($group);
    $empty  = $stocks[0];
    $held   = $stocks[1];
    createOrgStocks($this->organisation, [$empty, $held]);
    DB::table('location_org_stocks')->whereIn('org_stock_id', $this->organisation->orgStocks()->where('stock_id', $empty->id)->pluck('id'))->update(['quantity' => 0]);

    $tradeUnit = StoreTradeUnit::make()->action($group, TradeUnit::factory()->definition());
    foreach ([$empty, $held] as $stock) {
        DB::table('model_has_trade_units')->insert([
            'model_type' => 'Stock', 'model_id' => $stock->id, 'trade_unit_id' => $tradeUnit->id,
            'quantity' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    \App\Actions\Goods\Stock\MergeDuplicateStock::make()->handle($empty->refresh(), $held->refresh());

    expect(DB::table('model_has_trade_units')->where('model_type', 'Stock')->where('model_id', $empty->id)->count())->toBe(0)
        ->and(DB::table('model_has_trade_units')->where('model_type', 'Stock')->where('model_id', $held->id)->where('trade_unit_id', $tradeUnit->id)->count())->toBe(1)
        ->and($empty->refresh()->state)->toBe(\App\Enums\Goods\Stock\StockStateEnum::DISCONTINUED);
});

test('merging a duplicate trade unit hands its stock to the twin so the product finds its org stock again', function () {
    list(, , $shop) = createShop();
    list(, $product) = createProduct($shop);
    $tradeUnit = $product->tradeUnits()->first();
    $duplicate = StoreTradeUnit::make()->action($shop->group, TradeUnit::factory()->definition());
    // Aurora fetches bypass the unique code validation, so the twin is renamed in place
    DB::table('trade_units')->where('id', $duplicate->id)->update(['code' => $tradeUnit->code]);

    $stock = createStocks($shop->group)[2];
    [$orgStock] = createOrgStocks($shop->organisation, [$stock]);
    foreach ([['Stock', $stock->id], ['OrgStock', $orgStock->id]] as [$type, $id]) {
        DB::table('model_has_trade_units')->insert([
            'model_type' => $type, 'model_id' => $id, 'trade_unit_id' => $duplicate->id,
            'quantity' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    $tag = \App\Models\Helpers\Tag::create(['group_id' => $shop->group_id, 'name' => 'Made In '.uniqid(), 'scope' => \App\Enums\Helpers\Tag\TagScopeEnum::PRODUCT_PROPERTY]);
    $tradeUnit->tags()->attach($tag->id);

    \App\Actions\Goods\TradeUnit\MergeDuplicateTradeUnit::make()->handle($duplicate, $tradeUnit);

    expect($duplicate->fresh()->trashed())->toBeTrue()
        ->and($product->refresh()->tags->pluck('id'))->toContain($tag->id)
        ->and(DB::table('model_has_trade_units')->where('trade_unit_id', $tradeUnit->id)->where('model_type', 'Stock')->where('model_id', $stock->id)->exists())->toBeTrue()
        ->and($product->refresh()->orgStocks()->where('org_stocks.id', $orgStock->id)->exists())->toBeTrue();
});

test('an abandoned low stock audit lock expires instead of holding the SKO for ever', function () {
    $warehouse = createWarehouse();
    $stock     = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE]));
    $orgStock  = StoreOrgStock::make()->action($this->organisation, $stock);

    $lock = ['org_stock_id' => $orgStock->id, 'is_locked' => true, 'source' => 'detail'];

    expect(HandleLowStockAuditLock::make()->handle($warehouse, $lock + ['holder' => 'tab-that-died'])['granted'])->toBeTrue()
        ->and(HandleLowStockAuditLock::make()->handle($warehouse, $lock + ['holder' => 'another-tab'])['granted'])->toBeFalse();

    $this->travel(31)->minutes();

    expect(HandleLowStockAuditLock::make()->handle($warehouse, $lock + ['holder' => 'another-tab'])['granted'])->toBeTrue();
});

test('a low stock audit still open keeps its lock through the heartbeat', function () {
    $warehouse = createWarehouse();
    $stock     = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE]));
    $orgStock  = StoreOrgStock::make()->action($this->organisation, $stock);

    $lock = ['org_stock_id' => $orgStock->id, 'is_locked' => true, 'source' => 'detail'];

    HandleLowStockAuditLock::make()->handle($warehouse, $lock + ['holder' => 'counting-tab']);

    foreach ([20, 40] as $minutes) {
        $this->travelTo(now()->addMinutes($minutes));

        expect(HandleLowStockAuditLock::make()->handle($warehouse, $lock + ['holder' => 'counting-tab'])['granted'])->toBeTrue()
            ->and(HandleLowStockAuditLock::make()->handle($warehouse, $lock + ['holder' => 'another-tab'])['granted'])->toBeFalse();
    }
});

describe('stock cover thresholds per family', function () {
    test('a per-family overstock_days override changes the bucket', function () {
        $stock = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE]));
        [$orgStock] = createOrgStocks($this->organisation, [$stock]);
        $orgStock->update(['state' => OrgStockStateEnum::ACTIVE, 'is_on_demand' => false, 'estimated_lead_time_days' => 10, 'quantity_available' => 50]);
        $orgStock->stats->update(['days_of_cover' => 50, 'predicted_daily_usage' => 1, 'stock_value' => 100]);

        $buckets = GetOrganisationStockCoverBuckets::make();
        expect($buckets->bucketOf($orgStock->fresh()))->toBe('ok');

        $stockFamily = StoreStockFamily::make()->action($this->group, StockFamily::factory()->definition());
        UpdateStock::make()->action($stock, ['stock_family_id' => $stockFamily->id]);
        UpdateStockFamily::make()->action($stockFamily, ['overstock_days' => 40]);

        expect($buckets->bucketOf($orgStock->fresh()))->toBe('excess');
    });
});

describe('out of stock forecast', function () {
    beforeEach(function () {
        list(, , $this->shop) = createShop();

        $this->warehouse = createWarehouse();
        $this->customer  = createCustomer($this->shop);

        list($this->tradeUnit, $this->product) = createProduct($this->shop);
    });

    test('an empty SKO is a stock out only once it feeds a product on sale and has received stock', function () {
        $stock = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE]));
        [$orgStock] = createOrgStocks($this->shop->organisation, [$stock]);
        $this->product->orgStocks()->syncWithoutDetaching([$orgStock->id => ['quantity' => 1]]);
        $orgStock->update(['state' => OrgStockStateEnum::ACTIVE, 'is_on_demand' => false, 'quantity_available' => 0]);
        $this->shop->update(['state' => ShopStateEnum::OPEN]);
        $this->product->update(['is_for_sale' => true]);
        $buckets = GetOrganisationStockCoverBuckets::make();

        expect($buckets->bucketOf($orgStock->fresh()))->toBeNull();

        DB::table('org_stock_movements')->insert([
            'group_id'        => $orgStock->group_id,
            'organisation_id' => $orgStock->organisation_id,
            'warehouse_id'    => $this->warehouse->id,
            'org_stock_id'    => $orgStock->id,
            'date'            => now()->subMonth(),
            'class'           => OrgStockMovementClassEnum::MOVEMENT->value,
            'type'            => OrgStockMovementTypeEnum::PURCHASE->value,
            'flow'            => OrgStockMovementFlowEnum::IN->value,
            'quantity'        => 10,
            'org_amount'      => 0,
            'grp_amount'      => 0,
            'data'            => '{}',
        ]);

        expect($buckets->bucketOf($orgStock->fresh()))->toBe('out');

        $this->product->update(['is_for_sale' => false]);

        expect($buckets->bucketOf($orgStock->fresh()))->toBeNull();
    });

    test('dispatches from delivery notes made in aiku feed the forecast although they carry no date', function () {
        [, $deliveryNoteItem] = packedDeliveryNote($this);
        $deliveryNoteItem->update(['quantity_dispatched' => 10]);

        OrgStockHydrateOutOfStockForecast::run($deliveryNoteItem->orgStock);
        $stats = $deliveryNoteItem->orgStock->stats->refresh();

        expect($deliveryNoteItem->refresh()->date)->toBeNull()
            ->and($stats->forecast_source)->toBe('croston')
            ->and((float) $stats->predicted_daily_usage)->toBeGreaterThan(0.0);
    });

    test('dispatches from the coming quarter last year do not revive the forecast of an item that stopped selling', function () {
        [, $deliveryNoteItem] = packedDeliveryNote($this);
        $deliveryNoteItem->update(['quantity_dispatched' => 30]);
        DB::table('delivery_note_items')->where('id', $deliveryNoteItem->id)
            ->update(['created_at' => now()->subYear()->addDays(20)]);

        OrgStockHydrateOutOfStockForecast::run($deliveryNoteItem->orgStock);
        $stats = $deliveryNoteItem->orgStock->stats->refresh();

        expect($stats->forecast_source)->toBeNull()
            ->and($stats->predicted_daily_usage)->toBeNull()
            ->and($stats->recommended_order_quantity)->toBeNull();
    });

    test('a fresh TimesFM demand forecast, without one-off orders and never above the busiest six weeks, drives days of cover', function () {
        [, $deliveryNoteItem] = packedDeliveryNote($this);
        $orgStock             = $deliveryNoteItem->orgStock;
        $orgStock->update(['state' => OrgStockStateEnum::ACTIVE, 'quantity_available' => 100]);
        foreach (range(1, 20) as $weeksAgo) {
            DB::table('delivery_note_items')->insert([
                'group_id'            => $deliveryNoteItem->group_id,
                'organisation_id'     => $deliveryNoteItem->organisation_id,
                'shop_id'             => $deliveryNoteItem->shop_id,
                'delivery_note_id'    => $deliveryNoteItem->delivery_note_id,
                'org_stock_id'        => $orgStock->id,
                'quantity_required'   => $weeksAgo === 3 ? 500 : 7,
                'quantity_dispatched' => $weeksAgo === 3 ? 500 : 7,
                'data'                => '{}',
                'created_at'          => now()->subWeeks($weeksAgo)->startOfDay()->addHours(10),
                'updated_at'          => now(),
            ]);
        }

        config(['services.timesfm.url' => 'http://timesfm.test', 'services.timesfm.token' => 'secret']);
        Http::fake(['timesfm.test/forecast' => fn ($request) => Http::response([
            'version' => '3',
            'deciles' => array_fill(0, count($request['series']), array_fill(0, $request['horizon'], array_fill(0, 9, 100))),
        ])]);

        expect(ForecastOrgStockDemand::run())->toBeGreaterThanOrEqual(1);
        Http::assertSent(fn ($request) => $request['horizon'] === 8);
        Http::assertSent(fn ($request) => $request['horizon'] === 6);

        $busiestSixWeeks = 5 * 7 + 3 * 7;
        $forecast        = $orgStock->stats->refresh()->demand_forecast;
        expect($forecast['correction'])->toBeGreaterThanOrEqual(0.75)->toBeLessThanOrEqual(1.5)
            ->and($forecast['capped'])->toBeTrue()
            ->and($forecast['weeks'])->toHaveCount(8)
            ->and(array_sum(array_column(array_slice($forecast['weeks'], 0, 6), 0)))->toEqualWithDelta($busiestSixWeeks, 0.01)
            ->and($forecast['record'])->toHaveCount(1);

        OrgStockHydrateOutOfStockForecast::run($orgStock->fresh());
        $stats = $orgStock->stats->refresh();
        expect($stats->forecast_source)->toBe('timesfm')
            ->and((float) $stats->predicted_daily_usage)->toEqualWithDelta($busiestSixWeeks / 42, 0.0001)
            ->and((float) $stats->days_of_cover)->toEqualWithDelta(100 / ($busiestSixWeeks / 42), 0.2);

        ForecastOrgStockDemand::run();
        expect($orgStock->stats->refresh()->demand_forecast['record'])->toHaveCount(1);

        $orgStock->update(['quantity_available' => 0]);
        OrgStockHydrateOutOfStockForecast::run($orgStock->fresh());
        expect($orgStock->stats->refresh()->forecast_source)->not->toBe('timesfm');
        $orgStock->update(['quantity_available' => 100]);

        $partPackMovementId = DB::table('org_stock_movements')->insertGetId([
            'group_id'                   => $orgStock->group_id,
            'organisation_id'            => $orgStock->organisation_id,
            'warehouse_id'               => $this->warehouse->id,
            'org_stock_id'               => $orgStock->id,
            'date'                       => now()->subDays(100),
            'class'                      => OrgStockMovementClassEnum::MOVEMENT->value,
            'type'                       => OrgStockMovementTypeEnum::PURCHASE->value,
            'flow'                       => OrgStockMovementFlowEnum::IN->value,
            'quantity'                   => 0.667,
            'running_quantity_org_stock' => 0.667,
            'org_amount'                 => 0,
            'grp_amount'                 => 0,
            'data'                       => '{}',
        ]);
        OrgStockHydrateOutOfStockForecast::run($orgStock->fresh());
        expect($orgStock->stats->refresh()->forecast_source)->not->toBe('timesfm');

        $productLinks = DB::table('product_has_org_stocks')->where('org_stock_id', $orgStock->id)->pluck('quantity', 'product_id');
        $productState = DB::table('products')->where('id', $this->product->id)->value('state');
        DB::table('products')->where('id', $this->product->id)->update(['state' => ProductStateEnum::ACTIVE->value]);
        DB::table('product_has_org_stocks')->where('org_stock_id', $orgStock->id)->update(['quantity' => 0.5]);
        $this->product->orgStocks()->syncWithoutDetaching([$orgStock->id => ['quantity' => 0.5]]);
        OrgStockHydrateOutOfStockForecast::run($orgStock->fresh());
        expect($orgStock->stats->refresh()->forecast_source)->toBe('timesfm');
        if (!$productLinks->has($this->product->id)) {
            $this->product->orgStocks()->detach($orgStock->id);
        }
        foreach ($productLinks as $productId => $quantity) {
            DB::table('product_has_org_stocks')->where('org_stock_id', $orgStock->id)->where('product_id', $productId)->update(['quantity' => $quantity]);
        }
        DB::table('products')->where('id', $this->product->id)->update(['state' => $productState]);
        DB::table('org_stock_movements')->where('id', $partPackMovementId)->delete();

        $orgStock->stats->update(['demand_forecast' => [...$forecast, 'from' => now()->subDays(3)->toDateString()]]);
        OrgStockHydrateOutOfStockForecast::run($orgStock->fresh());
        expect($orgStock->stats->refresh()->forecast_source)->not->toBe('timesfm');
    });

    test('an item that is almost never in stock cannot forecast more than twice its calendar dispatch rate', function () {
        [, $deliveryNoteItem] = packedDeliveryNote($this);
        $orgStock             = $deliveryNoteItem->orgStock;
        $orgStock->update(['state' => OrgStockStateEnum::ACTIVE, 'quantity_available' => 0]);
        DB::table('delivery_note_items')->where('org_stock_id', $orgStock->id)->delete();

        $movementBase = [
            'group_id'        => $orgStock->group_id,
            'organisation_id' => $orgStock->organisation_id,
            'warehouse_id'    => $this->warehouse->id,
            'org_stock_id'    => $orgStock->id,
            'class'           => OrgStockMovementClassEnum::MOVEMENT->value,
            'type'            => OrgStockMovementTypeEnum::PURCHASE->value,
            'flow'            => OrgStockMovementFlowEnum::IN->value,
            'quantity'        => 1,
            'org_amount'      => 0,
            'grp_amount'      => 0,
            'data'            => '{}',
        ];
        DB::table('org_stock_movements')->insert([
            [...$movementBase, 'date' => now()->subDays(60)->startOfDay()->addHours(8), 'running_quantity_org_stock' => 9000],
            [...$movementBase, 'date' => now()->subDays(57)->startOfDay()->addHours(8), 'running_quantity_org_stock' => 0],
        ]);
        foreach ([60, 59, 58] as $daysAgo) {
            DB::table('delivery_note_items')->insert([
                'group_id'            => $deliveryNoteItem->group_id,
                'organisation_id'     => $deliveryNoteItem->organisation_id,
                'shop_id'             => $deliveryNoteItem->shop_id,
                'delivery_note_id'    => $deliveryNoteItem->delivery_note_id,
                'org_stock_id'        => $orgStock->id,
                'quantity_required'   => 3000,
                'quantity_dispatched' => 3000,
                'data'                => '{}',
                'created_at'          => now()->subDays($daysAgo)->startOfDay()->addHours(10),
                'updated_at'          => now(),
            ]);
        }

        OrgStockHydrateOutOfStockForecast::run($orgStock->fresh());
        $stats = $orgStock->stats->refresh();

        $calendarRate = 9000 / 91;
        expect($stats->forecast_source)->toBe('holt')
            ->and((float) $stats->predicted_daily_usage)->toEqualWithDelta($calendarRate * 2, 0.01);
    });

    test('an item that sold all year and then ran out keeps its in-stock selling rate', function () {
        [, $deliveryNoteItem] = packedDeliveryNote($this);
        $orgStock             = $deliveryNoteItem->orgStock;
        $orgStock->update(['state' => OrgStockStateEnum::ACTIVE, 'quantity_available' => 0]);
        DB::table('delivery_note_items')->where('org_stock_id', $orgStock->id)->delete();

        $movementBase = [
            'group_id'        => $orgStock->group_id,
            'organisation_id' => $orgStock->organisation_id,
            'warehouse_id'    => $this->warehouse->id,
            'org_stock_id'    => $orgStock->id,
            'class'           => OrgStockMovementClassEnum::MOVEMENT->value,
            'type'            => OrgStockMovementTypeEnum::PURCHASE->value,
            'flow'            => OrgStockMovementFlowEnum::IN->value,
            'quantity'        => 1,
            'org_amount'      => 0,
            'grp_amount'      => 0,
            'data'            => '{}',
        ];
        DB::table('org_stock_movements')->insert([
            [...$movementBase, 'date' => now()->subDays(60)->startOfDay()->addHours(8), 'running_quantity_org_stock' => 30],
            [...$movementBase, 'date' => now()->subDays(57)->startOfDay()->addHours(8), 'running_quantity_org_stock' => 0],
        ]);
        foreach (array_fill_keys(range(226, 200), 100) + [60 => 10, 59 => 10, 58 => 10] as $daysAgo => $quantity) {
            DB::table('delivery_note_items')->insert([
                'group_id'            => $deliveryNoteItem->group_id,
                'organisation_id'     => $deliveryNoteItem->organisation_id,
                'shop_id'             => $deliveryNoteItem->shop_id,
                'delivery_note_id'    => $deliveryNoteItem->delivery_note_id,
                'org_stock_id'        => $orgStock->id,
                'quantity_required'   => $quantity,
                'quantity_dispatched' => $quantity,
                'data'                => '{}',
                'created_at'          => now()->subDays($daysAgo)->startOfDay()->addHours(10),
                'updated_at'          => now(),
            ]);
        }

        OrgStockHydrateOutOfStockForecast::run($orgStock->fresh());
        $stats = $orgStock->stats->refresh();

        expect($stats->forecast_source)->toBe('holt')
            ->and((float) $stats->predicted_daily_usage)->toBeGreaterThan(30 / 91 * 2 * 5)
            ->and((float) $stats->predicted_daily_usage)->toBeLessThanOrEqual(2730 / 365 * 2);
    });

    test('an item that ran out after selling steadily is not forecast from the slow weeks it was running down', function () {
        [, $deliveryNoteItem] = packedDeliveryNote($this);
        $orgStock             = $deliveryNoteItem->orgStock;
        $orgStock->update(['state' => OrgStockStateEnum::ACTIVE, 'quantity_available' => 0]);
        DB::table('delivery_note_items')->where('org_stock_id', $orgStock->id)->delete();
        DB::table('org_stock_movements')->where('org_stock_id', $orgStock->id)->delete();

        $movementBase = [
            'group_id'        => $orgStock->group_id,
            'organisation_id' => $orgStock->organisation_id,
            'warehouse_id'    => $this->warehouse->id,
            'org_stock_id'    => $orgStock->id,
            'class'           => OrgStockMovementClassEnum::MOVEMENT->value,
            'type'            => OrgStockMovementTypeEnum::PURCHASE->value,
            'flow'            => OrgStockMovementFlowEnum::IN->value,
            'quantity'        => 1,
            'org_amount'      => 0,
            'grp_amount'      => 0,
            'data'            => '{}',
        ];
        DB::table('org_stock_movements')->insert([
            [...$movementBase, 'date' => now()->subDays(300)->startOfDay()->addHours(8), 'running_quantity_org_stock' => 2000],
            [...$movementBase, 'date' => now()->subDays(45)->startOfDay()->addHours(8), 'running_quantity_org_stock' => 0],
        ]);
        DB::table('delivery_note_items')->insert(array_map(fn (int $daysAgo) => [
            'group_id'            => $deliveryNoteItem->group_id,
            'organisation_id'     => $deliveryNoteItem->organisation_id,
            'shop_id'             => $deliveryNoteItem->shop_id,
            'delivery_note_id'    => $deliveryNoteItem->delivery_note_id,
            'org_stock_id'        => $orgStock->id,
            'quantity_required'   => $daysAgo === 50 ? 1 : 3,
            'quantity_dispatched' => $daysAgo === 50 ? 1 : 3,
            'data'                => '{}',
            'created_at'          => now()->subDays($daysAgo)->startOfDay()->addHours(10),
            'updated_at'          => now(),
        ], [...range(299, 60), 50]));

        OrgStockHydrateOutOfStockForecast::run($orgStock->fresh());
        $stats = $orgStock->stats->refresh();

        expect($stats->forecast_source)->toBe('history')
            ->and((float) $stats->predicted_daily_usage)->toBeGreaterThan(2.5)
            ->and((float) $stats->predicted_daily_usage)->toBeLessThanOrEqual(3.0);
    });

    test('an item out of stock all window forecasts from its own selling rate over the last year', function () {
        [, $deliveryNoteItem] = packedDeliveryNote($this);
        $orgStock             = $deliveryNoteItem->orgStock;
        $orgStock->update(['state' => OrgStockStateEnum::ACTIVE, 'quantity_available' => 0]);
        DB::table('delivery_note_items')->where('org_stock_id', $orgStock->id)->delete();
        DB::table('org_stock_movements')->where('org_stock_id', $orgStock->id)->delete();

        $movementBase = [
            'group_id'        => $orgStock->group_id,
            'organisation_id' => $orgStock->organisation_id,
            'warehouse_id'    => $this->warehouse->id,
            'org_stock_id'    => $orgStock->id,
            'class'           => OrgStockMovementClassEnum::MOVEMENT->value,
            'type'            => OrgStockMovementTypeEnum::PURCHASE->value,
            'flow'            => OrgStockMovementFlowEnum::IN->value,
            'quantity'        => 1,
            'org_amount'      => 0,
            'grp_amount'      => 0,
            'data'            => '{}',
        ];
        DB::table('org_stock_movements')->insert([
            [...$movementBase, 'date' => now()->subDays(300)->startOfDay()->addHours(8), 'running_quantity_org_stock' => 600],
            [...$movementBase, 'date' => now()->subDays(100)->startOfDay()->addHours(8), 'running_quantity_org_stock' => 0],
        ]);
        foreach (range(295, 105, 10) as $daysAgo) {
            DB::table('delivery_note_items')->insert([
                'group_id'            => $deliveryNoteItem->group_id,
                'organisation_id'     => $deliveryNoteItem->organisation_id,
                'shop_id'             => $deliveryNoteItem->shop_id,
                'delivery_note_id'    => $deliveryNoteItem->delivery_note_id,
                'org_stock_id'        => $orgStock->id,
                'quantity_required'   => 30,
                'quantity_dispatched' => 30,
                'data'                => '{}',
                'created_at'          => now()->subDays($daysAgo)->startOfDay()->addHours(10),
                'updated_at'          => now(),
            ]);
        }

        OrgStockHydrateOutOfStockForecast::run($orgStock->fresh());
        $stats = $orgStock->stats->refresh();

        expect($stats->forecast_source)->toBe('history')
            ->and((float) $stats->predicted_daily_usage)->toEqualWithDelta(600 / 200, 0.0001)
            ->and((float) $stats->recommended_order_quantity)->toBeGreaterThan(0.0);

        $oneOff = DB::table('delivery_note_items')->insertGetId([
            'group_id'            => $deliveryNoteItem->group_id,
            'organisation_id'     => $deliveryNoteItem->organisation_id,
            'shop_id'             => $deliveryNoteItem->shop_id,
            'delivery_note_id'    => $deliveryNoteItem->delivery_note_id,
            'org_stock_id'        => $orgStock->id,
            'quantity_required'   => 30000,
            'quantity_dispatched' => 30000,
            'data'                => '{}',
            'created_at'          => now()->subDays(150)->startOfDay()->addHours(10),
            'updated_at'          => now(),
        ]);
        OrgStockHydrateOutOfStockForecast::run($orgStock->fresh());
        expect((float) $orgStock->stats->refresh()->predicted_daily_usage)->toEqualWithDelta((600 + 3 * 30) / 200, 0.0001);
        DB::table('delivery_note_items')->where('id', $oneOff)->delete();

        DB::table('delivery_note_items')->where('org_stock_id', $orgStock->id)->update(['created_at' => DB::raw("created_at - interval '90 days'")]);
        DB::table('org_stock_movements')->where('org_stock_id', $orgStock->id)->update(['date' => DB::raw("date - interval '90 days'")]);
        OrgStockHydrateOutOfStockForecast::run($orgStock->fresh());
        expect($orgStock->stats->refresh()->forecast_source)->not->toBe('history');
    });

    test('what production and consumption take out is demand, for TimesFM and for a component no customer buys', function () {
        [, $deliveryNoteItem] = packedDeliveryNote($this);
        $orgStock             = $deliveryNoteItem->orgStock;
        $orgStock->update(['state' => OrgStockStateEnum::ACTIVE, 'quantity_available' => 0]);
        DB::table('delivery_note_items')->where('org_stock_id', $orgStock->id)->delete();
        DB::table('org_stock_movements')->where('org_stock_id', $orgStock->id)->delete();

        foreach (range(1, 10) as $weeksAgo) {
            DB::table('org_stock_movements')->insert([
                'group_id'        => $orgStock->group_id,
                'organisation_id' => $orgStock->organisation_id,
                'warehouse_id'    => $this->warehouse->id,
                'org_stock_id'    => $orgStock->id,
                'date'            => now()->subWeeks($weeksAgo)->startOfDay()->addHours(8),
                'class'           => OrgStockMovementClassEnum::MOVEMENT->value,
                'type'            => $weeksAgo % 2 ? OrgStockMovementTypeEnum::CONSUMPTION->value : OrgStockMovementTypeEnum::PRODUCTION->value,
                'flow'            => OrgStockMovementFlowEnum::OUT->value,
                'quantity'        => -50,
                'org_amount'      => 0,
                'grp_amount'      => 0,
                'data'            => '{}',
            ]);
        }

        config(['services.timesfm.url' => 'http://timesfm.test', 'services.timesfm.token' => 'secret']);
        Http::fake(['timesfm.test/forecast' => fn ($request) => Http::response([
            'version' => '3',
            'deciles' => array_fill(0, count($request['series']), array_fill(0, $request['horizon'], array_fill(0, 9, 1))),
        ])]);
        ForecastOrgStockDemand::run();
        Http::assertSent(fn ($request) => $request['horizon'] === 8 && collect($request['series'])->contains(fn ($series) => array_sum($series) == 500 && max($series) == 50));
        expect($orgStock->stats->refresh()->demand_forecast['weeks'] ?? null)->toHaveCount(8);

        OrgStockHydrateOutOfStockForecast::run($orgStock->fresh());
        $stats = $orgStock->stats->refresh();
        expect($stats->forecast_source)->toBe('consumption')
            ->and((float) $stats->predicted_daily_usage)->toEqualWithDelta(500 / 91, 0.0001);
    });

    test('a sister organisation\'s shipments to a partner organisation are not borrowed as demand', function () {
        [$deliveryNote, $deliveryNoteItem] = packedDeliveryNote($this);
        $deliveryNoteItem->update(['quantity_dispatched' => 910]);

        $orgData = \App\Models\SysAdmin\Organisation::factory()->definition();
        data_set($orgData, 'code', 'acm2');
        data_set($orgData, 'type', \App\Enums\SysAdmin\Organisation\OrganisationTypeEnum::SHOP);
        $organisation2 = \App\Models\SysAdmin\Organisation::where('code', 'acm2')->first()
            ?? \App\Actions\SysAdmin\Organisation\StoreOrganisation::make()->action($this->group, $orgData);
        $orgStock = StoreOrgStock::make()->action($organisation2, $deliveryNoteItem->orgStock->stock);

        OrgStockHydrateOutOfStockForecast::run($orgStock->fresh());
        $stats = $orgStock->stats->refresh();
        expect($stats->forecast_source)->toBe('siblings')
            ->and((float) $stats->predicted_daily_usage)->toEqualWithDelta(910 / 91 * 0.5, 0.0001);

        $orgStock->update(['quantity_available' => 10]);
        OrgStockHydrateOutOfStockForecast::run($orgStock->fresh());
        expect($orgStock->stats->refresh()->forecast_source)->toBeNull();
        $orgStock->update(['quantity_available' => 0]);

        $partnerLink = ['group_id' => $this->group->id, 'organisation_id' => $organisation2->id, 'partner_id' => $this->organisation->id];
        $existingLink = DB::table('org_partners')->where($partnerLink)->first();
        if ($existingLink) {
            DB::table('org_partners')->where('id', $existingLink->id)->update(['customer_id' => $deliveryNote->customer_id]);
        } else {
            DB::table('org_partners')->insert([...$partnerLink, 'customer_id' => $deliveryNote->customer_id, 'sources' => '{}']);
        }

        OrgStockHydrateOutOfStockForecast::run($orgStock->fresh());
        $stats = $orgStock->stats->refresh();

        if ($existingLink) {
            DB::table('org_partners')->where('id', $existingLink->id)->update(['customer_id' => $existingLink->customer_id]);
        } else {
            DB::table('org_partners')->where($partnerLink)->delete();
        }

        expect($stats->forecast_source)->toBeNull()
            ->and($stats->predicted_daily_usage)->toBeNull()
            ->and($stats->recommended_order_quantity)->toBeNull();
    });
});

test('rebuilding the sales history of an sko drops the periods its invoice lines no longer fall in', function () {
    list(, , $shop) = createShop();
    $customer       = createCustomer($shop);
    [$orgStock]     = createOrgStocks($this->organisation, array_slice(createStocks($this->group), 0, 1));

    $orgStock->timeSeries()->get()->each(function ($series) {
        $series->records()->delete();
        $series->delete();
    });

    $seriesId   = DB::table('org_stock_time_series')->insertGetId(['org_stock_id' => $orgStock->id, 'frequency' => 'monthly']);
    $leftBehind = fn (): array => DB::table('org_stock_time_series_records')->where('org_stock_time_series_id', $seriesId)->orderBy('period')->pluck('period')->all();
    $fetchMonth = fn () => DB::table('org_stock_time_series_records')->insert([
        'org_stock_time_series_id'    => $seriesId,
        'frequency'                   => 'M',
        'period'                      => '2025-01',
        'from'                        => '2025-01-01 00:00:00',
        'to'                          => '2025-01-31 23:59:59',
        'sales_grp_currency_external' => 3714,
        'invoices'                    => 338,
    ]);

    $fetchMonth();
    RedoOrgStockTimeSeries::run($orgStock->id);
    expect($leftBehind())->toBe([]);

    $invoiceTransactionId = DB::table('invoice_transactions')->insertGetId([
        'group_id'        => $this->group->id,
        'organisation_id' => $this->organisation->id,
        'shop_id'         => $shop->id,
        'customer_id'     => $customer->id,
        'date'            => '2013-06-11 10:00:00',
        'tax_category_id' => DB::table('tax_categories')->value('id'),
        'net_amount'      => 12,
        'data'            => '{}',
    ]);
    DB::table('invoice_transaction_has_org_stocks')->insert([
        'invoice_transaction_id' => $invoiceTransactionId,
        'org_stock_id'           => $orgStock->id,
        'net_amount'             => 12,
        'org_net_amount'         => 12,
        'grp_net_amount'         => 12,
    ]);

    $fetchMonth();
    RedoOrgStockTimeSeries::run($orgStock->id);
    expect($leftBehind())->toBe(['2013-06']);

    $fetchMonth();
    RedoOrgStockTimeSeries::run($orgStock->id, '2013-06-01', '2013-06-30');
    expect($leftBehind())->toBe(['2013-06', '2025-01']);
});

test('an invoice line stored with an earlier date reaches the sales history of its sko', function () {
    list(, , $shop) = createShop();
    $customer       = createCustomer($shop);
    [$orgStock]     = createOrgStocks($this->organisation, array_slice(createStocks($this->group), 0, 1));

    $storeLine = function (string $date) use ($shop, $customer, $orgStock): InvoiceTransaction {
        $invoiceTransactionId = DB::table('invoice_transactions')->insertGetId([
            'group_id'        => $this->group->id,
            'organisation_id' => $this->organisation->id,
            'shop_id'         => $shop->id,
            'customer_id'     => $customer->id,
            'date'            => $date,
            'tax_category_id' => DB::table('tax_categories')->value('id'),
            'net_amount'      => 12,
            'data'            => '{}',
        ]);
        DB::table('invoice_transaction_has_org_stocks')->insert([
            'invoice_transaction_id' => $invoiceTransactionId,
            'org_stock_id'           => $orgStock->id,
            'net_amount'             => 12,
            'org_net_amount'         => 12,
            'grp_net_amount'         => 12,
        ]);

        return InvoiceTransaction::find($invoiceTransactionId);
    };

    $lateDate = now()->subDays(10);
    $forSko   = fn ($action, array $arguments) => $arguments[0] === $orgStock->id;

    Queue::fake();
    ProcessInvoiceTransactionTimeSeries::run($storeLine(now()->toDateTimeString()));
    ProcessOrgStockTimeSeriesRecords::assertNotPushed($forSko);

    ProcessInvoiceTransactionTimeSeries::run($storeLine($lateDate->toDateTimeString()));
    ProcessOrgStockTimeSeriesRecords::assertPushed(5, $forSko);
    ProcessOrgStockTimeSeriesRecords::assertPushed(
        1,
        fn ($action, array $arguments) => $forSko($action, $arguments) && $arguments[1] === TimeSeriesFrequencyEnum::MONTHLY
            && $arguments[2] === $lateDate->copy()->startOfMonth()->toDateString()
            && $arguments[3] === $lateDate->toDateString()
    );
});

test('deleting an invoice line with an earlier date takes it out of the sales history of its sko', function () {
    list(, , $shop) = createShop();
    $customer       = createCustomer($shop);
    [$orgStock]     = createOrgStocks($this->organisation, array_slice(createStocks($this->group), 0, 1));

    $invoiceTransactionId = DB::table('invoice_transactions')->insertGetId([
        'group_id'        => $this->group->id,
        'organisation_id' => $this->organisation->id,
        'shop_id'         => $shop->id,
        'customer_id'     => $customer->id,
        'date'            => now()->subDays(10)->toDateTimeString(),
        'tax_category_id' => DB::table('tax_categories')->value('id'),
        'net_amount'      => 12,
        'data'            => '{}',
    ]);
    DB::table('invoice_transaction_has_org_stocks')->insert([
        'invoice_transaction_id' => $invoiceTransactionId,
        'org_stock_id'           => $orgStock->id,
        'net_amount'             => 12,
        'org_net_amount'         => 12,
        'grp_net_amount'         => 12,
    ]);

    Queue::fake();
    DeleteInvoiceTransaction::make()->action(InvoiceTransaction::find($invoiceTransactionId));

    expect(DB::table('invoice_transaction_has_org_stocks')->where('invoice_transaction_id', $invoiceTransactionId)->exists())->toBeFalse();
    ProcessOrgStockTimeSeriesRecords::assertPushed(5, fn ($action, array $arguments) => $arguments[0] === $orgStock->id);
});

test('the sales history of a collection adds up the invoice lines of its products', function () {
    list(, , $shop) = createShop();
    $customer       = createCustomer($shop);
    list(, $product) = createProduct($shop);

    $collection = StoreCollection::make()->action($shop, ['code' => 'TsColl', 'name' => 'Time series collection', 'description' => 'Time series collection']);
    AttachModelToCollection::make()->action($collection, $product);

    $date = now()->subMonth()->startOfMonth()->addDays(3);
    DB::table('invoice_transactions')->insert([
        'group_id'        => $this->group->id,
        'organisation_id' => $this->organisation->id,
        'shop_id'         => $shop->id,
        'customer_id'     => $customer->id,
        'asset_id'        => $product->asset_id,
        'date'            => $date->toDateTimeString(),
        'tax_category_id' => DB::table('tax_categories')->value('id'),
        'net_amount'      => 12,
        'org_net_amount'  => 12,
        'grp_net_amount'  => 12,
        'data'            => '{}',
    ]);

    ProcessCollectionTimeSeriesRecords::run($collection->id, TimeSeriesFrequencyEnum::MONTHLY, $date->toDateString(), $date->toDateString());

    $recorded = DB::table('collection_time_series_records')
        ->join('collection_time_series', 'collection_time_series.id', '=', 'collection_time_series_records.collection_time_series_id')
        ->where('collection_time_series.collection_id', $collection->id)
        ->where('collection_time_series_records.period', $date->format('Y-m'))
        ->value('sales_grp_currency_external');

    expect($product->id)->not->toBe($product->asset_id)
        ->and((float) $recorded)->toBe(12.0)
        ->and($collection->source_id)->toBeNull();

    DB::table('collection_time_series_records')->whereIn('collection_time_series_id', $collection->timeSeries()->pluck('id'))->delete();
    RedoCollectionTimeSeries::run($collection->id);

    expect(DB::table('collection_time_series_records')->whereIn('collection_time_series_id', $collection->timeSeries()->pluck('id'))->where('frequency', 'M')->sum('sales_grp_currency_external'))->toEqual(12);

    $monthlySeriesId = $collection->timeSeries()->where('frequency', 'monthly')->value('id');
    DB::table('collection_time_series_records')->insert([
        'collection_time_series_id'   => $monthlySeriesId,
        'frequency'                   => 'M',
        'period'                      => '2019-03',
        'from'                        => '2019-03-01 00:00:00',
        'to'                          => '2019-03-31 23:59:59',
        'sales_grp_currency_external' => 500,
    ]);
    RedoCollectionTimeSeries::run($collection->id);

    expect(DB::table('collection_time_series_records')->where('collection_time_series_id', $monthlySeriesId)->pluck('period')->all())->toBe([$date->format('Y-m')]);

    $collection->products()->detach();
    RedoCollectionTimeSeries::run($collection->id);

    expect(DB::table('collection_time_series_records')->where('collection_time_series_id', $monthlySeriesId)->count())->toBe(0);
});

test('operators tick skos packed at a partner goods out location and the tick goes stale when more stock moves in', function () {
    $warehouse = createWarehouse();
    $bay       = StoreLocation::make()->action($warehouse, Location::factory()->definition());
    $shelf     = StoreLocation::make()->action($warehouse, Location::factory()->definition());
    UpdateLocation::make()->action($bay, ['is_goods_out' => true]);
    $orgStocks = createOrgStocks($this->organisation, array_slice(createStocks($this->group), 0, 2));

    $slots = [];
    foreach ($orgStocks as $orgStock) {
        $slot = StoreLocationOrgStock::make()->action($orgStock, $bay, ['type' => LocationStockTypeEnum::STORING]);
        UpdateLocationOrgStock::make()->action($slot, ['quantity' => 5]);
        $slots[] = $slot->refresh();
    }
    [$packedSlot, $otherSlot] = $slots;
    $dangerousTradeUnit = StoreTradeUnit::make()->action($this->group, TradeUnit::factory()->definition());
    $dangerousTradeUnit->update(['un_number' => '1197', 'proper_shipping_name' => 'Extracts, flavouring, liquid']);
    $orgStocks[0]->tradeUnits()->sync([$dangerousTradeUnit->id => ['quantity' => 1]]);
    $shelfSlot = StoreLocationOrgStock::make()->action($orgStocks[0], $shelf, ['type' => LocationStockTypeEnum::PICKING]);

    $pack = fn (LocationOrgStock $slot, bool $packed) => $this->patch(route('grp.models.location_org_stock.packed', $slot->id), ['packed' => $packed]);

    $pack($shelfSlot, true)->assertSessionHasErrors('packed');
    $pack($packedSlot, true)->assertRedirect();

    $packedSlot->refresh();
    expect($packedSlot->packed_by_user_id)->toBe($this->user->id)
        ->and($packedSlot->packed_at)->not->toBeNull()
        ->and((float) $packedSlot->packed_quantity)->toBe(5.0);

    $page = fn (array $query = []) => $this->withoutVite()->get(route('grp.org.warehouses.show.infrastructure.locations.show', array_merge(
        [$this->organisation->slug, $warehouse->slug, $bay->slug, 'tab' => 'org_stocks'],
        $query
    )))->assertOk()->viewData('page')['props'];

    $props = $page();
    $rows  = collect($props['org_stocks']['data'])->keyBy('location_org_stock_id');
    expect($rows[$packedSlot->id]['is_packed'])->toBeTrue()
        ->and($rows[$packedSlot->id]['packed_by'])->not->toBeNull()
        ->and($rows[$packedSlot->id]['un_numbers'])->toBe([['number' => '1197', 'shipping_name' => 'Extracts, flavouring, liquid']])
        ->and($rows[$otherSlot->id]['is_packed'])->toBeFalse()
        ->and($rows[$otherSlot->id]['un_numbers'])->toBe([])
        ->and(collect($props['packing_progress'])->firstWhere('id', $bay->id))->toMatchArray(['packed' => 1, 'total' => 2, 'is_current' => true])
        ->and(collect($page(['org_stocks_elements' => ['packed' => 'packed']])['org_stocks']['data'])->pluck('location_org_stock_id')->all())->toBe([$packedSlot->id])
        ->and(collect($page(['org_stocks_elements' => ['packed' => 'not_packed']])['org_stocks']['data'])->pluck('location_org_stock_id')->all())->toBe([$otherSlot->id])
        ->and(collect($page(['org_stocks_sort' => '-packed'])['org_stocks']['data'])->pluck('location_org_stock_id')->all())->toBe([$packedSlot->id, $otherSlot->id])
        ->and(collect($page(['org_stocks_sort' => 'packed'])['org_stocks']['data'])->pluck('location_org_stock_id')->all())->toBe([$otherSlot->id, $packedSlot->id]);

    UpdateLocationOrgStock::make()->action($packedSlot, ['quantity' => 8]);
    $row = collect($page()['org_stocks']['data'])->firstWhere('location_org_stock_id', $packedSlot->id);
    expect($row['is_packed'])->toBeFalse()
        ->and($row['packed_quantity'])->toBe('5')
        ->and(collect($page(['org_stocks_elements' => ['packed' => 'not_packed']])['org_stocks']['data'])->pluck('location_org_stock_id')->all())->toContain($packedSlot->id);

    $pack($packedSlot, false)->assertRedirect();
    expect($packedSlot->refresh()->packed_at)->toBeNull()
        ->and($packedSlot->packed_by_user_id)->toBeNull();
});

test('internal consumption takes the quantity used off its location as a consumption movement with the internal use reason', function () {
    $warehouse = createWarehouse();
    $shelf     = StoreLocation::make()->action($warehouse, Location::factory()->definition());
    $orgStock  = createOrgStocks($this->organisation, array_slice(createStocks($this->group), 0, 1))[0];
    $slot      = StoreLocationOrgStock::make()->action($orgStock, $shelf, ['type' => LocationStockTypeEnum::STORING]);
    UpdateLocationOrgStock::make()->action($slot, ['quantity' => 10]);
    $slot->refresh();

    $consume = fn (array $data) => $this->patch(route('grp.models.location_org_stock.consume', $slot->id), $data);

    $consume(['quantity' => 11])->assertSessionHasErrors('quantity');
    $consume(['quantity' => 0])->assertSessionHasErrors('quantity');
    expect((float) $slot->refresh()->quantity)->toBe(10.0);

    $consume(['quantity' => 3, 'note' => 'packing boxes'])->assertRedirect()->assertSessionHasNoErrors();

    $movement = $orgStock->orgStockMovements()->latest('id')->first();
    expect((float) $slot->refresh()->quantity)->toBe(7.0)
        ->and($movement->type)->toBe(OrgStockMovementTypeEnum::CONSUMPTION)
        ->and($movement->flow)->toBe(OrgStockMovementFlowEnum::OUT)
        ->and($movement->reason)->toBe(OrgStockMovementReasonEnum::INTERNAL_USE)
        ->and((float) $movement->quantity)->toBe(-3.0)
        ->and($movement->note)->toBe('packing boxes')
        ->and($movement->user_id)->toBe($this->user->id)
        ->and($movement->location_id)->toBe($shelf->id);
});

test('stock json endpoints refuse users without the warehouse or organisation inventory rule', function () {
    $warehouse = createWarehouse();
    list(, , $shop) = createShop();
    $stock    = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE]));
    $orgStock = StoreOrgStock::make()->action($this->organisation, $stock);

    $orgData = \App\Models\SysAdmin\Organisation::factory()->definition();
    data_set($orgData, 'code', 'acm2');
    data_set($orgData, 'type', \App\Enums\SysAdmin\Organisation\OrganisationTypeEnum::SHOP);
    $otherOrganisation = \App\Models\SysAdmin\Organisation::where('code', 'acm2')->first()
        ?? \App\Actions\SysAdmin\Organisation\StoreOrganisation::make()->action($this->group, $orgData);
    $otherStock    = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE]));
    $otherOrgStock = StoreOrgStock::make()->action($otherOrganisation, $otherStock);

    $user = \App\Models\SysAdmin\User::factory()->create(['group_id' => $this->group->id, 'status' => true]);

    $lockRoute = route('grp.json.warehouse.low_stock_audit_lock', $warehouse);
    $lock      = ['org_stock_id' => $orgStock->id, 'is_locked' => true, 'holder' => 'permission-test-tab'];

    actingAsUserWithOnlyPermissions($user, ["stocks.$warehouse->id.view"]);
    $this->postJson($lockRoute, $lock)->assertForbidden();

    actingAsUserWithOnlyPermissions($user, ["stocks.$warehouse->id.edit"]);
    $this->postJson($lockRoute, $lock)->assertOk()->assertJsonPath('granted', true);
    $this->postJson($lockRoute, ['org_stock_id' => $otherOrgStock->id] + $lock)->assertUnprocessable()->assertJsonValidationErrors('org_stock_id');

    $managementRoute = route('grp.json.warehouse.org_stock.stocks_management', [$warehouse, $orgStock->id]);
    $orgStocksRoute  = route('grp.json.org_stocks.index', ['organisation' => $this->organisation->id]);

    actingAsUserWithOnlyPermissions($user, ["products.$shop->id.view"]);
    $this->getJson($managementRoute)->assertForbidden();
    $this->getJson($orgStocksRoute)->assertForbidden();

    actingAsUserWithOnlyPermissions($user, ["stocks.$warehouse->id.view"]);
    $this->getJson($managementRoute)->assertOk();

    actingAsUserWithOnlyPermissions($user, ["inventory.{$this->organisation->id}.view"]);
    $this->getJson($orgStocksRoute)->assertOk();
});

test('stock, sko and trade unit showcases list their supplier products with links to the supplier', function () {
    $group     = $this->organisation->group;
    $stock     = createStocks($group)[0];
    [$orgStock] = createOrgStocks($this->organisation, [$stock]);
    $tradeUnit = StoreTradeUnit::make()->action($group, TradeUnit::factory()->definition());

    $supplier = StoreSupplier::make()->action($group, Supplier::factory()->definition());
    OrgSupplier::where('supplier_id', $supplier->id)->where('organisation_id', $this->organisation->id)->exists()
        || StoreOrgSupplier::make()->action($this->organisation, $supplier);
    $supplierProduct = StoreSupplierProduct::make()->action($supplier, array_merge(SupplierProduct::factory()->definition(), ['trade_units' => [$tradeUnit->id]]));
    $stock->supplierProducts()->attach($supplierProduct->id);
    $orgSupplierProduct = $supplierProduct->orgSupplierProducts()->where('organisation_id', $this->organisation->id)->firstOrFail();
    $orgStock->orgSupplierProducts()->attach($orgSupplierProduct->id, [
        'local_priority'                => 1,
        'status'                        => true,
        'stock_has_supplier_product_id' => DB::table('stock_has_supplier_products')->where('stock_id', $stock->id)->where('supplier_product_id', $supplierProduct->id)->value('id'),
    ]);

    $stockRows     = \App\Actions\Goods\Stock\UI\GetStockShowcase::run($stock->fresh())['supplier_products'];
    $tradeUnitRows = \App\Actions\Goods\TradeUnit\UI\GetTradeUnitShowcase::run($tradeUnit->fresh())['supplier_products'];
    $orgStockRows  = \App\Actions\SupplyChain\SupplierProduct\UI\GetSupplierProductsShowcaseData::make()->forOrgSupplierProducts($orgStock->fresh()->orgSupplierProducts);

    foreach ([$stockRows, $tradeUnitRows, $orgStockRows] as $rows) {
        $row = collect($rows)->firstWhere('code', $supplierProduct->code);
        expect($row)->not->toBeNull()
            ->and($row['supplier_name'])->toBe($supplier->name)
            ->and(route($row['route']['name'], $row['route']['parameters']))->toBeString()
            ->and(route($row['supplier_route']['name'], $row['supplier_route']['parameters']))->toBeString();
    }

    expect($stockRows[0]['route']['name'])->toBe('grp.supply-chain.supplier_products.show')
        ->and($orgStockRows[0]['route']['parameters']['orgSupplierProduct'])->toBe($orgSupplierProduct->slug);
});

test('the sko list search and the search index find a sko by the code of its supplier product', function () {
    $warehouse = createWarehouse();
    [$stock, $other] = array_slice(createStocks($this->group), 0, 2);
    [$orgStock, $otherOrgStock] = createOrgStocks($this->organisation, [$stock, $other]);

    $supplier = StoreSupplier::make()->action($this->group, Supplier::factory()->definition());
    OrgSupplier::where('supplier_id', $supplier->id)->where('organisation_id', $this->organisation->id)->exists()
        || StoreOrgSupplier::make()->action($this->organisation, $supplier);
    $supplierProduct = StoreSupplierProduct::make()->action($supplier, array_merge(SupplierProduct::factory()->definition(), ['code' => 'STAMF-'.$orgStock->id]));
    $stock->supplierProducts()->attach($supplierProduct->id);
    $orgStock->orgSupplierProducts()->attach(
        $supplierProduct->orgSupplierProducts()->where('organisation_id', $this->organisation->id)->value('id'),
        [
            'local_priority'                => 1,
            'status'                        => true,
            'stock_has_supplier_product_id' => DB::table('stock_has_supplier_products')->where('stock_id', $stock->id)->where('supplier_product_id', $supplierProduct->id)->value('id'),
        ]
    );

    $codesShown = fn (string $search): array => collect(
        $this->withoutVite()->get(route('grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.index', [
            $this->organisation->slug,
            $warehouse->slug,
            'index_filter' => ['global' => $search],
        ]))->assertOk()->viewData('page')['props']['index']['data']
    )->pluck('code')->all();

    expect(explode(' ', $orgStock->fresh()->toSearchableArray()['supplier_codes']))->toContain('STAMF-'.$orgStock->id)
        ->and(explode(' ', $otherOrgStock->fresh()->toSearchableArray()['supplier_codes']))->not->toContain('STAMF-'.$orgStock->id)
        ->and($codesShown('stamf-'.$orgStock->id))->toBe([$orgStock->code])
        ->and($codesShown($otherOrgStock->code))->toBe([$otherOrgStock->code]);
});
