<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Tue, 18 Feb 2025 10:56:59 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Ebay\Fulfilment;

use App\Actions\Dropshipping\Ebay\Orders\StoreOrderFromEbay;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\EbayUser;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class RepairDebugFulfilmentOrderFromEbay extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public $commandSignature = 'Repair:DebugFulfilmentOrderFromEbay {customerSalesChannel} {webhookId?}';

    public function asCommand(Command $command)
    {
        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->first();

        $this->handle($customerSalesChannel->user, $command->argument('webhookId'));
    }

    /**
     * @throws \Throwable
     */
    public function handle(EbayUser $ebayUser, $webhookId = null): void
    {
        $debugWebhooks = $ebayUser->debugWebhooks;
        if ($webhookId) {
            $debugWebhooks = $ebayUser->debugWebhooks()->where('id', $webhookId)->get();
        }

        foreach ($debugWebhooks as $webhook) {
            DB::transaction(function () use ($ebayUser, $webhook) {
                $fulfillmentOrder = $webhook->data;

                try {

                    if (Arr::get($fulfillmentOrder, 'cancelStatus.cancelState') == 'CANCELED') {
                        return;
                    }

                    if (DB::table('orders')->where('customer_id', $ebayUser->customer_id)
                        ->where('platform_order_id', Arr::get($fulfillmentOrder, 'orderId'))
                        ->exists()) {
                        return;
                    }

                    $lineItems = collect(Arr::get($fulfillmentOrder, 'lineItems', []))->pluck('legacyItemId')->filter()->toArray();

                    $hasOutProducts = DB::table('portfolios')->where('customer_sales_channel_id', $ebayUser->customer_sales_channel_id)
                        ->whereIn('platform_product_variant_id', $lineItems)->exists();

                    if ($hasOutProducts) {
                        StoreOrderFromEbay::run($ebayUser, $fulfillmentOrder);
                    }
                } catch (\Exception $e) {
                    dd($e->getMessage());
                }
            });
        }
    }
}
