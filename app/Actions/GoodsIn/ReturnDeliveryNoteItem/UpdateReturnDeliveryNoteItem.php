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
        if (Arr::has($modelData, 'state')) {
            $returnState = data_get($modelData, 'state');
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
        $changes = $returnDeliveryNoteItem->getChanges();
        $returnDeliveryNoteItem->refresh();

        return $returnDeliveryNoteItem;
    }

    public function rules(): array
    {
        return [
            'state'              => ['sometimes', Rule::enum(ReturnDeliveryNoteItemStateEnum::class)],
            'handled_at'                => ['sometimes', 'nullable'],
            'total_item_damaged'        => ['sometimes', 'numeric', 'gte:0'],
            'total_item_not_returned'   => ['sometimes', 'numeric', 'gte:0'],
            'total_item_returned'       => ['sometimes', 'numeric', 'gte:0'],
        ];
    }

    public function action(ReturnDeliveryNoteItem $returnDeliveryNoteItem, array $modelData): ReturnDeliveryNoteItem
    {
        $this->initialisationFromGroup($returnDeliveryNoteItem->group, $modelData);

        return $this->handle($returnDeliveryNoteItem, $this->validatedData);
    }
}
