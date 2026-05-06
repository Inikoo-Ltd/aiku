<?php

/*
 * author Louis Perez
 * created on 06-05-2026-00h-00m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\GoodsIn\ReturnDeliveryNoteItem;

use App\Actions\OrgAction;
use App\Models\GoodsIn\ReturnDeliveryNoteItem;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class UpsertReturnDeliveryNoteItemNotReturned extends OrgAction
{
    use AsAction;
    use WithAttributes;

    public function handle(ReturnDeliveryNoteItem $returnDeliveryNoteItem, array $modelData): ReturnDeliveryNoteItem
    {
        dd($modelData);
        // $returnDeliveryNoteItem->update([
        //     'total_item_not_returned' => $modelData['quantity'],
        // ]);

        // return $returnDeliveryNoteItem;
    }

    public function rules(): array
    {
        return [
            'quantity' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function asController(ReturnDeliveryNoteItem $returnDeliveryNoteItem, ActionRequest $request): ReturnDeliveryNoteItem
    {
        $this->initialisationFromShop($returnDeliveryNoteItem->shop, $request);

        return $this->handle($returnDeliveryNoteItem, $this->validatedData);
    }
}
