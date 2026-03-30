<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 22 Jul 2025 21:08:21 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Fulfilment\Callback;

use App\Actions\Dropshipping\Shopify\WithShopifyApi;
use App\Actions\Ordering\Order\UpdateState\CancelOrder;
use App\Actions\OrgAction;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;

class RetrieveShopifyCancelledOrders extends OrgAction
{
    use WithShopifyApi;
    use WithShopifyOrderRetrieval;

    /**
     * @throws \Throwable
     */
    public function handle(ShopifyUser $shopifyUser)
    {
        return $this->retrieveOrders($shopifyUser, 'CANCELLATION_REQUESTED');
    }


    protected function processFulfillmentOrders(ShopifyUser $shopifyUser, array $fulfillmentOrders): array
    {
        $fulfillmentOrdersId = collect($fulfillmentOrders)
            ->pluck('node.id')
            ->filter()
            ->values()
            ->all();
        $orders = Order::whereIn('platform_order_id', $fulfillmentOrdersId)->get()->keyBy('platform_order_id');

        foreach ($fulfillmentOrders as $edge) {
            $fulfillmentOrder = $edge['node'];
            $fulfillmentOrderId = data_get($fulfillmentOrder, 'id', null);

            if ($fulfillmentOrderId) {
                $currOrder = $orders->get($fulfillmentOrderId);

                if ($currOrder && in_array($currOrder->state, [
                    OrderStateEnum::CREATING,
                    OrderStateEnum::SUBMITTED,
                    OrderStateEnum::IN_WAREHOUSE
                ])) {
                    AcceptShopifyCancellationRequest::run($shopifyUser, $fulfillmentOrder['id'], __("Order cancellation request has been accepted."));
                    CancelOrder::run($currOrder);
                } elseif ($currOrder) {
                    RejectShopifyCancellationRequest::run($shopifyUser, $fulfillmentOrder['id'], __("Order cancellation request has been rejected. Processed orders can not be cancelled"));
                }

            }
        }

        return [true, 'Cancellations processed'];
    }

    public string $commandSignature = 'shopify:retrieve_cancelled_orders {customerSalesChannel}';

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): void
    {
        $this->executeCommand($command, 'customerSalesChannel');
    }
}
