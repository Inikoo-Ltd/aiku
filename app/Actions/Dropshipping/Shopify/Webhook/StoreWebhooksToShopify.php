<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:22 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Webhook;

use App\Actions\Dropshipping\ShopifyUser\RegisterCustomerFromShopify;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Fulfilment\Fulfilment;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Route;

class StoreWebhooksToShopify extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public string $commandSignature = 'shopify:webhook {shopify}';

    /**
     * @throws \Exception
     */
    public function handle(ShopifyUser $shopifyUser, ?Fulfilment $fulfilment)
    {
        $webhooks     = [];
        $webhookTypes = [];
        $routes       = collect(Route::getRoutes())->filter(function ($route) {
            return str_contains($route->getName(), 'webhooks.shopify');
        });

        foreach ($routes as $route) {
            $webhookTypes[] =             [
                "type"  => str_replace('webhooks/shopify-user/{shopifyUser}/', '', $route->uri()),
                "route" => route($route->getName(), [
                    'shopifyUser' => $shopifyUser->id
                ])
            ];
        }

        foreach ($webhookTypes as $webhookType) {
            $webhooks[]      = [
                "webhook" => [
                    "topic"   => $webhookType["type"],
                    "address" => $webhookType["route"],
                    "format"  => "json"
                ]
            ];
        }

        DB::transaction(function () use ($webhooks, $shopifyUser, $fulfilment) {
            DeleteWebhooksFromShopify::run($shopifyUser);

            $webhooksData = [];
            foreach ($webhooks as $webhook) {
                $webhook = $shopifyUser->api()->getRestClient()->request('POST', 'admin/api/2024-07/webhooks.json', $webhook);

                if (!$webhook['errors'] && is_array($webhook['body']['webhook']['container'])) {
                    $webhooksData[] = $webhook['body']['webhook']['container'];
                }
            }

            $fulfilmentCustomer = $shopifyUser?->customer?->fulfilmentCustomer;
            if (!$fulfilmentCustomer && $fulfilment) {
                RegisterCustomerFromShopify::run($shopifyUser, $fulfilment);
            }

            $this->update($shopifyUser, [
                'settings' => [
                    'webhooks' => $webhooksData
                ]
            ]);
        });
    }

    public function asController(ShopifyUser $shopifyUser, ActionRequest $request)
    {
        $shop = Shop::find($request->input('shop'));

        $this->handle($shopifyUser, $shop->fulfilment);
    }
}
