<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Fri, 31 Oct 2025 10:21:06 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Dropshipping;

use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\WooCommerceUser;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairMismatchShopProductsWoo
{
    use AsAction;
    use WithActionUpdate;

    public function handle(CustomerSalesChannel $customerSalesChannel, Collection $portfolios): void
    {
        /** @var WooCommerceUser $wooUser */
        $wooUser = $customerSalesChannel->user;

        foreach ($portfolios->chunk(20) as $portfolioChunk) {
            foreach ($portfolioChunk as $portfolio) {
                /** @var Product $product */
                $product = $portfolio->item;

                UpdatePortfolio::run($portfolio, [
                    'selling_price'         => $product->rrp,
                    'item_name'             => $product->name,
                    'customer_product_name' => $product->name,
                    'customer_price'        => $product->price,
                    'customer_description'  => $product->description
                ]);

                $response = $wooUser->updateWooCommerceProduct($portfolio->platform_product_id, [
                    'name' => $product->name,
                    'description' => $product->description,
                    'short_description' => $product->description,
                    'regular_price' => $product->price,
                    'price' => $product->rrp
                ]);

                echo Arr::get($response, 'name')."\n";
            }

            sleep(1);
        }
    }

    public function getCommandSignature(): string
    {
        return 'repair:woo_mismatch_shop_products {customerSalesChannel} {portfolio?}';
    }

    public function asCommand(Command $command): void
    {
        $customerSalesChannelSlug = $command->argument('customerSalesChannel');

        if (!blank($customerSalesChannelSlug)) {
            $customerSalesChannel = CustomerSalesChannel::where('slug', $customerSalesChannelSlug)->first();
            $portfolios = Portfolio::where('customer_sales_channel_id', $customerSalesChannel->id)
                ->get();

            $this->handle($customerSalesChannel, $portfolios);
        } else {
            foreach (WooCommerceUser::all() as $wooUser) {
                $portfolios = $wooUser->customerSalesChannel->portfolios;

                $this->handle($wooUser->customerSalesChannel, $portfolios);
            }
        }
    }
}
