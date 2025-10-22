<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 19 Jul 2025 09:01:58 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Ebay;

use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use Lorisleiva\Actions\Concerns\AsAction;

class CheckAllEbayChannels
{
    use asAction;
    use WithActionUpdate;

    public function handle(): void
    {
        $platform = Platform::where('type', PlatformTypeEnum::EBAY)->first();

        $customerSalesChannels = CustomerSalesChannel::where('platform_id', $platform->id)
            ->where('can_connect_to_platform', true)
            ->where('exist_in_platform', true)
            ->where('platform_status', true)
            ->get();

        /** @var CustomerSalesChannel $customerSalesChannel */
        foreach ($customerSalesChannels as $customerSalesChannel) {
            if ($customerSalesChannel->user) {
                $customerSalesChannel = CheckEbayChannel::run($customerSalesChannel->user);

                if (! $customerSalesChannel->platform_status && $customerSalesChannel->ping_error_count < 12) {
                    $customerSalesChannel->update([
                        'ping_error_count' => $customerSalesChannel->ping_error_count + 1,
                    ]);
                }
            }
        }

    }

    public function getCommandSignature(): string
    {
        return 'ebay:ping';
    }

    public function asCommand(): void
    {
        $this->handle();
    }
}
