<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 26 Sep 2026 10:30:00 British Summer Time, Sheffield, UK
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Ebay\Orders;

use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

class FetchEbayOrdersOnCustomerActivity
{
    use AsAction;

    public function handle(Customer $customer): void
    {
        if (!Cache::add('ebay-orders-fetched-on-activity:'.$customer->id, true, now()->addMinutes(10))) {
            return;
        }

        $customerSalesChannels = $customer->customerSalesChannels()
            ->whereHas('platform', fn ($query) => $query->where('type', PlatformTypeEnum::EBAY))
            ->where('status', CustomerSalesChannelStatusEnum::OPEN)
            ->where('platform_status', true)
            ->get();

        /** @var CustomerSalesChannel $customerSalesChannel */
        foreach ($customerSalesChannels as $customerSalesChannel) {
            if ($customerSalesChannel->user) {
                FetchEbayUserOrders::dispatch($customerSalesChannel->user);
            }
        }
    }
}
