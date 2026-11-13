<?php

/*
 * Author Louis Perez
 * Created on 12-11-2026-13h-56m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Dispatching\DeliveryNote\UpdateState\UpdateDeliveryNoteStateToInQueue;
use App\Actions\Dispatching\DeliveryNote\UpdateState\UpdateDeliveryNoteStateToUnassigned;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class UpdateMobileDeliveryNotePicker
{
    use AsController;
    use WithMobilePickingGuards;

    /**
     * @throws \Throwable
     */
    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanWork($deliveryNote, $user);
        $this->ensureState($deliveryNote, [DeliveryNoteStateEnum::UNASSIGNED, DeliveryNoteStateEnum::QUEUED]);
        $this->ensureNotInPickingSession($deliveryNote);

        $data = Validator::make($request->all(), [
            'user_id' => ['present', 'nullable', 'integer'],
        ])->validate();

        $before = $this->noteSnapshot($deliveryNote);

        if (empty($data['user_id'])) {
            if ($deliveryNote->state != DeliveryNoteStateEnum::QUEUED) {
                $this->refuseState(__('This delivery note has no picker to remove.'));
            }

            UpdateDeliveryNoteStateToUnassigned::make()->action($deliveryNote);

            return $this->respond($deliveryNote, $user, $before, $this->noteSnapshot($deliveryNote), __('Picker removed.'), __('Nothing changed.'));
        }

        $isPicker = app(IndexMobilePickers::class)->pickers($deliveryNote->warehouse)->contains('user_id', (int) $data['user_id']);
        if (!$isPicker) {
            throw ValidationException::withMessages(['user_id' => __('Choose one of the pickers.')]);
        }

        UpdateDeliveryNoteStateToInQueue::make()->action($deliveryNote, User::findOrFail($data['user_id']));

        return $this->respond($deliveryNote, $user, $before, $this->noteSnapshot($deliveryNote), __('Picker assigned.'), __('Nothing changed.'));
    }
}
