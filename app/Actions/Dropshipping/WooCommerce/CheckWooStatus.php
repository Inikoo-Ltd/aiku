<?php

/*
 * Author: Eka Yudinata <ekayudinatha@gmail.com>
 * Created: 2025-11-06 11:00:00
 * Copyright (c) 2025, Eka Yudinata
 */

namespace App\Actions\Dropshipping\WooCommerce;

use App\Actions\RetinaAction;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Support\Carbon;
use App\Actions\Dropshipping\CustomerSalesChannel\CloseCustomerSalesChannel;

class CheckWooStatus extends RetinaAction
{
    public function handle($slug): bool
    {
        $canConnectToPlatform = false;

        $customerSalesChannel = CustomerSalesChannel::where('slug', $slug)->firstOrFail();
        $wooCommerceUser = $customerSalesChannel->user;

        $connection = $wooCommerceUser->checkConnection();

        if ($connection) {
            $canConnectToPlatform = true;
        }

        // If can't connect to platform or it means the channel is down
        if (!$canConnectToPlatform) {

            $now = Carbon::now();
            $lastCheckedAt = $customerSalesChannel->checked_as_down_at;

            // Check if this is a different day than the last check
            $isDifferentDay = !$lastCheckedAt ||
                              !Carbon::parse($lastCheckedAt)->isSameDay($now);

            $customerSalesChannel->update([
                'is_down' => true,
                'checked_as_down_at' => now(),
                'checked_as_down_days' => $isDifferentDay
                    ? ($customerSalesChannel->checked_as_down_days ?? 0) + 1
                    : ($customerSalesChannel->checked_as_down_days ?? 0),
                'number_downside' => $customerSalesChannel->number_downside + 1,
            ]);
        } else {
            // reset the counter
            $customerSalesChannel->update([
                'is_down' => false,
                'checked_as_down_at' => null,
                'checked_as_down_days' => null,
                'number_downside' => null,
            ]);
        }

        // after 30 days still down, close the channel
        if ($customerSalesChannel->checked_as_down_days > 30) {
            CloseCustomerSalesChannel::run($customerSalesChannel);
        }

        return $canConnectToPlatform;
    }

    public function asController($slug)
    {
        return $this->handle($slug);
    }
}
