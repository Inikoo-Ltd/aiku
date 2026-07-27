<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Masters;

use App\Actions\Catalogue\Product\UpdateProduct;
use App\Models\Catalogue\Product;
use App\Models\Masters\MasterShop;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Corrects `products.units` where it is merely a stale number, and nothing else.
 *
 * A product qualifies only when four independent sources agree on the pack size and the product's
 * units column is the lone dissenter:
 *   - the master asset's own units
 *   - the majority of that master's live children
 *   - the master's trade-unit composition (single line, quantity equal to its units)
 *   - THE PRODUCT'S OWN trade-unit composition (single line, same quantity)
 *
 * That last condition is what makes this safe to run as a scalar write. Products whose own
 * composition agrees with their own units describe a genuinely different pack, so correcting them
 * means changing the bill of materials - which cascades into product_has_org_stocks and the
 * hydrated weights - and belongs in a separate, reviewed operation. This action never touches
 * model_has_trade_units, product_has_org_stocks or any org stock.
 */
class SyncMasterChildUnitsWhereScalarIsStale
{
    use AsAction;

    public string $commandSignature = 'repair:master_child_units {master_shop : master shop slug} {--shop= : limit to one shop code} {--fix : Write corrections (default is report only)}';

    /**
     * @return array{checked: int, fixed: int}
     */
    public function handle(MasterShop $masterShop, bool $fix = false, ?Command $command = null, ?string $shopCode = null): array
    {
        $counts = ['checked' => 0, 'fixed' => 0];

        foreach ($this->staleScalarProducts($masterShop, $shopCode) as $row) {
            $counts['checked']++;

            $command?->line(sprintf(
                '%s %s: units %s -> %s (master %s, %d of %d siblings agree)',
                $row->shop_code,
                $row->product_code,
                rtrim(rtrim($row->product_units, '0'), '.'),
                rtrim(rtrim($row->master_units, '0'), '.'),
                $row->master_code,
                $row->majority_count,
                $row->sibling_count
            ));

            if ($fix) {
                $product = Product::find($row->product_id);
                if ($product) {
                    UpdateProduct::make()->action($product, ['units' => $row->master_units]);
                    $counts['fixed']++;
                }
            }
        }

        return $counts;
    }

    /**
     * @return array<int, object>
     */
    private function staleScalarProducts(MasterShop $masterShop, ?string $shopCode): array
    {
        /**
         * The shop filter is appended rather than passed as a nullable parameter: an
         * `(cast(? as text) is null or shop_code = ?)` condition makes Postgres plan the whole
         * statement generically and it never returns on the production catalogue. Note it can only
         * narrow which deviants are corrected, never which siblings are counted - the majority has
         * to be measured across every shop or it is not a majority.
         */
        $shopCondition = $shopCode ? 'and c.shop_code = ?' : '';
        $bindings      = $shopCode ? [$masterShop->id, $shopCode] : [$masterShop->id];

        return DB::select(
            "
            with child as (
                select ma.id as ma_id, ma.code as master_code, round(ma.units, 3) as m_units,
                       p.id as product_id, p.code as product_code, round(p.units, 3) as p_units,
                       s.code as shop_code
                from master_assets ma
                join products p on p.master_product_id = ma.id
                    and p.deleted_at is null and p.is_for_sale and not p.not_follow_master_trade_units
                join shops s on s.id = p.shop_id
                where ma.master_shop_id = ? and ma.deleted_at is null and ma.status
            ),
            split as (
                select ma_id from child group by ma_id having count(distinct p_units) > 1
            ),
            counts as (
                select c.ma_id, c.m_units, c.p_units, count(*) as n
                from child c join split on split.ma_id = c.ma_id
                group by c.ma_id, c.m_units, c.p_units
            ),
            maj as (
                select ma_id, m_units, p_units as majority_units, n,
                       sum(n) over (partition by ma_id) as total,
                       row_number() over (partition by ma_id order by n desc) as rk
                from counts
            ),
            master_pivot as (
                select model_id as ma_id, count(*) as lines, round(sum(quantity), 3) as qty
                from model_has_trade_units where model_type = 'MasterAsset' group by model_id
            ),
            product_pivot as (
                select model_id as product_id, count(*) as lines, round(sum(quantity), 3) as qty
                from model_has_trade_units where model_type = 'Product' group by model_id
            ),
            agreed as (
                select m.ma_id, m.m_units, m.n as majority_count, m.total as sibling_count
                from maj m
                join master_pivot mp on mp.ma_id = m.ma_id
                where m.rk = 1 and m.majority_units = m.m_units and mp.lines = 1 and mp.qty = m.m_units
            )
            select c.product_id, c.product_code, c.shop_code, c.master_code,
                   c.p_units as product_units, a.m_units as master_units,
                   a.majority_count, a.sibling_count
            from child c
            join agreed a on a.ma_id = c.ma_id
            join product_pivot pp on pp.product_id = c.product_id
            where c.p_units <> a.m_units
              and pp.lines = 1
              and pp.qty = a.m_units
              $shopCondition
            order by c.shop_code, c.product_code
            ",
            $bindings
        );
    }

    public function asCommand(Command $command): int
    {
        $masterShop = MasterShop::where('slug', $command->argument('master_shop'))->firstOrFail();
        $shopCode   = $command->option('shop');
        $fix        = (bool) $command->option('fix');

        if (!$fix) {
            $command->warn('REPORT ONLY: pass --fix to write corrections');
        }

        $counts = $this->handle($masterShop, $fix, $command, $shopCode);

        $command->info(sprintf(
            'Done: %d products with a stale units scalar%s, %d %s',
            $counts['checked'],
            $shopCode ? " in $shopCode" : '',
            $fix ? $counts['fixed'] : $counts['checked'],
            $fix ? 'corrected' : 'to correct'
        ));

        return 0;
    }
}
