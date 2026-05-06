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
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class UpdateReturnDeliveryNoteItem extends OrgAction
{
    use WithActionUpdate;

    public function handle(ReturnDeliveryNoteItem $returnDeliveryNoteItem, array $modelData): ReturnDeliveryNoteItem
    {
        
        if (Arr::has($modelData, 'return_state')) {
            $returnState = data_get($modelData, 'return_state');
            $timestampField = match ($returnState) {
                ReturnDeliveryNoteItemStateEnum::CANCELLED  => 'cancelled_at',
                ReturnDeliveryNoteItemStateEnum::HANDLING   => 'handled_at',
                ReturnDeliveryNoteItemStateEnum::PROCESSED  => 'processed_at',
                default => null,
            };

            if ($timestampField) {
                data_set($modelData, $timestampField, now());
            }
        }

        $returnDeliveryNoteItem->update($modelData);

        return $returnDeliveryNoteItem;
    }

    public function rules(): array
    {
        return [
            'return_state'  => ['sometimes', Rule::enum(ReturnDeliveryNoteItemStateEnum::class)],
            'handled_at'    => ['sometimes', 'nullable'],
        ];
    }

    public function action(ReturnDeliveryNoteItem $returnDeliveryNoteItem, array $modelData): ReturnDeliveryNoteItem
    {
        $this->initialisationFromGroup($returnDeliveryNoteItem->group, $modelData);

        return $this->handle($returnDeliveryNoteItem, $this->validatedData);
    }
}
