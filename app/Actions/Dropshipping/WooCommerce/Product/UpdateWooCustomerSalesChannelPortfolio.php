<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2025 08:26:56 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce\Product;

use App\Models\Catalogue\Product;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\WooCommerceUser;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Arr;
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
        /** @var WooCommerceUser $wooCommerceUser */
        $wooCommerceUser = $customerSalesChannel->user;

        if(! $wooCommerceUser ) {
            return;
        }

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

        foreach (collect($portfoliosID)->chunk(100) as $portfolioIdChunk) {
            $productData = [];
            foreach ($portfolioIdChunk as $portfolio) {
                $portfolio = Portfolio::find($portfolio);

                if (!$portfolio || $portfolio->platform_product_id == null || !$portfolio->customerSalesChannel || !$portfolio->platform_status) {
                    return;
                }

                /** @var Product $product */
                $product = $portfolio->item;

                $availableQuantity = $product->available_quantity ?? 0;

                if (! $product->is_for_sale) {
                    $availableQuantity = 0;
                }

                if ($customerSalesChannel->max_quantity_advertise > 0) {
                    $availableQuantity = min($availableQuantity, $customerSalesChannel->max_quantity_advertise);
                }

                $productData['update'][] =
                    [
                        "id" => $portfolio->platform_product_id,
                        "stock_quantity" => $availableQuantity
                    ];
            }

            $stockUpdated = $wooCommerceUser->batchUpdateWooCommerceProducts($productData);

            if ($wooPortfolioUpdated = Arr::get($stockUpdated, 'update')) {
                $customerSalesChannel->update([
                    'ban_stock_update_util' => null
                ]);
            } else {
                $ban = true;
                $rawMessage = Arr::get($stockUpdated, '0');

                if (is_array($rawMessage)) {
                    $rawMessage = json_encode($rawMessage);
                }

                if (is_string($rawMessage)) {
                    $messageData = json_decode(Arr::get($stockUpdated, '0'), true);
                    $message = $rawMessage;

                    if ($messageData) {
                        $message = Arr::get($messageData, 'message');
                        if (Arr::get($messageData, 'code') == 'rest_invalid_param' || Arr::get($messageData, 'code') == 'woocommerce_rest_product_invalid_id' || Arr::get($messageData, 'data.status') == 404 || Arr::get($messageData, 'data.status') == 400) {
                            $ban = false;
                        }
                    }

                    if ($ban) {
                        $customerSalesChannel->update([
                            'ban_stock_update_util' => now()->addSeconds(10)
                        ]);
                    }
                }
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
