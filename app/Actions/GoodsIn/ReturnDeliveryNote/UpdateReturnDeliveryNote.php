<?php

namespace App\Actions\GoodsIn\ReturnDeliveryNote;

use App\Actions\GoodsIn\ReturnDeliveryNoteItem\UpdateReturnDeliveryNoteItem;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\GoodsIn\ReturnDeliveryNote\ReturnDeliveryNoteStateEnum;
use App\Enums\GoodsIn\ReturnDeliveryNoteItem\ReturnDeliveryNoteItemStateEnum;
use App\Models\GoodsIn\ReturnDeliveryNote;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class UpdateReturnDeliveryNote extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;

    public function handle(ReturnDeliveryNote $returnDeliveryNote, array $modelData): ReturnDeliveryNote
    {
        if (Arr::has($modelData, 'state')) {
            $returnState = data_get($modelData, 'state');
            $timestampField = match ($returnState) {
                ReturnDeliveryNoteStateEnum::CANCELLED => 'cancelled_at',
                ReturnDeliveryNoteStateEnum::RETURNING => 'returning_at',
                ReturnDeliveryNoteStateEnum::RETURNED  => 'returned_at',
                default => null,
            };

            if ($timestampField) {
                data_set($modelData, $timestampField, now());
            }

            if ($returnState == ReturnDeliveryNoteStateEnum::RECEIVED) {
                foreach ($returnDeliveryNote->returnDeliveryNoteItem as $returnedItem) {
                    UpdateReturnDeliveryNoteItem::make()->action($returnedItem, [
                        'state'  => ReturnDeliveryNoteItemStateEnum::UNASSIGNED,
                        'handling_at'   => null,
                    ]);
                }
            }
        }

        $returnDeliveryNote->update($modelData);

        return $returnDeliveryNote;
    }

    public function rules(): array
    {
        return [
            'handler_user_id'   => ['sometimes', 'nullable'],
            'reference'         => ['sometimes', 'string'],
            'returning_at'      => ['sometimes', 'nullable'],
            'state'      => ['sometimes', Rule::enum(ReturnDeliveryNoteStateEnum::class)],
        ];
    }

    public function action(ReturnDeliveryNote $returnDeliveryNote, array $modelData): ReturnDeliveryNote
    {
        $this->initialisationFromWarehouse($returnDeliveryNote->warehouse, $modelData);

        return $this->handle($returnDeliveryNote, $this->validatedData);
    }
}
