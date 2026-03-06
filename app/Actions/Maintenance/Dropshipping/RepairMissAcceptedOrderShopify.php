<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Fri, 31 Oct 2025 10:21:06 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Dropshipping;

use App\Actions\Dropshipping\Shopify\Fulfilment\Callback\WithShopifyOrderRetrieval;
use App\Actions\Dropshipping\Shopify\Fulfilment\Webhooks\CreateFulfilmentOrderFromShopify;
use App\Actions\Dropshipping\Shopify\WithShopifyApi;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairMissAcceptedOrderShopify
{
    use AsAction;
    use WithActionUpdate;
    use WithShopifyApi;
    use WithShopifyOrderRetrieval;

    /**
     * @throws \Throwable
     */
    public function handle(ShopifyUser $shopifyUser): array
    {
        return $this->retrieveOrders($shopifyUser, 'FULFILLMENT_ACCEPTED');
    }

    protected function processFulfillmentOrders(ShopifyUser $shopifyUser, array $fulfillmentOrders): array
    {
        foreach ($fulfillmentOrders as $edge) {
            $fulfillmentOrder = $edge['node'];
            CreateFulfilmentOrderFromShopify::run($shopifyUser, $fulfillmentOrder);
        }

        return [];
    }

    public function getCommandSignature(): string
    {
        return 'repair:shopify_miss_accepted_order {customerSalesChannel} {portfolio?}';
    }

    public function asCommand(Command $command): void
    {
        $customerSalesChannelSlug = $command->argument('customerSalesChannel');

        if (!blank($customerSalesChannelSlug)) {
            $customerSalesChannel = CustomerSalesChannel::where('slug', $customerSalesChannelSlug)->first();
            $this->handle($customerSalesChannel->user);
        }
    }
}
