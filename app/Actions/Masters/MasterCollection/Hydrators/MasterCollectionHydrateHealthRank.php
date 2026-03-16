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
                    SUM(CASE WHEN it.date >= NOW() - INTERVAL '90 days' THEN COALESCE(it.grp_net_amount, 0) ELSE 0 END) AS revenue_3m
                FROM invoice_transactions it
                JOIN master_collection_has_models mchm ON mchm.model_id = it.master_asset_id AND mchm.model_type = 'MasterAsset'
                WHERE it.in_process = false
                  AND it.deleted_at IS NULL
                GROUP BY mchm.master_collection_id
            ),
            active AS (
                SELECT master_collection_id, revenue_3m
                FROM stats
                WHERE last_sale_date >= NOW() - INTERVAL '90 days'
            ),
            total AS (
                SELECT GREATEST(SUM(revenue_3m), 1) AS total_revenue FROM active
            ),
            cumulative AS (
                SELECT
                    a.master_collection_id,
                    SUM(a.revenue_3m) OVER (ORDER BY a.revenue_3m DESC ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) / t.total_revenue AS cum_pct
                FROM active a
                CROSS JOIN total t
            ),
            final_ranks AS (
                SELECT
                    s.master_collection_id,
                    CASE WHEN EXISTS (
                        SELECT 1 FROM master_collection_has_models mchm2
                        JOIN master_assets ma ON ma.id = mchm2.model_id AND mchm2.model_type = 'MasterAsset'
                        WHERE mchm2.master_collection_id = s.master_collection_id
                          AND ma.units > 0
                    ) THEN 'Z' ELSE 'D' END AS health_rank
                FROM stats s
                WHERE s.last_sale_date IS NULL OR s.last_sale_date < NOW() - INTERVAL '90 days'

                UNION ALL

                SELECT
                    master_collection_id,
                    CASE
                        WHEN cum_pct <= 0.15 THEN 'A'
                        WHEN cum_pct <= 0.50 THEN 'B'
                        ELSE 'C'
                    END AS health_rank
                FROM cumulative
            )
            UPDATE master_collections
            SET health_rank = fr.health_rank
            FROM final_ranks fr
            WHERE master_collections.id = fr.master_collection_id
        ");

        DB::statement("
            UPDATE master_collections
            SET health_rank = CASE WHEN EXISTS (
                SELECT 1 FROM master_collection_has_models mchm
                JOIN master_assets ma ON ma.id = mchm.model_id AND mchm.model_type = 'MasterAsset'
                WHERE mchm.master_collection_id = master_collections.id
                  AND ma.units > 0
            ) THEN 'Z' ELSE 'D' END
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
