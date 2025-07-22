<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 22 Jul 2025 21:08:21 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Fulfilment\Callback;

use App\Actions\Dropshipping\Shopify\WithShopifyApi;
use App\Actions\OrgAction;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Console\Command;

class RetrieveShopifyAcceptedOrders extends OrgAction
{
    use WithShopifyApi;
    use WithShopifyOrderRetrieval;

    /**
     * @throws \Throwable
     */
    public function handle(ShopifyUser $shopifyUser)
    {
        return $this->retrieveOrders($shopifyUser, 'FULFILLMENT_ACCEPTED');
    }

    /**
     * Process the fulfillment orders
     */
    protected function processFulfillmentOrders(ShopifyUser $shopifyUser, array $fulfillmentOrders): array
    {
        foreach ($fulfillmentOrders as $edge) {
            $fulfillmentOrder = $edge['node'];
            print_r($fulfillmentOrder);
        }

        return [true, 'Retrieved accepted fulfillment orders'];
    }

    public string $commandSignature = 'shopify:retrieve_accepted_orders {customerSalesChannel}';

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): void
    {
        $this->executeCommand($command, 'customerSalesChannel');
    }
}
