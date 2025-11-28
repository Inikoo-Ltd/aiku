<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:12 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order\UpdateState;

use App\Actions\Comms\Email\SendDispatchedOrderEmailToCustomer;
use App\Actions\Comms\Email\SendDispatchedOrderEmailToSubscribers;
use App\Actions\Dropshipping\Ebay\Orders\FulfillOrderToEbay;
use App\Actions\Dropshipping\Magento\Orders\FulfillOrderToMagento;
use App\Actions\Dropshipping\Shopify\Fulfilment\FulfillOrderToShopify;
use App\Actions\Dropshipping\WooCommerce\Orders\FulfillOrderToWooCommerce;
use App\Actions\Ordering\Order\HasOrderHydrators;
use App\Actions\Ordering\Order\UpdateOrder;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Enums\Ordering\Transaction\TransactionStateEnum;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class DispatchOrder extends OrgAction
{
    use WithActionUpdate;
    use HasOrderHydrators;

    /**
     * @throws \Throwable
     */
    public function handle(Order $order): Order
    {
        $oldState = $order->state;
        $data = [
            'state'         => OrderStateEnum::DISPATCHED,
            'dispatched_at' => now()
        ];

        $order = DB::transaction(function () use ($order, $data) {
            /** @var Transaction $transaction */
            foreach ($order->transactions()->where('model_type', 'Product')->get() as $transaction) {
                $transaction->update([
                    'state'               => TransactionStateEnum::DISPATCHED,
                    'quantity_dispatched' => $transaction->quantity_picked,
                ]);
            }

            $this->update($order, $data);

            if ($order->shop->masterShop) {
                $order->shop->masterShop->orderingStats->update(
                    [
                        'last_order_dispatched_at' => now()
                    ]
                );
            }


            $order->refresh();


            if ($order->customerSalesChannel?->user && app()->isProduction()) {
                match ($order->customerSalesChannel->platform->type) {
                    PlatformTypeEnum::WOOCOMMERCE => FulfillOrderToWooCommerce::run($order),
                    PlatformTypeEnum::EBAY => FulfillOrderToEbay::run($order),
                    PlatformTypeEnum::MAGENTO => FulfillOrderToMagento::run($order),
                    //                PlatformTypeEnum::AMAZON => FulfillOrderToAmazon::run($order),
                    PlatformTypeEnum::SHOPIFY => FulfillOrderToShopify::run($order),
                    default => null,
                };
            } elseif ($order->customerSalesChannel?->platform?->type !== PlatformTypeEnum::MANUAL) {
                UpdateOrder::run($order, [
                    'shipping_notes' => __('We\'re unable update shipping to customer\'s sales channel due to their sales channel are not found or already deleted.')
                ]);
            }

            return $order;
        });

        $this->orderHydrators($order);
        $this->orderHandlingHydrators($order, $oldState);
        $this->orderHandlingHydrators($order, OrderStateEnum::DISPATCHED);

        SendDispatchedOrderEmailToSubscribers::dispatch($order);
        SendDispatchedOrderEmailToCustomer::dispatch($order);

        return $order;
    }

    /**
     * @throws \Throwable
     */
    public function action(Order $order): Order
    {
        return $this->handle($order);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Order $order, ActionRequest $request): Order
    {
        $this->initialisationFromShop($order->shop, $request);

        return $this->handle($order);
    }
}
