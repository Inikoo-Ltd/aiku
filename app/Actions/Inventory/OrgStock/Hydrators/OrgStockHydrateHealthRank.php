<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 12 Mar 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Inventory\OrgStock\Hydrators;

use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class OrgStockHydrateHealthRank implements ShouldBeUnique
{
    use AsAction;

    public string $commandSignature = 'hydrate:org-stock-health-rank';

    public function asCommand(Command $command): void
    {
        $this->handle();
        $command->info('Org stock health ranks updated.');
    }

    public function handle(): void
    {
        DB::statement("
            WITH stats AS (
                SELECT
                    itos.org_stock_id,
                    MAX(it.date) AS last_sale_date,
                    SUM(CASE WHEN it.date >= NOW() - INTERVAL '90 days' THEN COALESCE(itos.grp_net_amount, 0) ELSE 0 END) AS revenue_3m
                FROM invoice_transaction_has_org_stocks itos
                JOIN invoice_transactions it ON it.id = itos.invoice_transaction_id
                WHERE it.in_process = false
                  AND it.deleted_at IS NULL
                  AND itos.org_stock_id IS NOT NULL
                GROUP BY itos.org_stock_id
            ),
            active AS (
                SELECT org_stock_id, revenue_3m
                FROM stats
                WHERE last_sale_date >= NOW() - INTERVAL '90 days'
            ),
            total AS (
                SELECT GREATEST(SUM(revenue_3m), 1) AS total_revenue FROM active
            ),
            cumulative AS (
                SELECT
                    a.org_stock_id,
                    SUM(a.revenue_3m) OVER (ORDER BY a.revenue_3m DESC ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) / t.total_revenue AS cum_pct
                FROM active a
                CROSS JOIN total t
            ),
            final_ranks AS (
                SELECT
                    s.org_stock_id,
                    CASE WHEN os.quantity_in_locations > 0 THEN 'Z' ELSE 'D' END AS health_rank
                FROM stats s
                JOIN org_stocks os ON os.id = s.org_stock_id
                WHERE s.last_sale_date IS NULL OR s.last_sale_date < NOW() - INTERVAL '90 days'

                UNION ALL

                SELECT
                    org_stock_id,
                    CASE
                        WHEN cum_pct <= 0.15 THEN 'A'
                        WHEN cum_pct <= 0.50 THEN 'B'
                        ELSE 'C'
                    END AS health_rank
                FROM cumulative
            )
            UPDATE org_stocks
            SET health_rank = fr.health_rank
            FROM final_ranks fr
            WHERE org_stocks.id = fr.org_stock_id
        ");

        DB::statement("
            UPDATE org_stocks
            SET health_rank = CASE WHEN quantity_in_locations > 0 THEN 'Z' ELSE 'D' END
            WHERE NOT EXISTS (
                SELECT 1
                FROM invoice_transaction_has_org_stocks itos
                JOIN invoice_transactions it ON it.id = itos.invoice_transaction_id
                WHERE itos.org_stock_id = org_stocks.id
                  AND it.deleted_at IS NULL
            )
        ");
    }
}
