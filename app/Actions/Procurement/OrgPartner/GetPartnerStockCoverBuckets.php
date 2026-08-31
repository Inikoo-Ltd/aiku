<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 31 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgPartner;

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
        'w1'     => ['label' => 'Out before next delivery', 'tone' => 'red'],
        'w2'     => ['label' => 'Out within :days days', 'tone' => 'orange'],
        'w3'     => ['label' => 'Out within :days days', 'tone' => 'amber'],
        'w4'     => ['label' => 'Out within :days days', 'tone' => 'yellow'],
        'ok'     => ['label' => 'Covered', 'tone' => 'green'],
        'dead'   => ['label' => 'Dead stock', 'tone' => 'gray'],
        'never'  => ['label' => 'We never stocked', 'tone' => 'violet'],
    ];

    private function bucketLabel(string $bucket, int $leadDays): string
    {
        $edges = ['w2' => 2, 'w3' => 3, 'w4' => 4];

        return __(self::BUCKETS[$bucket]['label'], ['days' => ($edges[$bucket] ?? 1) * $leadDays]);
    }

    private function bucketExpression(int $leadDays): string
    {
        return "case
            when os.id is null then 'never'
            when os.quantity_available <= 0 then 'out'
            when s.days_of_cover <= $leadDays then 'w1'
            when s.days_of_cover <= ".(2 * $leadDays)." then 'w2'
            when s.days_of_cover <= ".(3 * $leadDays)." then 'w3'
            when s.days_of_cover <= ".(4 * $leadDays)." then 'w4'
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
     * @return array{total: int, lead_time: array{days: int, source: string, samples: int}, buckets: array<int, array{bucket: string, label: string, tone: string, count: int, on_list: int, on_the_way: int, stock_value: float}>}
     */
    public function handle(OrgPartner $orgPartner): array
    {
        $leadTime   = GetPartnerLeadTime::run($orgPartner);
        $expression = $this->bucketExpression($leadTime['days']);

        $rows = $this->scopedQuery($orgPartner)
            ->selectRaw("$expression as bucket,
                count(*) as total,
                count(*) filter (where ".$this->onShoppingListExpression($orgPartner).") as on_list,
                count(*) filter (where coalesce(s.on_the_way_po_count, 0) > 0) as on_the_way,
                coalesce(sum(s.stock_value), 0) as stock_value")
            ->groupByRaw($expression)
            ->get()
            ->keyBy('bucket');

        $buckets = collect(self::BUCKETS)->map(function ($meta, $bucket) use ($rows, $leadTime) {
            $row = $rows->get($bucket);

            return [
                'bucket'      => $bucket,
                'label'       => in_array($bucket, ['out', 'ok', 'dead', 'never'], true)
                    ? __($meta['label'])
                    : $this->bucketLabel($bucket, $leadTime['days']),
                'tone'        => $meta['tone'],
                'count'       => (int) ($row->total ?? 0),
                'on_list'     => (int) ($row->on_list ?? 0),
                'on_the_way'  => (int) ($row->on_the_way ?? 0),
                'stock_value' => (float) ($row->stock_value ?? 0),
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
    public function stockIdsInBucket(OrgPartner $orgPartner, string $bucket): array
    {
        return $this->scopedQuery($orgPartner)
            ->whereRaw($this->bucketExpression(GetPartnerLeadTime::run($orgPartner)['days']).' = ?', [$bucket])
            ->when(
                $bucket === 'dead',
                fn ($query) => $query->orderByDesc('s.stock_value'),
                fn ($query) => $query->orderByRaw('s.days_of_cover nulls last')
            )
            ->pluck('p.stock_id')
            ->all();
    }
}
