<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 26 Feb 2024 21:27:03 Central Standard Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\FulfilmentCustomer;

use App\Actions\CRM\Customer\UpdateCustomer;
use App\Actions\Fulfilment\Fulfilment\Hydrators\FulfilmentHydrateCustomers;
use App\Actions\Fulfilment\FulfilmentCustomer\Search\FulfilmentCustomerRecordSearch;
use App\Actions\Helpers\Address\UpdateAddress;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithModelAddressActions;
use App\Enums\Fulfilment\FulfilmentCustomer\FulfilmentCustomerStatusEnum;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use OwenIt\Auditing\Events\AuditCustom;

class UpdateFulfilmentCustomer extends OrgAction
{
    use WithActionUpdate;
    use WithModelAddressActions;

    public function handle(FulfilmentCustomer $fulfilmentCustomer, array $modelData): FulfilmentCustomer
    {
        $customerData       = Arr::only($modelData, ['contact_name', 'company_name', 'email', 'phone']);
        $contactAddressData = Arr::get($modelData, 'address');
        UpdateCustomer::run($fulfilmentCustomer->customer, $customerData);
        Arr::forget($modelData, ['contact_name', 'company_name', 'email', 'phone', 'address']);

        $oldData = [
            'pallets_storage' => $fulfilmentCustomer->pallets_storage,
            'items_storage'   => $fulfilmentCustomer->items_storage,
            'dropshipping'    => $fulfilmentCustomer->dropshipping,
            'space_rental'    => $fulfilmentCustomer->space_rental
        ];

        if (!blank($contactAddressData)) {
            if ($fulfilmentCustomer->customer->address) {
                UpdateAddress::run($fulfilmentCustomer->customer->address, $contactAddressData);
            } else {
                $this->addAddressToModelFromArray(
                    model: $fulfilmentCustomer->customer,
                    addressData: $contactAddressData,
                    scope: 'billing',
                    updateLocation: false,
                    canShip: true
                );
            }
        }

        foreach ($modelData as $key => $value) {
            data_set(
                $modelData,
                match ($key) {
                    'product' => 'data.product',
                    'shipments_per_week' => 'data.shipments_per_week',
                    'size_and_weight' => 'data.size_and_weight',
                    default => $key
                },
                $value
            );
        }
        data_forget($modelData, 'product');
        data_forget($modelData, 'shipments_per_week');
        data_forget($modelData, 'size_and_weight');

        if ($fulfilmentCustomer->number_pallets > 0 && Arr::exists($modelData, 'pallets_storage')) {
            throw ValidationException::withMessages(['message' => __('You can\'t unselect because you already have pallets.')]);
        }

        if ($fulfilmentCustomer->number_spaces > 0 && Arr::exists($modelData, 'space_rental')) {
            throw ValidationException::withMessages(['message' => __('You can\'t unselect because you already have spaces.')]);
        }

        if ($fulfilmentCustomer->customer->shopifyUser && Arr::exists($modelData, 'dropshipping')) {
            throw ValidationException::withMessages(['message' => __('You can\'t unselect because you already have platform accounts.')]);
        }

        $fulfilmentCustomer = $this->update($fulfilmentCustomer, $modelData, ['data']);

        $attributes = ['pallets_storage', 'items_storage', 'dropshipping', 'space_rental'];
        if (collect($attributes)->contains(fn ($attr) => $fulfilmentCustomer->wasChanged($attr))) {
            $fulfilmentCustomer->customer->auditEvent    = 'update';
            $fulfilmentCustomer->customer->isCustomEvent = true;

            $newData = [
                'pallets_storage' => $fulfilmentCustomer->pallets_storage,
                'items_storage'   => $fulfilmentCustomer->items_storage,
                'dropshipping'    => $fulfilmentCustomer->dropshipping,
                'space_rental'    => $fulfilmentCustomer->space_rental
            ];


            $fulfilmentCustomer->customer->auditCustomOld = $oldData;
            $fulfilmentCustomer->customer->auditCustomNew = $newData;
            Event::dispatch(new AuditCustom($fulfilmentCustomer->customer));
        }


        FulfilmentHydrateCustomers::dispatch($fulfilmentCustomer->fulfilment);
        FulfilmentCustomerRecordSearch::dispatch($fulfilmentCustomer);

        return $fulfilmentCustomer;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("fulfilment-shop.{$this->fulfilment->id}.edit");
    }

    public function rules(): array
    {
        return [
            'contact_name'      => ['sometimes', 'nullable', 'string'],
            'company_name'      => ['sometimes', 'nullable', 'string'],
            'email'             => ['sometimes', 'nullable', 'string', Rule::unique('customers')->where('shop_id', $this->shop->id)],
            'phone'             => ['sometimes', 'nullable', 'string'],
            'pallets_storage'   => ['sometimes', 'boolean'],
            'items_storage'     => ['sometimes', 'boolean'],
            'dropshipping'      => ['sometimes', 'boolean'],
            'space_rental'      => ['sometimes', 'boolean'],
            'address'           => ['sometimes'],
            'product'           => ['sometimes', 'required', 'string'],
            'shipments_per_week' => ['sometimes', 'required', 'string'],
            'size_and_weight'   => ['sometimes', 'required', 'string'],
            'status' => [
                'sometimes',
                Rule::enum(FulfilmentCustomerStatusEnum::class)
            ]
        ];
    }


    public function asController(
        Organisation $organisation,
        Shop $shop,
        Fulfilment $fulfilment,
        FulfilmentCustomer $fulfilmentCustomer,
        ActionRequest $request
    ): FulfilmentCustomer {
        $this->initialisationFromFulfilment($fulfilmentCustomer->fulfilment, $request);

        return $this->handle($fulfilmentCustomer, $this->validatedData);
    }

    public function action(FulfilmentCustomer $fulfilmentCustomer, array $modelData): FulfilmentCustomer
    {
        $this->asAction = true;
        $this->initialisationFromFulfilment($fulfilmentCustomer->fulfilment, $modelData);

        return $this->handle($fulfilmentCustomer, $this->validatedData);
    }


}
