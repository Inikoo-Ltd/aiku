<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 20 May 2026 16:54:16 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote\UpdateState;

use App\Actions\Catalogue\Shop\Hydrators\HasDeliveryNoteHydrators;
use App\Actions\Dispatching\DeliveryNoteItem\CalculateDeliveryNoteItemTotalPicked;
use App\Actions\Dispatching\Packing\DeletePacking;
use App\Actions\Dispatching\PickingSession\AutoFinishPackingPickingSession;
use App\Actions\Ordering\Order\UpdateState\UpdateOrderStateToHandlingBlocked;
use App\Actions\Ordering\Order\UpdateState\UpdateOrderStateToPicked;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\SysAdmin\User;
use Illuminate\Console\Command;
use Lorisleiva\Actions\ActionRequest;

class UndoPackingDeliveryNote extends OrgAction
{
    use WithActionUpdate;
    use HasDeliveryNoteHydrators;

    private DeliveryNote $deliveryNote;
    protected User $user;

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(DeliveryNote $deliveryNote): DeliveryNote
    {
        $oldState = $deliveryNote->state;

        foreach ($deliveryNote->deliveryNoteItems as $item) {
            $item->update([
                'state'         => $this->isWaiting($item)
                    ? DeliveryNoteItemStateEnum::HANDLING_BLOCKED->value
                    : DeliveryNoteItemStateEnum::PICKED->value,
            ]);

            foreach ($item->packings as $packing) {
                DeletePacking::run($packing);
            }

            CalculateDeliveryNoteItemTotalPicked::run($item);
        }

        $hasWaiting = $deliveryNote->deliveryNoteItems->contains(fn ($item) => $this->isWaiting($item));

        if ($hasWaiting) {
            data_set($modelData, 'state', DeliveryNoteStateEnum::HANDLING_BLOCKED->value);
            data_set($modelData, 'handling_blocked_at', now());
        } else {
            data_set($modelData, 'state', DeliveryNoteStateEnum::PICKED->value);
        }

        if ($deliveryNote->type != DeliveryNoteTypeEnum::REPLACEMENT) {
            $order = $deliveryNote->orders->first();

            if ($hasWaiting) {
                UpdateOrderStateToHandlingBlocked::make()->action($order, $deliveryNote);
            } else {
                UpdateOrderStateToPicked::make()->action($order, $deliveryNote);
            }
        }

        $deliveryNote = $this->update($deliveryNote, $modelData);

        if ($deliveryNote->pickingSessions) {
            foreach ($deliveryNote->pickingSessions as $pickingSession) {
                AutoFinishPackingPickingSession::run($pickingSession);
            }
        }

        $this->deliveryNoteHandlingHydrators($deliveryNote, $oldState);
        $this->deliveryNoteHandlingHydrators($deliveryNote, $deliveryNote->state);

        return $deliveryNote;
    }

    private function isWaiting(DeliveryNoteItem $deliveryNoteItem): bool
    {
        return $deliveryNoteItem->has_waiting_warehouse || $deliveryNoteItem->has_waiting_crm;
    }


    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): DeliveryNote
    {
        $this->user         = $request->user();
        $this->deliveryNote = $deliveryNote;
        $this->initialisationFromShop($deliveryNote->shop, $request);

        return $this->handle($deliveryNote);
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function action(DeliveryNote $deliveryNote, User $user): DeliveryNote
    {
        $this->user         = $user;
        $this->deliveryNote = $deliveryNote;
        $this->initialisationFromShop($deliveryNote->shop, []);

        return $this->handle($deliveryNote);
    }

    public string $commandSignature = 'delivery-note:undo-packing {deliveryNote}';

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function asCommand(Command $command): void
    {
        $deliveryNote = DeliveryNote::where('slug', $command->argument('deliveryNote'))->first();

        $this->handle($deliveryNote);
    }
}
