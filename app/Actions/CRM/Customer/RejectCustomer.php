<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Jun 2024 22:24:08 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateCrmStats;
use App\Actions\Comms\Email\SendCustomerRejectEmail;
use App\Actions\Fulfilment\Fulfilment\Hydrators\FulfilmentHydrateCustomers;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\CRM\Customer\CustomerRejectReasonEnum;
use App\Enums\CRM\Customer\CustomerStatusEnum;
use App\Models\CRM\Customer;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class RejectCustomer extends OrgAction
{
    use WithActionUpdate;
    use WithCRMEditAuthorisation;

    public function handle(Customer $customer, array $modelData): Customer
    {
        data_set($modelData, 'status', CustomerStatusEnum::REJECTED);
        data_set($modelData, 'rejected_at', now());

        if ($modelData['rejected_reason'] === CustomerRejectReasonEnum::SPAM->value) {
            data_set($modelData, 'rejected_notes', __('rejected.spam'));
        }

        if ($modelData['rejected_reason'] === CustomerRejectReasonEnum::DUPLICATED->value) {
            data_set($modelData, 'rejected_notes', __('rejected.duplicated'));
        }

        $customer = $this->update($customer, $modelData);
        ShopHydrateCrmStats::dispatch($customer->shop);

        if ($customer->fulfilmentCustomer) {
            FulfilmentHydrateCustomers::dispatch($customer->fulfilmentCustomer->fulfilment);
        }

        if (!$this->asAction) {
            SendCustomerRejectEmail::dispatch($customer);
        }


        return $customer;
    }

    public function rules(): array
    {
        return [
            'rejected_reason' => ['required', 'string', Rule::enum(CustomerRejectReasonEnum::class)],
            'rejected_notes' => [
                'required_if:rejected_reason,other',
                'string'
            ],
        ];
    }

    public function asController(Customer $customer, ActionRequest $request): Customer
    {
        $this->initialisationFromShop($customer->shop, $request);

        return $this->handle($customer, $this->validatedData);
    }

    public function action(Customer $customer, array $modelData): Customer
    {
        $this->asAction = true;
        $this->initialisation($customer->organisation, $modelData);

        return $this->handle($customer, $this->validatedData);
    }
}
