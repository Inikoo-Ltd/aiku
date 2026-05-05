<?php

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

class SetReturningReturnDeliveryNote extends OrgAction
{
    use WithActionUpdate;

    public function handle(ReturnDeliveryNote $returnDeliveryNote, array $modelData): ReturnDeliveryNote
    {
        $oldState = $returnDeliveryNote->return_state;

        if ($oldState !== ReturnDeliveryNoteStateEnum::RECEIVED) {
            throw ValidationException::withMessages([
                'message' => __('Delivery note can not be handled.').' ['.__('Invalid state').': '.$oldState->value.']',
            ]);
        }

        data_set($modelData, 'handling_at', now());
        data_set($modelData, 'return_state', ReturnDeliveryNoteStateEnum::RETURNING);

        $returnDeliveryNote = DB::transaction(function () use ($returnDeliveryNote, $modelData) {
            $returnDeliveryNote = $this->update($returnDeliveryNote, $modelData);

            foreach ($returnDeliveryNote->returnDeliveryNoteItem as $item) {
                UpdateReturnDeliveryNoteItem::make()->action($item, [
                    'return_state'        => ReturnDeliveryNoteItemStateEnum::HANDLING,
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
