<?php

/*
 * Author Louis Perez
 * Created on 12-11-2026-13h-56m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Dispatching\DeliveryNote\UpdateState\StartHandlingDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\UpdateState\StartHandlingWithTrolleyDeliveryNote;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\Trolley;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class StartMobilePicking
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
        $this->ensureState($deliveryNote, [DeliveryNoteStateEnum::UNASSIGNED, DeliveryNoteStateEnum::QUEUED, DeliveryNoteStateEnum::HANDLING_BLOCKED]);

        if ($deliveryNote->state == DeliveryNoteStateEnum::HANDLING_BLOCKED && $deliveryNote->hasBlockingItems()) {
            $this->refuseState(__('Something on this delivery note is still waiting, so picking can not resume yet.'));
        }

        $data = Validator::make($request->all(), [
            'trolley_id' => ['sometimes', 'nullable', 'integer'],
        ])->validate();

        $trolley = null;
        if (!empty($data['trolley_id']) && !$this->isDropshipping($deliveryNote)) {
            $trolley = $this->freeTrolley($deliveryNote, (int) $data['trolley_id']);
        }

        $before = $this->noteSnapshot($deliveryNote);

        if ($trolley) {
            StartHandlingWithTrolleyDeliveryNote::run($deliveryNote, $user, ['trolley' => $trolley->id]);
        } else {
            StartHandlingDeliveryNote::make()->action($deliveryNote, $user);
        }

        return $this->respond($deliveryNote, $user, $before, $this->noteSnapshot($deliveryNote), __('Picking started.'), __('Nothing changed.'));
    }

    private function freeTrolley(DeliveryNote $deliveryNote, int $trolleyId): Trolley
    {
        /** @var Trolley|null $trolley */
        $trolley = Trolley::find($trolleyId);
        abort_unless($trolley && $trolley->warehouse_id === $deliveryNote->warehouse_id, 404);

        if ($trolley->isInUse()) {
            throw ValidationException::withMessages(['trolley_id' => __('That trolley is in use.')]);
        }

        return $trolley;
    }
}
