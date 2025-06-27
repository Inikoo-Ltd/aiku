<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 26 Jun 2024 15:13:29 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\CustomerSalesChannel;

use App\Actions\Dropshipping\Platform\Hydrators\PlatformHydrateCustomers;
use App\Actions\OrgAction;
use App\Enums\Dropshipping\CustomerSalesChannelStateEnum;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreCustomerSalesChannel extends OrgAction
{
    public function handle(Customer $customer, Platform $platform, array $modelData): CustomerSalesChannel
    {
        $modelData['group_id']        = $customer->group_id;
        $modelData['organisation_id'] = $customer->organisation_id;
        $modelData['shop_id']         = $customer->shop_id;
        $modelData['platform_id']         = $platform->id;
        $customerSalesChannel = $customer->customerSalesChannels()->create($modelData);

        PlatformHydrateCustomers::dispatch($platform)->delay($this->hydratorsDelay);

        return $customerSalesChannel;
    }

    public function rules(): array
    {
        return [
            'name' => 'nullable|string|max:255',
            'reference' => 'nullable|string|max:255',
            'platform_user_type' => ['sometimes','nullable','string','max:255'],
            'platform_user_id'   => ['sometimes','nullable','integer'],
            'state'   => ['sometimes','nullable', Rule::enum(CustomerSalesChannelStateEnum::class)],
        ];
    }


    public function action(Customer $customer, Platform $platform, array $modelData, int $hydratorsDelay = 0): CustomerSalesChannel
    {
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisation($customer->organisation, $modelData);

        return $this->handle($customer, $platform, $this->validatedData);
    }

    public function asController(Customer $customer, Platform $platform, ActionRequest $request): void
    {
        $this->initialisation($customer->organisation, $request);
        $this->handle($customer, $platform, $this->validatedData);
    }
}
