<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:32:25 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer;

use App\Actions\Helpers\Address\Hydrators\AddressHydrateUsage;
use App\Actions\OrgAction;
use App\Models\CRM\Customer;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Helpers\Address;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class DeleteCustomerDeliveryAddress extends OrgAction
{
    public function handle(Customer $customer, array $modelData)
    {
        $address = Address::find(Arr::get($modelData, 'address_id'));
        $customer->addresses()->detach($address->id);

        $address->delete();

        AddressHydrateUsage::dispatch($address);
        return $customer;
    }

    public function rules(): array
    {
        return [
            'address_id' => ['required', Rule::exists('addresses', 'id')],
        ];
    }

    public function asController(FulfilmentCustomer $fulfilmentCustomer, ActionRequest $actionRequest): void
    {
        $this->initialisationFromFulfilment($fulfilmentCustomer->fulfilment, $actionRequest);

        $this->handle($fulfilmentCustomer->customer, $this->validatedData);
    }
}