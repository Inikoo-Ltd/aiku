<?php

namespace App\Actions\Dispatching\DeliveryNoteItem\Return;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\DeliveryNoteItem\Return\ReturnDeliveryNoteItemStateEnum;
use App\Models\Dispatching\ReturnDeliveryNoteItem;
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
