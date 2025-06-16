<?php

/*
 * author Arya Permana - Kirin
 * created on 23-05-2025-14h-25m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dispatching\Picking;

use App\Actions\Dispatching\DeliveryNoteItem\CalculateDeliveryNoteItemTotalPicked;
use App\Actions\OrgAction;
use App\Models\Dispatching\Picking;
use Lorisleiva\Actions\ActionRequest;

class DeletePicking extends OrgAction
{
    public function handle(Picking $picking): bool
    {
        $deliveryNoteItem = $picking->deliveryNoteItem;
        $picking->delete();

        $deliveryNoteItem->refresh();

        CalculateDeliveryNoteItemTotalPicked::make()->action($deliveryNoteItem);

        return true;
    }

    public function asController(Picking $picking, ActionRequest $request)
    {
        $this->initialisationFromShop($picking->shop, $request);

        $this->handle($picking);
    }

    public function action(Picking $picking): bool
    {
        $this->initialisationFromShop($picking->shop, []);

        return $this->handle($picking);
    }
}
