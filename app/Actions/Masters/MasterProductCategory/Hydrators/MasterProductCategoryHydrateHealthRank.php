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
                    SUM(CASE WHEN date >= NOW() - INTERVAL '90 days' THEN COALESCE(grp_net_amount, 0) ELSE 0 END) AS revenue_3m
                FROM invoice_transactions
                WHERE in_process = false
                  AND deleted_at IS NULL
                  AND {$fkColumn} IS NOT NULL
                GROUP BY {$fkColumn}
            ),
            active AS (
                SELECT category_id, revenue_3m
                FROM stats
                WHERE last_sale_date >= NOW() - INTERVAL '90 days'
            ),
            total AS (
                SELECT GREATEST(SUM(revenue_3m), 1) AS total_revenue FROM active
            ),
            cumulative AS (
                SELECT
                    a.category_id,
                    SUM(a.revenue_3m) OVER (ORDER BY a.revenue_3m DESC ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) / t.total_revenue AS cum_pct
                FROM active a
                CROSS JOIN total t
            ),
            final_ranks AS (
                SELECT
                    s.category_id,
                    CASE WHEN EXISTS (
                        SELECT 1 FROM invoice_transactions it2
                        JOIN master_assets ma ON ma.id = it2.master_asset_id
                        WHERE it2.{$fkColumn} = s.category_id
                          AND it2.deleted_at IS NULL
                          AND ma.units > 0
                    ) THEN 'Z' ELSE 'D' END AS health_rank
                FROM stats s
                WHERE s.last_sale_date IS NULL OR s.last_sale_date < NOW() - INTERVAL '90 days'

                UNION ALL

                SELECT
                    category_id,
                    CASE
                        WHEN cum_pct <= 0.15 THEN 'A'
                        WHEN cum_pct <= 0.50 THEN 'B'
                        ELSE 'C'
                    END AS health_rank
                FROM cumulative
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
