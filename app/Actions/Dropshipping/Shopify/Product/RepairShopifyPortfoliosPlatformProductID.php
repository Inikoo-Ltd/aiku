<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 27 Jul 2025 13:37:25 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairShopifyPortfoliosPlatformProductID
{
    use AsAction;


    public function getCommandSignature(): string
    {
        return 'shopify:repair_product_id';
    }


    private function isNumericOnly(?string $platformProductId): bool
    {
        if (is_null($platformProductId)) {
            return false;
        }

        // Check if the ID consists only of digits
        return preg_match('/^\d+$/', $platformProductId) === 1;
    }

    public function asCommand(Command $command): void
    {
        $shopifyPlatform = Platform::where('type', PlatformTypeEnum::SHOPIFY)->first();

        if (!$shopifyPlatform) {
            $command->error('Shopify platform not found');

            return;
        }

        $command->info('Starting to process portfolios in chunks...');

        // Query portfolios that are in the platform (have a platform_id)
        Portfolio::where('platform_id', $shopifyPlatform->id)
            ->whereNotNull('platform_product_id')
            ->chunkById(1000, function ($portfolios) use ($command) {
                $command->info('Processing chunk of '.$portfolios->count().' portfolios...');

                foreach ($portfolios as $portfolio) {
                    $platformProductId = $portfolio->platform_product_id;

                    // Check if the platform product ID consists only of numbers
                    if ($this->isNumericOnly($platformProductId)) {
                        $command->info('Updating platform product ID for portfolio '.$platformProductId);


                        $correctPlatformProductId = 'gid://shopify/Product/'.$platformProductId;

                        $portfolio->update(['platform_product_id' => $correctPlatformProductId]);
                    }
                }

                $command->info('Chunk processed successfully.');
            });

        $command->info('All portfolios have been processed.');
    }


}
