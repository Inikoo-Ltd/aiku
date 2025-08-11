<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Tue, 18 Feb 2025 10:56:59 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Fulfilment\Callback;

use App\Actions\Dropshipping\Shopify\WithShopifyApi;
use App\Actions\OrgAction;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Console\Command;

class RetrieveShopifyAssignedOrders extends OrgAction
{
    use WithShopifyApi;
    use WithShopifyOrderRetrieval;

    /**
     * @throws \Throwable
     */
    public function handle(ShopifyUser $shopifyUser)
    {
        return $this->retrieveOrders($shopifyUser, 'FULFILLMENT_REQUESTED');
    }

    /**
     * Process the fulfillment orders
     */
    protected function processFulfillmentOrders(ShopifyUser $shopifyUser, array $fulfillmentOrders): array
    {
        foreach ($fulfillmentOrders as $edge) {
            $fulfillmentOrder = $edge['node'];

            if (!isset($fulfillmentOrder['destination'])) {
                RejectShopifyFulfillmentRequest::run($shopifyUser, $fulfillmentOrder['id'], __("Order don't have shipping information"));
            } else {
                AcceptShopifyFulfillmentRequest::run($shopifyUser, $fulfillmentOrder);
            }
        }

        return [true, 'Retrieved assigned fulfillment orders'];
    }

    public string $commandSignature = 'shopify:retrieve_orders {customerSalesChannel}';

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): void
    {
        $this->executeCommand($command, 'customerSalesChannel');
    }
}
