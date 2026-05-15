<?php

/*
 * author Louis Perez
 * created on 11-05-2026-12h-59m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\GoodsIn\ReturnDeliveryNoteItem;

use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\GoodsIn\Sowing\SowingTypeEnum;
use App\Models\GoodsIn\ReturnDeliveryNoteItem;
use Illuminate\Console\Command;

class CalculateReturnDeliveryNoteItemTotalSowed extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;

    public function handle(ReturnDeliveryNoteItem $returnDeliveryNoteItem): ReturnDeliveryNoteItem
    {
        $sowings = $returnDeliveryNoteItem->sowings;

        $totalReturned = $sowings->whereIn('type', [
            SowingTypeEnum::SOW
        ])->sum('quantity');

        $totalNotReturned = $sowings->whereIn('type', [
            SowingTypeEnum::NOT_SOW,
        ])->sum('quantity');

        $totalDamaged = $sowings->whereIn('type', [
            SowingTypeEnum::DAMAGED,
        ])->sum('quantity');

        $isFullySowed        = $totalReturned == $returnDeliveryNoteItem->total_expected_qty;
        $isMarkedAsUnpickable = ($totalNotReturned + $totalDamaged) == ($returnDeliveryNoteItem->total_expected_qty - $totalReturned);

        $isCompleted = $isFullySowed || $isMarkedAsUnpickable;

        $dataToUpdate = [
            'total_item_returned'       => $totalReturned,
            'total_item_not_returned'   => $totalNotReturned,
            'total_item_damaged'        => $totalDamaged,
            'is_handled'                => $isCompleted,
        ];

        $returnDeliveryNoteItem = $this->update($returnDeliveryNoteItem, $dataToUpdate);
        $returnDeliveryNoteItem->refresh();

        return $returnDeliveryNoteItem;
    }

    public function action(ReturnDeliveryNoteItem $returnDeliveryNoteItem): ReturnDeliveryNoteItem
    {
        $this->initialisationFromShop($returnDeliveryNoteItem->shop, []);

        return $this->handle($returnDeliveryNoteItem);
    }

    public function getCommandSignature(): string
    {
        return 'calculate:return_delivery_note_item_total_sowed {return_delivery_note_item}';
    }

    public function asCommand(Command $command): int
    {
        $returnDeliveryNoteItem = ReturnDeliveryNoteItem::findOrFail($command->argument('return_delivery_note_item'));
        $this->handle($returnDeliveryNoteItem);

        return 0;
    }

}
