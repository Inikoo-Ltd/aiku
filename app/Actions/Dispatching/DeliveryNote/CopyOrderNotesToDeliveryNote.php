<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Feb 2023 16:47:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Events\UpdateOrderNotesEvent;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class CopyOrderNotesToDeliveryNote extends OrgAction
{
    use WithActionUpdate;

    public function handle(DeliveryNote $deliveryNote, array $modelData, bool $fromOrder = false)
    {
        $order = $deliveryNote->orders->first();
        $customerNotes = $deliveryNote->customer_notes;
        $publicNotes = $deliveryNote->public_notes;
        $internalNotes = $deliveryNote->internal_notes;
        $shippingNotes = $deliveryNote->shipping_notes;

        if (Arr::exists($modelData, 'customer_notes') && Arr::get($modelData, 'customer_notes') == true) {
            $customerNotes  = $order->customer_notes;
        }
        if (Arr::exists($modelData, 'public_notes') && Arr::get($modelData, 'public_notes') == true) {
            $publicNotes = $order->public_notes;
        }
        if (Arr::exists($modelData, 'internal_notes') && Arr::get($modelData, 'internal_notes') == true) {
            $internalNotes = $order->internal_notes;
        }
        if (Arr::exists($modelData, 'shipping_notes') && Arr::get($modelData, 'shipping_notes') == true) {
            $shippingNotes = $order->shipping_notes;
        }

        $deliveryNote = $this->update($deliveryNote, [
            'customer_notes'            => $customerNotes,
            'public_notes'              => $publicNotes,
            'internal_notes'            => $internalNotes,
            'shipping_notes'            => $shippingNotes,
        ]);

        $deliveryNote->refresh();

        if ($fromOrder) {
            UpdateOrderNotesEvent::dispatch($deliveryNote);
        }

        return $deliveryNote;
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

    public function jsonResponse(DeliveryNote $deliveryNote)
    {
        return [
            'customer_notes'            => $deliveryNote->customer_notes,
            'public_notes'              => $deliveryNote->public_notes,
            'internal_notes'            => $deliveryNote->internal_notes,
            'shipping_notes'            => $deliveryNote->shipping_notes,
        ];
    }

    public function asController(DeliveryNote $deliveryNote, ActionRequest $request)
    {
        $this->initialisationFromShop($deliveryNote->shop, $request);

        return $this->handle($deliveryNote, $this->validatedData);
    }

    public function action(DeliveryNote $deliveryNote, array $modelData, bool $fromOrder)
    {
        $this->initialisationFromShop($deliveryNote->shop, $modelData);

        return $this->handle($deliveryNote, $this->validatedData, $fromOrder);
    }
}
