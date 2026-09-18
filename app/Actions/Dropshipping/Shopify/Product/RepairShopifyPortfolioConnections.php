<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 18 Sep 2026 14:30:00 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Re-points the portfolios the reconciliation found linked to a product that is gone from the shop
 * onto the one active product whose single variant carries the same SKU (HELP-3205).
 *
 * Only our own portfolio rows are written, nothing is sent to Shopify. The product being linked to is
 * the merchant's own listing, so the portfolio is marked as an adopted variant: title, description,
 * price and dimension pushes stay away from it and removing the portfolio never deletes the listing.
 * It shows as connected only when the variant is already stocked at our fulfilment location.
 *
 * A portfolio can carry the sku of another product of the same channel. The listing with that sku
 * belongs to the portfolio whose product code it is, so the one that merely borrows the sku is skipped.
 */
class RepairShopifyPortfolioConnections
{
    use AsAction;

    public string $commandSignature = 'shopify:repair-portfolios {customerSalesChannel} {--limit= : Repair at most this many portfolios, for a test batch} {--dry-run : Report what would be repaired without writing}';

    /**
     * @return array{complete: bool, reason: string|null, repaired: int, connected: int, not_at_location: int, skipped_variant_taken: int, skipped_sku_of_another_product: int, portfolio_ids: array<int, int>}
     */
    public function handle(CustomerSalesChannel $customerSalesChannel, ?int $limit = null, bool $dryRun = false): array
    {
        $report = ReconcileShopifyPortfolioConnections::run($customerSalesChannel);

        $result = [
            'complete'              => $report['complete'],
            'reason'                => $report['reason'],
            'repaired'              => 0,
            'connected'             => 0,
            'not_at_location'       => 0,
            'skipped_variant_taken'          => 0,
            'skipped_sku_of_another_product' => 0,
            'portfolio_ids'                  => []
        ];

        if (!$report['complete']) {
            return $result;
        }

        $takenVariantIds = $customerSalesChannel->portfolios()
            ->whereNotNull('platform_product_variant_id')
            ->pluck('id', 'platform_product_variant_id')
            ->all();

        $portfolioIdByProductCode = $customerSalesChannel->portfolios()
            ->whereNotNull('item_code')
            ->pluck('id', 'item_code')
            ->mapWithKeys(fn (int $portfolioId, string $productCode) => [Str::lower($productCode) => $portfolioId])
            ->all();

        foreach ($report['rows'] as $row) {
            if ($row['repair'] !== 'repairable') {
                continue;
            }

            if ($limit !== null && $result['repaired'] >= $limit) {
                break;
            }

            $skuOwnerId = $portfolioIdByProductCode[$row['repair_sku']] ?? null;

            if ($skuOwnerId !== null && $skuOwnerId !== $row['portfolio_id']) {
                $result['skipped_sku_of_another_product']++;

                continue;
            }

            $variantOwnerId = $takenVariantIds[$row['repair_variant_id']] ?? null;

            if ($variantOwnerId !== null && $variantOwnerId !== $row['portfolio_id']) {
                $result['skipped_variant_taken']++;

                continue;
            }

            if (!$dryRun) {
                $this->repoint(Portfolio::find($row['portfolio_id']), $row);
            }

            $takenVariantIds[$row['repair_variant_id']] = $row['portfolio_id'];

            $result['repaired']++;
            $result[$row['repair_at_location'] ? 'connected' : 'not_at_location']++;
            $result['portfolio_ids'][] = $row['portfolio_id'];
        }

        return $result;
    }

    private function repoint(Portfolio $portfolio, array $row): void
    {
        UpdatePortfolio::run($portfolio, [
            'platform_product_id'         => $row['repair_product_id'],
            'platform_product_variant_id' => $row['repair_variant_id'],
            'platform_status'             => $row['repair_at_location'],
            'errors_response'             => null
        ]);

        $portfolio->markShopifyVariantAdopted(true);
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->first();

        if (!$customerSalesChannel) {
            $command->error('Customer sales channel not found');

            return 1;
        }

        $limit  = $command->option('limit') !== null ? (int) $command->option('limit') : null;
        $dryRun = (bool) $command->option('dry-run');

        $result = $this->handle($customerSalesChannel, $limit, $dryRun);

        if (!$result['complete']) {
            $command->error($result['reason']);

            return 1;
        }

        $command->table(
            [$dryRun ? 'Would repair' : 'Repaired', 'Already stocked at our location', 'Not yet stocked at our location', 'Skipped, variant linked to another portfolio', 'Skipped, sku is the code of another product'],
            [[$result['repaired'], $result['connected'], $result['not_at_location'], $result['skipped_variant_taken'], $result['skipped_sku_of_another_product']]]
        );

        $command->line('Portfolio ids: '.implode(',', array_slice($result['portfolio_ids'], 0, 50)).(count($result['portfolio_ids']) > 50 ? ' ...' : ''));

        return 0;
    }
}
