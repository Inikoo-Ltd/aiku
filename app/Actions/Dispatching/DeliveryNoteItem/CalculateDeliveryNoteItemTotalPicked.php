<?php
/*
 * author Arya Permana - Kirin
 * created on 23-05-2025-14h-59m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dispatching\DeliveryNoteItem;

use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Enums\Dispatching\Picking\PickingTypeEnum;
use App\Models\Dispatching\DeliveryNoteItem;
use Illuminate\Validation\Rules\Enum;
use Lorisleiva\Actions\ActionRequest;

class CalculateDeliveryNoteItemTotalPicked extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithDeliveryNoteItemNoStrictRules;

    public function handle(DeliveryNoteItem $deliveryNoteItem): DeliveryNoteItem
    {
        $totalPicked = $deliveryNoteItem->pickings()->where('type', PickingTypeEnum::PICK)->sum('quantity');
        $totalNotPicked = $deliveryNoteItem->pickings()->where('type', PickingTypeEnum::NOT_PICK)->sum('quantity');
        return $this->update($deliveryNoteItem, ['quantity_picked' => $totalPicked, 'quantity_not_picked' => $totalNotPicked]);
    }

    public function action(DeliveryNoteItem $deliveryNoteItem): DeliveryNoteItem
    {
        $this->initialisationFromShop($deliveryNoteItem->shop, []);

        return $this->handle($deliveryNoteItem);
    }
}