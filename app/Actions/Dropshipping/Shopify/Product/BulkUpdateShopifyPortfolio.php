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
use App\Actions\Dropshipping\WooCommerce\Product\UpdateWooCustomerSalesChannelPortfolio;
use App\Enums\Ordering\PlatformLogs\PlatformPortfolioLogsStatusEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Sentry;

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

        $productMap = Product::on('aiku_no_sticky')
            ->whereIn('id', $portfolios->pluck('item_id')->unique())
            ->select('id', 'code', 'available_quantity', 'is_for_sale', 'exclusive_for_customer_id', 'state')
            ->get()
            ->keyBy('id');

        foreach ($portfolios->chunk(50) as $portfolioChunk) {
            try {
                $this->processChunk($shopifyUser, $customerSalesChannel, $portfolioChunk, $productMap, $command);
            } catch (\Throwable $e) {
                Sentry::captureException($e);
            }
        }
    }

    /**
     * @param  Collection<int, Portfolio>  $portfolios
     * @param  Collection<int, Product>  $productMap
     */
    private function processChunk(ShopifyUser $shopifyUser, CustomerSalesChannel $customerSalesChannel, Collection $portfolios, Collection $productMap, ?Command $command = null): void
    {
        $logs                   = [];
        $inventoryItems         = [];
        $portfoliosToUpdateData = [];
        $indexToPortfolioId     = [];

        $variantsByProduct = $this->getShopifyVariantsBatch($shopifyUser, self::shopifyIdsToFetch($portfolios));

        foreach ($portfolios as $portfolio) {
            $productData = $productMap->get($portfolio->item_id);

            if (!$productData instanceof Product) {
                continue;
            }

            $availableQuantity = UpdateWooCustomerSalesChannelPortfolio::quantityToSend($productData, $customerSalesChannel);

            $shopifyData = self::resolveVariant($portfolio, $productData, $variantsByProduct[$portfolio->platform_product_id] ?? []);

            if (!$shopifyData) {
                $portfolio->update(['stock_last_fail_updated_at' => now()]);
                UpdatePlatformPortfolioLog::dispatch(StorePlatformPortfolioLog::run($portfolio, []), [
                    'status'   => PlatformPortfolioLogsStatusEnum::FAIL,
                    'response' => 'No variant on Shopify matches this sku'
                ]);
                continue;
            }

            $variantId       = $shopifyData['variantId'];
            $inventoryItemId = $shopifyData['inventoryItemId'];

            if ($portfolio->platform_product_variant_id !== $variantId) {
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
                if ($portfolio && str_contains($failedIndices[$index], 'not stocked at the location')) {
                    StoreShopifyLocationToProductVariant::dispatch($portfolio);
                }
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

    /**
     * @param  Collection<int, Portfolio>  $portfolios
     * @return list<string>
     */
    public static function shopifyIdsToFetch(Collection $portfolios): array
    {
        return $portfolios->pluck('platform_product_id')
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * A stored variant id is only trusted while its sku still belongs to this portfolio: Shopify
     * keeps the id when a merchant deletes or reorders variants, and the first variant of a
     * product is not ours unless its sku says so.
     *
     * @param  list<array{variantId: string, inventoryItemId: string|null, sku: string}>  $variants
     * @return array{variantId: string, inventoryItemId: string|null, sku: string}|null
     */
    public static function resolveVariant(Portfolio $portfolio, Product $product, array $variants): ?array
    {
        if (empty($variants)) {
            return null;
        }

        $ownSkus = array_filter([Str::lower((string)$portfolio->sku), Str::lower((string)$product->code)]);

        foreach ($variants as $variant) {
            if (in_array(Str::lower($variant['sku']), $ownSkus, true)) {
                return $variant;
            }
        }

        $unlabelled = array_values(array_filter($variants, fn (array $variant) => $variant['sku'] === ''));

        foreach ($unlabelled as $variant) {
            if ($variant['variantId'] === $portfolio->platform_product_variant_id) {
                return $variant;
            }
        }

        return count($variants) === 1 && count($unlabelled) === 1 ? $unlabelled[0] : null;
    }

    /**
     * @param  list<string>  $productIds
     * @return array<string, list<array{variantId: string, inventoryItemId: string|null, sku: string}>>
     */
    private function getShopifyVariantsBatch(ShopifyUser $shopifyUser, array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        $query = <<<'QUERY'
            query getProductsVariants($ids: [ID!]!) {
                nodes(ids: $ids) {
                    ... on Product {
                        id
                        variants(first: 100) {
                            edges {
                                node {
                                    id
                                    sku
                                    inventoryItem {
                                        id
                                    }
                                }
                            }
                        }
                    }
                }
            }
        QUERY;

        [$status, $res] = $this->doPost($shopifyUser, $query, ['ids' => $productIds]);

        if (!$status) {
            return [];
        }

        $body    = $res['body']->toArray();
        $results = [];
        foreach ($body['data']['nodes'] ?? [] as $node) {
            if (!$node || !isset($node['id'])) {
                continue;
            }

            $results[$node['id']] = array_map(fn (array $edge) => [
                'variantId'       => $edge['node']['id'],
                'inventoryItemId' => $edge['node']['inventoryItem']['id'] ?? null,
                'sku'             => trim((string)($edge['node']['sku'] ?? '')),
            ], $node['variants']['edges'] ?? []);
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
