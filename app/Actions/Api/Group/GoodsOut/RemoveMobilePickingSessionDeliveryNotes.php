<?php

/*
 * Author Louis Perez
 * Created on 13-11-2026-15h-47m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Dispatching\PickingSession\RemoveDeliveryNotesFromPickingSession;
use App\Enums\Dispatching\PickingSession\PickingSessionStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Inventory\PickingSession;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class RemoveMobilePickingSessionDeliveryNotes
{
    use AsController;
    use WithMobilePickingSessions;

    /**
     * @throws \Throwable
     */
    public function asController(PickingSession $pickingSession, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanWorkPickingSession($pickingSession, $user);
        $this->ensurePickingSessionState($pickingSession, [PickingSessionStateEnum::IN_PROCESS]);

        $deliveryNoteIds = $this->validatedDeliveryNoteIds($request);
        $outsiders       = array_values(array_diff($deliveryNoteIds, $pickingSession->deliveryNotes()->pluck('delivery_notes.id')->all()));

        if ($outsiders) {
            $references = DeliveryNote::whereIn('id', $outsiders)->where('warehouse_id', $pickingSession->warehouse_id)->pluck('reference', 'id');

            throw ValidationException::withMessages([
                'delivery_note_ids' => __('These delivery notes are not in this picking session: :references.', [
                    'references' => collect($outsiders)->map(fn (int $id) => $references->get($id, '#'.$id))->implode(', '),
                ]),
            ]);
        }

        RemoveDeliveryNotesFromPickingSession::make()->handle($pickingSession, $deliveryNoteIds);

        return $this->respondWithPickingSession($pickingSession, $user, 'done', __('Delivery notes removed.'));
    }
}
