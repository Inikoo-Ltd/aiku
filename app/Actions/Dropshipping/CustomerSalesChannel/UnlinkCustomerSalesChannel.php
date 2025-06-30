<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 26 Jun 2024 15:13:29 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\CustomerSalesChannel;

use App\Actions\Dropshipping\Magento\DeleteMagentoUser;
use App\Actions\Dropshipping\ShopifyUser\DeleteRetinaShopifyUser;
use App\Actions\Dropshipping\WooCommerce\DeleteWooCommerceUser;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use Lorisleiva\Actions\ActionRequest;

class UnlinkCustomerSalesChannel extends OrgAction
{
    use WithActionUpdate;

    public function handle(CustomerSalesChannel $customerSalesChannel): void
    {
        $hasOrders = $customerSalesChannel->orders()->exists();
        $hasFulfilmentOrders = $customerSalesChannel->fulfilmentOrders()->exists();

        UpdateCustomerSalesChannel::run($customerSalesChannel, [
            'status' => CustomerSalesChannelStatusEnum::CLOSED
        ]);

        if ($customerSalesChannel->user) {
            match ($customerSalesChannel->platform->type) {
                PlatformTypeEnum::SHOPIFY => DeleteRetinaShopifyUser::run($customerSalesChannel->user),
                PlatformTypeEnum::WOOCOMMERCE => DeleteWooCommerceUser::run($customerSalesChannel->user),
                PlatformTypeEnum::MAGENTO => DeleteMagentoUser::run($customerSalesChannel->user),
                default => null
            };
        }

        if (!$hasFulfilmentOrders || !$hasOrders) {
            $customerSalesChannel->delete();
        }
    }

    public function action(CustomerSalesChannel $customerSalesChannel, array $modelData, int $hydratorsDelay = 0): void
    {
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisation($customerSalesChannel->organisation, $modelData);

        $this->handle($customerSalesChannel);
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): void
    {
        $this->initialisation($customerSalesChannel->organisation, $request);
        $this->handle($customerSalesChannel);
    }
}
