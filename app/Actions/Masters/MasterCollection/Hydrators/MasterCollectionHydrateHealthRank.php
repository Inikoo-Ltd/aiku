<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 12 Mar 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Masters\MasterCollection\Hydrators;

use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class MasterCollectionHydrateHealthRank implements ShouldBeUnique
{
    use AsAction;

    public string $commandSignature = 'hydrate:master-collection-health-rank';

    public function asCommand(Command $command): void
    {
        $this->handle();
        $command->info('Master collection health ranks updated.');
    }

    public function handle(): void
    {
        DB::statement("
            WITH stats AS (
                SELECT
                    mchm.master_collection_id,
                    MAX(it.date) AS last_sale_date,
                    SUM(CASE WHEN it.date >= NOW() - INTERVAL '365 days' THEN it.quantity ELSE 0 END) AS qty_12m,
                    SUM(CASE WHEN it.date >= NOW() - INTERVAL '365 days' THEN COALESCE(it.grp_net_amount, 0) ELSE 0 END) AS revenue_12m
                FROM invoice_transactions it
                JOIN master_collection_has_models mchm ON mchm.model_id = it.master_asset_id AND mchm.model_type = 'MasterAsset'
                WHERE it.in_process = false
                  AND it.deleted_at IS NULL
                GROUP BY mchm.master_collection_id
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
                    s.master_collection_id,
                    (s.qty_12m / m.max_qty + s.revenue_12m / m.max_revenue) / 2 AS score
                FROM stats s
                CROSS JOIN max_values m
                WHERE s.last_sale_date >= NOW() - INTERVAL '365 days'
            ),
            ranked AS (
                SELECT
                    master_collection_id,
                    PERCENT_RANK() OVER (ORDER BY score) AS pct_rank
                FROM active_scored
            ),
            final_ranks AS (
                SELECT master_collection_id, 'D' AS health_rank
                FROM stats
                WHERE last_sale_date IS NULL OR last_sale_date < NOW() - INTERVAL '365 days'

                UNION ALL

                SELECT
                    master_collection_id,
                    CASE
                        WHEN pct_rank >= 0.85 THEN 'A'
                        WHEN pct_rank >= 0.50 THEN 'B'
                        ELSE 'C'
                    END AS health_rank
                FROM ranked
            )
            UPDATE master_collections
            SET health_rank = fr.health_rank
            FROM final_ranks fr
            WHERE master_collections.id = fr.master_collection_id
        ");

        DB::statement("
            UPDATE master_collections
            SET health_rank = 'D'
            WHERE NOT EXISTS (
                SELECT 1
                FROM master_collection_has_models mchm
                JOIN invoice_transactions it ON it.master_asset_id = mchm.model_id AND mchm.model_type = 'MasterAsset'
                WHERE mchm.master_collection_id = master_collections.id
                  AND it.deleted_at IS NULL
            )
        ");
    }
}
