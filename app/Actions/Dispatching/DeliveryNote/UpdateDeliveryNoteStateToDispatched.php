<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Feb 2023 16:47:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\Dispatching\DeliveryNoteItem\UpdateDeliveryNoteItem;
use App\Actions\Ordering\Order\UpdateStateToDispatchedOrder;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Models\Dispatching\DeliveryNote;
use Lorisleiva\Actions\ActionRequest;

class UpdateDeliveryNoteStateToDispatched extends OrgAction
{
    use WithActionUpdate;

    public function handle(DeliveryNote $deliveryNote): DeliveryNote
    {
        data_set($modelData, 'dispatched_at', now());
        data_set($modelData, 'state', DeliveryNoteStateEnum::DISPATCHED->value);
        
        foreach($deliveryNote->deliveryNoteItems as $item) {
            $this->update($item, [
                'state' => DeliveryNoteItemStateEnum::DISPATCHED,
                'dispatched_at' => now(),
                'quantity_dispatched' => $item->quantity_packed
            ]);
        }
        
        $deliveryNote = $this->update($deliveryNote, $modelData);
    
        $deliveryNote->refresh();
        UpdateStateToDispatchedOrder::make()->action($deliveryNote->order);

        return $deliveryNote;
    }

    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): DeliveryNote
    {
        $this->initialisationFromShop($deliveryNote->shop, $request);

        return $this->handle($deliveryNote);
    }

    public function action(DeliveryNote $deliveryNote): DeliveryNote
    {
        $this->initialisationFromShop($deliveryNote->shop, []);

        return $this->handle($deliveryNote);
    }
}
