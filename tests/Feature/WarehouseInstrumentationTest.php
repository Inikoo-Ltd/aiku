<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 06 Aug 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Dispatching\BackfillWarehouseTimestamps;
use App\Actions\Dispatching\DeliveryNote\UpdateState\StartPackingDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\UpdateState\StartHandlingDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\UpdateState\UpdateDeliveryNoteStatePacked;
use App\Actions\Dispatching\DeliveryNote\UpdateState\UpdateDeliveryNoteStateToInQueue;
use App\Actions\Dispatching\DeliveryNote\UpdateState\UpdateDeliveryNoteStateToPicked;
use App\Actions\Dispatching\DeliveryNoteItem\StoreDeliveryNoteItem;
use App\Actions\Dispatching\Packing\StorePacking;
use App\Actions\Dispatching\Picking\StorePicking;
use App\Actions\Inventory\Location\StoreLocation;
use App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock;
use App\Actions\Inventory\OrgStock\StoreOrgStock;
use App\Actions\Goods\Stock\StoreStock;
use App\Actions\Goods\Stock\UpdateStock;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\Order\UpdateState\SubmitOrder;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\Ordering\Order\UpdateState\SendOrderToWarehouse;
use App\Enums\Goods\Stock\StockStateEnum;
use App\Enums\Inventory\LocationStock\LocationStockTypeEnum;
use App\Models\Dispatching\Packing;
use App\Models\Dispatching\Picking;
use App\Models\Helpers\Address;
use App\Models\Inventory\Location;
use App\Models\Goods\Stock;
use App\Models\Ordering\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    list(
        $this->organisation,
        $this->user,
        $this->shop
    ) = createShop();

    $this->group      = $this->organisation->group;
    $this->adminGuest = createAdminGuest($this->group);
    $this->warehouse  = createWarehouse();

    list($this->tradeUnit, $this->product) = createProduct($this->shop);

    $this->customer = createCustomer($this->shop);

    app()->instance('group', $this->group);
    setPermissionsTeamId($this->group->id);
    actingAs($this->adminGuest->getUser());
});

/**
 * Takes one order all the way to a packed delivery note, which is the only state in which all four
 * lifecycle timestamps exist.
 */
function packedDeliveryNote($ctx, bool $markWholeNotePacked = false): array
{
    $order = StoreOrder::make()->action($ctx->customer, [
        'reference'        => 'O'.Str::random(8),
        'date'             => date('Y-m-d'),
        'customer_id'      => $ctx->customer->id,
        'delivery_address' => new Address(Address::factory()->definition()),
        'billing_address'  => new Address(Address::factory()->definition()),
    ]);
    StoreTransaction::make()->action($order, $ctx->product->historicAsset, Transaction::factory()->definition());
    $order = SubmitOrder::make()->action($order);

    $deliveryNote = SendOrderToWarehouse::make()->action($order, ['warehouse_id' => $ctx->warehouse->id]);

    $stock    = StoreStock::make()->action($ctx->group, Stock::factory()->definition());
    $stock    = UpdateStock::make()->action($stock, ['state' => StockStateEnum::ACTIVE]);
    $orgStock = StoreOrgStock::make()->action($ctx->organisation, $stock);

    $deliveryNoteItem = StoreDeliveryNoteItem::make()->action($deliveryNote, [
        'delivery_note_id'  => $deliveryNote->id,
        'org_stock_id'      => $orgStock->id,
        'transaction_id'    => $order->transactions()->first()->id,
        'quantity_required' => 10,
    ]);

    $deliveryNote = UpdateDeliveryNoteStateToInQueue::make()->action($deliveryNote, $ctx->user);
    $deliveryNote = StartHandlingDeliveryNote::make()->action($deliveryNote, $ctx->user);

    $location         = StoreLocation::make()->action($ctx->warehouse, Location::factory()->definition());
    $locationOrgStock = StoreLocationOrgStock::make()->action(
        orgStock: $deliveryNoteItem->orgStock,
        location: $location,
        modelData: ['quantity' => 100, 'type' => LocationStockTypeEnum::PICKING, 'fetched_at' => now()],
        strict: false
    );

    StorePicking::make()->action($deliveryNoteItem, $ctx->user, [
        'picker_user_id'        => $ctx->user->id,
        'location_org_stock_id' => $locationOrgStock->id,
        'quantity'              => 10,
    ]);

    $deliveryNote = UpdateDeliveryNoteStateToPicked::run($deliveryNote->refresh());
    $deliveryNote = StartPackingDeliveryNote::make()->action($deliveryNote, $ctx->user);

    if ($markWholeNotePacked) {
        UpdateDeliveryNoteStatePacked::make()->action($deliveryNote, $ctx->user);
    } else {
        StorePacking::make()->action($deliveryNoteItem->refresh(), $ctx->user, []);
    }

    return [$deliveryNote->refresh(), $deliveryNoteItem->refresh()];
}

function clearInstrumentation(int $deliveryNoteId): void
{
    DB::table('pickings')->where('delivery_note_id', $deliveryNoteId)->update(['last_picked_at' => null]);
    DB::table('packings')->where('delivery_note_id', $deliveryNoteId)
        ->update(['queued_at' => null, 'packing_at' => null, 'done_at' => null]);
}

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

test('lines swept up by marking a whole note packed are flagged so rates can exclude them', function () {
    [$deliveryNote] = packedDeliveryNote($this, markWholeNotePacked: true);

    $packing = Packing::where('delivery_note_id', $deliveryNote->id)->first();

    expect($packing->data['auto_packed'] ?? null)->toBeTrue()
        ->and($packing->done_at)->not->toBeNull();
});
