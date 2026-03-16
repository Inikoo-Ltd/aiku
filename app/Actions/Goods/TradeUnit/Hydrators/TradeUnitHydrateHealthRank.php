<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 12 Mar 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Goods\TradeUnit\Hydrators;

use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class TradeUnitHydrateHealthRank implements ShouldBeUnique
{
    use AsAction;

    public string $commandSignature = 'hydrate:trade-unit-health-rank';

    public function asCommand(Command $command): void
    {
        $this->handle();
        $command->info('Trade unit health ranks updated.');
    }

    public function handle(): void
    {
        DB::statement("
            WITH stats AS (
                SELECT
                    ittu.trade_unit_id,
                    MAX(it.date) AS last_sale_date,
                    SUM(CASE WHEN it.date >= NOW() - INTERVAL '90 days' THEN COALESCE(it.grp_net_amount, 0) ELSE 0 END) AS revenue_3m
                FROM invoice_transaction_has_trade_units ittu
                JOIN invoice_transactions it ON it.id = ittu.invoice_transaction_id
                WHERE it.in_process = false
                  AND it.deleted_at IS NULL
                  AND ittu.trade_unit_id IS NOT NULL
                GROUP BY ittu.trade_unit_id
            ),
            active AS (
                SELECT trade_unit_id, revenue_3m
                FROM stats
                WHERE last_sale_date >= NOW() - INTERVAL '90 days'
            ),
            total AS (
                SELECT GREATEST(SUM(revenue_3m), 1) AS total_revenue FROM active
            ),
            cumulative AS (
                SELECT
                    a.trade_unit_id,
                    SUM(a.revenue_3m) OVER (ORDER BY a.revenue_3m DESC ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) / t.total_revenue AS cum_pct
                FROM active a
                CROSS JOIN total t
            ),
            final_ranks AS (
                SELECT
                    s.trade_unit_id,
                    CASE WHEN EXISTS (
                        SELECT 1 FROM model_has_trade_units mhtu
                        JOIN org_stocks os ON os.id = mhtu.model_id AND mhtu.model_type = 'OrgStock'
                        WHERE mhtu.trade_unit_id = s.trade_unit_id
                          AND os.quantity_in_locations > 0
                    ) THEN 'Z' ELSE 'D' END AS health_rank
                FROM stats s
                WHERE s.last_sale_date IS NULL OR s.last_sale_date < NOW() - INTERVAL '90 days'

                UNION ALL

                SELECT
                    trade_unit_id,
                    CASE
                        WHEN cum_pct <= 0.15 THEN 'A'
                        WHEN cum_pct <= 0.50 THEN 'B'
                        ELSE 'C'
                    END AS health_rank
                FROM cumulative
            )
            UPDATE trade_units
            SET health_rank = fr.health_rank
            FROM final_ranks fr
            WHERE trade_units.id = fr.trade_unit_id
        ");

        DB::statement("
            UPDATE trade_units
            SET health_rank = CASE WHEN EXISTS (
                SELECT 1 FROM model_has_trade_units mhtu
                JOIN org_stocks os ON os.id = mhtu.model_id AND mhtu.model_type = 'OrgStock'
                WHERE mhtu.trade_unit_id = trade_units.id
                  AND os.quantity_in_locations > 0
            ) THEN 'Z' ELSE 'D' END
            WHERE NOT EXISTS (
                SELECT 1
                FROM invoice_transaction_has_trade_units ittu
                JOIN invoice_transactions it ON it.id = ittu.invoice_transaction_id
                WHERE ittu.trade_unit_id = trade_units.id
                  AND it.deleted_at IS NULL
            )
        ");
    }
}
