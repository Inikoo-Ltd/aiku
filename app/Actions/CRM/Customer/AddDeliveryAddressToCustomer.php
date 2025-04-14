<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:32:25 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithModelAddressActions;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Http\Resources\CRM\CustomersResource;
use App\Models\CRM\Customer;
use App\Rules\ValidAddress;
use Lorisleiva\Actions\ActionRequest;

class AddDeliveryAddressToCustomer extends OrgAction
{
    use WithActionUpdate;
    use WithModelAddressActions;

    private Customer $customer;

    public function handle(Customer $customer, array $modelData): Customer
    {
        return $this->addAddressToModelFromArray(
            model: $customer,
            addressData: $modelData['delivery_address'],
            scope: 'delivery',
            updateLocation: false,
            updateAddressField: false
        );
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if ($this->shop->type == ShopTypeEnum::FULFILMENT) {
            $fulfilment = $this->shop->fulfilment;

            return $request->user()->authTo([
                "fulfilment-shop.$fulfilment->id.edit",
                "supervisor-fulfilment-shop.".$fulfilment->id,
            ]);
        } else {
            return $request->user()->authTo("crm.{$this->shop->id}.edit");
        }
    }

    public function rules(): array
    {
        return [
            'delivery_address' => ['required', new ValidAddress()],
        ];
    }


    public function asController(Customer $customer, ActionRequest $request): Customer
    {
        $this->customer = $customer;
        $this->initialisationFromShop($customer->shop, $request);

        return $this->handle($customer, $this->validatedData);
    }

    public function action(Customer $customer, array $modelData, int $hydratorsDelay = 0, bool $strict = true): Customer
    {
        $this->asAction       = true;
        $this->customer       = $customer;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->strict         = $strict;
        $this->initialisationFromShop($customer->shop, $modelData);

        return $this->handle($customer, $this->validatedData);
    }


    public function jsonResponse(Customer $customer): CustomersResource
    {
        return new CustomersResource($customer);
    }
}
