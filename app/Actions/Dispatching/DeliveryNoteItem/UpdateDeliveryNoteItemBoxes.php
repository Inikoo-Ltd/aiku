<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 24 Sep 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNoteItem;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Dispatching\DeliveryNoteItem;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class UpdateDeliveryNoteItemBoxes extends OrgAction
{
    use WithActionUpdate;

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(DeliveryNoteItem $deliveryNoteItem, array $modelData): DeliveryNoteItem
    {
        if (in_array($deliveryNoteItem->deliveryNote->state, [DeliveryNoteStateEnum::DISPATCHED, DeliveryNoteStateEnum::CANCELLED])) {
            throw ValidationException::withMessages([
                'boxes' => __('The delivery note is :state, its boxes cannot change', ['state' => $deliveryNoteItem->deliveryNote->state->value]),
            ]);
        }

        $boxes = collect(Arr::get($modelData, 'boxes', []))
            ->groupBy(fn (array $row) => (int)$row['box'])
            ->map(fn (Collection $rows, int $box) => ['box' => $box, 'quantity' => round((float)$rows->sum('quantity'), 3)])
            ->filter(fn (array $row) => $row['quantity'] > 0)
            ->sortKeys()
            ->values();

        if (round($boxes->sum('quantity'), 3) > round((float)$deliveryNoteItem->quantity_picked, 3)) {
            throw ValidationException::withMessages([
                'boxes' => __('Only :picked picked, the boxes cannot hold more', ['picked' => (float)$deliveryNoteItem->quantity_picked]),
            ]);
        }

        return $this->update($deliveryNoteItem, ['boxes' => $boxes->isEmpty() ? null : $boxes->all()]);
    }

    public function rules(): array
    {
        return [
            'boxes'            => ['present', 'array'],
            'boxes.*.box'      => ['required', 'integer', 'min:1', 'max:999'],
            'boxes.*.quantity' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function asController(DeliveryNoteItem $deliveryNoteItem, ActionRequest $request): DeliveryNoteItem
    {
        $this->initialisationFromShop($deliveryNoteItem->shop, $request);

        return $this->handle($deliveryNoteItem, $this->validatedData);
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function action(DeliveryNoteItem $deliveryNoteItem, array $modelData): DeliveryNoteItem
    {
        $this->asAction = true;
        $this->initialisationFromShop($deliveryNoteItem->shop, $modelData);

        return $this->handle($deliveryNoteItem, $this->validatedData);
    }
}
