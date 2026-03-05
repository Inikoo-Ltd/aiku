<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 05 Mar 2026 16:53:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Allegro\User;

use App\Actions\Dropshipping\CustomerSalesChannel\UpdateCustomerSalesChannel;
use App\Models\AllegroUser;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class CheckAllegroChannel
{
    use AsAction;

    public function handle(AllegroUser $allegroUser): void
    {
        try {
            $platformStatus = $canConnectToPlatform = $existInPlatform = false;

            $customerSalesChannel = $allegroUser->customerSalesChannel;

            if (!$customerSalesChannel) {
                return;
            }

            $isExpired = $allegroUser->access_token_expire_in && now()->greaterThanOrEqualTo(Carbon::createFromTimestamp($allegroUser->access_token_expire_in));

            // Try to refresh token if expired
            if ($isExpired && $allegroUser->refresh_token) {
                try {
                    $allegroUser->refreshAndPersistTokens();
                    $isExpired = false;
                } catch (\Exception $e) {
                    Log::error('Failed to refresh Allegro token: ' . $e->getMessage());
                }
            }

            $checkConnection = $allegroUser->getUserInfo();

            if ($checkConnection) {
                $platformStatus = $canConnectToPlatform = $existInPlatform = true;
            }

            $data = [
                'platform_status'         => $platformStatus,
                'can_connect_to_platform' => $canConnectToPlatform,
                'exist_in_platform'       => $existInPlatform
            ];

            UpdateCustomerSalesChannel::run($customerSalesChannel, $data);
        } catch (\Exception $e) {
            Log::error('Failed to check Allegro channel: ' . $e->getMessage());
        }
    }
}
