<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Actions\Dropshipping\Portfolio\WithPortfolioReconciliationCsv;
use App\Enums\Dropshipping\PortfolioConnectionAuditEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Reconciles every portfolio of a Shopify channel against the shop's real catalogue and reports
 * which ones are genuinely connected, which are repairable by re-matching on SKU, and which have
 * nothing on the Shopify side to match.
 *
 * Read only: nothing is written to the portfolios and nothing is pushed to Shopify.
 */
class ReconcileShopifyPortfolioConnections
{
    use AsAction;
    use WithPortfolioReconciliationCsv;

    public string $jobQueue = 'shopify-bulk';

    public string $commandSignature = 'shopify:reconcile-portfolios {customerSalesChannel} {--csv= : Where to write the row per portfolio detail, defaults to storage/app/<channel>-reconciliation.csv}';

    private const string ACTIVE_STATUS = 'ACTIVE';

    /**
     * @return array{complete: bool, reason: string|null, customer_sales_channel: string, catalogue: array, totals: array<string, int>, by_status: array<string, int>, rows: array<int, array>}
     */
    public function handle(CustomerSalesChannel $customerSalesChannel): array
    {
        $report = [
            'complete'               => false,
            'reason'                 => null,
            'customer_sales_channel' => $customerSalesChannel->slug,
            'catalogue'              => ['variants_read' => 0, 'products_read' => 0],
            'totals'                 => [],
            'by_status'              => [],
            'rows'                   => []
        ];

        if ($customerSalesChannel->platform?->type !== PlatformTypeEnum::SHOPIFY) {
            $report['reason'] = 'Channel is not a Shopify channel';

            return $report;
        }

        $shopifyUser = $customerSalesChannel->user;

        if (!$shopifyUser) {
            $report['reason'] = 'Channel has no Shopify user';

            return $report;
        }

        $snapshot = GetShopifyCatalogueSnapshot::run($shopifyUser);

        $report['catalogue'] = [
            'variants_read' => $snapshot['variants_read'],
            'products_read' => count($snapshot['products'])
        ];

        if (!$snapshot['complete']) {
            $report['reason'] = 'Could not read the Shopify catalogue in full, so no portfolio can be judged. '.$snapshot['reason'];

            return $report;
        }

        $report['rows']     = $this->auditPortfolios($customerSalesChannel, $snapshot);
        $report['by_status'] = $this->countBy($report['rows'], 'status');
        $report['totals']   = $this->summarise($report['rows']);
        $report['complete'] = true;

        return $report;
    }

    /**
     * @return array<int, array>
     */
    private function auditPortfolios(CustomerSalesChannel $customerSalesChannel, array $snapshot): array
    {
        $rows = [];

        $customerSalesChannel->portfolios()
            ->select(['id', 'reference', 'item_name', 'item_code', 'sku', 'status', 'platform_product_id', 'platform_status'])
            ->chunkById(1000, function ($portfolios) use ($snapshot, &$rows) {
                foreach ($portfolios as $portfolio) {
                    $rows[] = $this->auditPortfolio($portfolio, $snapshot);
                }
            });

        return $rows;
    }

    private function auditPortfolio($portfolio, array $snapshot): array
    {
        $candidateSkus = $this->candidateSkus($portfolio);
        $status        = $this->resolveStatus($portfolio, $candidateSkus, $snapshot);
        $repair        = $this->resolveRepair($status, $candidateSkus, $snapshot);

        return [
            'portfolio_id'        => $portfolio->id,
            'reference'           => $portfolio->reference,
            'item_name'           => $portfolio->item_name,
            'sku'                 => $portfolio->sku ?: $portfolio->item_code,
            'platform_product_id' => $portfolio->platform_product_id,
            'status'              => $status->value,
            'shows_as_connected'  => (bool) $portfolio->platform_status,
            'misreported'         => (bool) $portfolio->platform_status && !$status->isConnected(),
            'repair'              => $repair['repair'],
            'repair_product_id'   => $repair['repair_product_id']
        ];
    }

    /**
     * @return array<int, string>
     */
    private function candidateSkus($portfolio): array
    {
        return array_values(array_unique(array_map(
            fn (string $sku) => Str::lower($sku),
            array_filter([$portfolio->sku, $portfolio->item_code])
        )));
    }

