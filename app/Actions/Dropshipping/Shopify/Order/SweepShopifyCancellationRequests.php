<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Oct 2026 14:30:00 Central European Standard Time, Trnava, Slovakia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Order;

use App\Actions\Dropshipping\Shopify\Fulfilment\Callback\RetrieveShopifyCancelledOrders;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use App\Models\Ordering\Order;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A cancellation request whose notification found nothing, twice, would otherwise wait until the
 * order ships (PRE-34). Only stores with an order AW can still cancel are read, which keeps the
 * Shopify calls to a handful; each store's requests are then answered as the webhook answers them.
 * Channels that are not open are swept too, as their cancellation requests still go through.
 */
class SweepShopifyCancellationRequests
{
    use AsAction;

    public string $commandSignature = 'shopify:sweep-cancellation-requests';

    private const int SPREAD_SECONDS = 600;

    public function handle(): int
    {
        $platform = Platform::where('type', PlatformTypeEnum::SHOPIFY)->first();
        $dispatched = 0;

        $customerSalesChannels = CustomerSalesChannel::where('platform_id', $platform->id)
            ->where('platform_status', true)
            ->whereIn('id', Order::whereIn('state', [OrderStateEnum::CREATING, OrderStateEnum::SUBMITTED, OrderStateEnum::IN_WAREHOUSE])
                ->where('platform_order_id', 'like', 'gid://shopify/FulfillmentOrder/%')
                ->select('customer_sales_channel_id'))
            ->with('user')
            ->get();

        foreach ($customerSalesChannels as $customerSalesChannel) {
            if ($customerSalesChannel->user) {
                RetrieveShopifyCancelledOrders::dispatch($customerSalesChannel->user)
                    ->delay(now()->addSeconds(rand(0, self::SPREAD_SECONDS)));
                $dispatched++;
            }
        }

        return $dispatched;
    }

    public function asCommand(): void
    {
        Nightwatch::dontSample();
        $this->handle();
    }
}
