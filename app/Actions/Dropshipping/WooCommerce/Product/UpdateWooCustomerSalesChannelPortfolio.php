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

        if (!$wooCommerceUser) {
            return;
        }

        Portfolio::query()
            ->select([
                'id',
                'item_id',
                'item_type',
                'platform_product_id',
                'platform_status',
                'stock_last_updated_at',
            ])
            ->where('customer_sales_channel_id', $customerSalesChannel->id)
            ->whereNotNull('platform_product_id')
            ->where('item_type', 'Product')
            ->where('platform_status', true)
            ->with('item:id,available_quantity,is_for_sale,available_quantity_updated_at')
            ->chunkById(500, function ($portfolioChunk) use ($customerSalesChannel, $wooCommerceUser): void {
                $updates = [];

                foreach ($portfolioChunk as $portfolio) {
                    if (!$this->checkIfApplicable($portfolio)) {
                        continue;
                    }

                    $product = $portfolio->item;

                    if (!$product instanceof Product || $portfolio->platform_product_id === null || !$portfolio->platform_status) {
                        continue;
                    }

                    $availableQuantity = $product->available_quantity ?? 0;

                    if (!$product->is_for_sale) {
                        $availableQuantity = 0;
                    }

                    if ($customerSalesChannel->max_quantity_advertise > 0) {
                        $availableQuantity = min($availableQuantity, $customerSalesChannel->max_quantity_advertise);
                    }

                    $updates[] = [
                        'id'             => $portfolio->platform_product_id,
                        'stock_quantity' => $availableQuantity,
                    ];
                }

                foreach (collect($updates)->chunk(20) as $updateChunk) {
                    if ($updateChunk->isEmpty()) {
                        continue;
                    }

                    UpdateBatchWooCustomerSalesChannelPortfolio::dispatch($wooCommerceUser, [
                        'update' => $updateChunk->values()->all(),
                    ]);
                }
            });
    }

    public function checkIfApplicable(Portfolio $portfolio): bool
    {
        $applicable = false;


        if (!$portfolio->stock_last_updated_at) {
            $applicable = true;
        } else {
            $product = $portfolio->item;

            if (!$product instanceof Product) {
                return false;
            }

            if (!$product->available_quantity_updated_at || !$portfolio->stock_last_updated_at || $product->available_quantity_updated_at->gt($portfolio->stock_last_updated_at)) {
                $applicable = true;
            }
        }

        return $applicable;
    }
}
