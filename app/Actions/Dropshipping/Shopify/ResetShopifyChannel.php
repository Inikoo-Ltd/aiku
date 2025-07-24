<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 19 Jul 2025 09:01:58 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify;

use App\Actions\Dropshipping\Shopify\FulfilmentService\DeleteAllFulfilmentServices;
use App\Actions\Dropshipping\Shopify\FulfilmentService\StoreFulfilmentService;
use App\Actions\Dropshipping\Shopify\Webhook\DeleteWebhooksFromShopify;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class ResetShopifyChannel
{
    use asAction;

    public function handle(CustomerSalesChannel $customerSalesChannel): void
    {
        DeleteWebhooksFromShopify::run($customerSalesChannel->user);
        DeleteAllFulfilmentServices::run($customerSalesChannel);
        StoreFulfilmentService::run($customerSalesChannel);

        CheckShopifyChannel::run($customerSalesChannel);
    }

    public function getCommandSignature(): string
    {
        return 'shopify:reset {customerSalesChannel}';
    }

    public function asCommand(Command $command): void
    {
        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->firstOrFail();
        $this->handle($customerSalesChannel);
    }

    public function asController(CustomerSalesChannel $customerSalesChannel): void
    {
        $this->handle($customerSalesChannel);
    }

}
