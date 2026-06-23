<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 19 Jul 2025 09:01:58 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce;

use App\Actions\Dropshipping\CustomerSalesChannel\UpdateCustomerSalesChannel;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dropshipping\CustomerSalesChannelStateEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\WooCommerceUser;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class CheckWooChannel
{
    use AsAction;
    use WithActionUpdate;

    public function handle(WooCommerceUser $wooCommerceUser): CustomerSalesChannel
    {
        $platformStatus = $canConnectToPlatform = $existInPlatform = false;

        $connection = $wooCommerceUser->checkConnection();

        if ($connection) {
            $platformStatus       = true;
            $canConnectToPlatform = true;
            $existInPlatform      = true;

            $webhooks = Arr::get($wooCommerceUser->settings, 'webhooks', []);
            if (blank($webhooks)) {
                $webhooks = $wooCommerceUser->registerWooCommerceWebhooks();

                $this->update($wooCommerceUser, [
                    'settings' => array_merge($wooCommerceUser->settings, [
                        'webhooks' => $webhooks
                    ])
                ]);
            }

            $weightOption = Arr::get($wooCommerceUser->settings, 'weight_option');
            if (blank($weightOption)) {
                $weightOption = $wooCommerceUser->getProductWeightSettings();

                $this->update($wooCommerceUser, [
                    'settings' => array_merge($wooCommerceUser->settings, [
                        'weight_option' => $weightOption
                    ])
                ]);
            }
        }

        $data = [
            'name'                    => $wooCommerceUser->name,
            'platform_status'         => $platformStatus,
            'can_connect_to_platform' => $canConnectToPlatform,
            'exist_in_platform'       => $existInPlatform
        ];
        if ($platformStatus) {
            $data['state']                 = CustomerSalesChannelStateEnum::AUTHENTICATED;
            $data['ban_stock_update_util'] = null;
        } else {
            $data['state'] = CustomerSalesChannelStateEnum::NOT_READY;
        }

        return UpdateCustomerSalesChannel::run($wooCommerceUser->customerSalesChannel, $data);
    }


    public function getCommandSignature(): string
    {
        return 'woo:check {customerSalesChannel}';
    }

    public function asCommand(Command $command): void
    {
        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->firstOrFail();
        $updatedChannel       = $this->handle($customerSalesChannel->user);

        $statusData = [
            ['Customer Sales Channel', $updatedChannel->slug],
            ['Platform Status', $updatedChannel->platform_status ? 'Yes' : 'No'],
            ['Can Connect to Platform', $updatedChannel->can_connect_to_platform ? 'Yes' : 'No'],
            ['Exist in Platform', $updatedChannel->exist_in_platform ? 'Yes' : 'No'],
            ['Ban', $updatedChannel->ban_stock_update_util ?? '-']
        ];


        $command->info("\nCustomer Sales Channel Status:");
        $command->table(['Field', 'Value'], $statusData);

        $command->info("\nShop data updated successfully.");
    }
}
