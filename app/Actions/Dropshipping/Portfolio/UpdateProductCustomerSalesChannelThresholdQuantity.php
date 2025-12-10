<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Tue, 30 Aug 2022 13:05:43 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia F
 */

namespace App\Actions\Dropshipping\Portfolio;

use App\Actions\Dropshipping\Ebay\Product\UpdateEbayPortfolioThreshold;
use App\Actions\Dropshipping\Shopify\Product\UpdateShopifyPortfolioThreshold;
use App\Actions\Dropshipping\WooCommerce\Product\UpdateWooPortfolioThreshold;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Catalogue\Product;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class UpdateProductCustomerSalesChannelThresholdQuantity implements ShouldBeUnique
{
    use WithActionUpdate;

    public string $commandSignature = 'csc:threshold {productId}';

    public function getJobUniqueId(int $productId): string
    {
        return $productId;
    }

    public function handle(int $productId): void
    {
        $product = Product::find($productId);
        $portfolios = $product->portfolios()
            ->whereHas('customerSalesChannel', function ($query) use ($product) {
                return $query->whereNull('closed_at')
                    ->where('platform_status', true)
                    ->where('stock_threshold', '=', $product->available_quantity);
            })
            ->where('platform_status', true)
            ->whereNotNull('platform_product_id')
            ->get();

        foreach ($portfolios as $portfolio) {
            if ($customerSalesChannel = $portfolio->customerSalesChannel) {
                if ($customerSalesChannel->user) {
                    match ($customerSalesChannel->platform->type) {
                        PlatformTypeEnum::EBAY => UpdateEbayPortfolioThreshold::run($customerSalesChannel, $portfolio),
                        PlatformTypeEnum::WOOCOMMERCE => UpdateWooPortfolioThreshold::run($customerSalesChannel, $portfolio),
                        PlatformTypeEnum::SHOPIFY => UpdateShopifyPortfolioThreshold::run($customerSalesChannel, $portfolio),
                        default => null
                    };
                }
            }
        }
    }

    public function asCommand(Command $command): void
    {
        $this->handle($command->argument('productId'));
    }
}
