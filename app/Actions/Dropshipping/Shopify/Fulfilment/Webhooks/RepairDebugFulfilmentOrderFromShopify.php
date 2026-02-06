<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Tue, 18 Feb 2025 10:56:59 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Fulfilment\Webhooks;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class RepairDebugFulfilmentOrderFromShopify extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public $commandSignature = 'Repair:DebugFulfilmentOrderFromShopify {customerSalesChannel} {webhookId?}';

    public function asCommand(Command $command)
    {
        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->first();

        $this->handle($customerSalesChannel->user, $command->argument('webhookId'));
    }

    /**
     * @throws \Throwable
     */
    public function handle(ShopifyUser $shopifyUser, $webhookId = null): void
    {
        $debugWebhooks = $shopifyUser->debugWebhooks;
        if ($webhookId) {
            $debugWebhooks = $shopifyUser->debugWebhooks()->where('id', $webhookId)->get();
        }

        foreach ($debugWebhooks as $webhook) {
            DB::transaction(function () use ($shopifyUser, $webhook) {
                $fulfillmentOrder = $webhook->data;

                CreateFulfilmentOrderFromShopify::run($shopifyUser, $fulfillmentOrder);
            });
        }
    }
}
