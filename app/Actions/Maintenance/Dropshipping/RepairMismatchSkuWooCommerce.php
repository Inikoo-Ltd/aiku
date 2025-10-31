<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Fri, 31 Oct 2025 10:21:06 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Dropshipping;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\WooCommerceUser;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairMismatchSkuWooCommerce
{
    use AsAction;
    use WithActionUpdate;

    public function handle(WooCommerceUser $wooCommerceUser, Collection $portfolios): void
    {
        $collected = [];
        foreach ($portfolios as $portfolio) {
            $fetchedPortfolio = $wooCommerceUser->getWooCommerceProduct($portfolio->platform_product_id, false);
            $sku = Arr::get($fetchedPortfolio, 'sku');

            if (Str::lower($sku) !== $portfolio->sku) {
                $collected['update'][] = [
                    'sku' => $portfolio->sku,
                    'id' => $portfolio->platform_product_id
                ];
            }
        }

        $wooCommerceUser->batchUpdateWooCommerceProducts($collected);
    }

    public function getCommandSignature(): string
    {
        return 'repair:woo_miss_sku {customerSalesChannel} {portfolio?}';
    }

    public function asCommand(Command $command): void
    {
        $customerSalesChannelSlug = $command->argument('customerSalesChannel');

        if (! blank($customerSalesChannelSlug)) {
            $customerSalesChannel = CustomerSalesChannel::where('slug', $customerSalesChannelSlug)->first();
            if ($portfolioSlug = $command->argument('portfolio')) {
                $portfolioChunk = Portfolio::where('item_code', $portfolioSlug)
                    ->where('customer_sales_channel_id', $customerSalesChannel->id)
                    ->whereNotNull('platform_product_id')
                    ->get()
                    ->chunk(100);
            } else {
                $portfolioChunk = Portfolio::where('customer_sales_channel_id', $customerSalesChannel->id)
                    ->whereNotNull('platform_product_id')
                    ->get()
                    ->chunk(100);
            }

            foreach ($portfolioChunk as $portfolios) {
                $this->handle($customerSalesChannel->user, $portfolios);
            }
        } else {
            foreach (WooCommerceUser::all() as $wooUser) {
                $portfolios = $wooUser->customerSalesChannel->portfolios;

                $this->handle($wooUser, $portfolios);
            }
        }
    }
}
