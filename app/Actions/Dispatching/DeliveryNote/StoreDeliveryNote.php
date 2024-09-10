<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Feb 2023 16:47:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\Dispatching\DeliveryNote\Search\DeliveryNoteRecordSearch;
use App\Actions\OrgAction;
use App\Actions\Traits\WithFixedAddressActions;
use App\Actions\Traits\WithModelAddressActions;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStatusEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Ordering\Order;
use App\Rules\IUnique;
use App\Rules\ValidAddress;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreDeliveryNote extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithFixedAddressActions;
    use WithModelAddressActions;

    public function handle(
        Order $order,
        array $modelData,
    ): DeliveryNote {
        $deliveryAddress = $modelData['delivery_address'];
        data_forget($modelData, 'delivery_address');

        data_set($modelData, 'shop_id', $order->shop_id);
        data_set($modelData, 'customer_id', $order->customer_id);
        data_set($modelData, 'group_id', $order->group_id);
        data_set($modelData, 'organisation_id', $order->organisation_id);

        /** @var DeliveryNote $deliveryNote */
        $deliveryNote = $order->deliveryNotes()->create($modelData);
        $deliveryNote->stats()->create();

        if ($deliveryNote->delivery_locked) {
            $deliveryNote = $this->createFixedAddress(
                $deliveryNote,
                $deliveryAddress,
                'Ordering',
                'delivery',
                'address_id'
            );
        } else {
            $deliveryNote = $this->addAddressToModel(
                model: $deliveryNote,
                addressData: $deliveryAddress->toArray(),
                scope: 'delivery',
                updateLocation: false,
            );
        }

        $deliveryNote->updateQuietly(
            [
                'delivery_country_id' => $deliveryNote->address->country_id
            ]
        );

        DeliveryNoteRecordSearch::dispatch($deliveryNote);

        return $deliveryNote;
    }

    public function rules(): array
    {
        $rules = [
            'reference'           => [
                'required',
                'max:64',
                'string',
                new IUnique(
                    table: 'delivery_notes',
                    extraConditions: [
                        ['column' => 'organisation_id', 'value' => $this->organisation->id],
                    ]
                ),
            ],
            'state'            => [
                'sometimes',
                'required',
                new Enum(DeliveryNoteStateEnum::class)
            ],
            'status'           => [
                'sometimes',
                'required',
                new Enum(DeliveryNoteStatusEnum::class)
            ],
            'delivery_address' => ['required', new ValidAddress()],
            'email'            => ['sometimes', 'nullable', 'email'],
            'phone'            => ['sometimes', 'nullable', 'string'],
            'date'             => ['required', 'date'],
            'created_at'       => ['sometimes', 'date'],
            'cancelled_at'     => ['sometimes', 'date'],
            'source_id'        => ['sometimes', 'string'],
            'warehouse_id'     => [
                'required',
                Rule::exists('warehouses', 'id')
                    ->where('organisation_id', $this->organisation->id),
            ],
            'delivery_locked'  => ['sometimes', 'boolean'],
            'fetched_at'       => ['sometimes', 'date'],
        ];

        if (!$this->strict) {
            $rules['reference'] = ['required', 'max:64', 'string'];
        }

        return $rules;
    }

    public function action(Order $order, array $modelData, int $hydratorsDelay = 0, bool $strict = true): DeliveryNote
    {
        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($order->shop, $modelData);

        return $this->handle($order, $this->validatedData);
    }
}
