<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 10 Mar 2025 16:53:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Allegro\Order;

use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\AllegroUser;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class FetchAllegroOrdersFromApi extends RetinaAction
{
    use WithActionUpdate;

    public string $commandSignature = 'allegro:fetch-order {customerSalesChannel}';

    public function handle(AllegroUser $allegroUser): void
    {
        $allegroOrders = $allegroUser->getOrders([
            'status' => 'READY_FOR_PROCESSING'
        ]);

        foreach (Arr::get($allegroOrders, 'checkoutForms', []) as $allegroOrder) {
            ValidateIncomingAllegroOrder::run($allegroUser, $allegroOrder);
        }
    }

    public function asCommand(Command $command)
    {
        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->firstOrFail();

        $this->handle($customerSalesChannel->user);
    }
}
