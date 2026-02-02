<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 27 Jul 2025 13:37:25 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairShopifyPortfoliosUpdateDescription
{
    use AsAction;


    public function getCommandSignature(): string
    {
        return 'shopify:repair_description {customerSalesChannel}';
    }

    public function handle(Portfolio $portfolio, Command $command): void
    {
        list($status, $response) = UpdateShopifyProduct::run($portfolio);

        if ($status === true) {
            $command->info("Successfully updated shopify product $portfolio->item_code description.\n");
        }
    }

    public function asCommand(Command $command): void
    {
        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->first();
        $shopifyPlatform = $customerSalesChannel->platform;
        if (!$shopifyPlatform) {
            $command->error('Shopify platform not found');

            return;
        }

        $command->info('Starting to process portfolios in chunks...');

        // Query portfolios that are in the platform (have a platform_id)
        Portfolio::where('platform_id', $shopifyPlatform->id)
            ->where('customer_sales_channel_id', $customerSalesChannel->id)
            ->whereNotNull('platform_product_id')
            ->chunkById(1000, function ($portfolios) use ($command) {
                $command->info('Processing chunk of '.$portfolios->count().' portfolios...');

                foreach ($portfolios as $portfolio) {
                    $this->handle($portfolio, $command);
                }

                $command->info('Chunk processed successfully.');
            });

        $command->info('All portfolios have been processed.');
    }


}
