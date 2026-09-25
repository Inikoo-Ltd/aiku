<?php

namespace App\Actions\GoodsIn\ReturnDeliveryNoteItem;

use App\Models\GoodsIn\ReturnDeliveryNoteItem;

trait WithReturnedItemLocation
{
    protected function missingLocationMessage(ReturnDeliveryNoteItem $returnDeliveryNoteItem): string
    {
        return __('Choose the location the goods go back to. If :code has no location, add one on the SKO page first.', [
            'code' => $returnDeliveryNoteItem->orgStock?->code,
        ]);
    }
}
