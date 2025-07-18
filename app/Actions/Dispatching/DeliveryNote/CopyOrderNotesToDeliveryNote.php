<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Feb 2023 16:47:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class CopyOrderNotesToDeliveryNote extends OrgAction
{
    use WithActionUpdate;

    public function handle(DeliveryNote $deliveryNote, array $modelData)
    {
        $order = $deliveryNote->orders->first();
        $customerNotes = '';
        $publicNotes = '';
        $internalNotes = '';
        $shippingNotes = '';

        if(Arr::exists($modelData, 'customer_notes')) {
            $customerNotes  = $order->customer_notes;
        }
        if(Arr::exists($modelData, 'public_notes'))  {
            $publicNotes = $order->public_notes;
        }
        if(Arr::exists($modelData, 'internal_notes'))  {
            $internalNotes = $order->internal_notes;
        }
        if(Arr::exists($modelData, 'shipping_notes'))  {
            $shippingNotes = $order->shipping_notes;
        }

        $this->update($deliveryNote, [
            'customer_notes'            => $customerNotes,
            'public_notes'              => $publicNotes,
            'internal_notes'            => $internalNotes,
            'shipping_notes'            => $shippingNotes,
        ]);
    }

    public function rules(): array
    {
        $rules = [
            'customer_notes'            => ['sometimes', 'boolean'],
            'public_notes'              => ['sometimes', 'boolean'],
            'internal_notes'            => ['sometimes', 'boolean'],
            'shipping_notes'            => ['sometimes', 'boolean'],
        ];
        return $rules;
    }

    public function asController(DeliveryNote $deliveryNote, ActionRequest $request)
    {
        $this->initialisationFromShop($deliveryNote->shop, $request);

        return $this->handle($deliveryNote, $this->validatedData);
    }
}
