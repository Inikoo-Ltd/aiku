<?php

/*
 * Author Louis Perez
 * Created on 12-11-2026-13h-56m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Dispatching\Picking\DeletePicking;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Dispatching\Picking;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class DeleteMobilePicking
{
    use AsController;
    use WithMobilePickingGuards;

    /**
     * @throws \Throwable
     */
    public function asController(DeliveryNote $deliveryNote, DeliveryNoteItem $deliveryNoteItem, Picking $picking, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanWork($deliveryNote, $user);
        $this->ensureState($deliveryNote, [DeliveryNoteStateEnum::HANDLING, DeliveryNoteStateEnum::HANDLING_BLOCKED]);
        $this->ensureCanHandle($deliveryNote);
        $this->ensureLineOfNote($deliveryNote, $deliveryNoteItem);
        abort_unless($picking->delivery_note_item_id === $deliveryNoteItem->id, 404);

        $before = $this->lineSnapshot($deliveryNoteItem);
        $this->passingWebRefusals(fn () => DeletePicking::make()->action($picking, $user), 'picking');

        return $this->respond($deliveryNote, $user, $before, $this->lineSnapshot($deliveryNoteItem), __('Pick undone.'), __('Nothing changed on this line.'));
    }
}
