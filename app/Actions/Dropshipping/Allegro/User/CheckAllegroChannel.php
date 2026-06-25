<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 05 Mar 2026 16:53:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Allegro\User;

use App\Actions\Dropshipping\CustomerSalesChannel\UpdateCustomerSalesChannel;
use App\Enums\Dropshipping\CustomerSalesChannelStateEnum;
use App\Models\Dropshipping\AllegroUser;
use App\Models\Dropshipping\CustomerSalesChannel;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Sentry;

class CheckAllegroChannel
{
    use AsAction;

    public function handle(AllegroUser $allegroUser): void
    {
        $platformStatus = $canConnectToPlatform = $existInPlatform = false;

        $customerSalesChannel = $allegroUser->customerSalesChannel;

        if (!$customerSalesChannel) {
            return;
        }

        $isExpired = $allegroUser->access_token_expire_in && now()->greaterThanOrEqualTo(Carbon::createFromTimestamp($allegroUser->access_token_expire_in));

        if ($isExpired && $allegroUser->refresh_token) {
            try {
                $allegroUser->refreshAndPersistTokens();
            } catch (\Exception $e) {
                Sentry::captureException($e);
            }
        }

        try {
            $checkConnection = $allegroUser->getUserInfo();

            if ($checkConnection) {
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

        UpdateCustomerSalesChannel::run($customerSalesChannel, $data);
    }

    public string $commandSignature = 'allegro:check {customerSalesChannel}';

    public function asCommand(Command $command): void
    {
        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->firstOrFail();

        $this->handle($customerSalesChannel->user);
    }
}
