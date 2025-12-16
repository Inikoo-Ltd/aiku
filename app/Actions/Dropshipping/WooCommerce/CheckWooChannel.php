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
    use asAction;
    use WithActionUpdate;

    public function handle(WooCommerceUser $wooCommerceUser): CustomerSalesChannel
    {
        $platformStatus = $canConnectToPlatform = $existInPlatform = false;

        $webhooks = [];
        if (Arr::has($wooCommerceUser->settings, 'webhooks')) {
            $webhooks = $wooCommerceUser->registerWooCommerceWebhooks();
        }

        $connection = $wooCommerceUser->checkConnection();

        if ($connection) {
            $platformStatus       = true;
            $canConnectToPlatform = true;
            $existInPlatform      = true;
        }

        if (!blank($webhooks)) {
            $this->update($wooCommerceUser, [
                'settings' => array_merge($wooCommerceUser->settings, [
                    'webhooks' => $webhooks
                ])
            ]);
        }

        $data = [
            'state'                   => CustomerSalesChannelStateEnum::AUTHENTICATED,
            'name'                    => $wooCommerceUser->name,
            'platform_status'         => $platformStatus,
            'can_connect_to_platform' => $canConnectToPlatform,
            'exist_in_platform'       => $existInPlatform
        ];
        if ($platformStatus) {
            $data['ban_stock_update_util'] = null;
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
        $customerSalesChannel = $this->handle($customerSalesChannel->user);

        // Display CustomerSalesChannel status information
        $statusData = [
            ['Customer Sales Channel', $customerSalesChannel->slug],
            ['Platform Status', $customerSalesChannel->platform_status ? 'Yes' : 'No'],
            ['Can Connect to Platform', $customerSalesChannel->can_connect_to_platform ? 'Yes' : 'No'],
            ['Exist in Platform', $customerSalesChannel->exist_in_platform ? 'Yes' : 'No'],
            ['Ban', $customerSalesChannel->ban_stock_update_util ?? '-']
        ];


        $command->info("\nCustomer Sales Channel Status:");
        $command->table(['Field', 'Value'], $statusData);

        $command->info("\nShop data updated successfully.");
    }
}
