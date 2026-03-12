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
                    SUM(CASE WHEN date >= NOW() - INTERVAL '365 days' THEN quantity ELSE 0 END) AS qty_12m,
                    SUM(CASE WHEN date >= NOW() - INTERVAL '365 days' THEN COALESCE(grp_net_amount, 0) ELSE 0 END) AS revenue_12m
                FROM invoice_transactions
                WHERE in_process = false
                  AND deleted_at IS NULL
                  AND master_asset_id IS NOT NULL
                GROUP BY master_asset_id
            ),
            max_values AS (
                SELECT
                    GREATEST(MAX(qty_12m), 1)     AS max_qty,
                    GREATEST(MAX(revenue_12m), 1) AS max_revenue
                FROM stats
                WHERE last_sale_date >= NOW() - INTERVAL '365 days'
            ),
            active_scored AS (
                SELECT
                    s.master_asset_id,
                    (s.qty_12m / m.max_qty + s.revenue_12m / m.max_revenue) / 2 AS score
                FROM stats s
                CROSS JOIN max_values m
                WHERE s.last_sale_date >= NOW() - INTERVAL '365 days'
            ),
            ranked AS (
                SELECT
                    master_asset_id,
                    PERCENT_RANK() OVER (ORDER BY score) AS pct_rank
                FROM active_scored
            ),
            final_ranks AS (
                SELECT master_asset_id, 'D' AS health_rank
                FROM stats
                WHERE last_sale_date IS NULL OR last_sale_date < NOW() - INTERVAL '365 days'

                UNION ALL

                SELECT
                    master_asset_id,
                    CASE
                        WHEN pct_rank >= 0.85 THEN 'A'
                        WHEN pct_rank >= 0.50 THEN 'B'
                        ELSE 'C'
                    END AS health_rank
                FROM ranked
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
