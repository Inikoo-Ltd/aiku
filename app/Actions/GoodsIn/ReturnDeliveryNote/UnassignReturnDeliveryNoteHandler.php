<?php

/*
 * author Louis Perez
 * created on 15-05-2026-11h-13m
 * GitHub: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\GoodsIn\ReturnDeliveryNote;

use App\Actions\GoodsIn\ReturnDeliveryNote\Traits\WithHydrateReturnDeliveryNotes;
use App\Actions\GoodsIn\ReturnDeliveryNote\Traits\WithReturnDeliveryNoteController;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\GoodsIn\ReturnDeliveryNote\ReturnDeliveryNoteStateEnum;
use App\Models\GoodsIn\ReturnDeliveryNote;
use Illuminate\Validation\ValidationException;

class UnassignReturnDeliveryNoteHandler extends OrgAction
{
    use WithActionUpdate;
    use WithHydrateReturnDeliveryNotes;
    use WithReturnDeliveryNoteController;

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(ReturnDeliveryNote $returnDeliveryNote): ReturnDeliveryNote
    {
        if (!$returnDeliveryNote->handler_user_id) {
            throw ValidationException::withMessages([
                'handler_id' => 'Unable to clear this return handler. No handler is currently assigned',
            ]);
        }

        if ($returnDeliveryNote->returnDeliveryNoteItem()->where(function ($q) {
            $q->where('total_item_not_returned', '>', 0)
                ->orWhere('total_item_returned', '>', 0)
                ->orWhere('total_item_damaged', '>', 0);
        })->exists()) {
            throw ValidationException::withMessages([
                'handler_id' => 'Unable to clear this return handler. Processed item exists',
            ]);
        }

        $returnDeliveryNote = UpdateReturnDeliveryNote::make()->action($returnDeliveryNote, [
            'state'           => ReturnDeliveryNoteStateEnum::RECEIVED,
            'returning_at'    => null,
            'handler_user_id' => null,
        ]);

        $this->hydrateReturnDeliveryNotes($returnDeliveryNote);

        return $returnDeliveryNote;
    }

}
