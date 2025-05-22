<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Feb 2023 16:47:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNoteItem;

use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Models\Dispatching\DeliveryNoteItem;
use Illuminate\Validation\Rules\Enum;
use Lorisleiva\Actions\ActionRequest;

class UpdateDeliveryNoteItem extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithDeliveryNoteItemNoStrictRules;

    public function handle(DeliveryNoteItem $deliveryNoteItem, array $modelData): DeliveryNoteItem
    {
        $deliveryNoteItem = $this->update($deliveryNoteItem, $modelData, ['data']);

        if ($this->strict) {
            if ($deliveryNoteItem->wasChanged('quantity_picked') && $deliveryNoteItem->quantity_picked === $deliveryNoteItem->quantity_required) {
                UpdateDeliveryNoteItem::run($deliveryNoteItem, [
                    'state' => DeliveryNoteItemStateEnum::HANDLING->value
                ]);
            }

            if ($deliveryNoteItem->wasChanged('quantity_packed') && $deliveryNoteItem->quantity_packed === $deliveryNoteItem->quantity_required) {
                UpdateDeliveryNoteItem::run($deliveryNoteItem, [
                    'state' => DeliveryNoteItemStateEnum::PACKED->value
                ]);
            }
        }

        return $deliveryNoteItem;
    }

    public function rules(): array
    {
        $rules = [
            'quantity_picked'     => ['sometimes', 'numeric'],
            'quantity_packed'     => ['sometimes', 'numeric'],
            'state'               => ['sometimes', new Enum(DeliveryNoteItemStateEnum::class)],
        ];

        if (!$this->strict) {
            $rules = $this->noStrictUpdateRules($rules);
            $rules = $this->deliveryNoteItemNonStrictRules($rules);
        }

        return $rules;
    }

    public function action(DeliveryNoteItem $deliveryNoteItem, array $modelData, int $hydratorsDelay = 0, $strict = true): DeliveryNoteItem
    {
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($deliveryNoteItem->shop, $modelData);
        return $this->handle($deliveryNoteItem, $this->validatedData);
    }

    public function asController(DeliveryNoteItem $deliveryNoteItem, ActionRequest $request): DeliveryNoteItem
    {
        $this->initialisationFromShop($deliveryNoteItem->shop, $request);

        return $this->handle($deliveryNoteItem, $this->validatedData);
    }
}
