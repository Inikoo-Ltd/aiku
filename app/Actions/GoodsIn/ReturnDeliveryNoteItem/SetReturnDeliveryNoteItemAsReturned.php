<?php

/*
 * author Louis Perez
 * created on 06-05-2026-00h-00m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\GoodsIn\ReturnDeliveryNoteItem;

use App\Actions\Traits\Authorisations\Inventory\WithReturnsAuthorisation;
use App\Actions\OrgAction;
use App\Models\GoodsIn\ReturnDeliveryNoteItem;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class SetReturnDeliveryNoteItemAsReturned extends OrgAction
{
    use WithReturnsAuthorisation;
    use WithReturnItemPuts;
    use WithReturnedItemLocation;
    use AsAction;
    use WithAttributes;

    public function handle(ReturnDeliveryNoteItem $returnDeliveryNoteItem, array $modelData): ReturnDeliveryNoteItem
    {
        $this->ensureReturnIsBeingReturned($returnDeliveryNoteItem);
        $this->ensureLocationHoldsItem($returnDeliveryNoteItem, $modelData['location_org_stock_id'] ?? null);

        return $this->storeLockedPut($returnDeliveryNoteItem, $modelData, null);
    }

    public function rules(): array
    {
        return [
            'location_org_stock_id' => ['sometimes', Rule::Exists('location_org_stocks', 'id')->where('warehouse_id', $this->warehouse->id)]
        ];
    }

    public function afterValidator(Validator $validator, ActionRequest $request): void
    {
        if (!$request->input('location_org_stock_id')) {
            $validator->errors()->add('message', $this->missingLocationMessage($request->returnDeliveryNoteItem));
        }
    }

    public function asController(ReturnDeliveryNoteItem $returnDeliveryNoteItem, ActionRequest $request): void
    {
        $this->initialisationFromWarehouse($returnDeliveryNoteItem->returnDeliveryNote->warehouse, $request);

        $this->handle($returnDeliveryNoteItem, $this->validatedData);
    }
}
