<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 12 Mar 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Goods\TradeUnitFamily\Hydrators;

use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class TradeUnitFamilyHydrateHealthRank implements ShouldBeUnique
{
    use AsAction;

    public string $commandSignature = 'hydrate:trade-unit-family-health-rank';

    public function asCommand(Command $command): void
    {
        $this->handle();
        $command->info('Trade unit family health ranks updated.');
    }

    public function handle(): void
    {
        DB::statement("
            WITH stats AS (
                SELECT
                    ittu.trade_unit_family_id,
                    MAX(it.date) AS last_sale_date,
                    SUM(CASE WHEN it.date >= NOW() - INTERVAL '90 days' THEN COALESCE(it.grp_net_amount, 0) ELSE 0 END) AS revenue_3m
                FROM invoice_transaction_has_trade_units ittu
                JOIN invoice_transactions it ON it.id = ittu.invoice_transaction_id
                WHERE it.in_process = false
                  AND it.deleted_at IS NULL
                  AND ittu.trade_unit_family_id IS NOT NULL
                GROUP BY ittu.trade_unit_family_id
            ),
            active AS (
                SELECT trade_unit_family_id, revenue_3m
                FROM stats
                WHERE last_sale_date >= NOW() - INTERVAL '90 days'
            ),
            total AS (
                SELECT GREATEST(SUM(revenue_3m), 1) AS total_revenue FROM active
            ),
            cumulative AS (
                SELECT
                    a.trade_unit_family_id,
                    SUM(a.revenue_3m) OVER (ORDER BY a.revenue_3m DESC ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) / t.total_revenue AS cum_pct
                FROM active a
                CROSS JOIN total t
            ),
            final_ranks AS (
                SELECT trade_unit_family_id, 'D' AS health_rank
                FROM stats
                WHERE last_sale_date IS NULL OR last_sale_date < NOW() - INTERVAL '90 days'

                UNION ALL

                SELECT
                    trade_unit_family_id,
                    CASE
                        WHEN cum_pct <= 0.15 THEN 'A'
                        WHEN cum_pct <= 0.50 THEN 'B'
                        ELSE 'C'
                    END AS health_rank
                FROM cumulative
            )
            UPDATE trade_unit_families
            SET health_rank = fr.health_rank
            FROM final_ranks fr
            WHERE trade_unit_families.id = fr.trade_unit_family_id
        ");

        DB::statement("
            UPDATE trade_unit_families
            SET health_rank = 'D'
            WHERE NOT EXISTS (
                SELECT 1
                FROM invoice_transaction_has_trade_units ittu
                JOIN invoice_transactions it ON it.id = ittu.invoice_transaction_id
                WHERE ittu.trade_unit_family_id = trade_unit_families.id
                  AND it.deleted_at IS NULL
            )
        ");
    }
}
