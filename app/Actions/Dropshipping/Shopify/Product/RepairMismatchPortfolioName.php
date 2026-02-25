<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 27 Jul 2025 13:37:25 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Actions\Dropshipping\Portfolio\StorePortfolio;
use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairMismatchPortfolioName
{
    use AsAction;
    use WithActionUpdate;


    public function getCommandSignature(): string
    {
        return 'portfolio:repair_name {customerSalesChannel}';
    }

    public function handle(CustomerSalesChannel $customerSalesChannel, Command $command): void
    {
        $portfolios = $customerSalesChannel->portfolios;

        foreach ($portfolios as $portfolio) {
            /** @var Product $product */
            $product = Product::where('shop_id', $customerSalesChannel->shop_id)
                ->where('code', $portfolio->item_code)
                ->first();

            if (!$product) {
                continue;
            }

            $portfolio = UpdatePortfolio::run($portfolio, [
                'item_name' => $product->name,
                'customer_product_name' => $product->name,
                'item_id' => $product->id,
                'sku' => StorePortfolio::make()->getSku($product)
            ]);

            if ($portfolio->customer_product_name === $product->name) {
                $command->info("Successfully update portfolio $portfolio->item_id name $portfolio->customer_product_name.\n");
            }
        }
    }

    public function asCommand(Command $command): void
    {
        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))
            ->first();

        $this->handle($customerSalesChannel, $command);
    }
}
