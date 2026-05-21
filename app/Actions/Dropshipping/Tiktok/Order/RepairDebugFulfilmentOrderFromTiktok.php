<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Tue, 18 Feb 2025 10:56:59 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Tiktok\Order;

use App\Actions\Dropshipping\Shopify\Fulfilment\Webhooks\CreateFulfilmentOrderFromShopify;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Dropshipping\TiktokUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class RepairDebugFulfilmentOrderFromTiktok extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public $commandSignature = 'Repair:DebugFulfilmentOrderFromTiktok {customerSalesChannel} {webhookId?}';

    public function asCommand(Command $command)
    {
        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->first();

        $this->handle($customerSalesChannel->user, $command->argument('webhookId'));
    }

    /**
     * @throws \Throwable
     */
    public function handle(TiktokUser $tiktokUser, $webhookId = null): void
    {
        $debugWebhooks = $tiktokUser->debugWebhooks;
        if ($webhookId) {
            $debugWebhooks = $tiktokUser->debugWebhooks()->where('id', $webhookId)->get();
        }

        foreach ($debugWebhooks as $webhook) {
            DB::transaction(function () use ($tiktokUser, $webhook) {
                $fulfillmentOrder = $webhook->data;

                ValidateIncomingTiktokOrder::run($tiktokUser, $fulfillmentOrder);
            });
        }
    }
}
