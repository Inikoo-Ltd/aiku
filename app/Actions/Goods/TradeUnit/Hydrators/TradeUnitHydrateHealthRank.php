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
                    SUM(CASE WHEN it.date >= NOW() - INTERVAL '365 days' THEN 1 ELSE 0 END) AS freq_12m
                FROM invoice_transaction_has_trade_units ittu
                JOIN invoice_transactions it ON it.id = ittu.invoice_transaction_id
                WHERE it.in_process = false
                  AND it.deleted_at IS NULL
                  AND ittu.trade_unit_id IS NOT NULL
                GROUP BY ittu.trade_unit_id
            ),
            max_values AS (
                SELECT GREATEST(MAX(freq_12m), 1) AS max_freq
                FROM stats
                WHERE last_sale_date >= NOW() - INTERVAL '365 days'
            ),
            active_scored AS (
                SELECT
                    s.trade_unit_id,
                    s.freq_12m / m.max_freq AS score
                FROM stats s
                CROSS JOIN max_values m
                WHERE s.last_sale_date >= NOW() - INTERVAL '365 days'
            ),
            ranked AS (
                SELECT
                    trade_unit_id,
                    PERCENT_RANK() OVER (ORDER BY score) AS pct_rank
                FROM active_scored
            ),
            final_ranks AS (
                SELECT trade_unit_id, 'D' AS health_rank
                FROM stats
                WHERE last_sale_date IS NULL OR last_sale_date < NOW() - INTERVAL '365 days'

                UNION ALL

                SELECT
                    trade_unit_id,
                    CASE
                        WHEN pct_rank >= 0.85 THEN 'A'
                        WHEN pct_rank >= 0.50 THEN 'B'
                        ELSE 'C'
                    END AS health_rank
                FROM ranked
            )
            UPDATE trade_units
            SET health_rank = fr.health_rank
            FROM final_ranks fr
            WHERE trade_units.id = fr.trade_unit_id
        ");

        DB::statement("
            UPDATE trade_units
            SET health_rank = 'D'
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
