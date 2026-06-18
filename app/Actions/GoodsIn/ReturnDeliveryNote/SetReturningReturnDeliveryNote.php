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
use App\Actions\GoodsIn\ReturnDeliveryNote\Traits\WithReturnDeliveryNoteTransition;
use App\Actions\GoodsIn\ReturnDeliveryNoteItem\UpdateReturnDeliveryNoteItem;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\GoodsIn\ReturnDeliveryNote\ReturnDeliveryNoteStateEnum;
use App\Enums\GoodsIn\ReturnDeliveryNoteItem\ReturnDeliveryNoteItemStateEnum;
use App\Models\GoodsIn\ReturnDeliveryNote;
use Illuminate\Support\Facades\DB;

class SetReturningReturnDeliveryNote extends OrgAction
{
    use WithActionUpdate;
    use WithHydrateReturnDeliveryNotes;
    use WithReturnDeliveryNoteController;
    use WithReturnDeliveryNoteTransition;

    /**
     * @throws \Throwable
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(ReturnDeliveryNote $returnDeliveryNote): ReturnDeliveryNote
    {
        $this->validateReturnDeliveryNoteState($returnDeliveryNote, ReturnDeliveryNoteStateEnum::RECEIVED);

        $modelData = [
            'state'           => ReturnDeliveryNoteStateEnum::RETURNING,
            'handler_user_id' => request()->user()->id,
        ];

        $returnDeliveryNote = DB::transaction(function () use ($returnDeliveryNote, $modelData) {
            $returnDeliveryNote = UpdateReturnDeliveryNote::make()->action($returnDeliveryNote, $modelData);

            foreach ($returnDeliveryNote->returnDeliveryNoteItem as $item) {
                UpdateReturnDeliveryNoteItem::make()->action($item, [
                    'state'        => ReturnDeliveryNoteItemStateEnum::HANDLING,
                ]);
            }

            return $returnDeliveryNote;
        });

        $this->hydrateReturnDeliveryNotes($returnDeliveryNote);

        return $returnDeliveryNote;
    }

}
