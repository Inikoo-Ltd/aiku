<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2025 08:26:56 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\AsCommand;

class UpdateInventoryInShopifyCustomerSalesChannel
{
    use AsCommand;
    use AsAction;

    public string $commandSignature = 'shopify:update-inventory-customer-sales-channel {customerSalesChannelId}';

    public function handle(CustomerSalesChannel $customerSalesChannel): void
    {
        if ($customerSalesChannel->user) {
            BulkUpdateShopifyPortfolio::dispatch($customerSalesChannel->id);
        }
    }

    public function asCommand(Command $command): void
    {
        $customerSalesChannelId = $command->argument('customerSalesChannelId');
        $customerSalesChannel   = CustomerSalesChannel::findOrFail($customerSalesChannelId);
        $this->handle($customerSalesChannel);
    }
}
