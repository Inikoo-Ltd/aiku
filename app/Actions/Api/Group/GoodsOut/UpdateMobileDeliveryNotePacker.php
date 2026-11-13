<?php

/*
 * Author Louis Perez
 * Created on 13-11-2026-15h-47m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Dispatching\DeliveryNote\UpdateDeliveryNote;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class UpdateMobileDeliveryNotePacker
{
    use AsController;
    use WithMobilePickingGuards;

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanWork($deliveryNote, $user);
        $this->ensureState($deliveryNote, [DeliveryNoteStateEnum::PACKING, DeliveryNoteStateEnum::PACKED]);
        $this->ensureNotInPickingSession($deliveryNote);

        $data = Validator::make($request->all(), [
            'user_id' => ['required', 'integer'],
        ])->validate();

        $isPicker = app(IndexMobilePickers::class)->pickers($deliveryNote->warehouse)->contains('user_id', (int) $data['user_id']);
        if (!$isPicker) {
            throw ValidationException::withMessages(['user_id' => __('Choose one of the pickers.')]);
        }

        $packerBefore = $deliveryNote->packer_user_id;

        UpdateDeliveryNote::make()->action($deliveryNote, ['packer_user_id' => (int) $data['user_id']]);

        return $packerBefore === (int) $data['user_id']
            ? $this->respondWith($deliveryNote, $user, 'nothing_changed', __('Nothing changed.'))
            : $this->respondWith($deliveryNote, $user, 'done', __('Packer changed.'));
    }
}
