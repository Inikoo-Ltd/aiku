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

class DetachCustomerToPlatform extends OrgAction
{
    /**
     * @var \App\Models\CRM\Customer
     */
    private Customer $customer;

    public function handle(Customer $customer, Platform $platform): Customer
    {
        $customer->platforms()->detach($platform->id);

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

    public function asController(Organisation $organisation, Customer $customer, Platform $platform, ActionRequest $request): void
    {
        $this->initialisation($organisation, $request);
        $this->handle($customer, $platform, $this->validatedData);
    }
}
