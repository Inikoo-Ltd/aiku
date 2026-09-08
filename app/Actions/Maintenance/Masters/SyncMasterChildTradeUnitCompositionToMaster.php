<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Masters;

use App\Actions\Catalogue\Product\SyncProductTradeUnits;
use App\Actions\Catalogue\Product\UpdateProduct;
use App\Models\Catalogue\Product;
use App\Models\Masters\MasterShop;
use Generator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Brings a child product's bill of materials back onto its master's pack size.
 *
 * This is the other half of `repair:master_child_units`, which corrects products whose `units`
 * column is merely a stale number while their composition already agrees with the master. Here the
 * product's units and its own trade-unit quantity agree with each other and dissent from the
 * master, so the bill of materials itself is what is wrong and rewriting it cascades into
 * `product_has_org_stocks`, master stocks and the hydrated weights. That is why the write goes
 * through SyncProductTradeUnits and never touches `model_has_trade_units` directly.
 *
 * A product qualifies only when the master's units, the majority of that master's live children and
 * the master's own composition (a single line whose quantity equals its units) all agree, the
 * product dissents from all three, and the product's own composition is a single line of the same
 * trade unit whose quantity equals its own units. Anything less consistent than that is a judgement
 * call for a human and is reported, not written.
 */
class SyncMasterChildTradeUnitCompositionToMaster
{
    use AsAction;

    private const int MASTER_CHUNK = 200;

    public string $commandSignature = 'repair:master_child_trade_unit_composition {master_shop : master shop slug} {--shop= : limit to one shop code} {--fix : Write corrections (default is report only)}';

    /**
     * @return array{checked: int, fixed: int, skipped: int}
     */
    public function handle(MasterShop $masterShop, bool $fix = false, ?Command $command = null, ?string $shopCode = null): array
    {
        $counts = ['checked' => 0, 'fixed' => 0, 'skipped' => 0];

        foreach ($this->divergentCompositions($masterShop, $shopCode) as $row) {
            if ($row->trade_unit_id !== $row->master_trade_unit_id) {
                $counts['skipped']++;
                $command?->warn(sprintf(
                    '%s %s: built from a different trade unit than master %s, left alone',
                    $row->shop_code,
                    $row->product_code,
                    $row->master_code
                ));

                continue;
            }

            $counts['checked']++;

            $command?->line(sprintf(
                '%s %s: %s x%s -> x%s (master %s, %d of %d siblings agree)',
                $row->shop_code,
                $row->product_code,
                $row->trade_unit_code,
                $this->trim($row->product_units),
                $this->trim($row->master_units),
                $row->master_code,
                $row->majority_count,
                $row->sibling_count
            ));

            if ($fix) {
                $product = Product::find($row->product_id);
                if ($product) {
                    SyncProductTradeUnits::run($product, [
                        ['id' => $row->trade_unit_id, 'quantity' => $row->master_units],
                    ]);
                    UpdateProduct::make()->action($product, ['units' => $row->master_units]);
                    $counts['fixed']++;
                }
            }
        }

        return $counts;
    }

    /**
     * Candidate masters are found first, without touching `model_has_trade_units`, then their
     * compositions are read a chunk of masters at a time. Aggregating that whole table up front
     * leaves a production run silent for minutes before it prints anything, which reads as a hang.
     *
     * @return Generator<int, object>
     */
    private function divergentCompositions(MasterShop $masterShop, ?string $shopCode): Generator
    {
        foreach (array_chunk($this->agreedMasters($masterShop), self::MASTER_CHUNK) as $masters) {
            $byId = collect($masters)->keyBy('ma_id');

            $masterPivots = $this->compositions('MasterAsset', $byId->keys()->all());

            $masterPivots = $masterPivots->filter(
                fn ($pivot, $maId) => $pivot->lines == 1 && (float) $pivot->qty === (float) $byId[$maId]->m_units
            );

            if ($masterPivots->isEmpty()) {
                continue;
            }

            $children = $this->children($masterPivots->keys()->all(), $shopCode);

            if ($children->isEmpty()) {
                continue;
            }

            $productPivots = $this->compositions('Product', $children->pluck('product_id')->all());

            foreach ($children as $child) {
                $master = $byId[$child->ma_id];
                $pivot  = $productPivots->get($child->product_id);

                if (!$pivot || $pivot->lines != 1) {
                    continue;
                }

                if ((float) $child->p_units === (float) $master->m_units) {
                    continue;
                }

                if ((float) $pivot->qty !== (float) $child->p_units) {
                    continue;
                }

                yield (object) [
                    'product_id'           => $child->product_id,
                    'product_code'         => $child->product_code,
                    'shop_code'            => $child->shop_code,
                    'product_units'        => (float) $child->p_units,
                    'master_code'          => $master->master_code,
                    'master_units'         => (float) $master->m_units,
                    'majority_count'       => $master->majority_count,
                    'sibling_count'        => $master->sibling_count,
                    'trade_unit_id'        => $pivot->trade_unit_id,
                    'trade_unit_code'      => $pivot->trade_unit_code,
                    'master_trade_unit_id' => $masterPivots[$child->ma_id]->trade_unit_id,
                ];
            }
        }
    }

