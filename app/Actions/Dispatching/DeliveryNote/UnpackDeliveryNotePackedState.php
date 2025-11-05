<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Feb 2023 16:47:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\Catalogue\Shop\Hydrators\HasDeliveryNoteHydrators;
use App\Actions\Dispatching\DeliveryNoteItem\CalculateDeliveryNoteItemTotalPicked;
use App\Actions\Dispatching\DeliveryNoteItem\UpdateDeliveryNoteItem;
use App\Actions\Dispatching\Picking\DeletePicking;
use App\Actions\Dispatching\PickingSession\AutoFinishPackingPickingSession;
use App\Actions\Ordering\Order\UpdateState\UpdateOrderStateToHandling;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\SysAdmin\User;
use Illuminate\Console\Command;
use Lorisleiva\Actions\ActionRequest;

class UnpackDeliveryNotePackedState extends OrgAction
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
        data_set($modelData, 'packed_at', null);
        data_set($modelData, 'packer_user_id', null);
        data_set($modelData, 'state', DeliveryNoteStateEnum::HANDLING->value);

        data_set($modelData, 'parcels', []);

        foreach ($deliveryNote->deliveryNoteItems as $item) {
            UpdateDeliveryNoteItem::make()->action($item, [
                'state' => DeliveryNoteItemStateEnum::HANDLING->value
            ]);

            foreach ($item->pickings as $picking) {
                DeletePicking::run($picking);
            }

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
        $this->deliveryNoteHandlingHydrators($deliveryNote, DeliveryNoteStateEnum::HANDLING);

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

    public string $commandSignature = 'delivery-note:unpack {deliveryNote}';

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function asCommand(Command $command): void
    {
        $deliveryNote = DeliveryNote::where('slug', $command->argument('deliveryNote'))->first();

        $this->handle($deliveryNote);
    }
}
