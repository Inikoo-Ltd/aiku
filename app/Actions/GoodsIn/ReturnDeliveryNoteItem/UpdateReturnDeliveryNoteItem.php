<?php

/*
 * author Louis Perez
 * created on 05-05-2026-13h-37m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\GoodsIn\ReturnDeliveryNoteItem;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\GoodsIn\ReturnDeliveryNoteItem\ReturnDeliveryNoteItemStateEnum;
use App\Models\GoodsIn\ReturnDeliveryNoteItem;
use Illuminate\Validation\Rule;

class UpdateReturnDeliveryNoteItem extends OrgAction
{
    use WithActionUpdate;

    public function handle(ReturnDeliveryNoteItem $returnDeliveryNoteItem, array $modelData): ReturnDeliveryNoteItem
    {
        if ($currState = data_get($modelData, 'return_state')) {
            if ($currState == ReturnDeliveryNoteItemStateEnum::CANCELLED) {
                data_set($modelData, 'cancelled_at', now());
            }

            if ($currState == ReturnDeliveryNoteItemStateEnum::HANDLING) {
                data_set($model_data, 'handled_at', now());
            }
        }

        $returnDeliveryNoteItem->update($modelData);

        return $returnDeliveryNoteItem;
    }

    public function rules(): array
    {
        return [
            'return_state'  => ['sometimes', Rule::enum(ReturnDeliveryNoteItemStateEnum::class)],
        ];
    }

    public function action(ReturnDeliveryNoteItem $returnDeliveryNoteItem, array $modelData): ReturnDeliveryNoteItem
    {
        $this->initialisationFromGroup($returnDeliveryNoteItem->group, $modelData);

        return $this->handle($returnDeliveryNoteItem, $this->validatedData);
    }
}
