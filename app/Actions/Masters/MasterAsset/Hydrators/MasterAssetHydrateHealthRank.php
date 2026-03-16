<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 12 Mar 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Masters\MasterAsset\Hydrators;

use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class MasterAssetHydrateHealthRank implements ShouldBeUnique
{
    use AsAction;

    public string $commandSignature = 'hydrate:master-asset-health-rank';

    public function asCommand(Command $command): void
    {
        $this->handle();
        $command->info('Master asset health ranks updated.');
    }

    public function handle(): void
    {
        DB::statement("
            WITH stats AS (
                SELECT
                    master_asset_id,
                    MAX(date) AS last_sale_date,
                    SUM(CASE WHEN date >= NOW() - INTERVAL '90 days' THEN COALESCE(grp_net_amount, 0) ELSE 0 END) AS revenue_3m
                FROM invoice_transactions
                WHERE in_process = false
                  AND deleted_at IS NULL
                  AND master_asset_id IS NOT NULL
                GROUP BY master_asset_id
            ),
            active AS (
                SELECT master_asset_id, revenue_3m
                FROM stats
                WHERE last_sale_date >= NOW() - INTERVAL '90 days'
            ),
            total AS (
                SELECT GREATEST(SUM(revenue_3m), 1) AS total_revenue FROM active
            ),
            cumulative AS (
                SELECT
                    a.master_asset_id,
                    SUM(a.revenue_3m) OVER (ORDER BY a.revenue_3m DESC ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) / t.total_revenue AS cum_pct
                FROM active a
                CROSS JOIN total t
            ),
            final_ranks AS (
                SELECT master_asset_id, 'D' AS health_rank
                FROM stats
                WHERE last_sale_date IS NULL OR last_sale_date < NOW() - INTERVAL '90 days'

                UNION ALL

                SELECT
                    master_asset_id,
                    CASE
                        WHEN cum_pct <= 0.15 THEN 'A'
                        WHEN cum_pct <= 0.50 THEN 'B'
                        ELSE 'C'
                    END AS health_rank
                FROM cumulative
            )
            UPDATE master_assets
            SET health_rank = fr.health_rank
            FROM final_ranks fr
            WHERE master_assets.id = fr.master_asset_id
        ");

        DB::statement("
            UPDATE master_assets
            SET health_rank = 'D'
            WHERE NOT EXISTS (
                SELECT 1
                FROM invoice_transactions it
                WHERE it.master_asset_id = master_assets.id
                  AND it.deleted_at IS NULL
            )
        ");
    }
}
