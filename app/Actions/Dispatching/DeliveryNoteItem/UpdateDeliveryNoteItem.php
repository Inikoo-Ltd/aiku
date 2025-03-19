<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Feb 2023 16:47:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNoteItem;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Models\Dispatching\DeliveryNoteItem;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateDeliveryNoteItem extends OrgAction
{
    use WithActionUpdate;

    public function handle(DeliveryNoteItem $deliveryNoteItem, array $modelData): DeliveryNoteItem
    {
        $deliveryNoteItem = $this->update($deliveryNoteItem, $modelData, ['data']);

        if ($deliveryNoteItem->wasChanged('weight')) {

        }

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

        ];

        if (!$this->strict) {
            $rules['transaction_id']      = [
                'sometimes',
                'nullable',
                Rule::Exists('transactions', 'id')->where('shop_id', $this->shop->id)
            ];
            $rules['state']               = ['sometimes', 'nullable', Rule::enum(DeliveryNoteItemStateEnum::class)];
            $rules['quantity_required']   = ['sometimes', 'numeric'];
            $rules['quantity_picked']     = ['sometimes', 'numeric'];
            $rules['quantity_packed']     = ['sometimes', 'numeric'];
            $rules['quantity_dispatched'] = ['sometimes', 'numeric'];
            $rules['source_id']           = ['sometimes', 'string', 'max:255'];
            $rules['last_fetched_at']     = ['sometimes', 'date'];
            $rules['created_at']          = ['sometimes', 'date'];
            $rules['weight']              = ['sometimes', 'numeric', 'min:0'];
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
