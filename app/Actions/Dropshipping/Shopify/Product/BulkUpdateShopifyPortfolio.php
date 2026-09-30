<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2025 08:26:56 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Actions\Dropshipping\Portfolio\Logs\StorePlatformPortfolioLog;
use App\Actions\Dropshipping\Portfolio\Logs\UpdatePlatformPortfolioLog;
use App\Actions\Dropshipping\Shopify\CheckShopifyChannel;
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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Sentry;

class BulkUpdateShopifyPortfolio implements ShouldBeUnique
{
    use AsAction;
    use WithShopifyApi;

    public string $jobQueue = 'shopify-slave';
    public int $jobTries = 1;

    private const string ACTIVE_STATUS = 'ACTIVE';

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

        if (!$shopifyUser->shopify_location_id) {
            CheckShopifyChannel::run($customerSalesChannel);
            $shopifyUser->refresh();
        }

        if (!$shopifyUser->shopify_location_id) {
            $command?->error('No Shopify location for this channel, stock can not be sent');
            Log::error('Shopify stock update skipped, no location', ['customer_sales_channel_id' => $customerSalesChannel->id]);

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

        $holdersByVariant = [];
        foreach ($portfolios as $portfolio) {
            if ($portfolio->platform_product_variant_id) {
                $holdersByVariant[$portfolio->platform_product_variant_id][$portfolio->id] = self::sharedListingHolder($portfolio, $productMap->get($portfolio->item_id), $customerSalesChannel);
            }
        }

        $variantsSent = [];
        foreach ($portfolios->chunk(50) as $portfolioChunk) {
            try {
                $this->processChunk($shopifyUser, $customerSalesChannel, $portfolioChunk, $productMap, $holdersByVariant, $variantsSent, $command);
            } catch (\Throwable $e) {
                Sentry::captureException($e);
            }
        }
    }

