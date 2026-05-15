<?php

/*
 * author Louis Perez
 * created on 05-05-2026-13h-35m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\GoodsIn\ReturnDeliveryNote;

use App\Actions\Dispatching\DeliveryNote\UpdateDeliveryNote;
use App\Actions\GoodsIn\ReturnDeliveryNote\Traits\WithHydrateReturnDeliveryNotes;
use App\Actions\GoodsIn\ReturnDeliveryNoteItem\UpdateReturnDeliveryNoteItem;
use App\Actions\GoodsIn\Sowing\DeleteSowing;
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
    use WithHydrateReturnDeliveryNotes;

    public function handle(ReturnDeliveryNote $returnDeliveryNote): ReturnDeliveryNote
    {
        $oldState = $returnDeliveryNote->state;

        if (in_array($oldState, [ReturnDeliveryNoteStateEnum::RETURNED, ReturnDeliveryNoteStateEnum::CANCELLED])) {
            throw ValidationException::withMessages([
                'message' => __('Delivery note can not be cancelled.').' ['.__('Invalid state').': '.$oldState->value.']',
            ]);
        }

        $cancelledRef = $returnDeliveryNote->reference.'-CANCELLED';

        $cancelledCount = DB::table('delivery_notes')
            ->where('reference', 'like', $cancelledRef.'%')
            ->count();

        $newCancelledRef = $cancelledRef.($cancelledCount > 0 ? '-'.($cancelledCount + 1) : '');

        $modelData = [];
        data_set($modelData, 'reference', $newCancelledRef);
        data_set($modelData, 'state', ReturnDeliveryNoteStateEnum::CANCELLED);

        $returnDeliveryNote = DB::transaction(function () use ($returnDeliveryNote, $modelData) {
            $returnDeliveryNote = UpdateReturnDeliveryNote::make()->action($returnDeliveryNote, $modelData);

            foreach ($returnDeliveryNote->returnDeliveryNoteItem as $item) {
                foreach ($item->sowings as $sowing) {
                    DeleteSowing::make()->action($sowing);
                }

                UpdateReturnDeliveryNoteItem::make()->action($item, [
                    'state'        => ReturnDeliveryNoteItemStateEnum::CANCELLED,
                ]);
            }

            UpdateDeliveryNote::make()->action($returnDeliveryNote->deliveryNote, [
                'is_returned'   => false
            ]);

            return $returnDeliveryNote;
        });
        
        $this->hydrateReturnDeliveryNotes($returnDeliveryNote);

        return $returnDeliveryNote;
    }

    public function asController(ReturnDeliveryNote $returnDeliveryNote, ActionRequest $request): ReturnDeliveryNote
    {
        $this->initialisationFromWarehouse($returnDeliveryNote->warehouse, $request);

        return $this->handle($returnDeliveryNote, $this->validatedData);
    }
}
