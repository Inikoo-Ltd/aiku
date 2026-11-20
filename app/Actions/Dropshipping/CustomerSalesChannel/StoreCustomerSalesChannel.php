<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 26 Jun 2024 15:13:29 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\CustomerSalesChannel;

use App\Actions\Catalogue\ShopPlatformStats\ShopPlatformStatsHydrateCustomers;
use App\Actions\Catalogue\ShopPlatformStats\ShopPlatformStatsHydrateCustomerSalesChannel;
use App\Actions\Dropshipping\Platform\Hydrators\PlatformHydrateCustomers;
use App\Actions\OrgAction;
use App\Enums\Dropshipping\CustomerSalesChannelStateEnum;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreCustomerSalesChannel extends OrgAction
{
    public function handle(Customer $customer, Platform $platform, array $modelData): CustomerSalesChannel
    {
        if ($platform->type == PlatformTypeEnum::MANUAL) {
            data_set($modelData, 'can_connect_to_platform', true);
            data_set($modelData, 'exist_in_platform', true);
            data_set($modelData, 'platform_status', true);
        }

        data_set($modelData, 'status', CustomerSalesChannelStatusEnum::OPEN, overwrite: false);

        $modelData['group_id']          = $customer->group_id;
        $modelData['organisation_id']   = $customer->organisation_id;
        $modelData['shop_id']           = $customer->shop_id;
        $modelData['platform_id']       = $platform->id;

        if ($customer->shop->customersChooseChannelLanguage()) {
            data_set($modelData, 'language_id', $this->customerLanguageId($customer), overwrite: false);
        }

        /** @var CustomerSalesChannel $customerSalesChannel */
        $customerSalesChannel           = $customer->customerSalesChannels()->create($modelData);

        PlatformHydrateCustomers::dispatch($platform)->delay($this->hydratorsDelay);
        ShopPlatformStatsHydrateCustomers::dispatch($customer->shop, $platform)->delay($this->hydratorsDelay);
        ShopPlatformStatsHydrateCustomerSalesChannel::dispatch($customer->shop, $platform)->delay($this->hydratorsDelay);


        return $customerSalesChannel;
    }

    /**
     * The language of the customer's main web user, when the shop offers it.
     */
    public function customerLanguageId(Customer $customer): ?int
    {
        $languageId = $customer->webUsers()->orderByDesc('is_root')->orderBy('id')->value('language_id');

        return in_array($languageId, $customer->shop->enabledLanguageIds()) ? $languageId : null;
    }

    public function rules(): array
    {
        return [
            'name'               => 'nullable|string|max:255',
            'reference'          => 'nullable|string|max:255',
            'platform_user_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'platform_user_id'   => ['sometimes', 'nullable', 'integer'],
            'state'              => ['sometimes', 'nullable', Rule::enum(CustomerSalesChannelStateEnum::class)],
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
