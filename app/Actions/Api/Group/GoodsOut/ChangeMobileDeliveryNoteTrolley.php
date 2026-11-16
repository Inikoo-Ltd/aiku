<?php

/*
 * Author Louis Perez
 * Created on 12-11-2026-13h-56m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Dispatching\Trolley\ChangeTrolleyDeliveryNote;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\Trolley;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class ChangeMobileDeliveryNoteTrolley
{
    use AsController;
    use WithMobilePickingGuards;

    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanWork($deliveryNote, $user);
        $this->ensureState($deliveryNote, [DeliveryNoteStateEnum::HANDLING, DeliveryNoteStateEnum::HANDLING_BLOCKED, DeliveryNoteStateEnum::PICKED]);
        $this->ensureCanHandle($deliveryNote);

        if ($this->isDropshipping($deliveryNote)) {
            $this->refuseState(__('Dropshipping delivery notes are picked without a trolley.'));
        }

        $data = Validator::make($request->all(), [
            'trolley_id' => ['present', 'nullable', 'integer'],
        ])->validate();

        $trolley = null;
        if (!empty($data['trolley_id'])) {
            $trolley = Trolley::find($data['trolley_id']);
            abort_unless($trolley && $trolley->warehouse_id === $deliveryNote->warehouse_id, 404);

            if ($trolley->picking_session_id || ($trolley->current_delivery_note_id && $trolley->current_delivery_note_id !== $deliveryNote->id)) {
                throw ValidationException::withMessages(['trolley_id' => __('That trolley is in use.')]);
            }
        }

        $before = $this->noteSnapshot($deliveryNote);
        ChangeTrolleyDeliveryNote::run($deliveryNote, ['trolley' => $trolley?->id]);

        return $this->respond($deliveryNote, $user, $before, $this->noteSnapshot($deliveryNote), __('Trolley changed.'), __('The trolley did not change.'));
    }
}
