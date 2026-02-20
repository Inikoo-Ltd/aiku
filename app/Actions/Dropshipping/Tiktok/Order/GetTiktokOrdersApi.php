<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 10 Mar 2025 16:53:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Tiktok\Order;

use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\TiktokUser;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class GetTiktokOrdersApi extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public string $commandSignature = 'tiktok:get-order {customerSalesChannel}';

    public function handle(TiktokUser $tiktokUser): void
    {
        $tiktokOrders = $tiktokUser->getOrders([
            'page_size' => 100
        ], [
            'status' => 'AWAITING_SHIPMENT'
        ]);

        foreach (Arr::get($tiktokOrders, 'data.orders') as $order) {
            ValidateIncomingTiktokOrder::run($tiktokUser, $order);
        }
    }

    public function asCommand(Command $command)
    {
        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->firstOrFail();

        $this->handle($customerSalesChannel->user);
    }
}
