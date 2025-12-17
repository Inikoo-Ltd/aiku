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
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class PingActiveWooChannel
{
    use asAction;
    use WithActionUpdate;

    public string $commandSignature = 'woo:ping_active_channel';

    public function asCommand(Command $command): void
    {
        $this->handle($command);
    }

    public function handle(Command $command): void
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

                $errorMsg = '-';

                if (is_string(Arr::get($customerSalesChannel->user->data, '0'))) {
                    $errorMsg = Arr::get($customerSalesChannel->user->data, '0');
                } elseif (is_array(Arr::get($customerSalesChannel->user->data, '0'))) {
                    $errorMsg = Arr::get($customerSalesChannel->user->data, '0.message');
                }

                $statusData = [
                    ['Customer Sales Channel', $customerSalesChannel->slug],
                    ['Platform Status', $customerSalesChannel->platform_status ? 'Yes' : 'No'],
                    ['Can Connect to Platform', $customerSalesChannel->can_connect_to_platform ? 'Yes' : 'No'],
                    ['Exist in Platform', $customerSalesChannel->exist_in_platform ? 'Yes' : 'No'],
                    ['Ban', $customerSalesChannel->ban_stock_update_util ?? '-'],
                    ['Ping Error Count', $customerSalesChannel->ping_error_count],
                    ['Error Message', Str::substr($errorMsg, 0, 120)],
                ];


                $command->info("\nCustomer Sales Channel Status:");
                $command->table(['Field', 'Value'], $statusData);
            }
        }
    }
}
