<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2025 08:26:56 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Actions\Dropshipping\Portfolio\Logs\StorePlatformPortfolioLog;
use App\Actions\Dropshipping\Portfolio\Logs\UpdatePlatformPortfolioLog;
use App\Actions\Dropshipping\Shopify\WithShopifyApi;
use App\Enums\Ordering\PlatformLogs\PlatformPortfolioLogsStatusEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class BulkUpdateShopifyPortfolio implements ShouldBeUnique
{
    use AsAction;
    use WithShopifyApi;

    public string $jobQueue = 'shopify-slave';
    public int $jobTries = 1;

    public function getJobUniqueId(?int $customerSalesChannelId): string
    {
        return $customerSalesChannelId ?? 'empty';
    }

    public function handle(?int $customerSalesChannelId, ?Command $command = null): void
    {
        if (!$customerSalesChannelId) {
            return;
        }

        $customerSalesChannel = CustomerSalesChannel::on('aiku_no_sticky')->find($customerSalesChannelId);

        if (!$customerSalesChannel) {
            return;
        }

        /** @var ShopifyUser $shopifyUser */
        $shopifyUser = $customerSalesChannel->user;
        if (!$shopifyUser instanceof ShopifyUser) {
            $command?->error('Shopify user not found');

            return;
        }

        $portfolios = Portfolio::on('aiku_no_sticky')
            ->where('customer_sales_channel_id', $customerSalesChannel->id)
            ->whereNotNull('platform_product_id')
            ->where('status', true)
            ->get()
            ->keyBy('id');

        if ($portfolios->isEmpty()) {
            return;
        }

        $productMap = DB::connection('aiku_no_sticky')
            ->table('products')
            ->whereIn('id', $portfolios->pluck('item_id')->unique())
            ->select('id', 'available_quantity', 'is_for_sale')
            ->get()
            ->keyBy('id');

        $maxQtyAd = $customerSalesChannel->max_quantity_advertise;

        foreach ($portfolios->chunk(100) as $portfolioChunk) {
            try {
                $this->processChunk($shopifyUser, $portfolioChunk, $productMap, $maxQtyAd, $command);
            } catch (\Throwable) {
                // Individual chunk failure handled by not throwing to allow other chunks to proceed
            }
        }
    }

    /**
     * @param  Collection<int, Portfolio>  $portfolios
     * @param  Collection<int, \stdClass>  $productMap
     */
    private function processChunk(ShopifyUser $shopifyUser, Collection $portfolios, Collection $productMap, ?int $maxQtyAd, ?Command $command = null): void
    {
        $logs                   = [];
        $inventoryItems         = [];
        $portfoliosToUpdateData = [];
        $indexToPortfolioId     = [];

        $shopifyIdsToFetch = $portfolios->map(fn ($p) => $p->platform_product_variant_id ?: $p->platform_product_id)
            ->filter()
            ->unique()
            ->toArray();

        $shopifyDataMap = $this->getShopifyDataBatch($shopifyUser, $shopifyIdsToFetch);

        foreach ($portfolios as $portfolio) {
            $productData = $productMap->get($portfolio->item_id);

            if (!$productData instanceof \stdClass) {
                continue;
            }

            $availableQuantity = $productData->available_quantity;
            if (!$productData->is_for_sale) {
                $availableQuantity = 0;
            }

            if ($maxQtyAd > 0) {
                $availableQuantity = min($availableQuantity, $maxQtyAd);
            }

            $key         = $portfolio->platform_product_variant_id ?: $portfolio->platform_product_id;
            $shopifyData = $shopifyDataMap[$key] ?? null;

            if (!$shopifyData) {
                continue;
            }

            $variantId       = $shopifyData['variantId'];
            $inventoryItemId = $shopifyData['inventoryItemId'];

            if ($variantId && $portfolio->platform_product_variant_id !== $variantId) {
                $portfolio->update(['platform_product_variant_id' => $variantId]);
            }

            if (!$inventoryItemId) {
                continue;
            }


            $currentIndex                      = count($inventoryItems);
            $inventoryItems[]                  = [
                'inventoryItemId' => $inventoryItemId,
                'locationId'      => $shopifyUser->shopify_location_id,
                'quantity'        => (int)$availableQuantity,
            ];
            $indexToPortfolioId[$currentIndex] = $portfolio->id;

            $portfoliosToUpdateData[$portfolio->id] = [
                'last_stock_value' => $availableQuantity,
            ];

            $logs[$portfolio->id] = StorePlatformPortfolioLog::run($portfolio, []);
        }

        if (empty($inventoryItems)) {
            return;
        }

        $mutation = <<<'MUTATION'
            mutation inventorySetQuantities($input: InventorySetQuantitiesInput!) {
                inventorySetQuantities(input: $input) {
                    userErrors {
                        field
                        message
                    }
                }
            }
        MUTATION;

        $variables = [
            'input' => [
                'reason'                => 'correction',
                'name'                  => 'available',
                'quantities'            => $inventoryItems,
                'ignoreCompareQuantity' => true
            ]
        ];

        [$status, $res] = $this->doPost($shopifyUser, $mutation, $variables);

        if (!$status) {
            $this->bulkUpdateLogs($logs, [
                'status'   => PlatformPortfolioLogsStatusEnum::FAIL,
                'response' => $res
            ]);

            foreach ($portfoliosToUpdateData as $portfolioId => $data) {
                $command?->error(json_encode($res));
                $portfolios->get($portfolioId)?->update([
                    'stock_last_fail_updated_at' => now(),
                ]);
            }

            return;
        }

        $body = $res['body']->toArray();

        $userErrors    = $body['data']['inventorySetQuantities']['userErrors'] ?? [];
        $failedIndices = [];
        foreach ($userErrors as $error) {
            $path = $error['field'] ?? [];
            if (isset($path[2]) && is_numeric($path[2])) {
                $failedIndices[(int)$path[2]] = $error['message'];
            }
        }

        foreach ($inventoryItems as $index => $item) {
            $portfolioId = $indexToPortfolioId[$index];
            $portfolio   = $portfolios->get($portfolioId);
            $log         = $logs[$portfolioId] ?? null;

            if (isset($failedIndices[$index])) {
                $portfolio?->update([
                    'stock_last_fail_updated_at' => now(),
                ]);
                if ($log) {
                    UpdatePlatformPortfolioLog::dispatch($log, [
                        'status'   => PlatformPortfolioLogsStatusEnum::FAIL,
                        'response' => $failedIndices[$index]
                    ]);
                }
            } else {
                $command?->line("Portfolio $portfolioId usefully updated");
                $portfolio?->update([
                    'last_stock_value'      => $portfoliosToUpdateData[$portfolioId]['last_stock_value'],
                    'stock_last_updated_at' => now(),
                ]);
                if ($log) {
                    UpdatePlatformPortfolioLog::dispatch($log, [
                        'status' => PlatformPortfolioLogsStatusEnum::OK
                    ]);
                }
            }
        }
    }

    private function getShopifyDataBatch(ShopifyUser $shopifyUser, array $shopifyIds): array
    {
        if (empty($shopifyIds)) {
            return [];
        }

        $query = <<<'QUERY'
            query getNodes($ids: [ID!]!) {
                nodes(ids: $ids) {
                    __typename
                    ... on Product {
                        id
                        variants(first: 1) {
                            edges {
                                node {
                                    id
                                    inventoryItem {
                                        id
                                    }
                                }
                            }
                        }
                    }
                    ... on ProductVariant {
                        id
                        inventoryItem {
                            id
                        }
                    }
                }
            }
        QUERY;

        [$status, $res] = $this->doPost($shopifyUser, $query, ['ids' => $shopifyIds]);

        if (!$status) {
            return [];
        }

        $body    = $res['body']->toArray();
        $results = [];
        foreach ($body['data']['nodes'] ?? [] as $node) {
            if (!$node) {
                continue;
            }

            if ($node['__typename'] === 'Product') {
                $variant = $node['variants']['edges'][0]['node'] ?? null;
                if ($variant) {
                    $results[$node['id']] = [
                        'variantId'       => $variant['id'],
                        'inventoryItemId' => $variant['inventoryItem']['id'] ?? null
                    ];
                }
            } elseif ($node['__typename'] === 'ProductVariant') {
                $results[$node['id']] = [
                    'variantId'       => $node['id'],
                    'inventoryItemId' => $node['inventoryItem']['id'] ?? null
                ];
            }
        }

        return $results;
    }

    public function bulkUpdateLogs(array $platformPortfolioLogs, array $modelData): void
    {
        foreach ($platformPortfolioLogs as $platformPortfolioLog) {
            UpdatePlatformPortfolioLog::dispatch($platformPortfolioLog, $modelData);
        }
    }

    public function getCommandSignature(): string
    {
        return 'dropshipping:bulk-update-shopify-portfolio {customerSalesChannelId}';
    }

    public function asCommand(Command $command): int
    {
        $this->handle($command->argument('customerSalesChannelId'), $command);

        return 0;
    }

}
