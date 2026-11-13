<?php

/*
 * Author Louis Perez
 * Created on 13-11-2026-14h-45m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Dispatching\DeliveryNoteItem\UpdateDeliveryNoteItemUnpack;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class UnpackMobileDeliveryNoteItem
{
    use AsController;
    use WithMobilePickingGuards;
    use WithMobilePackingMessages;

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function asController(DeliveryNote $deliveryNote, DeliveryNoteItem $deliveryNoteItem, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanWork($deliveryNote, $user);
        $this->ensureState($deliveryNote, [DeliveryNoteStateEnum::PACKING, DeliveryNoteStateEnum::PACKED]);
        $this->ensureCanHandle($deliveryNote);
        $this->ensureLineOfNote($deliveryNote, $deliveryNoteItem);

        $packedBefore = $this->packedQuantity($deliveryNoteItem);

        UpdateDeliveryNoteItemUnpack::make()->action($deliveryNoteItem, $user);

        return $packedBefore > 0
            ? $this->respondWith($deliveryNote, $user, 'done', __('Line unpacked.'))
            : $this->respondWith($deliveryNote, $user, 'nothing_changed', __('Nothing was packed on this line.'));
    }
}
