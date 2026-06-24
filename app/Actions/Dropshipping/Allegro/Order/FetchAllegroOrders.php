<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 10 Mar 2025 16:53:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Allegro\Order;

use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use Sentry;

class FetchAllegroOrders extends RetinaAction
{
    use WithActionUpdate;

    public string $commandSignature = 'allegro:fetch-orders';

    public function handle(): void
    {
        $platform = Platform::where('type', PlatformTypeEnum::ALLEGRO)->first();

        $customerSalesChannels = CustomerSalesChannel::where('platform_id', $platform->id)
            ->where('can_connect_to_platform', true)
            ->where('exist_in_platform', true)
            ->where('platform_status', true)
            ->get();

        /** @var CustomerSalesChannel $customerSalesChannel */
        foreach ($customerSalesChannels as $customerSalesChannel) {
            if ($customerSalesChannel->user) {
                GetAllegroOrdersFromApi::dispatch($customerSalesChannel->user);
            }
        }
    }

    public function asCommand(): void
    {
        $this->handle();
    }
}
