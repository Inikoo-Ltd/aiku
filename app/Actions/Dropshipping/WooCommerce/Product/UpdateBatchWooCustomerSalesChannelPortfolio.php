<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2025 08:26:56 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce\Product;

use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\WooCommerceUser;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateBatchWooCustomerSalesChannelPortfolio
{
    use AsAction;


    public string $jobQueue = 'woo';

    public function handle(WooCommerceUser $wooCommerceUser, array $productData): void
    {
        $customerSalesChannel = $wooCommerceUser->customerSalesChannel;

        if (!$customerSalesChannel) {
            return;
        }

        $requestedQuantities = [];
        foreach (Arr::get($productData, 'update', []) as $product) {
            if (Arr::has($product, ['id', 'stock_quantity'])) {
                $requestedQuantities[(int) Arr::get($product, 'id')] = (int) Arr::get($product, 'stock_quantity');
            }
        }

        if (blank($requestedQuantities)) {
            return;
        }

        $response = $wooCommerceUser->batchUpdateWooCommerceProducts($productData);
        $responseItems = Arr::get($response, 'update');

        if (!is_array($responseItems)) {
            $this->handleFailedBatch($customerSalesChannel, $response);

            return;
        }

        $processedQuantities = [];
        $erroredPlatformProductIds = [];

        foreach ($responseItems as $responseItem) {
            $platformProductId = Arr::get($responseItem, 'id');

            if (!$platformProductId || !array_key_exists((int) $platformProductId, $requestedQuantities)) {
                continue;
            }

            if (Arr::has($responseItem, 'error')) {
                $erroredPlatformProductIds[] = (int) $platformProductId;
            } else {
                $processedQuantities[(int) $platformProductId] = (int) (Arr::get($responseItem, 'stock_quantity') ?? $requestedQuantities[(int) $platformProductId]);
            }
        }

        $updatedAt = now();

        $succeededByStockValue = [];
        foreach ($processedQuantities as $platformProductId => $stockQuantity) {
            $succeededByStockValue[$stockQuantity][] = $platformProductId;
        }

        foreach ($succeededByStockValue as $stockQuantity => $platformProductIds) {
            Portfolio::query()
                ->where('customer_sales_channel_id', $customerSalesChannel->id)
                ->whereIn('platform_product_id', array_map('strval', $platformProductIds))
                ->update([
                    'last_stock_value'      => $stockQuantity,
                    'stock_last_updated_at' => $updatedAt
                ]);
        }

        $failedPlatformProductIds = array_merge(
            $erroredPlatformProductIds,
            array_values(array_diff(array_keys($requestedQuantities), array_keys($processedQuantities), $erroredPlatformProductIds))
        );

        if (filled($failedPlatformProductIds)) {
            Portfolio::query()
                ->where('customer_sales_channel_id', $customerSalesChannel->id)
                ->whereIn('platform_product_id', array_map('strval', $failedPlatformProductIds))
                ->update([
                    'stock_last_fail_updated_at' => $updatedAt
                ]);
        }

        if (filled($processedQuantities) && $customerSalesChannel->ban_stock_update_util !== null) {
            $customerSalesChannel->update([
                'ban_stock_update_util' => null
            ]);
        }
    }

    public function handleFailedBatch(CustomerSalesChannel $customerSalesChannel, ?array $response): void
    {
        $rawMessage = Arr::get($response, '0', Arr::get($response, 'message'));

        if (is_array($rawMessage)) {
            $rawMessage = json_encode($rawMessage);
        }

        $ban = true;

        if (is_string($rawMessage)) {
            $messageData = json_decode($rawMessage, true);

            if ($messageData) {
                if (in_array(Arr::get($messageData, 'code'), ['rest_invalid_param', 'woocommerce_rest_product_invalid_id'])
                    || in_array(Arr::get($messageData, 'data.status'), [400, 404])) {
                    $ban = false;
                }
            }
        }

        if ($ban) {
            $customerSalesChannel->update([
                'ban_stock_update_util' => now()->addSeconds(10)
            ]);
        }
    }
}
