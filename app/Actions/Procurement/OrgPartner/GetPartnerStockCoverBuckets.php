<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 31 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgPartner;

use App\Enums\Catalogue\HealthRankEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Procurement\OrgPartner;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetPartnerStockCoverBuckets
{
    use AsObject;

    public const BUCKETS = [
        'out'    => ['label' => 'Out of stock', 'tone' => 'red-deep'],
        'w1'     => ['label' => 'Doomed: gone before any delivery lands', 'tone' => 'red'],
        'w2'     => ['label' => 'Critical: out within :days days', 'tone' => 'orange'],
        'w3'     => ['label' => 'Danger: out within :days days', 'tone' => 'amber'],
        'w4'     => ['label' => 'Watch: out within :days days', 'tone' => 'yellow'],
        'ok'     => ['label' => 'Covered', 'tone' => 'green'],
        'dead'   => ['label' => 'Dead stock', 'tone' => 'gray'],
        'never'  => ['label' => 'We never stocked', 'tone' => 'violet'],
    ];

    private function bucketLabel(string $bucket, int $leadDays): string
    {
        $edges = ['w2' => 2, 'w3' => 3, 'w4' => 4];

        return __(self::BUCKETS[$bucket]['label'], ['days' => ($edges[$bucket] ?? 1) * $leadDays]);
    }

    /**
     * Per-row lead time: the seller item's own measured or estimated days, partner-level fallback.
     */
    private function bucketExpression(int $leadDays): string
    {
        $lead = "coalesce(p.measured_lead_time_days, p.estimated_lead_time_days, $leadDays)";

        return "case
            when os.id is null then 'never'
            when os.quantity_available <= 0 then 'out'
            when s.days_of_cover <= $lead then 'w1'
            when s.days_of_cover <= 2 * $lead then 'w2'
            when s.days_of_cover <= 3 * $lead then 'w3'
            when s.days_of_cover <= 4 * $lead then 'w4'
            when coalesce(s.predicted_daily_usage, 0) = 0 and s.stock_value > 0 then 'dead'
            else 'ok' end";
    }

    /**
     * Everything this partner can sell us, with our own stock alongside it when we carry it.
     */
    private function scopedQuery(OrgPartner $orgPartner): Builder
    {
        return DB::table('org_stocks as p')
            ->leftJoin('org_stocks as os', function ($join) use ($orgPartner) {
                $join->on('os.stock_id', 'p.stock_id')
                    ->where('os.organisation_id', $orgPartner->organisation_id)
                    ->where('os.state', OrgStockStateEnum::ACTIVE->value);
            })
            ->leftJoin('org_stock_stats as s', 's.org_stock_id', 'os.id')
            ->where('p.organisation_id', $orgPartner->partner_id)
            ->where('p.state', OrgStockStateEnum::ACTIVE->value);
    }

    private function onShoppingListExpression(OrgPartner $orgPartner): string
    {
        return "exists (select 1 from partner_shopping_list_items sli
            where sli.stock_id = p.stock_id
                and sli.org_partner_id = ".(int) $orgPartner->id."
                and sli.state = '".ShoppingListItemStateEnum::OPEN->value."'
                and sli.deleted_at is null)";
    }

    /**
     * @return array{total: int, lead_time: array{days: int, source: string, samples: int}, buckets: array<int, array{bucket: string, label: string, tone: string, count: int, on_list: int, on_the_way: int, stock_value: float, ranks: array<int, array{rank: string, count: int, on_list: int}>}>}
     */
    public function handle(OrgPartner $orgPartner): array
    {
        $leadTime   = GetPartnerLeadTime::run($orgPartner);
        $expression = $this->bucketExpression($leadTime['days']);

        $rows = $this->scopedQuery($orgPartner)
            ->selectRaw("$expression as bucket,
                os.health_rank,
                count(*) as total,
                count(*) filter (where ".$this->onShoppingListExpression($orgPartner).") as on_list,
                count(*) filter (where coalesce(s.on_the_way_po_count, 0) > 0) as on_the_way,
                count(*) filter (where coalesce(s.on_the_way_po_count, 0) > 0 or ".$this->onShoppingListExpression($orgPartner).") as handled,
                coalesce(sum(s.stock_value), 0) as stock_value")
            ->groupByRaw("$expression, os.health_rank")
            ->get()
            ->groupBy('bucket');

        $buckets = collect(self::BUCKETS)->map(function ($meta, $bucket) use ($rows, $leadTime) {
            $bucketRows = $rows->get($bucket, collect());
            $byRank     = $bucketRows->keyBy('health_rank');

            return [
                'bucket'      => $bucket,
                'label'       => in_array($bucket, ['out', 'ok', 'dead', 'never'], true)
                    ? __($meta['label'])
                    : $this->bucketLabel($bucket, $leadTime['days']),
                'tone'        => $meta['tone'],
                'count'       => (int) $bucketRows->sum('total'),
                'on_list'     => (int) $bucketRows->sum('on_list'),
                'on_the_way'  => (int) $bucketRows->sum('on_the_way'),
                'untouched'   => (int) max(0, $bucketRows->sum('total') - $bucketRows->sum('handled')),
                'stock_value' => (float) $bucketRows->sum('stock_value'),
                'ranks'       => $bucket === 'never' ? [] : collect(HealthRankEnum::cases())->map(fn ($rank) => [
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
     * @return array<int, int> stock ids in the given bucket
     */
    public function stockIdsInBucket(OrgPartner $orgPartner, string $bucket, ?string $rank = null): array
    {
        return $this->scopedQuery($orgPartner)
            ->whereRaw($this->bucketExpression(GetPartnerLeadTime::run($orgPartner)['days']).' = ?', [$bucket])
            ->when($rank, fn ($query) => $query->where('os.health_rank', $rank))
            ->orderByRaw("case os.health_rank when 'A' then 1 when 'B' then 2 when 'C' then 3 when 'D' then 4 when 'Z' then 5 end nulls last")
            ->when(
                $bucket === 'dead',
                fn ($query) => $query->orderByDesc('s.stock_value'),
                fn ($query) => $query->orderByRaw('s.days_of_cover nulls last')
            )
            ->pluck('p.stock_id')
            ->all();
    }
}
