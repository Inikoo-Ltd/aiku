<?php

/*
 * Author Louis Perez
 * Created on 12-11-2026-13h-56m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Dispatching\Picking\SetAsWaitingCrm;
use App\Actions\Dispatching\Picking\SetAsWaitingWarehouse;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class SetMobileDeliveryNoteItemAsWaiting
{
    use AsController;
    use WithMobilePickingGuards;

    public function asController(DeliveryNote $deliveryNote, DeliveryNoteItem $deliveryNoteItem, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureLineAction($deliveryNote, $user);
        abort_unless($this->pickingSettings($deliveryNote->organisation)['allow_waiting'], 403, __('Waiting is not enabled for this organisation.'));
        $this->ensureLineOfNote($deliveryNote, $deliveryNoteItem);

        $data = Validator::make($request->all(), [
            'kind'     => ['required', Rule::in(['warehouse', 'crm'])],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'note'     => ['sometimes', 'nullable', 'string', 'max:1000'],
        ])->validate();

        $modelData = array_filter([
            'quantity' => (float) $data['quantity'],
            'note'     => $data['note'] ?? null,
        ], fn ($value) => $value !== null);

        $before = $this->lineSnapshot($deliveryNoteItem);

        $this->passingWebRefusals(fn () => $data['kind'] === 'warehouse'
            ? SetAsWaitingWarehouse::make()->action($deliveryNoteItem->refresh(), $user, $modelData)
            : SetAsWaitingCrm::make()->action($deliveryNoteItem->refresh(), $user, $modelData), 'quantity');

        return $this->respond(
            $deliveryNote,
            $user,
            $before,
            $this->lineSnapshot($deliveryNoteItem),
            $data['kind'] === 'warehouse' ? __('Set as waiting for the warehouse.') : __('Passed to customer services.'),
            __('Nothing changed on this line.')
        );
    }
}
