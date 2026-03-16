<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 11 Mar 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Catalogue\Asset\Hydrators;

use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class AssetHydrateHealthRank implements ShouldBeUnique
{
    use AsAction;

    public string $commandSignature = 'hydrate:asset-health-rank';

    public function asCommand(Command $command): void
    {
        $this->handle();
        $command->info('Asset health ranks updated.');
    }

    public function handle(): void
    {
        DB::statement("
            WITH stats AS (
                SELECT
                    asset_id,
                    MAX(date) AS last_sale_date,
                    SUM(CASE WHEN date >= NOW() - INTERVAL '90 days' THEN COALESCE(grp_net_amount, 0) ELSE 0 END) AS revenue_3m
                FROM invoice_transactions
                WHERE in_process = false
                  AND deleted_at IS NULL
                  AND asset_id IS NOT NULL
                GROUP BY asset_id
            ),
            active AS (
                SELECT asset_id, revenue_3m
                FROM stats
                WHERE last_sale_date >= NOW() - INTERVAL '90 days'
            ),
            total AS (
                SELECT GREATEST(SUM(revenue_3m), 1) AS total_revenue FROM active
            ),
            cumulative AS (
                SELECT
                    a.asset_id,
                    SUM(a.revenue_3m) OVER (ORDER BY a.revenue_3m DESC ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) / t.total_revenue AS cum_pct
                FROM active a
                CROSS JOIN total t
            ),
            final_ranks AS (
                SELECT asset_id, 'D' AS health_rank
                FROM stats
                WHERE last_sale_date IS NULL OR last_sale_date < NOW() - INTERVAL '90 days'

                UNION ALL

                SELECT
                    asset_id,
                    CASE
                        WHEN cum_pct <= 0.15 THEN 'A'
                        WHEN cum_pct <= 0.50 THEN 'B'
                        ELSE 'C'
                    END AS health_rank
                FROM cumulative
            )
            UPDATE assets
            SET health_rank = fr.health_rank
            FROM final_ranks fr
            WHERE assets.id = fr.asset_id
        ");

        DB::statement("
            UPDATE assets
            SET health_rank = 'D'
            WHERE NOT EXISTS (
                SELECT 1
                FROM invoice_transactions it
                WHERE it.asset_id = assets.id
                  AND it.deleted_at IS NULL
            )
        ");
    }
}
