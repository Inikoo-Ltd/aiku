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
     * Every fulfilment request is accepted and imported, whatever is wrong with it. An order we
     * refused left nothing behind in AW, so the customer saw no order at all and had to go digging
     * through Shopify to find out why (HELP-3151). The problems are written onto the imported order
     * as notes instead, and the office decides whether to cancel it.
     */
    protected function processFulfillmentOrders(ShopifyUser $shopifyUser, array $fulfillmentOrders): array
    {
        foreach ($fulfillmentOrders as $edge) {
            $fulfillmentOrder = $edge['node'];

            $shopifyUser->debugWebhooks()->create([
                'data' => $fulfillmentOrder
            ]);

            AcceptShopifyFulfillmentRequest::run($shopifyUser, $fulfillmentOrder);
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
