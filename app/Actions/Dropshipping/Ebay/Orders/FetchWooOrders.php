<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 05 Jul 2025 10:39:37 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Ebay\Orders;

use App\Actions\Dropshipping\WooCommerce\Orders\FetchWooUserOrders;
use App\Actions\RetinaAction;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use Lorisleiva\Actions\Concerns\AsCommand;
use Sentry;

class FetchWooOrders extends RetinaAction
{
    use AsCommand;

    public string $commandSignature = 'fetch:woo-orders';


    public function asCommand(): void
    {
        $this->handle();
    }

    public function handle(): void
    {
        $platform = Platform::where('type', PlatformTypeEnum::WOOCOMMERCE)->first();

        $customerSalesChannels = CustomerSalesChannel::where('platform_id', $platform->id)
            ->where('can_connect_to_platform', true)
            ->where('exist_in_platform', true)
            ->where('platform_status', true)
            ->get();

        /** @var CustomerSalesChannel $customerSalesChannel */
        foreach ($customerSalesChannels as $customerSalesChannel) {
            if ($customerSalesChannel->user) {
                try {
                    FetchWooUserOrders::dispatch($customerSalesChannel->user);
                } catch (\Exception $e) {
                    Sentry::captureException($e);
                }
            }
        }
    }
}
