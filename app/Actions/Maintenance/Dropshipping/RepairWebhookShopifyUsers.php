<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 12 Jul 2025 20:47:59 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Dropshipping;

use App\Actions\Dropshipping\Shopify\Webhook\DeleteWebhooksFromShopify;
use App\Actions\Dropshipping\Shopify\Webhook\IndexShopifyUserWebhooks;
use App\Actions\Dropshipping\Shopify\WithShopifyApi;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairWebhookShopifyUsers
{
    use AsAction;
    use WithActionUpdate;
    use WithShopifyApi;

    public function handle(ShopifyUser $shopifyUser)
    {


        if ($shopifyUser->getShopifyClient()) {
            print "Shopify User: {$shopifyUser->name} - {$shopifyUser->id}\n";


            $webhooksData = IndexShopifyUserWebhooks::run($shopifyUser);


            if ($webhooksData[0] && is_array($webhooksData[1])) {
                foreach ($webhooksData[1] as $webhook) {
                    if (in_array($webhook['topic'], ['PRODUCTS_DELETE', 'PRODUCTS_UPDATE'])) {
                        $webhookId = $webhook['id'];
                        print "Deleting webhook: {$webhook['topic']} - $webhookId\n";

                        DeleteWebhooksFromShopify::make()->deleteWebhook($shopifyUser, $webhookId);
                    }
                }
            }
        }
    }

    public function getCommandSignature(): string
    {
        return 'repair:shopify_webhooks';
    }

    public function asCommand(): void
    {

        foreach (ShopifyUser::withTrashed()->orderBy('id', 'desc')->get() as $shopifyUser) {
            $this->handle($shopifyUser);
        }
    }

}
