<?php

/*
 * Author: Eka Yudinata <ekayudinatha@gmail.com>
 * Created: 2025-11-06 11:00:00
 * Copyright (c) 2025, Eka Yudinata
 */

namespace App\Actions\Dropshipping\WooCommerce;

use App\Actions\RetinaAction;
use App\Actions\Dropshipping\CustomerSalesChannel\UpdateCustomerSalesChannel;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dropshipping\CustomerSalesChannelStateEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\WooCommerceUser;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Support\Facades\Log;

class CheckWooStatus extends RetinaAction
{

    public function handle($slug)
    {

        $canConnectToPlatform = false;

        $customerSalesChannel = CustomerSalesChannel::where('slug', $slug)->firstOrFail();
        $wooCommerceUser = $customerSalesChannel->user;

        $connection = $wooCommerceUser->checkConnection();

        if (Arr::has($connection, 'environment')) {
            $canConnectToPlatform = true;
        }

        if ($canConnectToPlatform) {

            // $customerSalesChannel->update([
            //     'is_down' => null,
            //     'checked_as_down_at' => null,
            //     'checked_as_down_days' => null,
            //     'number_downside' => null,
            // ]);
        }
        
        return [
            "can_connect_to_platform" => $canConnectToPlatform,
        ];
    }

    public function asController($slug, ActionRequest $request)
    {
        return $this->handle($slug);
    }
}