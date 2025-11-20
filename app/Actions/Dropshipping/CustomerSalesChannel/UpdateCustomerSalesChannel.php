<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Tue, 30 Aug 2022 13:05:43 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia F
 */

namespace App\Actions\Dropshipping\CustomerSalesChannel;

use App\Actions\Dropshipping\Ebay\UpdateShippingPolicyEbayUser;
use App\Actions\Dropshipping\Platform\Shop\Hydrators\ShopHydratePlatformSalesIntervalsNewChannels;
use App\Actions\Dropshipping\Platform\Shop\Hydrators\ShopHydratePlatformSalesIntervalsNewCustomers;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dropshipping\CustomerSalesChannelStateEnum;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\AmazonUser;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\EbayUser;
use App\Models\Dropshipping\MagentoUser;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Dropshipping\WooCommerceUser;
use App\Rules\IUnique;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateCustomerSalesChannel extends OrgAction
{
    use WithActionUpdate;


    private CustomerSalesChannel $customerSalesChannel;

    public function handle(CustomerSalesChannel $customerSalesChannel, array $modelData): CustomerSalesChannel
    {
        /** @var EbayUser|WooCommerceUser|ShopifyUser|MagentoUser|AmazonUser|Model $platformUser */
        $platformUser = $customerSalesChannel->user;

        $shippingService = Arr::pull($modelData, 'shipping_service');
        $shippingPrice = Arr::pull($modelData, 'shipping_price');
        $shippingDispatchTime = Arr::pull($modelData, 'shipping_max_dispatch_time');

        if (Arr::has($modelData, 'is_vat_adjustment')) {
            data_set($modelData, 'settings.tax_category.checked', Arr::get($modelData, 'is_vat_adjustment'));
        }

        if (Arr::has($modelData, 'tax_category_id')) {
            data_set($modelData, 'settings.tax_category.id', Arr::get($modelData, 'tax_category_id'));
        }

        if ($shippingService) {
            $shippingServiceData = $platformUser->getServicesWithCarrierInfo()[$shippingService];
            data_set($modelData, 'settings.shipping', $shippingServiceData);
        }
        if ($shippingPrice) {
            data_set($modelData, 'settings.shipping.price', $shippingPrice);
        }
        if ($shippingDispatchTime) {
            data_set($modelData, 'settings.shipping.max_dispatch_time', $shippingDispatchTime);
        }

        data_forget($modelData, 'tax_category_id');
        data_forget($modelData, 'is_vat_adjustment');

        $customerSalesChannel = $this->update($customerSalesChannel, $modelData, 'settings');
        $changes = Arr::except($customerSalesChannel->getChanges(), ['updated_at', 'last_fetched_at']);

        if ($customerSalesChannel->platform->type === PlatformTypeEnum::EBAY) {
            if ($shippingService || $shippingPrice || $shippingDispatchTime) {
                UpdateShippingPolicyEbayUser::run($customerSalesChannel->user, $modelData);
            }
        }

        if (Arr::has($changes, 'status')) {
            ShopHydratePlatformSalesIntervalsNewChannels::dispatch($customerSalesChannel->shop, $customerSalesChannel->platform->id)->delay($this->hydratorsDelay);
            ShopHydratePlatformSalesIntervalsNewCustomers::dispatch($customerSalesChannel->shop, $customerSalesChannel->platform->id)->delay($this->hydratorsDelay);

        }

        return $customerSalesChannel;



    }

    public function rules(): array
    {
        return [
            'reference'         => [
                'sometimes',
                'required',
                'max:255',
                'string',
                new IUnique(
                    table: 'customer_sales_channels',
                    extraConditions: [
                        ['column' => 'customer_id', 'value' => $this->customerSalesChannel->customer_id],
                        [
                            'column'   => 'id',
                            'operator' => '!=',
                            'value'    => $this->customerSalesChannel->id
                        ]
                    ]
                ),
            ],
            'tax_category_id'   => ['sometimes', 'integer', Rule::exists('tax_categories', 'id')],
            'status'            => ['sometimes', Rule::enum(CustomerSalesChannelStatusEnum::class)],
            'state'             => ['sometimes', Rule::enum(CustomerSalesChannelStateEnum::class)],
            'name'              => ['sometimes', 'string', 'max:255'],
            'shipping_service'              => ['sometimes', 'string', 'max:255'],
            'shipping_price'              => ['sometimes', 'string', 'max:255'],
            'shipping_max_dispatch_time'              => ['sometimes', 'string', 'max:255'],
            'closed_at'         => ['sometimes', 'date'],
        ];
    }

    public function action(CustomerSalesChannel $customerSalesChannel, array $modelData, int $hydratorsDelay = 0): CustomerSalesChannel
    {
        $this->asAction             = true;
        $this->customerSalesChannel = $customerSalesChannel;
        $this->hydratorsDelay       = $hydratorsDelay;
        $this->initialisation($customerSalesChannel->organisation, $modelData);

        return $this->handle($customerSalesChannel, $this->validatedData);
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): CustomerSalesChannel
    {
        $this->customerSalesChannel = $customerSalesChannel;

        $this->initialisationFromShop($customerSalesChannel->shop, $request);

        return $this->handle($customerSalesChannel, $this->validatedData);
    }


}
