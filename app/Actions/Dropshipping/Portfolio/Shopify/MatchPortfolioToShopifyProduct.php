<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 21 Jul 2025 18:17:12 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Portfolio\Shopify;

use App\Actions\Dropshipping\Shopify\Product\FindShopifyProductVariant;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Console\Command;

class MatchPortfolioToShopifyProduct
{

    public function handle(Portfolio $portfolio)
    {
        $shopifyUser = $portfolio->customerSalesChannel->user;

        $search = trim($portfolio->sku.' '.$portfolio->barcode);

        return FindShopifyProductVariant::run($shopifyUser, $search);
    }

    public function getCommandSignature(): string
    {
        return 'portfolio:match_shopify_product {portfolio}';
    }

    public function asCommand(Command $command): void
    {
        $portfolio = Portfolio::where('slug', $command->argument('portfolio'))->first();


        $result = $this->handle($portfolio);

        print_r($result);
    }
}
