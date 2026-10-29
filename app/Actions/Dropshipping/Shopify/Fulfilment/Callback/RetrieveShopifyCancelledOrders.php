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
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Shopify can send the cancellation request notification before the request shows in
 * assignedFulfillmentOrders (PRE-34: notified at 10:02:54, request sent at 10:02:55), so the read
 * the webhook triggers finds nothing. The webhook therefore runs this again a minute later, and
 * SweepShopifyCancellationRequests runs it for every store with an order still to cancel.
 * CancelOrder refunds the order every time it runs, so the webhook, the retry and the sweep take
 * turns per store and the order state is read only once the turn is held.
 */
class RetrieveShopifyCancelledOrders extends OrgAction
{
    use WithShopifyApi;
    use WithShopifyOrderRetrieval;

    public const int RETRY_SECONDS = 60;

    private const int LOCK_SECONDS = 120;

    private const int LOCK_WAIT_SECONDS = 15;

    public string $jobQueue = 'shopify';

    public int $jobTries = 1;

    /**
     * @throws \Throwable
     */
    public function handle(ShopifyUser $shopifyUser): array
    {
        return $this->retrieveOrders($shopifyUser, 'CANCELLATION_REQUESTED');
    }


    protected function processFulfillmentOrders(ShopifyUser $shopifyUser, array $fulfillmentOrders): array
    {
        if (!$fulfillmentOrders) {
            return [true, 'No cancellation requests'];
        }

        try {
            return Cache::lock('shopify_cancellation_requests_'.$shopifyUser->id, self::LOCK_SECONDS)
                ->block(self::LOCK_WAIT_SECONDS, fn () => $this->answerCancellationRequests($shopifyUser, $fulfillmentOrders));
        } catch (LockTimeoutException) {
            return [false, 'Cancellation requests skipped, another run held the lock for over '.self::LOCK_WAIT_SECONDS.' seconds'];
        }
    }

    private function answerCancellationRequests(ShopifyUser $shopifyUser, array $fulfillmentOrders): array
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
                } elseif ($currOrder?->state === OrderStateEnum::CANCELLED) {
                    AcceptShopifyCancellationRequest::run($shopifyUser, $fulfillmentOrder['id'], __("Order cancellation request has been accepted."));
                } elseif ($currOrder) {
                    RejectShopifyCancellationRequest::run($shopifyUser, $fulfillmentOrder['id'], __("Order cancellation request has been rejected. Processed orders can not be cancelled"));
                }

            }
        }

        return [true, 'Cancellations processed'];
    }

    /**
     * @throws \Throwable
     */
    public function asJob(ShopifyUser $shopifyUser): void
    {
        [$status, $message] = $this->handle($shopifyUser);

        if (!$status) {
            Log::warning('Shopify cancellation requests retry failed for '.$shopifyUser->customerSalesChannel?->slug.' ('.$shopifyUser->name.'): '.$message);
        }
    }

    public string $commandSignature = 'shopify:retrieve_cancelled_orders {customerSalesChannel}';

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        return $this->executeCommand($command, 'customerSalesChannel');
    }
}
