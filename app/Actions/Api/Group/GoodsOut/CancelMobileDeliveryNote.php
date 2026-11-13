<?php

/*
 * Author Louis Perez
 * Created on 13-11-2026-15h-47m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Dispatching\DeliveryNote\UI\ShowDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\UpdateState\CancelDeliveryNote;
use App\Models\Dispatching\DeliveryNote;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class CancelMobileDeliveryNote
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
        $this->ensureCanView($deliveryNote, $user);
        abort_unless($deliveryNote->canBeCancelledBy($user), 403, __('Only a dispatch supervisor or customer services can cancel a delivery note.'));
        $this->ensureNotLockedInAurora($deliveryNote);

        if (!ShowDeliveryNote::canBeCancelledInWarehouse($deliveryNote, $user)) {
            $this->refuseState(__('This delivery note can not be cancelled.'));
        }

        $data = Validator::make($request->all(), [
            'create_return' => ['sometimes', 'boolean'],
        ])->validate();

        CancelDeliveryNote::make()->action($deliveryNote, $user, true, false, null, (bool) ($data['create_return'] ?? true));

        return $this->respondWith($deliveryNote, $user, 'done', __('Delivery note cancelled.'));
    }
}
