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
use Lorisleiva\Actions\Concerns\AsAction;

class CheckWooChannel
{
    use asAction;
    use WithActionUpdate;

    public function handle(WooCommerceUser $wooCommerceUser): CustomerSalesChannel
    {
        $platformStatus = $canConnectToPlatform = $existInPlatform = false;
        $webhooks = $wooCommerceUser->registerWooCommerceWebhooks();

        if ($wooCommerceUser->checkConnection() && ! blank($webhooks)) {
            $platformStatus = true;
            $canConnectToPlatform = true;
            $existInPlatform = true;
        }

        return UpdateCustomerSalesChannel::run($wooCommerceUser->customerSalesChannel, [
            'state' => CustomerSalesChannelStateEnum::AUTHENTICATED,
            'name'                    => $wooCommerceUser->name,
            'platform_status'         => $platformStatus,
            'can_connect_to_platform' => $canConnectToPlatform,
            'exist_in_platform'       => $existInPlatform,
            'settings' => array_merge($wooCommerceUser->settings, [
                'webhooks' => $webhooks
            ])
        ]);
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
            ['Exist in Platform', $customerSalesChannel->exist_in_platform ? 'Yes' : 'No']
        ];


        $shopData = $customerSalesChannel->user->data['shop'] ?? [];


        if (empty($shopData)) {
            $command->info("No shop data found.");

            return;
        }

        $command->info("\nCustomer Sales Channel Status:");
        $command->table(['Field', 'Value'], $statusData);

        $command->info("\nShop data updated successfully.");
    }
}
