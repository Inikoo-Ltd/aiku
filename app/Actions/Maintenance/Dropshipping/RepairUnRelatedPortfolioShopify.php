<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Fri, 31 Oct 2025 10:21:06 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Dropshipping;

use App\Actions\Retina\Dropshipping\Portfolio\UnlinkAndDeleteBulkRetinaPortfolio;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairUnRelatedPortfolioShopify
{
    use AsAction;
    use WithActionUpdate;

    public function handle(ShopifyUser $shopifyUser, Collection $portfolios): void
    {
        UnlinkAndDeleteBulkRetinaPortfolio::run([
            'portfolios' => $portfolios->pluck('id')->toArray()
        ]);

        /*$products = $shopifyUser->getAllShopifyProducts(250);
        $productIds = collect($products['products'])->pluck('id')->toArray();

        echo count($productIds) . "\n";

        $response = $shopifyUser->batchDeleteShopifyProducts($productIds);
        dd($response);*/
    }

    public function getCommandSignature(): string
    {
        return 'repair:shopify_unrelated_sku {customerSalesChannel} {portfolio?}';
    }

    public function asCommand(Command $command): void
    {
        $customerSalesChannelSlug = $command->argument('customerSalesChannel');

        if (!blank($customerSalesChannelSlug)) {
            $customerSalesChannel = CustomerSalesChannel::where('slug', $customerSalesChannelSlug)->first();
            $portfolios = Portfolio::where('customer_sales_channel_id', $customerSalesChannel->id)
                ->get();
            echo count($portfolios) . "\n";
            $this->handle($customerSalesChannel->user, $portfolios);
        }
    }
}