    private function resolveStatus($portfolio, array $candidateSkus, array $snapshot): PortfolioConnectionAuditEnum
    {
        if (blank($candidateSkus)) {
            return PortfolioConnectionAuditEnum::NO_SKU;
        }

        if (blank($portfolio->platform_product_id)) {
            return PortfolioConnectionAuditEnum::NOT_LINKED;
        }

        if (!CheckIfShopifyProductIDIsValid::run($portfolio->platform_product_id)) {
            return PortfolioConnectionAuditEnum::INVALID_PRODUCT_ID;
        }

        $product = $snapshot['products'][$portfolio->platform_product_id] ?? null;

        if (!$product) {
            return PortfolioConnectionAuditEnum::MISSING_IN_PLATFORM;
        }

        if (Arr::get($product, 'status') !== self::ACTIVE_STATUS) {
            return PortfolioConnectionAuditEnum::INACTIVE_IN_PLATFORM;
        }

        $matchingVariants = array_filter(
            Arr::get($product, 'variants', []),
            fn (array $variant) => $variant['sku'] && in_array(Str::lower($variant['sku']), $candidateSkus, true)
        );

        if (blank($matchingVariants)) {
            return PortfolioConnectionAuditEnum::WRONG_PRODUCT_LINKED;
        }

        foreach ($matchingVariants as $variant) {
            if ($variant['at_location']) {
                return PortfolioConnectionAuditEnum::CONNECTED;
            }
        }

        return PortfolioConnectionAuditEnum::NOT_AT_FULFILMENT_LOCATION;
    }

    /**
     * @return array{repair: string, repair_product_id: string|null}
     */
    private function resolveRepair(PortfolioConnectionAuditEnum $status, array $candidateSkus, array $snapshot): array
    {
        if ($status->isConnected()) {
            return ['repair' => 'none', 'repair_product_id' => null];
        }

        foreach ($candidateSkus as $candidateSku) {
            $isActiveByProductId = $snapshot['product_ids_by_sku'][$candidateSku] ?? [];

            if (count($isActiveByProductId) > 1) {
                return ['repair' => 'ambiguous', 'repair_product_id' => null];
            }

            if (count($isActiveByProductId) === 1) {
                $productId = array_key_first($isActiveByProductId);

                /* A draft or archived product carrying the SKU is still a real match to re-link to,
                   it just has to be published first, so it is not the same dead end as a SKU the
                   shop has never heard of. */
                return [
                    'repair'            => $isActiveByProductId[$productId] ? 'repairable' : 'match_not_active',
                    'repair_product_id' => $productId
                ];
            }
        }

        return ['repair' => 'unresolved', 'repair_product_id' => null];
    }

    /**
     * @return array<string, int>
     */
    private function summarise(array $rows): array
    {
        $repairs = $this->countBy($rows, 'repair');

        return [
            'portfolios'       => count($rows),
            'connected'        => $repairs['none'] ?? 0,
            'broken'           => count($rows) - ($repairs['none'] ?? 0),
            'repairable'       => $repairs['repairable'] ?? 0,
            'match_not_active' => $repairs['match_not_active'] ?? 0,
            'ambiguous'        => $repairs['ambiguous'] ?? 0,
            'unresolved'       => $repairs['unresolved'] ?? 0,
            'misreported'      => count(array_filter($rows, fn (array $row) => $row['misreported']))
        ];
    }

    /**
     * @return array<string, int>
     */
    private function countBy(array $rows, string $key): array
    {
        $counts = [];

        foreach ($rows as $row) {
            $counts[$row[$key]] = ($counts[$row[$key]] ?? 0) + 1;
        }

        arsort($counts);

        return $counts;
    }

    public function asCommand(Command $command): int
    {
        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->first();

        if (!$customerSalesChannel) {
            $command->error('Customer sales channel not found');

            return 1;
        }

        $report = $this->handle($customerSalesChannel);

        $command->info("Shopify catalogue read: {$report['catalogue']['products_read']} products, {$report['catalogue']['variants_read']} variants");

        if (!$report['complete']) {
            $command->error($report['reason']);

            return 1;
        }

        $this->renderReport($command, $report);

        $path = $command->option('csv') ?: storage_path('app/'.$report['customer_sales_channel'].'-reconciliation.csv');
        $this->writeCsv($path, $report['rows']);
        $command->info('Per portfolio detail written to '.$path);

        return 0;
    }

    private function renderReport(Command $command, array $report): void
    {
        $command->table(
            ['Portfolios', 'Connected', 'Need repair', 'Repairable by SKU', 'SKU match not active', 'Ambiguous SKU', 'Unresolved', 'Wrongly shown as connected'],
            [array_values($report['totals'])]
        );

        $labels = PortfolioConnectionAuditEnum::labels();

        $command->table(
            ['Finding', 'Portfolios'],
            array_map(
                fn (string $status, int $count) => [Arr::get($labels, $status, $status), $count],
                array_keys($report['by_status']),
                $report['by_status']
            )
        );
    }

    private function writeCsv(string $path, array $rows): void
    {
        $handle = fopen($path, 'w');

        $this->writeReconciliationCsv($handle, $rows);

        fclose($handle);
    }
}
