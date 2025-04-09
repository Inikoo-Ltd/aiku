<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 26 Jun 2024 15:13:29 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer;

use App\Actions\OrgAction;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\Platform;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;

class AttachCustomerToPlatform extends OrgAction
{
    /**
     * @var \App\Models\CRM\Customer
     */
    private Customer $customer;

    public function handle(Customer $customer, Platform $platform, array $pivotData): Customer
    {
        $pivotData['group_id']        = $this->organisation->group_id;
        $pivotData['organisation_id'] = $this->organisation->id;
        $pivotData['shop_id']         = $customer->shop_id;
        $customer->platforms()->attach($platform->id, $pivotData);


        return $customer;
    }

    public function rules(): array
    {
        return [
            'reference' => 'nullable|string|max:255',
        ];
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        /*        if ($this->customer->platforms()->count() >= 1) {
                    abort(403);
                }*/
    }

    public function action(Customer $customer, Platform $platform, array $modelData): Customer
    {
        $this->customer = $customer;
        $this->initialisation($customer->organisation, $modelData);

        return $this->handle($customer, $platform, $this->validatedData);
    }

    public function asController(Customer $customer, Platform $platform, ActionRequest $request): void
    {
        $this->initialisation($customer->organisation, $request);
        $this->handle($customer, $platform, $this->validatedData);
    }
}
