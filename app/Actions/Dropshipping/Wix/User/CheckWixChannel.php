<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Wix\User;

use App\Actions\Dropshipping\CustomerSalesChannel\UpdateCustomerSalesChannel;
use App\Enums\Dropshipping\CustomerSalesChannelStateEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\WixUser;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use Sentry;

class CheckWixChannel
{
    use AsAction;

    public function handle(WixUser $wixUser): ?CustomerSalesChannel
    {
        $platformStatus = $canConnectToPlatform = $existInPlatform = false;

        $customerSalesChannel = $wixUser->customerSalesChannel;

        if (!$customerSalesChannel) {
            return null;
        }

        $reason = null;

        try {
            if ($wixUser->getUserInfo()) {
                $canConnectToPlatform = $existInPlatform = true;

                if ($wixUser->hasWixStores()) {
                    $platformStatus = true;
                } else {
                    $reason = __('Wix Stores is not installed on this site. Add the Wix Stores app to the site, then reconnect the channel.');
                }
            }
        } catch (\Exception $e) {
            Sentry::captureException($e);

            $reason = $e->getMessage();
        }

        $data = [
            'platform_status'         => $platformStatus,
            'can_connect_to_platform' => $canConnectToPlatform,
            'exist_in_platform'       => $existInPlatform
        ];

        $settings = $customerSalesChannel->settings ?? [];
        data_set($settings, 'wix.not_ready_reason', $reason);
        $data['settings'] = $settings;

        if ($platformStatus) {
            $data['state']                 = CustomerSalesChannelStateEnum::AUTHENTICATED;
            $data['ban_stock_update_util'] = null;
        } else {
            $data['state'] = CustomerSalesChannelStateEnum::NOT_READY;
        }

        return UpdateCustomerSalesChannel::run($customerSalesChannel, $data);
    }

    public string $commandSignature = 'wix:check {customerSalesChannel}';

    public function asCommand(Command $command): void
    {
        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->firstOrFail();

        $this->handle($customerSalesChannel->user);
    }
}
