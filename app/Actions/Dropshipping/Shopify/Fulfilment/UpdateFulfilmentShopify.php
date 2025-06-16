<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Fulfilment;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\ShopifyUserHasFulfilment;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class UpdateFulfilmentShopify extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public string $commandSignature = 'shopify:fulfilment {order}';

    /**
     * @throws \Exception
     */
    public function handle(Order $order, array $modelData): void
    {
        $fulfilment = ShopifyUserHasFulfilment::where('order_id', $order->id)->first();

        $fulfilmentId = $fulfilment->shopify_fulfilment_id;
        $shopifyUser  = $fulfilment->shopifyUser;

        $body = [
            "fulfillment" => [
                "notify_customer" => true,
                "tracking_info"   => [
                    "company" => Arr::get($modelData, 'company'),
                    "number"  => Arr::get($modelData, 'number')
                ]
            ]
        ];

        $result = $shopifyUser->api()->getRestClient()->request('POST', "admin/api/2024-07/fulfillments/$fulfilmentId/update_tracking.json", $body);
        dd($result);
    }

    public function asCommand(Command $command)
    {
        $order = Order::where('slug', $command->argument('order'))->first();

        $this->handle($order, [
            'company' => 'DHL',
            'number'  => 'DHL0001'
        ]);
    }
}