    /**
     * @param  Collection<int, Portfolio>  $portfolios
     * @param  Collection<int, Product>  $productMap
     * @param  array<string, array<int, array{code: string, sku: string, quantity: int|null}>>  $holdersByVariant
     * @param  array<string, int>  $variantsSent  variants already sent stock in this run, variant id => portfolio id
     */
    private function processChunk(ShopifyUser $shopifyUser, CustomerSalesChannel $customerSalesChannel, Collection $portfolios, Collection $productMap, array $holdersByVariant, array &$variantsSent, ?Command $command = null): void
    {
        $logs                   = [];
        $inventoryItems         = [];
        $portfoliosToUpdateData = [];
        $indexToPortfolioId     = [];

        [$variantsByProduct, $statusByProduct, $catalogueRead] = $this->getShopifyVariantsBatch($shopifyUser, self::shopifyIdsToFetch($portfolios));
        $channelSkus       = $productMap->pluck('code')->merge($portfolios->pluck('sku'))->filter()->map(fn ($sku) => Str::lower($sku))->unique()->values()->all();

        foreach ($portfolios as $portfolio) {
            $productData = $productMap->get($portfolio->item_id);

            if (!$productData instanceof Product) {
                continue;
            }

            $availableQuantity = UpdateWooCustomerSalesChannelPortfolio::quantityToSend($productData, $customerSalesChannel);

            $shopifyData = self::resolveVariant($portfolio, $productData, $variantsByProduct[$portfolio->platform_product_id] ?? [], $channelSkus);

            if (!$shopifyData) {
                $portfolio->update(['stock_last_fail_updated_at' => now()]);
                UpdatePlatformPortfolioLog::dispatch(StorePlatformPortfolioLog::run($portfolio, []), [
                    'status'   => PlatformPortfolioLogsStatusEnum::FAIL,
                    'response' => $catalogueRead && !isset($statusByProduct[$portfolio->platform_product_id])
                        ? 'This product is no longer in your Shopify store'
                        : 'No variant on Shopify matches this sku'
                ]);
                continue;
            }

            $variantId       = $shopifyData['variantId'];
            $inventoryItemId = $shopifyData['inventoryItemId'];

            $holders                = $holdersByVariant[$variantId] ?? [];
            $holders[$portfolio->id] = self::sharedListingHolder($portfolio, $productData, $customerSalesChannel);
            $sharedListingNote      = null;

            if (count($holders) > 1 || isset($variantsSent[$variantId])) {
                [$senderId, $ordersReachSender] = self::sharedListingSender($holders, $shopifyData['sku']);

                if ($senderId !== $portfolio->id || isset($variantsSent[$variantId])) {
                    $otherCodes = array_filter(array_column(array_diff_key($holders, [$portfolio->id => true]), 'code'));
                    $portfolio->update(['stock_last_fail_updated_at' => now()]);
                    UpdatePlatformPortfolioLog::dispatch(StorePlatformPortfolioLog::run($portfolio, []), [
                        'status'   => PlatformPortfolioLogsStatusEnum::FAIL,
                        'response' => $otherCodes
                            ? 'This Shopify listing is also linked to '.implode(', ', $otherCodes).', so its stock is not sent'
                            : 'This Shopify listing already got its stock from another product, so its stock is not sent'
                    ]);
                    continue;
                }

                if (!$ordersReachSender) {
                    $availableQuantity = 0;
                    $sharedListingNote = 'Several products are linked to this Shopify listing and it can not be told which of them it sells, so 0 is sent';
                }
            }

            $variantsSent[$variantId] = $portfolio->id;

            if ($portfolio->platform_product_variant_id !== $variantId) {
                LinkShopifyPortfolio::run($portfolio, null, $variantId);
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
                'note'             => $sharedListingNote,
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
                if ($portfolio && str_contains($failedIndices[$index], 'not stocked at the location') && !$portfoliosToUpdateData[$portfolioId]['note']) {
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
                $listingStatus = $statusByProduct[$portfolio?->platform_product_id] ?? null;
                $portfolio?->update([
                    'last_stock_value'      => $portfoliosToUpdateData[$portfolioId]['last_stock_value'],
                    'stock_last_updated_at' => now(),
                ]);
                if ($log && $portfoliosToUpdateData[$portfolioId]['note']) {
                    UpdatePlatformPortfolioLog::dispatch($log, [
                        'status'   => PlatformPortfolioLogsStatusEnum::FAIL,
                        'response' => $portfoliosToUpdateData[$portfolioId]['note']
                    ]);
                } elseif ($log) {
                    UpdatePlatformPortfolioLog::dispatch($log, $listingStatus === self::ACTIVE_STATUS
                        ? ['status' => PlatformPortfolioLogsStatusEnum::OK]
                        : ['status' => PlatformPortfolioLogsStatusEnum::OK, 'response' => 'Stock sent, but this product is '.Str::lower((string)$listingStatus).' in your Shopify store, so it is not for sale there']);
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
     * product is not ours unless its sku says so. The product code is looked for before the sku,
     * because a portfolio can carry the sku of another product whose variant sits on the same listing.
     * A listing with one variant whose id we stored is still ours when the merchant relabelled its sku
     * with a text that is not the sku or code of any product on the channel.
     *
     * @param  list<array{variantId: string, inventoryItemId: string|null, sku: string}>  $variants
     * @param  list<string>  $channelSkus  lowercased codes and skus of every product on the channel
     * @return array{variantId: string, inventoryItemId: string|null, sku: string}|null
     */
    public static function resolveVariant(Portfolio $portfolio, Product $product, array $variants, array $channelSkus = []): ?array
    {
        if (empty($variants)) {
            return null;
        }

        $ownSkus = array_filter([Str::lower((string)$product->code), Str::lower((string)$portfolio->sku)]);

        foreach ($ownSkus as $ownSku) {
            foreach ($variants as $variant) {
                if (Str::lower($variant['sku']) === $ownSku) {
                    return $variant;
                }
            }
        }

        $unlabelled = array_values(array_filter($variants, fn (array $variant) => $variant['sku'] === ''));

        foreach ($unlabelled as $variant) {
            if ($variant['variantId'] === $portfolio->platform_product_variant_id) {
                return $variant;
            }
        }

        if (count($variants) === 1 && count($unlabelled) === 1) {
            return $unlabelled[0];
        }

        if (count($variants) === 1 && $variants[0]['variantId'] === $portfolio->platform_product_variant_id && !in_array(Str::lower($variants[0]['sku']), $channelSkus, true)) {
            return $variants[0];
        }

        return null;
    }

    /**
     * @return array{code: string, sku: string, quantity: int|null}
     */
    private static function sharedListingHolder(Portfolio $portfolio, ?Product $product, CustomerSalesChannel $customerSalesChannel): array
    {
        return [
            'code'     => (string)$portfolio->item_code,
            'sku'      => (string)$portfolio->sku,
            'quantity' => $product ? (int)UpdateWooCustomerSalesChannelPortfolio::quantityToSend($product, $customerSalesChannel) : null,
        ];
    }

    /**
     * A listing linked to several portfolios gets its stock from the one its orders go to, chosen the way
     * order lines are matched: the portfolio whose product code the listing carries, then the one whose
     * sku it carries, oldest first. When no portfolio carries the listing sku, or several carry it and
     * would send different stock, it can not be told which product the listing sells: the oldest sends 0.
     *
     * @param  array<int, array{code: string, sku: string, quantity?: int|null}>  $holders  portfolio id => product code, sku and stock to send
     * @return array{0: int, 1: bool}  the portfolio that sends, and whether it sends its own stock
     */
    public static function sharedListingSender(array $holders, string $listingSku): array
    {
        $listingSku = Str::lower(trim($listingSku));
        ksort($holders);

        if ($listingSku !== '') {
            foreach (['code', 'sku'] as $field) {
                $carriers = array_filter($holders, fn (array $holder) => Str::lower(trim($holder[$field])) === $listingSku);

                if ($carriers) {
                    return [array_key_first($carriers), count(array_unique(array_map(fn (array $holder) => $holder['quantity'] ?? null, $carriers))) === 1];
                }
            }
        }

        return [array_key_first($holders), false];
    }

    /**
     * @param  list<string>  $productIds
     * @return array{0: array<string, list<array{variantId: string, inventoryItemId: string|null, sku: string}>>, 1: array<string, string|null>, 2: bool}
     */
    private function getShopifyVariantsBatch(ShopifyUser $shopifyUser, array $productIds): array
    {
        if (empty($productIds)) {
            return [[], [], false];
        }

        $query = <<<'QUERY'
            query getProductsVariants($ids: [ID!]!) {
                nodes(ids: $ids) {
                    ... on Product {
                        id
                        status
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
            return [[], [], false];
        }

        $body     = $res['body']->toArray();
        $results  = [];
        $statuses = [];
        foreach ($body['data']['nodes'] ?? [] as $node) {
            if (!$node || !isset($node['id'])) {
                continue;
            }

            $statuses[$node['id']] = $node['status'] ?? null;
            $results[$node['id']]  = array_map(fn (array $edge) => [
                'variantId'       => $edge['node']['id'],
                'inventoryItemId' => $edge['node']['inventoryItem']['id'] ?? null,
                'sku'             => trim((string)($edge['node']['sku'] ?? '')),
            ], $node['variants']['edges'] ?? []);
        }

        return [$results, $statuses, isset($body['data']['nodes'])];
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
