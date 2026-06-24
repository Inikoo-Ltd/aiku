<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2025 08:26:56 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce\Product;

use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\WooCommerceUser;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateBatchWooCustomerSalesChannelPortfolio implements ShouldBeUnique
{
    use AsAction;


    public string $jobQueue = 'woo';

    public function getJobUniqueId(WooCommerceUser $wooCommerceUser): string
    {
        return $wooCommerceUser->id;
    }

    public function handle(WooCommerceUser $wooCommerceUser, array $productData): void
    {
        $customerSalesChannel = $wooCommerceUser->customerSalesChannel;

        $requestedQuantities = collect(Arr::get($productData, 'update', []))
            ->filter(fn (array $product): bool => Arr::has($product, ['id', 'stock_quantity']))
            ->mapWithKeys(fn (array $product): array => [
                (string) Arr::get($product, 'id') => (int) Arr::get($product, 'stock_quantity')
            ]);

        $stockUpdated = $wooCommerceUser->batchUpdateWooCommerceProducts($productData);

        if (Arr::get($stockUpdated, 'update')) {
            $updatedQuantities = collect(Arr::get($stockUpdated, 'update', []))
                ->filter(fn (array $product): bool => Arr::has($product, ['id', 'stock_quantity']))
                ->mapWithKeys(fn (array $product): array => [
                    (string) Arr::get($product, 'id') => (int) Arr::get($product, 'stock_quantity')
                ]);

            $successfulQuantities = $requestedQuantities->filter(
                fn (int $stockQuantity, string $platformProductId): bool => $updatedQuantities->has($platformProductId)
                    && $updatedQuantities->get($platformProductId) === $stockQuantity
            );

            $failedPlatformProductIds = $requestedQuantities
                ->keys()
                ->diff($successfulQuantities->keys())
                ->values();

            $updatedAt = now();

            foreach ($successfulQuantities as $platformProductId => $stockQuantity) {
                Portfolio::query()
                    ->where('customer_sales_channel_id', $customerSalesChannel->id)
                    ->where('platform_product_id', $platformProductId)
                    ->update([
                        'last_stock_value'      => $stockQuantity,
                        'stock_last_updated_at' => $updatedAt
                    ]);
            }

            if ($failedPlatformProductIds->isNotEmpty()) {
                Portfolio::query()
                    ->where('customer_sales_channel_id', $customerSalesChannel->id)
                    ->whereIn('platform_product_id', $failedPlatformProductIds->all())
                    ->update([
                        'stock_last_fail_updated_at' => $updatedAt
                    ]);

                $customerSalesChannel->update([
                    'ban_stock_update_util' => now()->addSeconds(10)
                ]);

                return;
            }

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
                $messageData = json_decode($rawMessage, true);
                if ($messageData) {
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
