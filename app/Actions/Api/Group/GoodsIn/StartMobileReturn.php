<?php

/*
 * Author Louis Perez
 * Created on 16-11-2026-11h-33m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsIn;

use App\Actions\GoodsIn\ReturnDeliveryNote\SetReturningReturnDeliveryNote;
use App\Enums\GoodsIn\ReturnDeliveryNote\ReturnDeliveryNoteStateEnum;
use App\Models\GoodsIn\ReturnDeliveryNote;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class StartMobileReturn
{
    use AsController;
    use WithMobileReturns;

    /**
     * @throws \Throwable
     */
    public function asController(Warehouse $warehouse, ReturnDeliveryNote $returnDeliveryNote, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanHandleReturn($user, $warehouse, $returnDeliveryNote);

        DB::transaction(function () use ($returnDeliveryNote) {
            $returnDeliveryNote = ReturnDeliveryNote::lockForUpdate()->findOrFail($returnDeliveryNote->id);
            $this->ensureReturnState($returnDeliveryNote, ReturnDeliveryNoteStateEnum::RECEIVED);

            SetReturningReturnDeliveryNote::make()->handle($returnDeliveryNote);
        });

        return $this->respondWithReturn($warehouse, $returnDeliveryNote, $user, 'done', __('Return started; you are its handler.'));
    }
}
