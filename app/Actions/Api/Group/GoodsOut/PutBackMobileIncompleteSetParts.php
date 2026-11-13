<?php

/*
 * Author Louis Perez
 * Created on 12-11-2026-13h-56m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Dispatching\DeliveryNote\PutBackIncompleteSetParts;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class PutBackMobileIncompleteSetParts
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
        $this->ensureState($deliveryNote, [DeliveryNoteStateEnum::HANDLING, DeliveryNoteStateEnum::HANDLING_BLOCKED]);

        if ($deliveryNote->state == DeliveryNoteStateEnum::HANDLING) {
            $this->ensureCanHandle($deliveryNote);
        }

        if (!$deliveryNote->hasIncompleteSets()) {
            $this->refuseState(__('No set on this delivery note is missing a part.'));
        }

        $before = $this->noteSnapshot($deliveryNote);
        PutBackIncompleteSetParts::make()->action($deliveryNote, $user);

        return $this->respond($deliveryNote, $user, $before, $this->noteSnapshot($deliveryNote), __('The parts of the incomplete sets are marked as not picked.'), __('Nothing changed.'));
    }
}
