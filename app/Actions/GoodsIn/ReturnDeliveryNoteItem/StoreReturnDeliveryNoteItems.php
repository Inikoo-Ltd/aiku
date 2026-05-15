<?php

/*
 * author Louis Perez
 * created on 05-05-2026-13h-36m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\GoodsIn\ReturnDeliveryNoteItem;

use App\Actions\OrgAction;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\GoodsIn\ReturnDeliveryNote;
use App\Models\GoodsIn\ReturnDeliveryNoteItem;
use Illuminate\Validation\Rule;

class StoreReturnDeliveryNoteItems extends OrgAction
{
    public function handle(ReturnDeliveryNote $returnDeliveryNote, array $modelData): ReturnDeliveryNoteItem
    {
        $deliveryNoteItem = DeliveryNoteItem::find(data_get($modelData, 'delivery_note_items_id'));

        data_set($modelData, 'group_id', $returnDeliveryNote->group_id, false);
        data_set($modelData, 'organisation_id', $returnDeliveryNote->organisation_id, false);
        data_set($modelData, 'shop_id', $returnDeliveryNote->shop_id, false);
        data_set($modelData, 'return_delivery_note_id', $returnDeliveryNote->id, false);
        data_set($modelData, 'delivery_note_items_id', $deliveryNoteItem->id, false);
        data_set($modelData, 'stock_family_id', $deliveryNoteItem->stock_family_id, false);
        data_set($modelData, 'stock_id', $deliveryNoteItem->stock_id, false);
        data_set($modelData, 'org_stock_family_id', $deliveryNoteItem->org_stock_family_id, false);
        data_set($modelData, 'org_stock_id', $deliveryNoteItem->org_stock_id, false);
        data_set($modelData, 'total_expected_qty', $deliveryNoteItem->quantity_dispatched, 0);

        return $returnDeliveryNote->returnDeliveryNoteItem()->create($modelData);
    }

    public function rules(): array
    {
        return [
            'delivery_note_items_id'    => [
                'required',
                Rule::exists('delivery_note_items', 'id')
            ],
        ];
    }

    public function action(ReturnDeliveryNote $returnDeliveryNote, array $modelData, int $hydratorsDelay = 0, $strict = true): ReturnDeliveryNoteItem
    {
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($returnDeliveryNote->shop, $modelData);

        return $this->handle($returnDeliveryNote, $this->validatedData);
    }
}
