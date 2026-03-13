<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 12 Mar 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Inventory\OrgStockFamily\Hydrators;

use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class OrgStockFamilyHydrateHealthRank implements ShouldBeUnique
{
    use AsAction;

    public string $commandSignature = 'hydrate:org-stock-family-health-rank';

    public function asCommand(Command $command): void
    {
        $this->handle();
        $command->info('Org stock family health ranks updated.');
    }

    public function handle(): void
    {
        DB::statement("
            WITH stats AS (
                SELECT
                    itos.org_stock_family_id,
                    MAX(it.date) AS last_sale_date,
                    SUM(CASE WHEN it.date >= NOW() - INTERVAL '90 days' THEN COALESCE(it.grp_net_amount, 0) ELSE 0 END) AS revenue_3m
                FROM invoice_transaction_has_org_stocks itos
                JOIN invoice_transactions it ON it.id = itos.invoice_transaction_id
                WHERE it.in_process = false
                  AND it.deleted_at IS NULL
                  AND itos.org_stock_family_id IS NOT NULL
                GROUP BY itos.org_stock_family_id
            ),
            active AS (
                SELECT org_stock_family_id, revenue_3m
                FROM stats
                WHERE last_sale_date >= NOW() - INTERVAL '90 days'
            ),
            total AS (
                SELECT GREATEST(SUM(revenue_3m), 1) AS total_revenue FROM active
            ),
            cumulative AS (
                SELECT
                    a.org_stock_family_id,
                    SUM(a.revenue_3m) OVER (ORDER BY a.revenue_3m DESC ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) / t.total_revenue AS cum_pct
                FROM active a
                CROSS JOIN total t
            ),
            final_ranks AS (
                SELECT org_stock_family_id, 'D' AS health_rank
                FROM stats
                WHERE last_sale_date IS NULL OR last_sale_date < NOW() - INTERVAL '90 days'

                UNION ALL

                SELECT
                    org_stock_family_id,
                    CASE
                        WHEN cum_pct <= 0.15 THEN 'A'
                        WHEN cum_pct <= 0.50 THEN 'B'
                        ELSE 'C'
                    END AS health_rank
                FROM cumulative
            )
            UPDATE org_stock_families
            SET health_rank = fr.health_rank
            FROM final_ranks fr
            WHERE org_stock_families.id = fr.org_stock_family_id
        ");

        DB::statement("
            UPDATE org_stock_families
            SET health_rank = 'D'
            WHERE NOT EXISTS (
                SELECT 1
                FROM invoice_transaction_has_org_stocks itos
                JOIN invoice_transactions it ON it.id = itos.invoice_transaction_id
                WHERE itos.org_stock_family_id = org_stock_families.id
                  AND it.deleted_at IS NULL
            )
        ");
    }
}
