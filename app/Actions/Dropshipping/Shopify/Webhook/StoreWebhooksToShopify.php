<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:22 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Webhook;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Route;

class StoreWebhooksToShopify extends OrgAction
{
    use WithActionUpdate;


    /**
     * @throws \Throwable
     */
    public function handle(ShopifyUser $shopifyUser): void
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

        DB::transaction(function () use ($webhooks, $shopifyUser) {

            DeleteWebhooksFromShopify::run($shopifyUser);

            $webhooksData = [];
            foreach ($webhooks as $webhook) {
                $webhook = $shopifyUser->api()->getRestClient()->request('POST', 'admin/api/2024-07/webhooks.json', $webhook);
                if (! blank(Arr::get($webhook, 'body.webhook'))) {
                    $webhooksData[] = Arr::get($webhook, 'body.webhook');
                }
            }

            $this->update($shopifyUser, [
                'settings' => [
                    'webhooks' => $webhooksData
                ]
            ]);
        });
    }


}
