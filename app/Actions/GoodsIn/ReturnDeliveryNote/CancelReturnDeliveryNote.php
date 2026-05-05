<?php

/*
 * author Louis Perez
 * created on 05-05-2026-13h-35m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\GoodsIn\ReturnDeliveryNote;

use App\Actions\GoodsIn\ReturnDeliveryNoteItem\UpdateReturnDeliveryNoteItem;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\GoodsIn\ReturnDeliveryNote\ReturnDeliveryNoteStateEnum;
use App\Enums\GoodsIn\ReturnDeliveryNoteItem\ReturnDeliveryNoteItemStateEnum;
use App\Models\GoodsIn\ReturnDeliveryNote;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class CancelReturnDeliveryNote extends OrgAction
{
    use WithActionUpdate;

    public function handle(ReturnDeliveryNote $returnDeliveryNote): ReturnDeliveryNote
    {
        $oldState = $returnDeliveryNote->return_state;

        if (in_array($oldState, [ReturnDeliveryNoteStateEnum::RECEIVED, ReturnDeliveryNoteStateEnum::CANCELLED])) {
            throw ValidationException::withMessages([
                'message' => __('Delivery note can not be cancelled.').' ['.__('Invalid state').': '.$oldState->value.']',
            ]);
        }

        $cancelledRef = $returnDeliveryNote->reference.'-CANCELLED';

        $cancelledCount = DB::table('delivery_notes')
            ->where('reference', 'like', $cancelledRef.'%')
            ->count();

        $newCancelledRef = $cancelledRef.($cancelledCount > 0 ? '-'.($cancelledCount + 1) : '');

        data_set($modelData, 'reference', $newCancelledRef);
        data_set($modelData, 'cancelled_at', now());
        data_set($modelData, 'return_state', ReturnDeliveryNoteStateEnum::CANCELLED);

        $returnDeliveryNote = DB::transaction(function () use ($returnDeliveryNote, $modelData) {
            $returnDeliveryNote = $this->update($returnDeliveryNote, $modelData);

            foreach ($returnDeliveryNote->returnDeliveryNoteItem as $item) {
                UpdateReturnDeliveryNoteItem::make()->action($item, [
                    'return_state'        => ReturnDeliveryNoteItemStateEnum::CANCELLED,
                ]);
            }

            return $returnDeliveryNote;
        });
        // TODO hydrator here

        return $returnDeliveryNote;
    }

    public function asController(ReturnDeliveryNote $returnDeliveryNote, ActionRequest $request): ReturnDeliveryNote
    {
        $this->initialisationFromWarehouse($returnDeliveryNote->warehouse, $request);

        return $this->handle($returnDeliveryNote, $this->validatedData);
    }
}
