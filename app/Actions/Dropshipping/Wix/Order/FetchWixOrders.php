<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Wix\Order;

use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;

class FetchWixOrders extends RetinaAction
{
    use WithActionUpdate;

    public string $commandSignature = 'wix:fetch-orders';

    public function handle(): void
    {
        $platformIds = Platform::where('type', PlatformTypeEnum::WIX)->pluck('id');

        $customerSalesChannels = CustomerSalesChannel::whereIn('platform_id', $platformIds)
            ->where('can_connect_to_platform', true)
            ->where('exist_in_platform', true)
            ->where('platform_status', true)
            ->get();

        /** @var CustomerSalesChannel $customerSalesChannel */
        foreach ($customerSalesChannels as $customerSalesChannel) {
            if ($customerSalesChannel->user) {
                GetWixOrdersFromApi::dispatch($customerSalesChannel->user);
            }
        }
    }

    public function asCommand(): void
    {
        $this->handle();
    }
}
