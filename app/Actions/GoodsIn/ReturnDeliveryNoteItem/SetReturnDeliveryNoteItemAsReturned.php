<?php

/*
 * author Louis Perez
 * created on 06-05-2026-00h-00m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\GoodsIn\ReturnDeliveryNoteItem;

use App\Actions\GoodsIn\Sowing\StoreSowing;
use App\Actions\OrgAction;
use App\Enums\GoodsIn\ReturnDeliveryNoteItem\ReturnDeliveryNoteItemStateEnum;
use App\Models\GoodsIn\ReturnDeliveryNoteItem;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class SetReturnDeliveryNoteItemAsReturned extends OrgAction
{
    use AsAction;
    use WithAttributes;

    public function handle(ReturnDeliveryNoteItem $returnDeliveryNoteItem, array $modelData): ReturnDeliveryNoteItem
    {
        $user = auth()->user();
        
        $totalItemReturned = $returnDeliveryNoteItem->total_expected_qty - (
            $returnDeliveryNoteItem->total_item_damaged + 
            $returnDeliveryNoteItem->total_item_not_returned + 
            $returnDeliveryNoteItem->total_item_returned
        );

        data_set($modelData, 'quantity', $totalItemReturned);
        data_set($modelData, 'sower_user_id', $user->id);

        StoreSowing::make()->action($returnDeliveryNoteItem, $user, $modelData);
        CalculateReturnDeliveryNoteItemTotalSowed::make()->action($returnDeliveryNoteItem);

        return $returnDeliveryNoteItem;
    }

    public function rules(): array
    {
        return [
            'location_org_stock_id' => ['required', Rule::Exists('location_org_stocks', 'id')->where('warehouse_id', $this->warehouse->id)]
        ];
    }

    public function asController(ReturnDeliveryNoteItem $returnDeliveryNoteItem, ActionRequest $request): void
    {
        $this->initialisationFromWarehouse($returnDeliveryNoteItem->returnDeliveryNote->warehouse, $request);

        $this->handle($returnDeliveryNoteItem, $this->validatedData);
    }
}
