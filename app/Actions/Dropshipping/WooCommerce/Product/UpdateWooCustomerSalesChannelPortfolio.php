<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2025 08:26:56 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce\Product;

use App\Models\Catalogue\Product;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateWooCustomerSalesChannelPortfolio implements ShouldBeUnique
{
    use AsAction;


    public string $jobQueue = 'woo';

    public function getJobUniqueId(CustomerSalesChannel $customerSalesChannel): string
    {
        return $customerSalesChannel->id;
    }

    public function handle(CustomerSalesChannel $customerSalesChannel): void
    {
        $portfolios = Portfolio::where('customer_sales_channel_id', $customerSalesChannel->id)
            ->whereNotNull('platform_product_id')
            ->where('item_type', 'Product')
            ->where('platform_status', true)
            ->get();


        $portfoliosID = [];

        foreach ($portfolios->chunk(100) as $portfolioChunk) {
            foreach ($portfolioChunk as $portfolio) {
                if ($this->checkIfApplicable($portfolio)) {
                    $portfoliosID[$portfolio->id] = $portfolio->id;
                }
            }
        }

        // modify this so you call api only one time
        foreach ($portfoliosID as $portfolioId) {
            $portfolio = Portfolio::find($portfolioId);
            if ($portfolio) {
                UpdateWooPortfolio::run($portfolio->id);
            }
        }
    }

    public function checkIfApplicable(Portfolio $portfolio): bool
    {
        $applicable = false;


        if (!$portfolio->stock_last_updated_at) {
            $applicable = true;
        } else {
            /** @var Product $product */
            $product = $portfolio->item;

            if (!$product->available_quantity_updated_at || $product->available_quantity_updated_at->gt($portfolio->stock_last_updated_at)) {
                $applicable = true;
            }
        }

        return $applicable;
    }
}
