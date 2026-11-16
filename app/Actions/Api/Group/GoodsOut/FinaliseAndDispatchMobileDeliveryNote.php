<?php

/*
 * Author Louis Perez
 * Created on 13-11-2026-14h-45m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Dispatching\DeliveryNote\UpdateState\FinaliseAndDispatchDeliveryNote;
use App\Models\Dispatching\DeliveryNote;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class FinaliseAndDispatchMobileDeliveryNote
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
        $this->ensureActionAvailable($deliveryNote, $user, 'finalise_and_dispatch');

        $this->passingWebRefusals(fn () => FinaliseAndDispatchDeliveryNote::make()->action($deliveryNote), 'state');

        return $this->respondWith($deliveryNote, $user, 'done', $this->stateReachedMessage($deliveryNote));
    }
}
