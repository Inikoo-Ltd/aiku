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
use App\Models\GoodsIn\ReturnDeliveryNoteItem;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class UpsertReturnDeliveryNoteItemReturned extends OrgAction
{
    use AsAction;
    use WithAttributes;

    public function handle(ReturnDeliveryNoteItem $returnDeliveryNoteItem, array $modelData): ReturnDeliveryNoteItem
    {
        $user = auth()->user();

        data_set($modelData, 'sower_user_id', $user->id);

        StoreSowing::make()->action($returnDeliveryNoteItem, $user, $modelData);
        CalculateReturnDeliveryNoteItemTotalSowed::make()->action($returnDeliveryNoteItem);

        return $returnDeliveryNoteItem;
    }

    public function afterValidator(Validator $validator, ActionRequest $request)
    {
        $returnDeliveryNoteItem = $request->returnDeliveryNoteItem;

        $maxQty = $returnDeliveryNoteItem->total_expected_qty - (
            $returnDeliveryNoteItem->total_item_damaged + 
            $returnDeliveryNoteItem->total_item_not_returned + 
            $returnDeliveryNoteItem->total_item_returned
        );

        if ($request->input('quantity') > $maxQty) {
            throw ValidationException::withMessages(
                [
                    'message' => [
                        'quantity' => 'Invalid quantity were given (Exceed maximum possible amount)',
                    ]
                ]
            );
        }

    }

    public function rules(): array
    {
        return [
            'quantity'              => ['required', 'numeric', 'min:0'],
            'location_org_stock_id' => ['sometimes', Rule::Exists('location_org_stocks', 'id')->where('warehouse_id', $this->warehouse->id)]
        ];
    }

    public function asController(ReturnDeliveryNoteItem $returnDeliveryNoteItem, ActionRequest $request): void
    {
        $this->initialisationFromWarehouse($returnDeliveryNoteItem->returnDeliveryNote->warehouse, $request);

        $this->handle($returnDeliveryNoteItem, $this->validatedData);
    }
}
