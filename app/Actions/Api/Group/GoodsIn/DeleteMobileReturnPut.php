<?php

/*
 * Author Louis Perez
 * Created on 16-11-2026-11h-33m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsIn;

use App\Actions\GoodsIn\Sowing\DeleteSowing;
use App\Enums\GoodsIn\ReturnDeliveryNote\ReturnDeliveryNoteStateEnum;
use App\Models\GoodsIn\ReturnDeliveryNote;
use App\Models\GoodsIn\Sowing;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class DeleteMobileReturnPut
{
    use AsController;
    use WithMobileReturns;

    /**
     * @throws \Throwable
     */
    public function asController(Warehouse $warehouse, ReturnDeliveryNote $returnDeliveryNote, Sowing $sowing, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanHandleReturn($user, $warehouse, $returnDeliveryNote);
        abort_unless($sowing->return_id === $returnDeliveryNote->id && $sowing->return_item_id !== null, 404);

        $label = $this->putTypeLabel($sowing->type);

        DB::transaction(function () use ($returnDeliveryNote, $sowing, $user) {
            $returnDeliveryNote = ReturnDeliveryNote::lockForUpdate()->findOrFail($returnDeliveryNote->id);
            $this->ensureReturnState($returnDeliveryNote, ReturnDeliveryNoteStateEnum::RETURNING);

            $sowing = Sowing::lockForUpdate()->find($sowing->id);
            abort_unless($sowing, 404);

            DeleteSowing::make()->action($sowing, $user);
        });

        return $this->respondWithReturn($warehouse, $returnDeliveryNote, $user, 'done', __('Undone: :type.', ['type' => $label]));
    }
}
