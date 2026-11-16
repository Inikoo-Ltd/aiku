<?php

/*
 * Author Louis Perez
 * Created on 12-11-2026-13h-56m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Dispatching\Picking\StoreNotPickPicking;
use App\Enums\Dispatching\Picking\PickingNotPickedReasonEnum;
use App\Enums\Dispatching\Picking\PickingTypeEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class StoreMobileNotPickedPicking
{
    use AsController;
    use WithMobilePickingGuards;

    private const float QUANTITY_TOLERANCE = 0.000001;

    public function asController(DeliveryNote $deliveryNote, DeliveryNoteItem $deliveryNoteItem, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureLineAction($deliveryNote, $user);
        abort_unless($this->pickingSettings($deliveryNote->organisation)['allow_picker_set_not_picked'], 403, __('Pickers can not mark lines as not picked in this organisation.'));
        $this->ensureLineOfNote($deliveryNote, $deliveryNoteItem);

        $data = Validator::make($request->all(), [
            'quantity' => ['sometimes', 'nullable', 'numeric', 'gt:0'],
            'reason'   => ['required', Rule::enum(PickingNotPickedReasonEnum::class)],
            'note'     => ['sometimes', 'nullable', 'string', 'max:1000'],
        ])->validate();

        $deliveryNoteItem->refresh();
        $outstanding = StoreNotPickPicking::make()->outstandingQuantity($deliveryNoteItem);
        $quantity    = isset($data['quantity']) ? (float) $data['quantity'] : $outstanding;

        if ($quantity > $outstanding + self::QUANTITY_TOLERANCE) {
            throw ValidationException::withMessages(['quantity' => __('At most :quantity can be marked as not picked.', ['quantity' => $this->formatScanQuantity($deliveryNoteItem, $outstanding)])]);
        }

        $before = $this->lineSnapshot($deliveryNoteItem);

        $alreadyMarked = $outstanding <= 0 && $deliveryNoteItem->pickings()->where('type', PickingTypeEnum::NOT_PICK)->exists();
        if (!$alreadyMarked) {
            StoreNotPickPicking::run($deliveryNoteItem, $user, array_filter([
                'quantity'          => min($quantity, $outstanding),
                'not_picked_reason' => $data['reason'],
                'not_picked_note'   => $data['note'] ?? null,
            ], fn ($value) => $value !== null));
        }

        return $this->respond(
            $deliveryNote,
            $user,
            $before,
            $this->lineSnapshot($deliveryNoteItem),
            __(':quantity marked as not picked.', ['quantity' => $this->formatScanQuantity($deliveryNoteItem, min($quantity, $outstanding))]),
            __('Nothing is left to mark as not picked on this line.')
        );
    }
}
