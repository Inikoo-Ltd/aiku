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

class DeleteWebhooksFromShopify extends OrgAction
{
    use WithActionUpdate;

    /**
     * @throws \Exception
     */
    public function handle(ShopifyUser $shopifyUser): void
    {
        $webhooks = $shopifyUser->api()->getRestClient()->request('GET', 'admin/api/2024-07/webhooks.json');
        $body     = Arr::get($webhooks, 'body', []);

        if (Arr::exists($body, 'webhooks')) {
            foreach (Arr::get($body, 'webhooks', []) as $webhook) {
                $webhookId = $webhook['id'];
                $shopifyUser->api()->getRestClient()->request('DELETE', "admin/api/2024-07/webhooks/$webhookId.json");
            }

            $this->update($shopifyUser, [
                'settings' => []
            ]);
        }
    }
}
