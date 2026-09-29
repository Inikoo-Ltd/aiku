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

        $checkResult = $wooCommerceUser->checkConnectionWithError();
        $connection  = $checkResult['success'];

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

        $isBlocked = !$platformStatus && str_contains((string) $checkResult['message'], 'WooCommerce API Connection Error');

        $data = [
            'name'                    => $wooCommerceUser->name,
            'platform_status'         => $platformStatus,
            'can_connect_to_platform' => $canConnectToPlatform,
            'exist_in_platform'       => $existInPlatform,
            'is_blocked'              => $isBlocked,
        ];

        $settings = $wooCommerceUser->customerSalesChannel->settings ?? [];
        data_set($settings, 'woocommerce.not_ready_reason', $platformStatus ? null : $this->notReadyReason($isBlocked));
        $data['settings'] = $settings;

        if ($platformStatus) {
            $data['state']                 = CustomerSalesChannelStateEnum::AUTHENTICATED;
            $data['ban_stock_update_util'] = null;
            $data['ping_error_count']      = 0;
        } else {
            $data['state'] = CustomerSalesChannelStateEnum::NOT_READY;
        }

        return UpdateCustomerSalesChannel::run($wooCommerceUser->customerSalesChannel, $data);
    }

    private function notReadyReason(bool $isBlocked): string
    {
        return $isBlocked
            ? __('Your store did not answer - it may be down or blocking connections from our servers. Ask your hosting provider to allow our requests, then try again.')
            : __('Your store rejected our connection details. Generate a fresh WooCommerce REST API key (Settings > Advanced > REST API) and reconnect the channel.');
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
