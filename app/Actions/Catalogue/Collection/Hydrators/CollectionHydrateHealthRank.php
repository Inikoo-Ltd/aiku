<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 12 Mar 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Catalogue\Collection\Hydrators;

use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CollectionHydrateHealthRank implements ShouldBeUnique
{
    use AsAction;

    public string $commandSignature = 'hydrate:collection-health-rank';

    public function asCommand(Command $command): void
    {
        $this->handle();
        $command->info('Collection health ranks updated.');
    }

    public function handle(): void
    {
        DB::statement("
            WITH stats AS (
                SELECT
                    chm.collection_id,
                    MAX(it.date) AS last_sale_date,
                    SUM(CASE WHEN it.date >= NOW() - INTERVAL '90 days' THEN COALESCE(it.grp_net_amount, 0) ELSE 0 END) AS revenue_3m
                FROM invoice_transactions it
                JOIN products p ON p.asset_id = it.asset_id
                JOIN collection_has_models chm ON chm.model_id = p.id AND chm.model_type = 'Product'
                WHERE it.in_process = false
                  AND it.deleted_at IS NULL
                GROUP BY chm.collection_id
            ),
            active AS (
                SELECT collection_id, revenue_3m
                FROM stats
                WHERE last_sale_date >= NOW() - INTERVAL '90 days'
            ),
            total AS (
                SELECT GREATEST(SUM(revenue_3m), 1) AS total_revenue FROM active
            ),
            cumulative AS (
                SELECT
                    a.collection_id,
                    SUM(a.revenue_3m) OVER (ORDER BY a.revenue_3m DESC ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) / t.total_revenue AS cum_pct
                FROM active a
                CROSS JOIN total t
            ),
            final_ranks AS (
                SELECT collection_id, 'D' AS health_rank
                FROM stats
                WHERE last_sale_date IS NULL OR last_sale_date < NOW() - INTERVAL '90 days'

                UNION ALL

                SELECT
                    collection_id,
                    CASE
                        WHEN cum_pct <= 0.15 THEN 'A'
                        WHEN cum_pct <= 0.50 THEN 'B'
                        ELSE 'C'
                    END AS health_rank
                FROM cumulative
            )
            UPDATE collections
            SET health_rank = fr.health_rank
            FROM final_ranks fr
            WHERE collections.id = fr.collection_id
        ");

        DB::statement("
            UPDATE collections
            SET health_rank = 'D'
            WHERE NOT EXISTS (
                SELECT 1
                FROM collection_has_models chm
                JOIN products p ON p.id = chm.model_id AND chm.model_type = 'Product'
                JOIN invoice_transactions it ON it.asset_id = p.asset_id
                WHERE chm.collection_id = collections.id
                  AND it.deleted_at IS NULL
            )
        ");
    }
}
