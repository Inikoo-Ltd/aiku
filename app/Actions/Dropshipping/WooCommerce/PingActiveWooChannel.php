<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 19 Jul 2025 09:01:58 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce;

use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use Lorisleiva\Actions\Concerns\AsAction;

class PingActiveWooChannel
{
    use asAction;
    use WithActionUpdate;

    public string $commandSignature = 'woo:ping_active_channel';

    public function asCommand(): void
    {
        $this->handle();
    }

    public function handle(): void
    {
        $platform = Platform::where('type', PlatformTypeEnum::WOOCOMMERCE)->first();

        $customerSalesChannels = CustomerSalesChannel::where('platform_id', $platform->id)
            ->where('ping_error_count', '<', 12)
            ->get();

        /** @var CustomerSalesChannel $customerSalesChannel */
        foreach ($customerSalesChannels as $customerSalesChannel) {
            if ($customerSalesChannel->user) {
                $customerSalesChannel = CheckWooChannel::run($customerSalesChannel->user);

                if (! $customerSalesChannel->platform_status) {
                    $customerSalesChannel->update([
                        'ping_error_count' => $customerSalesChannel->ping_error_count + 1,
                    ]);
                } else {
                    $customerSalesChannel->update([
                        'ping_error_count' => 0
                    ]);
                }
            }
        }
    }
}
