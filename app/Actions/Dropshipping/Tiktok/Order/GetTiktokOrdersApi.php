<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 10 Mar 2025 16:53:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Tiktok\Order;

use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\TiktokUser;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class GetTiktokOrdersApi extends RetinaAction
{
    use WithActionUpdate;

    public string $commandSignature = 'tiktok:get-order {customerSalesChannel?}';

    public function handle(TiktokUser $tiktokUser): void
    {
        $tiktokOrders = $tiktokUser->getOrders([
            'page_size' => 100
        ], [
            'order_status' => 'AWAITING_SHIPMENT'
        ]);

        foreach (Arr::get($tiktokOrders, 'data.orders', []) as $order) {
            ValidateIncomingTiktokOrder::run($tiktokUser, $order);
        }
    }

    public function asCommand(Command $command)
    {
        $platform = Platform::where('type', PlatformTypeEnum::TIKTOK)->firstOrFail();
        $csc = $command->argument('customerSalesChannel');
        if ($csc) {
            $customerSalesChannels = CustomerSalesChannel::where('slug', $csc)->get();
        } else {
            $customerSalesChannels = CustomerSalesChannel::where('platform_id', $platform->id)
                ->where('platform_status', true)
                ->get();
        }

        foreach ($customerSalesChannels as $customerSalesChannel) {
            if ($customerSalesChannel->user) {
                $this->handle($customerSalesChannel->user);
            }
        }
    }
}
