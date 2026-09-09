<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 10 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Restock;

use App\Enums\Catalogue\HealthRankEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Models\Production\Production;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetProductionStockCoverBuckets
{
    use AsObject;

    public const BUCKETS = [
        'out'   => ['label' => 'Out of stock', 'tone' => 'red-deep'],
        'w1'    => ['label' => 'Doomed: gone before anything can be made', 'tone' => 'red'],
        'w2'    => ['label' => 'Critical: out within :days days', 'tone' => 'orange'],
        'w3'    => ['label' => 'Danger: out within :days days', 'tone' => 'amber'],
        'w4'    => ['label' => 'Watch: out within :days days', 'tone' => 'yellow'],
        'ok'    => ['label' => 'Covered', 'tone' => 'green'],
        'dead'  => ['label' => 'Dead stock', 'tone' => 'gray'],
        'never' => ['label' => 'Never made yet', 'tone' => 'violet'],
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
            when s.days_of_cover <= 2 * $leadDays then 'w2'
            when s.days_of_cover <= 3 * $leadDays then 'w3'
            when s.days_of_cover <= 4 * $leadDays then 'w4'
            when coalesce(s.predicted_daily_usage, 0) = 0 and s.stock_value > 0 then 'dead'
            else 'ok' end";
    }

    /**
     * Everything this factory can make, with the warehouse's own stock of it alongside.
     */
    private function scopedQuery(Production $production): Builder
    {
        return DB::table('artefacts as a')
            ->leftJoin('org_stocks as os', function ($join) {
                $join->on('os.id', 'a.org_stock_id')
                    ->where('os.state', OrgStockStateEnum::ACTIVE->value);
            })
            ->leftJoin('org_stock_stats as s', 's.org_stock_id', 'os.id')
            ->where('a.production_id', $production->id)
            ->whereNull('a.deleted_at')
            ->whereRaw('coalesce(os.is_on_demand, false) = false');
    }

    /**
     * Already handled: a line waiting on the To produce board, or work already on the floor.
     */
    private function inHandExpression(Production $production): string
    {
        return "exists (select 1 from partner_shopping_list_items sli
                where sli.stock_id = os.stock_id
                    and sli.state = '".ShoppingListItemStateEnum::OPEN->value."'
                    and sli.deleted_at is null)
            or exists (select 1 from job_order_items joi
                join job_orders jo on jo.id = joi.job_order_id
                where joi.artefact_id = a.id
                    and jo.state in ('".JobOrderStateEnum::IN_PROCESS->value."', '".JobOrderStateEnum::SUBMITTED->value."', '".JobOrderStateEnum::CONFIRMED->value."'))";
    }

    /**
     * @return array{total: int, lead_time: array{days: int, source: string, samples: int}, buckets: array<int, array<string, mixed>>}
     */
    public function handle(Production $production): array
    {
        $leadTime   = GetProductionLeadTime::run($production);
        $expression = $this->bucketExpression($leadTime['days']);
        $inHand     = $this->inHandExpression($production);

        $rows = $this->scopedQuery($production)
            ->selectRaw("$expression as bucket,
                os.health_rank,
                count(*) as total,
                count(*) filter (where $inHand) as in_hand,
                coalesce(sum(s.stock_value), 0) as stock_value,
                coalesce(sum(s.recommended_order_quantity) filter (where not ($inHand)), 0) as to_make")
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
                'in_hand'     => (int) $bucketRows->sum('in_hand'),
                'untouched'   => (int) max(0, $bucketRows->sum('total') - $bucketRows->sum('in_hand')),
                'to_make'     => (float) $bucketRows->sum('to_make'),
                'stock_value' => (float) $bucketRows->sum('stock_value'),
                'ranks'       => $bucket === 'never' ? [] : collect(HealthRankEnum::cases())->map(fn ($rank) => [
                    'rank'      => $rank->value,
                    'count'     => (int) ($byRank->get($rank->value)->total ?? 0),
                    'untouched' => (int) max(0, ($byRank->get($rank->value)->total ?? 0) - ($byRank->get($rank->value)->in_hand ?? 0)),
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
     * @return array<int, int> artefact ids in the given bucket, most urgent first
     */
    public function artefactIdsInBucket(Production $production, string $bucket, ?string $rank = null): array
    {
        return $this->scopedQuery($production)
            ->whereRaw($this->bucketExpression(GetProductionLeadTime::run($production)['days']).' = ?', [$bucket])
            ->when($rank, fn ($query) => $query->where('os.health_rank', $rank))
            ->orderByRaw("case os.health_rank when 'A' then 1 when 'B' then 2 when 'C' then 3 when 'D' then 4 when 'Z' then 5 end nulls last")
            ->when(
                $bucket === 'dead',
                fn ($query) => $query->orderByDesc('s.stock_value'),
                fn ($query) => $query->orderByRaw('s.days_of_cover nulls last')
            )
            ->pluck('a.id')
            ->all();
    }
}
