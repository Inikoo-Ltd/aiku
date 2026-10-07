<?php

/*
 * author Louis Perez
 * created on 15-05-2026-11h-13m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\GoodsIn\ReturnDeliveryNote;

use App\Actions\OrgAction;
use App\Enums\GoodsIn\ReturnDeliveryNote\ReturnDeliveryNoteTypeEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\GoodsIn\ReturnDeliveryNote;

class StoreReturnDeliveryNote extends OrgAction
{
    private ReturnDeliveryNoteTypeEnum $type = ReturnDeliveryNoteTypeEnum::RETURN;

    public function handle(DeliveryNote $deliveryNote, array $modelData)
    {
        data_set($modelData, 'group_id', $deliveryNote->group_id, false);
        data_set($modelData, 'organisation_id', $deliveryNote->organisation_id, false);
        data_set($modelData, 'warehouse_id', $deliveryNote->warehouse_id, false);
        data_set($modelData, 'shop_id', $deliveryNote->shop_id, false);
        data_set($modelData, 'customer_id', $deliveryNote->customer_id, false);
        data_set($modelData, 'delivery_note_id', $deliveryNote->id, false);
        data_set($modelData, 'order_id', $deliveryNote->orders()->first()->id);
        data_set($modelData, 'type', $this->type);

        // The counter spans both types so a note that was cancelled and later returned cannot collide.
        $previousReturns = $deliveryNote->returnedDeliveryNote()->withTrashed()->count();
        $counter         = $previousReturns ? $previousReturns + 1 : '';

        if ($this->type === ReturnDeliveryNoteTypeEnum::CANCELLATION) {
            /**
             * The note is renamed to <reference>-CANCELLED before the return is raised, and
             * carrying that through reads as GB585339-CANCELLED-can.
             */
            $baseReference = preg_replace('/-CANCELLED(-\d+)?$/', '', $deliveryNote->reference);
            $reference     = $baseReference.'-cancel-pick'.$counter;
        } else {
            $reference = $deliveryNote->reference.'-ret'.$counter;
        }

        data_set($modelData, 'reference', $reference);

        return $deliveryNote->returnedDeliveryNote()->create($modelData);
    }

    public function rules(): array
    {
        return [

        ];
    }

    public function action(DeliveryNote $deliveryNote, array $modelData, int $hydratorsDelay = 0, bool $strict = true, $audit = true, ReturnDeliveryNoteTypeEnum $type = ReturnDeliveryNoteTypeEnum::RETURN): ReturnDeliveryNote
    {
        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->type           = $type;
        $this->initialisationFromShop($deliveryNote->shop, $modelData);

        return $this->handle($deliveryNote, $this->validatedData);
    }
}