    /**
     * Masters whose units are backed by the majority of their live children. The majority is
     * measured across every shop, so no shop filter belongs here.
     *
     * It has to be a strict majority. On a tie the winning row is whichever the ordering happened
     * to put first, so a two-child master split one against one would be "confirmed" by an
     * arbitrary sort and rewritten on no evidence. Masters with no clear majority are a judgement
     * call and are left to RepairMasterProductUnitsIntegrity to report.
     *
     * @return array<int, object>
     */
    private function agreedMasters(MasterShop $masterShop): array
    {
        return DB::select(
            "
            with child as (
                select ma.id as ma_id, ma.code as master_code, round(ma.units, 3) as m_units,
                       round(p.units, 3) as p_units
                from master_assets ma
                join products p on p.master_product_id = ma.id
                    and p.deleted_at is null and p.is_for_sale and not p.not_follow_master_trade_units
                where ma.master_shop_id = ? and ma.deleted_at is null and ma.status
            ),
            split as (
                select ma_id from child group by ma_id having count(distinct p_units) > 1
            ),
            counts as (
                select c.ma_id, c.master_code, c.m_units, c.p_units, count(*) as n
                from child c join split on split.ma_id = c.ma_id
                group by c.ma_id, c.master_code, c.m_units, c.p_units
            ),
            maj as (
                select ma_id, master_code, m_units, p_units as majority_units, n,
                       sum(n) over (partition by ma_id) as total,
                       row_number() over (partition by ma_id order by n desc) as rk
                from counts
            )
            select ma_id, master_code, m_units, n as majority_count, total as sibling_count
            from maj
            where rk = 1 and majority_units = m_units and n * 2 > total
            order by ma_id
            ",
            [$masterShop->id]
        );
    }

    /**
     * @param  array<int, int>  $modelIds
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function compositions(string $modelType, array $modelIds)
    {
        return collect(DB::select(
            "
            select mhtu.model_id, count(*) as lines, round(sum(mhtu.quantity), 3) as qty,
                   min(mhtu.trade_unit_id) as trade_unit_id, min(tu.code) as trade_unit_code
            from model_has_trade_units mhtu
            join trade_units tu on tu.id = mhtu.trade_unit_id
            where mhtu.model_type = ? and mhtu.model_id = any(?)
            group by mhtu.model_id
            ",
            [$modelType, '{'.implode(',', $modelIds).'}']
        ))->keyBy('model_id');
    }

    /**
     * @param  array<int, int>  $masterIds
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function children(array $masterIds, ?string $shopCode)
    {
        /**
         * The shop filter is appended rather than bound as a nullable parameter: an
         * `(cast(? as text) is null or code = ?)` condition makes Postgres plan generically and the
         * statement never returns on the production catalogue.
         */
        $shopCondition = $shopCode ? 'and s.code = ?' : '';
        $bindings      = ['{'.implode(',', $masterIds).'}'];
        if ($shopCode) {
            $bindings[] = $shopCode;
        }

        return collect(DB::select(
            "
            select p.id as product_id, p.code as product_code, round(p.units, 3) as p_units,
                   p.master_product_id as ma_id, s.code as shop_code
            from products p
            join shops s on s.id = p.shop_id
            where p.master_product_id = any(?)
              and p.deleted_at is null and p.is_for_sale and not p.not_follow_master_trade_units
              $shopCondition
            order by s.code, p.code
            ",
            $bindings
        ));
    }

    private function trim(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
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
            'Done: %d products whose composition differs from master%s, %d %s, %d skipped',
            $counts['checked'],
            $shopCode ? " in $shopCode" : '',
            $fix ? $counts['fixed'] : $counts['checked'],
            $fix ? 'corrected' : 'to correct',
            $counts['skipped']
        ));

        return 0;
    }
}
