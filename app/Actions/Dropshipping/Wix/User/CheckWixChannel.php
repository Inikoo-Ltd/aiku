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

        try {
            if ($wixUser->getUserInfo()) {
                $platformStatus = $canConnectToPlatform = $existInPlatform = true;
            }
        } catch (\Exception $e) {
            Sentry::captureException($e);
        }

        $data = [
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

        return UpdateCustomerSalesChannel::run($customerSalesChannel, $data);
    }

    public string $commandSignature = 'wix:check {customerSalesChannel}';

    public function asCommand(Command $command): void
    {
        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->firstOrFail();

        $this->handle($customerSalesChannel->user);
    }
}
