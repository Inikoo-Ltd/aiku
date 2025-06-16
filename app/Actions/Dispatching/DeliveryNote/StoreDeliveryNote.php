<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Feb 2023 16:47:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateDeliveryNotes;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateDeliveryNotes;
use App\Actions\Dispatching\DeliveryNote\Search\DeliveryNoteRecordSearch;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateDeliveryNotes;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateDeliveryNotes;
use App\Actions\Traits\WithFixedAddressActions;
use App\Actions\Traits\WithModelAddressActions;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Ordering\Order;
use App\Rules\IUnique;
use App\Rules\ValidAddress;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreDeliveryNote extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithFixedAddressActions;
    use WithModelAddressActions;

    /**
     * @throws \Throwable
     */
    public function handle(Order $order, array $modelData): DeliveryNote
    {
        if (!Arr::has($modelData, 'delivery_address')) {
            $modelData['delivery_address'] = $order->deliveryAddress;
        }


        $deliveryAddress = Arr::pull($modelData, 'delivery_address');


        data_set($modelData, 'shop_id', $order->shop_id);
        data_set($modelData, 'customer_id', $order->customer_id);
        data_set($modelData, 'group_id', $order->group_id);
        data_set($modelData, 'organisation_id', $order->organisation_id);

        $deliveryNote = DB::transaction(function () use ($order, $modelData, $deliveryAddress) {
            /** @var DeliveryNote $deliveryNote */
            $deliveryNote = $order->deliveryNotes()->create($modelData);

            if ($deliveryNote->delivery_locked) {
                $this->createFixedAddress(
                    $deliveryNote,
                    $deliveryAddress,
                    'Ordering',
                    'delivery',
                    'address_id'
                );
                $deliveryNote->updateQuietly(
                    [
                        'delivery_country_id' => $deliveryNote->address->country_id
                    ]
                );
            } else {
                StoreDeliveryNoteAddress::make()->action($deliveryNote, [
                    'address' => $deliveryAddress
                ]);
            }


            return $deliveryNote;
        });
        $deliveryNote->refresh();

        DeliveryNoteRecordSearch::dispatch($deliveryNote)->delay($this->hydratorsDelay);
        GroupHydrateDeliveryNotes::dispatch($deliveryNote->group)->delay($this->hydratorsDelay);
        OrganisationHydrateDeliveryNotes::dispatch($deliveryNote->organisation)->delay($this->hydratorsDelay);
        ShopHydrateDeliveryNotes::dispatch($deliveryNote->shop)->delay($this->hydratorsDelay);
        CustomerHydrateDeliveryNotes::dispatch($deliveryNote->customer)->delay($this->hydratorsDelay);


        return $deliveryNote;
    }

    public function rules(): array
    {
        $rules = [
            'reference'       => [
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
            'email'           => ['sometimes', 'nullable', $this->strict ? 'email' : 'string'],
            'phone'           => ['sometimes', 'nullable', 'string'],
            'date'            => ['required', 'date'],
            'warehouse_id'    => [
                'required',
                Rule::exists('warehouses', 'id')
                    ->where('organisation_id', $this->organisation->id),
            ],
            'delivery_locked' => ['sometimes', 'boolean'],
            'weight'          => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];

        if (!$this->strict) {
            $rules['state'] = [
                'sometimes',
                'required',
                new Enum(DeliveryNoteStateEnum::class)
            ];


            $rules['delivery_address'] = ['required', new ValidAddress()];
            $rules['reference']        = ['required', 'string', 'max:255'];
            $rules['fetched_at']       = ['sometimes', 'date'];
            $rules['created_at']       = ['sometimes', 'date'];
            $rules['cancelled_at']     = ['sometimes', 'date'];
            $rules['submitted_at']     = ['sometimes', 'date'];
            $rules['source_id']        = ['sometimes', 'string', 'max:64'];
        }

        return $rules;
    }

    /**
     * @throws \Throwable
     */
    public function action(Order $order, array $modelData, int $hydratorsDelay = 0, bool $strict = true, $audit = true): DeliveryNote
    {
        if (!$audit) {
            DeliveryNote::disableAuditing();
        }
        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($order->shop, $modelData);

        return $this->handle($order, $this->validatedData);
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        if (!$this->has('warehouse_id')) {
            $warehouse = $this->shop->organisation->warehouses()->first();
            $this->set('warehouse_id', $warehouse->id);
        }
    }
}
