<?php

namespace App\Actions\Dispatching\DeliveryNote\Return;

use App\Actions\OrgAction;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\ReturnDeliveryNote;

class StoreReturnDeliveryNote extends OrgAction
{
    public function handle(DeliveryNote $deliveryNote, array $modelData)
    {
        data_set($modelData, 'group_id', $deliveryNote->group_id, false);
        data_set($modelData, 'organisation_id', $deliveryNote->organisation_id, false);
        data_set($modelData, 'warehouse_id', $deliveryNote->warehouse_id, false);
        data_set($modelData, 'shop_id', $deliveryNote->shop_id, false);
        data_set($modelData, 'customer_id', $deliveryNote->customer_id, false);
        data_set($modelData, 'delivery_note_id', $deliveryNote->id, false);
        data_set($modelData, 'order_id', $deliveryNote->orders()->first()->id);
        data_set($modelData, 'reference', $deliveryNote->reference);

        return $deliveryNote->returnedDeliveryNote()->create($modelData);
    }

    public function rules(): array
    {
        return [

        ];
    }

    public function action(DeliveryNote $deliveryNote, array $modelData, int $hydratorsDelay = 0, bool $strict = true, $audit = true): ReturnDeliveryNote
    {
        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($deliveryNote->shop, $modelData);

        return $this->handle($deliveryNote, $this->validatedData);
    }
}
