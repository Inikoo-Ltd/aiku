<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:12 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\Dropshipping\Shopify\Fulfilment\FulfillOrderToShopify;
use App\Actions\Dropshipping\WooCommerce\Orders\FulfillOrderToWooCommerce;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Enums\Ordering\Transaction\TransactionStateEnum;
use App\Models\Ordering\Order;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class UpdateStateToDispatchedOrder extends OrgAction
{
    use WithActionUpdate;
    use HasOrderHydrators;

    /**
     * @throws \Throwable
     */
    public function handle(Order $order): Order
    {
        $data = [
            'state'         => OrderStateEnum::DISPATCHED,
            'dispatched_at' => now()
        ];

        DB::transaction(function () use ($order, $data) {
            $order->transactions()->update([
                'state' => TransactionStateEnum::DISPATCHED
            ]);

            $this->update($order, $data);
            $this->orderHydrators($order);

            if ($order->shopifyOrder) {
                FulfillOrderToShopify::run($order);
            }

            match ($order->customerSalesChannel->platform->type) {
                PlatformTypeEnum::WOOCOMMERCE => FulfillOrderToWooCommerce::run($order),
                //                PlatformTypeEnum::EBAY => FulfillOrderToEbay::run($order),
                //                PlatformTypeEnum::AMAZON => FulfillOrderToAmazon::run($order),
                PlatformTypeEnum::SHOPIFY => FulfillOrderToShopify::run($order),
                default => null,
            };
        });

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
