<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Jun 2026 12:54:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Ebay\Orders;

use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use Lorisleiva\Actions\Concerns\AsAction;

class FetchEbayOrders
{

    use AsAction;

    public string $commandSignature = 'fetch:ebay-orders';

    public function asCommand(): void
    {
        $this->handle();
    }

    public function handle(): void
    {
        $platform = Platform::where('type', PlatformTypeEnum::EBAY)->first();

        $customerSalesChannels = CustomerSalesChannel::where('platform_id', $platform->id)
            ->where('status', CustomerSalesChannelStatusEnum::OPEN)
            ->get();

        /** @var CustomerSalesChannel $customerSalesChannel */
        foreach ($customerSalesChannels as $customerSalesChannel) {
            if ($customerSalesChannel->user) {
                $randomDelay = rand(1, 3600);
                FetchEbayUserOrders::dispatch($customerSalesChannel->user)->delay(now()->addSeconds($randomDelay));
            }
        }
    }
}
