<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 12 Mar 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Masters\MasterProductCategory\Hydrators;

use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class MasterProductCategoryHydrateHealthRank implements ShouldBeUnique
{
    use AsAction;

    public string $commandSignature = 'hydrate:master-product-category-health-rank';

    public function asCommand(Command $command): void
    {
        $this->handle();
        $command->info('Master product category health ranks updated.');
    }

    public function handle(): void
    {
        $this->rankByType('family', 'master_family_id');
        $this->rankByType('sub_department', 'master_sub_department_id');
        $this->rankByType('department', 'master_department_id');
    }

    private function rankByType(string $type, string $fkColumn): void
    {
        DB::statement("
            WITH stats AS (
                SELECT
                    {$fkColumn} AS category_id,
                    MAX(date) AS last_sale_date,
                    SUM(CASE WHEN date >= NOW() - INTERVAL '365 days' THEN quantity ELSE 0 END) AS qty_12m,
                    SUM(CASE WHEN date >= NOW() - INTERVAL '365 days' THEN COALESCE(grp_net_amount, 0) ELSE 0 END) AS revenue_12m
                FROM invoice_transactions
                WHERE in_process = false
                  AND deleted_at IS NULL
                  AND {$fkColumn} IS NOT NULL
                GROUP BY {$fkColumn}
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
                    s.category_id,
                    (s.qty_12m / m.max_qty + s.revenue_12m / m.max_revenue) / 2 AS score
                FROM stats s
                CROSS JOIN max_values m
                WHERE s.last_sale_date >= NOW() - INTERVAL '365 days'
            ),
            ranked AS (
                SELECT
                    category_id,
                    PERCENT_RANK() OVER (ORDER BY score) AS pct_rank
                FROM active_scored
            ),
            final_ranks AS (
                SELECT category_id, 'D' AS health_rank
                FROM stats
                WHERE last_sale_date IS NULL OR last_sale_date < NOW() - INTERVAL '365 days'

                UNION ALL

                SELECT
                    category_id,
                    CASE
                        WHEN pct_rank >= 0.85 THEN 'A'
                        WHEN pct_rank >= 0.50 THEN 'B'
                        ELSE 'C'
                    END AS health_rank
                FROM ranked
            )
            UPDATE master_product_categories
            SET health_rank = fr.health_rank
            FROM final_ranks fr
            WHERE master_product_categories.id = fr.category_id
              AND master_product_categories.type = '{$type}'
        ");

        DB::statement("
            UPDATE master_product_categories
            SET health_rank = 'D'
            WHERE type = '{$type}'
              AND NOT EXISTS (
                SELECT 1
                FROM invoice_transactions it
                WHERE it.{$fkColumn} = master_product_categories.id
                  AND it.deleted_at IS NULL
              )
        ");
    }
}
