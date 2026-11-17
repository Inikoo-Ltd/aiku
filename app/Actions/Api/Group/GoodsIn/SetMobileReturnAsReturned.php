<?php

/*
 * Author Louis Perez
 * Created on 16-11-2026-11h-33m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsIn;

use App\Actions\GoodsIn\ReturnDeliveryNote\SetReturnedReturnDeliveryNote;
use App\Enums\GoodsIn\ReturnDeliveryNote\ReturnDeliveryNoteStateEnum;
use App\Models\GoodsIn\ReturnDeliveryNote;
use App\Models\GoodsIn\ReturnDeliveryNoteItem;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class SetMobileReturnAsReturned
{
    use AsController;
    use WithMobileReturns;

    /**
     * @throws \Throwable
     */
    public function handle(ReturnDeliveryNote $returnDeliveryNote): int
    {
        return DB::transaction(function () use ($returnDeliveryNote) {
            $returnDeliveryNote = ReturnDeliveryNote::lockForUpdate()->findOrFail($returnDeliveryNote->id);
            $this->ensureReturnState($returnDeliveryNote, ReturnDeliveryNoteStateEnum::RETURNING);

            if (!$this->hasHandledItem($returnDeliveryNote)) {
                $this->refuseState(__('Put back, mark as damaged or as not returned at least one item first.'));
            }

            $itemsLeft = ReturnDeliveryNoteItem::where('return_delivery_note_id', $returnDeliveryNote->id)
                ->lockForUpdate()
                ->get()
                ->filter(fn (ReturnDeliveryNoteItem $item) => $this->remainingOf($item) > 0)
                ->count();

            SetReturnedReturnDeliveryNote::make()->handle($returnDeliveryNote);

            return $itemsLeft;
        });
    }

    /**
     * @throws \Throwable
     */
    public function asController(Warehouse $warehouse, ReturnDeliveryNote $returnDeliveryNote, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanHandleReturn($user, $warehouse, $returnDeliveryNote);

        $itemsLeft = $this->handle($returnDeliveryNote);

        return $this->respondWithReturn($warehouse, $returnDeliveryNote, $user, 'done', $itemsLeft === 0
            ? __('Set as returned.')
            : trans_choice('{1} Set as returned; what was left on :count item is now not returned.|[2,*] Set as returned; what was left on :count items is now not returned.', $itemsLeft, ['count' => $itemsLeft]));
    }
}
