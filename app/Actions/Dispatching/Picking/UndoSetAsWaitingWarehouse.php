<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 Apr 2026 11:00:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Picking;

use App\Actions\Dispatching\DeliveryNote\Hydrators\DeliveryNoteHydrateWaitingItems;
use App\Actions\Dispatching\DeliveryNoteItem\CalculateDeliveryNoteItemTotalPicked;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;

class UndoSetAsWaitingWarehouse extends OrgAction
{
    use WithActionUpdate;

    private DeliveryNoteItem $deliveryNoteItem;
    protected ?User $user = null;

    public function handle(DeliveryNoteItem $deliveryNoteItem): DeliveryNoteItem
    {

        if($deliveryNoteItem->quantity_waiting_warehouse==0){
            return $deliveryNoteItem;
        }

        $dataToUpdate = [
            'state'                      => DeliveryNoteItemStateEnum::HANDLING,
            'quantity_waiting_warehouse' => 0,
            'has_waiting_warehouse'      => false,
            'is_handled'                 => false,
        ];

        $deliveryNoteItem->update(
            $dataToUpdate
        );
        DeliveryNoteHydrateWaitingItems::run($deliveryNoteItem->delivery_note_id);
        CalculateDeliveryNoteItemTotalPicked::make()->action($deliveryNoteItem);

        return $deliveryNoteItem;
    }




    public function asController(DeliveryNoteItem $deliveryNoteItem, ActionRequest $request): DeliveryNoteItem
    {
        $this->initialisationFromShop($deliveryNoteItem->shop, $request);

        return $this->handle($deliveryNoteItem);
    }




}
