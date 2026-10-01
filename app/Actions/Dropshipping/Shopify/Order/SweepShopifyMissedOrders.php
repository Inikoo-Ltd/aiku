<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 09:00:00 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Order;

use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Only requests AW already accepted are swept: a request still waiting may be in the hands of the
 * webhook right now, and splitting it twice would send duplicate rejects and splits to the store.
 */
class SweepShopifyMissedOrders
{
    use AsAction;

    public string $commandSignature = 'shopify:sweep-missed-orders';

    private const int DAYS = 7;

    private const int SPREAD_SECONDS = 3000;

    public function handle(): int
    {
        $platform = Platform::where('type', PlatformTypeEnum::SHOPIFY)->first();
        $dispatched = 0;

        $customerSalesChannels = CustomerSalesChannel::where('platform_id', $platform->id)
            ->where('platform_status', true)
            ->where('status', CustomerSalesChannelStatusEnum::OPEN)
            ->with('user')
            ->get();

        foreach ($customerSalesChannels as $customerSalesChannel) {
            if ($customerSalesChannel->user) {
                FetchShopifyOrdersFromApi::dispatch($customerSalesChannel->user, self::DAYS, true)
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
