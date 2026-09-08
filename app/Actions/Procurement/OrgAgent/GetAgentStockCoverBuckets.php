<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 31 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgAgent;

use App\Enums\Catalogue\HealthRankEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\Procurement\OrgSupplierProduct\OrgSupplierProductStateEnum;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Procurement\OrgAgent;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetAgentStockCoverBuckets
{
    use AsObject;

    public const BUCKETS = [
        'out'    => ['label' => 'Out of stock', 'tone' => 'red-deep'],
        'w1'     => ['label' => 'Doomed: gone before any delivery lands', 'tone' => 'red'],
        'w2'     => ['label' => 'Critical: out within 2 lead times', 'tone' => 'orange'],
        'w3'     => ['label' => 'Danger: out within 3 lead times', 'tone' => 'amber'],
        'w4'     => ['label' => 'Watch: out within 4 lead times', 'tone' => 'yellow'],
        'ok'     => ['label' => 'Covered', 'tone' => 'green'],
        'dead'   => ['label' => 'Dead stock', 'tone' => 'gray'],
        'gone'   => ['label' => 'We stocked it, we stopped', 'tone' => 'slate'],
        'never'  => ['label' => 'We never stocked', 'tone' => 'violet'],
    ];

    private const NOT_ORDERABLE = ['ok', 'dead', 'gone', 'never'];

    /**
     * Windows are expressed in lead times, not days: every product is judged against its own
     * supplier's clock, so a single day count on the tile would be a lie for most of the rows.
     */
    public function bucketLabel(string $bucket): string
    {
        return __(self::BUCKETS[$bucket]['label']);
    }

    /**
     * Per-row lead time: the supplier product's own measured or estimated days, agent roll-up as
     * the last resort. Sea freight makes the agent windows wide, so a product with real history
     * must never be judged against the agent average.
     */
    private function bucketExpression(int $leadDays): string
    {
        $lead = "coalesce(sp.measured_lead_time_days, sp.estimated_lead_time_days, $leadDays)";

        return "case
            when os.id is null then 'never'
            when os.state <> '".OrgStockStateEnum::ACTIVE->value."' then 'gone'
            when os.quantity_available <= 0 then 'out'
            when s.days_of_cover <= $lead then 'w1'
            when s.days_of_cover <= 2 * $lead then 'w2'
            when s.days_of_cover <= 3 * $lead then 'w3'
            when s.days_of_cover <= 4 * $lead then 'w4'
            when coalesce(s.predicted_daily_usage, 0) = 0 and s.stock_value > 0 then 'dead'
            else 'ok' end";
    }

    /**
     * Everything this agent's sub-suppliers can sell us, with our own stock alongside it when we
     * carry it. A supplier product can map to several of our org stocks, so one is chosen — the
     * live one, else the fullest — and each product is counted exactly once.
     */
    /**
     * The one org stock that answers for a supplier product: the link table's own primary, so the
     * tiles, the bucket rows and the add guard all judge the same stock.
     */
    public static function bestOrgStock(): Builder
    {
        return DB::table('org_stock_has_org_supplier_products as link')
            ->join('org_stocks', 'org_stocks.id', 'link.org_stock_id')
            ->whereColumn('link.org_supplier_product_id', 'osp.id')
            ->where('link.status', true)
            ->orderBy('link.local_priority')
            ->orderByRaw("(org_stocks.state = '".OrgStockStateEnum::ACTIVE->value."') desc, org_stocks.quantity_available desc nulls last")
            ->select(['org_stocks.id', 'org_stocks.state', 'org_stocks.quantity_available', 'org_stocks.health_rank'])
            ->limit(1);
    }

    public function scopedQuery(OrgAgent $orgAgent): Builder
    {
        return DB::table('org_supplier_products as osp')
            ->join('supplier_products as sp', 'sp.id', 'osp.supplier_product_id')
            ->leftJoinLateral(self::bestOrgStock(), 'os')
            ->leftJoin('org_stock_stats as s', 's.org_stock_id', 'os.id')
            ->where('osp.org_agent_id', $orgAgent->id)
            ->where('osp.state', OrgSupplierProductStateEnum::ACTIVE->value)
            ->where('osp.is_available', true)
            ->whereNull('sp.deleted_at');
    }

    private function onShoppingListExpression(): string
    {
        return "exists (select 1 from shopping_list_items sli
            where sli.org_supplier_product_id = osp.id
                and sli.state = '".ShoppingListItemStateEnum::OPEN->value."'
                and sli.deleted_at is null)";
    }

    /**
     * @return array{total: int, lead_time: array{days: int, source: string, samples: int}, buckets: array<int, array<string, mixed>>}
     */
    public function handle(OrgAgent $orgAgent): array
    {
        $leadTimes  = GetAgentLeadTimes::run($orgAgent);
        $leadTime   = $leadTimes['agent'];
        $expression = $this->bucketExpression($leadTime['days']);

        $rows = $this->scopedQuery($orgAgent)
            ->selectRaw("$expression as bucket,
                os.health_rank,
                count(*) as total,
                count(*) filter (where ".$this->onShoppingListExpression().") as on_list,
                count(*) filter (where coalesce(s.on_the_way_po_count, 0) > 0) as on_the_way,
                count(*) filter (where coalesce(s.on_the_way_po_count, 0) > 0 or ".$this->onShoppingListExpression().") as handled,
                count(distinct sp.supplier_id) as suppliers,
                coalesce(sum(s.stock_value), 0) as stock_value")
            ->groupByRaw("$expression, os.health_rank")
            ->get()
            ->groupBy('bucket');

        $buckets = collect(self::BUCKETS)->map(function ($meta, $bucket) use ($rows, $leadTime) {
            $bucketRows = $rows->get($bucket, collect());
            $byRank     = $bucketRows->keyBy('health_rank');

            return [
                'bucket'      => $bucket,
                'label'       => $this->bucketLabel($bucket),
                'tone'        => $meta['tone'],
                'count'       => (int) $bucketRows->sum('total'),
                'on_list'     => (int) $bucketRows->sum('on_list'),
                'on_the_way'  => (int) $bucketRows->sum('on_the_way'),
                'untouched'   => (int) max(0, $bucketRows->sum('total') - $bucketRows->sum('handled')),
                'suppliers'   => (int) $bucketRows->max('suppliers'),
                'stock_value' => (float) $bucketRows->sum('stock_value'),
                'ranks'       => in_array($bucket, ['never', 'gone'], true) ? [] : collect(HealthRankEnum::cases())->map(fn ($rank) => [
                    'rank'    => $rank->value,
                    'count'   => (int) ($byRank->get($rank->value)->total ?? 0),
                    'on_list' => (int) ($byRank->get($rank->value)->on_list ?? 0),
                ])->values()->all(),
            ];
        })->values()->all();

        return [
            'total'     => collect($buckets)->sum('count'),
            'lead_time' => $leadTime,
            'buckets'   => $buckets,
        ];
    }

    /**
     * @return array<int, int> org supplier product ids in the given bucket, worst cover first
     */
    public function orgSupplierProductIdsInBucket(OrgAgent $orgAgent, string $bucket, ?string $rank = null, ?int $supplierId = null): array
    {
        return $this->scopedQuery($orgAgent)
            ->whereRaw($this->bucketExpression(GetAgentLeadTimes::run($orgAgent)['agent']['days']).' = ?', [$bucket])
            ->when($rank, fn ($query) => $query->where('os.health_rank', $rank))
            ->when($supplierId, fn ($query) => $query->where('sp.supplier_id', $supplierId))
            ->orderByRaw("case os.health_rank when 'A' then 1 when 'B' then 2 when 'C' then 3 when 'D' then 4 when 'Z' then 5 end nulls last")
            ->when(
                $bucket === 'dead',
                fn ($query) => $query->orderByDesc('s.stock_value'),
                fn ($query) => $query->orderByRaw('s.days_of_cover nulls last')
            )
            ->pluck('osp.id')
            ->all();
    }
}
