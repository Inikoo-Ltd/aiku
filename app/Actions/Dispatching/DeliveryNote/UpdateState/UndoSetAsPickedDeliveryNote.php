<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 20 May 2026 16:54:16 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote\UpdateState;

use App\Actions\Catalogue\Shop\Hydrators\HasDeliveryNoteHydrators;
use App\Actions\Dispatching\DeliveryNoteItem\CalculateDeliveryNoteItemTotalPicked;
use App\Actions\Dispatching\PickingSession\AutoFinishPackingPickingSession;
use App\Actions\Ordering\Order\UpdateState\UpdateOrderStateToHandling;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\SysAdmin\User;
use Illuminate\Console\Command;
use Lorisleiva\Actions\ActionRequest;

class UndoSetAsPickedDeliveryNote extends OrgAction
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
        data_set($modelData, 'state', DeliveryNoteStateEnum::HANDLING->value);


        foreach ($deliveryNote->deliveryNoteItems as $item) {

            CalculateDeliveryNoteItemTotalPicked::run($item);
        }

        if ($deliveryNote->type != DeliveryNoteTypeEnum::REPLACEMENT) {
            $order = $deliveryNote->orders->first();

            UpdateOrderStateToHandling::make()->action($order);
        }

        $deliveryNote = $this->update($deliveryNote, $modelData);

        if ($deliveryNote->pickingSessions) {
            foreach ($deliveryNote->pickingSessions as $pickingSession) {
                AutoFinishPackingPickingSession::run($pickingSession);
            }
        }

        $this->deliveryNoteHandlingHydrators($deliveryNote, $oldState);
        $this->deliveryNoteHandlingHydrators($deliveryNote, DeliveryNoteStateEnum::PACKING);

        return $deliveryNote;
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
