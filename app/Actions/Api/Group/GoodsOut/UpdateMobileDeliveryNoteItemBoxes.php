<?php

/*
 * Author Louis Perez
 * Created on 13-11-2026-14h-45m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Dispatching\DeliveryNote\DeliveryNoteBoxPackingList;
use App\Actions\Dispatching\DeliveryNoteItem\UpdateDeliveryNoteItemBoxes;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class UpdateMobileDeliveryNoteItemBoxes
{
    use AsController;
    use WithMobilePickingGuards;

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function asController(DeliveryNote $deliveryNote, DeliveryNoteItem $deliveryNoteItem, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanWork($deliveryNote, $user);
        $this->ensureState($deliveryNote, [DeliveryNoteStateEnum::PACKING, DeliveryNoteStateEnum::PACKED]);
        $this->ensureCanHandle($deliveryNote);

        if (!DeliveryNoteBoxPackingList::make()->isRequired($deliveryNote)) {
            throw ValidationException::withMessages(['boxes' => __('This delivery note does not need a packing list by box.')]);
        }

        $this->ensureLineOfNote($deliveryNote, $deliveryNoteItem);

        $data = Validator::make($request->all(), [
            'boxes'            => ['present', 'array'],
            'boxes.*.box'      => ['required', 'integer', 'min:1', 'max:999'],
            'boxes.*.quantity' => ['required', 'numeric', 'min:0'],
        ])->validate();

        UpdateDeliveryNoteItemBoxes::make()->action($deliveryNoteItem, [
            'boxes' => collect($data['boxes'])->map(fn (array $box) => ['box' => (int) $box['box'], 'quantity' => (float) $box['quantity']])->values()->all(),
        ]);

        return $this->respondWith($deliveryNote, $user, 'done', __('Boxes saved.'));
    }
}
