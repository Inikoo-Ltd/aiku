<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Wix\Order;

use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\WixUser;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class GetWixOrdersFromApi extends RetinaAction
{
    use WithActionUpdate;

    public string $commandSignature = 'wix:fetch-order {customerSalesChannel}';

    public function handle(WixUser $wixUser): void
    {
        $response = $wixUser->searchOrders([
            'filter' => [
                'status'            => ['$eq' => 'APPROVED'],
                'fulfillmentStatus' => ['$eq' => 'NOT_FULFILLED'],
            ],
            'cursorPaging' => [
                'limit' => 100
            ],
        ]);

        foreach (Arr::get($response, 'orders', []) as $wixOrder) {
            ValidateIncomingWixOrder::run($wixUser, $wixOrder);
        }
    }

    public function asCommand(Command $command): void
    {
        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->firstOrFail();

        $this->handle($customerSalesChannel->user);
    }
}
