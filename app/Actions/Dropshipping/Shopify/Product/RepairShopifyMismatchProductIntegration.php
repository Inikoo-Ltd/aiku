<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 27 Jul 2025 13:37:25 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Actions\Dropshipping\Shopify\WithShopifyApi;
use App\Actions\Retina\Dropshipping\Portfolio\UnlinkRetinaPortfolio;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairShopifyMismatchProductIntegration
{
    use WithShopifyApi;
    use AsAction;


    public function getCommandSignature(): string
    {
        return 'RepairShopifyMismatchProductIntegration {customerSalesChannel}';
    }

    public function handle(CustomerSalesChannel $customerSalesChannel, Command $command): void
    {
        foreach ($customerSalesChannel->portfolios as $portfolio) {
            if ($portfolio->item->shop_id !== $portfolio->shop_id) {
                $command->info("Repairing portfolio $portfolio->item_code Shop ID: {$portfolio->item->shop_id}");
                UnlinkRetinaPortfolio::run($portfolio);
            }
        }
    }

    public function asCommand(Command $command): void
    {
        $customerSalesChannel = $command->argument('customerSalesChannel');
        $command->info("Starting to repair...");
        $customerSalesChannel = CustomerSalesChannel::where('slug', $customerSalesChannel)->first();

        $this->handle($customerSalesChannel, $command);
    }
}
