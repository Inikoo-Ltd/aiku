<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 07 July 2025 17:48:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote\UpdateState;

use App\Actions\Catalogue\Shop\Hydrators\HasDeliveryNoteHydrators;
use App\Actions\Dispatching\DeliveryNote\Hydrators\DeliveryNoteHydratePickedBays;
use App\Actions\Dispatching\DeliveryNote\Hydrators\DeliveryNoteHydrateTrolleys;
use App\Actions\Dispatching\DeliveryNoteItem\UpdateDeliveryNoteItem;
use App\Actions\Dispatching\Packing\DeletePacking;
use App\Actions\Dispatching\PickedBay\Hydrators\PickedBayHydrateNumberDeliveryNotes;
use App\Actions\Dispatching\Picking\DeletePicking;
use App\Actions\Dispatching\Picking\StoreNotPickPicking;
use App\Actions\GoodsIn\ReturnDeliveryNote\ProcessReturnDeliveryNote;
use App\Actions\GoodsIn\StockDelivery\DeleteStockDelivery;
use App\Actions\Ordering\Order\UpdateState\RollbackOrderAfterDeliveryNoteCancellation;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemCancelStateEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Enums\Dispatching\Picking\PickingNotPickedReasonEnum;
use App\Enums\Dispatching\Picking\PickingTypeEnum;
use App\Enums\GoodsIn\ReturnDeliveryNote\ReturnDeliveryNoteTypeEnum;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Inventory\LocationOrgStock;
use App\Models\SysAdmin\User;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class CancelDeliveryNote extends OrgAction
{
    use WithActionUpdate;
    use HasDeliveryNoteHydrators;


    /**
     * HELP-2693. $createReturn false is the LEGACY auto-return path and must keep working as-is.
     *
     * It credits every picked line straight back to its location through DeletePicking, at
     * cancellation time, while the goods are still physically on the trolley. That is the
     * behaviour Raul flagged as wrong, but it is NOT dead code and must not be deleted:
     *
     *   - asCommand (delivery_note:cancel) cancels without a return.
     *   - action() is called by maintenance and repair code, including with $repair = true,
     *     which skips the picking loop entirely.
     *   - asController defaults to false, so any caller that does not send create_return
     *     keeps today's behaviour.
     *
     * Only the popup sends create_return = true. Before removing the legacy branch, every
     * caller above has to be migrated and back-filled, otherwise cancelled stock silently
     * stops returning to its location at all.
     *
     * @throws \Throwable
     */
    public function handle(DeliveryNote $deliveryNote, ?User $user, $modifyOrder = true, bool $repair = false, ?string $cancelledAt = null, bool $createReturn = false): DeliveryNote
    {
        $oldState = $deliveryNote->state;

        if (in_array($oldState, [DeliveryNoteStateEnum::DISPATCHED, DeliveryNoteStateEnum::CANCELLED])) {
            throw ValidationException::withMessages([
                'message' => __('Delivery note can not be cancelled.').' ['.__('Invalid state').': '.$oldState->value.']',
            ]);
        }

        $cancelledRef = $deliveryNote->reference.'-CANCELLED';

        $cancelledCount = DB::table('delivery_notes')
            ->where('reference', 'like', $deliveryNote->reference.'-CANCELLED%')
            ->count();

        $newCancelledRef = $cancelledRef.($cancelledCount > 0 ? '-'.($cancelledCount + 1) : '');

        data_set($modelData, 'reference', $newCancelledRef);
        data_set($modelData, 'cancelled_at', $cancelledAt ?? now());
        data_set($modelData, 'state', DeliveryNoteStateEnum::CANCELLED);

        $deliveryNote = DB::transaction(function () use ($deliveryNote, $modelData, $modifyOrder, $user, $repair, $createReturn) {
            $deliveryNote = $this->update($deliveryNote, $modelData);

            if ($repair) {
                foreach ($deliveryNote->deliveryNoteItems as $item) {
                    UpdateDeliveryNoteItem::make()->action($item, [
                        'state'        => DeliveryNoteItemStateEnum::CANCELLED,
                        'cancel_state' => DeliveryNoteItemCancelStateEnum::RETURNED,
                    ]);
                }

                return $deliveryNote;
            }

            foreach ($deliveryNote->packings as $packing) {
                DeletePacking::make()->action($packing);
            }

            foreach ($deliveryNote->pickings as $picking) {
                $deliveryNoteItem = $picking->deliveryNoteItem;

                $toPick = $deliveryNoteItem->quantity_required - $deliveryNoteItem->picked_quantity;

                /**
                 * With a return the picked stock stays off the shelf in the ledger. Deleting the
                 * picking here is what credited it back the instant the note was cancelled, while
                 * the goods were still on the trolley; the return's put-away does that credit
                 * instead, when someone has actually walked them back.
                 */
                if (!$createReturn) {
                    $locationPickingStock = LocationOrgStock::where('location_id', $picking->location_id)
                        ->where('org_stock_id', $picking->org_stock_id)->first();
                    if (!$locationPickingStock) {
                        $locationPickingStock = LocationOrgStock::where('org_stock_id', $picking->org_stock_id)->first();
                    }

                    if ($locationPickingStock && $picking->type == PickingTypeEnum::PICK && $picking->quantity > 0) {
                        DeletePicking::make()->action($picking, $user);
                    }
                }

                if ($toPick > 0) {
                    StoreNotPickPicking::make()->action(
                        $deliveryNoteItem,
                        $user,
                        [
                            'not_picked_reason' => PickingNotPickedReasonEnum::CANCELLED_BY_CUSTOMER,
                            'not_picked_note'   => "Delivery Note $deliveryNote->reference cancelled.",
                            'quantity'          => $toPick,
                        ],
                    );
                }
            }


            foreach ($deliveryNote->deliveryNoteItems as $item) {
                UpdateDeliveryNoteItem::make()->action($item, [
                    'state'        => DeliveryNoteItemStateEnum::CANCELLED,
                    'cancel_state' => DeliveryNoteItemCancelStateEnum::RETURNED,
                ]);
            }


            if ($deliveryNote->type == DeliveryNoteTypeEnum::ORDER && $modifyOrder) {
                $order = $deliveryNote->orders->first();
                if (!in_array($order->state, [
                    OrderStateEnum::CANCELLED,
                    OrderStateEnum::FINALISED,
                    OrderStateEnum::DISPATCHED
                ])) {
                    RollbackOrderAfterDeliveryNoteCancellation::make()->action($order);
                }
            }

            foreach ($deliveryNote->trolleys as $trolley) {
                DB::table('delivery_note_has_trolleys')
                    ->where('delivery_note_id', $deliveryNote->id)->where('trolley_id', $trolley->id)->delete();
                $trolley->update(['current_delivery_note_id' => null]);
            }

            foreach ($deliveryNote->pickedBays as $pickedBay) {
                DB::table('picked_bay_has_delivery_notes')
                    ->where('delivery_note_id', $deliveryNote->id)->where('picked_bay_id', $pickedBay->id)->delete();
                PickedBayHydrateNumberDeliveryNotes::run($pickedBay->id);
            }

            /**
             * Nothing picked means nothing to walk back, and the return would refuse to be created
             * and take the whole cancellation down with it. A note cancelled before anyone touched
             * it is cancelled plainly.
             */
            $hasPickedItems = $deliveryNote->deliveryNoteItems()
                ->where('quantity_picked', '>', 0)
                ->exists();

            if ($createReturn && $hasPickedItems) {
                ProcessReturnDeliveryNote::make()->action(
                    $deliveryNote->refresh(),
                    [],
                    ReturnDeliveryNoteTypeEnum::CANCELLATION
                );
            }

            return $deliveryNote;
        });

        $this->deliveryNoteHandlingHydrators($deliveryNote, $oldState);
        $this->deliveryNoteHandlingHydrators($deliveryNote, DeliveryNoteStateEnum::CANCELLED);

        $partnerStockDelivery = StockDelivery::where('delivery_note_id', $deliveryNote->id)
            ->where('state', StockDeliveryStateEnum::CONFIRMED)
            ->first();
        if ($partnerStockDelivery) {
            DeleteStockDelivery::make()->action($partnerStockDelivery);
        }

        DeliveryNoteHydrateTrolleys::dispatch($deliveryNote->id);
        DeliveryNoteHydratePickedBays::dispatch($deliveryNote->id);


        return $deliveryNote;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $deliveryNote = $request->route('deliveryNote');

        if (!$deliveryNote instanceof DeliveryNote) {
            return true;
        }

        return $request->user()->authTo([
            "supervisor-dispatching.$deliveryNote->warehouse_id",
            "org-admin.$deliveryNote->organisation_id",
            "orders.$deliveryNote->shop_id.edit",
            "crm.$deliveryNote->shop_id.edit",
        ]);
    }

    /**
     * @throws \Throwable
     */
    public function rules(): array
    {
        return [
            'create_return' => ['sometimes', 'boolean'],
        ];
    }

    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): DeliveryNote
    {
        $this->initialisationFromShop($deliveryNote->shop, $request);

        $createReturn = (bool)Arr::get($this->validatedData, 'create_return', false);

        return $this->handle($deliveryNote, $request->user(), true, false, null, $createReturn);
    }

    /**
     * @throws \Throwable
     */
    public function action(DeliveryNote $deliveryNote, ?User $user, $modifyOrder = true, bool $repair = false, ?string $cancelledAt = null, bool $createReturn = false): DeliveryNote
    {
        $this->asAction = true;
        $this->initialisationFromShop($deliveryNote->shop, []);

        return $this->handle($deliveryNote, $user, $modifyOrder, $repair, $cancelledAt, $createReturn);
    }

    public function getCommandSignature(): string
    {
        return 'delivery_note:cancel {delivery_note : ID, slug or reference of the delivery note} {--modifyOrder : Rollback the order}';
    }

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        $identifier = (string)$command->argument('delivery_note');

        $deliveryNote = null;

        if (is_numeric($identifier)) {
            $deliveryNote = DeliveryNote::query()->find($identifier);
        }

        if (!$deliveryNote) {
            $deliveryNote = DeliveryNote::query()->where('slug', $identifier)->first();
        }

        if (!$deliveryNote) {
            $deliveryNote = DeliveryNote::query()->where('reference', $identifier)->first();
        }


        if (!$deliveryNote) {
            $command->error("Delivery note '$identifier' not found (searched by id, slug, reference).");

            return 1;
        }

        $modifyOrder = (bool)$command->option('modifyOrder');

        $this->asAction = true;
        $this->initialisationFromShop($deliveryNote->shop, []);
        $this->handle($deliveryNote, null, $modifyOrder);

        $command->info("Cancelled delivery note $deliveryNote->reference");

        return 0;
    }
}
