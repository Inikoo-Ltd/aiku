<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 31 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgPartner;

use App\Enums\Catalogue\HealthRankEnum;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Procurement\OrgPartner;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetPartnerStockCoverBuckets
{
    use AsObject;

    public const int MINIMUM_LINE_VALUE = 10;

    public const int MINIMUM_COVER_DAYS = 30;

    public const int MAXIMUM_COVER_DAYS = 180;

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
        $lead       = "coalesce(p.measured_lead_time_days, p.estimated_lead_time_days, $leadDays)";
        $understock = "coalesce((stock_families.data->'stock_cover'->>'understock_days')::int, 2 * $lead)";

        return "case
            when os.id is null then 'never'
            when os.quantity_available <= 0 then 'out'
            when s.days_of_cover <= $lead then 'w1'
            when s.days_of_cover <= $understock then 'w2'
            when s.days_of_cover <= 3 * $lead then 'w3'
            when s.days_of_cover <= 4 * $lead then 'w4'
            when coalesce(s.predicted_daily_usage, 0) = 0 and s.stock_value > 0 then 'dead'
            else 'ok' end";
    }

    /**
     * The manufacturing hub that sells this stock too. A rescue may still buy it from a sister company
     * when the hub cannot ship in time, but the hub is the normal place to buy it, so it is flagged.
     */
    public static function hubNameSql(string $stockIdExpression): string
    {
        return "(select hub.name from org_stocks hub_os
            join organisations hub on hub.id = hub_os.organisation_id and hub.is_manufacturing_hub
            where hub_os.stock_id = $stockIdExpression and hub_os.state = '".OrgStockStateEnum::ACTIVE->value."'
            order by hub.id limit 1)";
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
            ->leftJoin('stocks', 'stocks.id', 'p.stock_id')
            ->leftJoin('stock_families', 'stock_families.id', 'stocks.stock_family_id')
            ->where('p.organisation_id', $orgPartner->partner_id)
            ->where('p.state', OrgStockStateEnum::ACTIVE->value)
            ->whereRaw('coalesce(os.is_on_demand, false) = false');
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
     * Our selling SKOs that are out, doomed or critical, nothing on order, that this partner can spare:
     * what an emergency order to a sister company can rescue. The partner keeps what its own demand
     * needs over its critical threshold (same edge that would flag it critical), so a rescue never
     * puts the partner itself at risk.
     *
     * @return array{order: array{lines: int, cost: float}, buckets: array<int, array{bucket: string, label: string, tone: string, count: int, bestsellers: int, lost: float, cost: float, order_lines: int, order_cost: float}>, top: array<int, array<string, mixed>>}
     */
    public function rescuable(OrgPartner $orgPartner, int $topLimit = 20): array
    {
        $leadTime = GetPartnerLeadTime::run($orgPartner);
        [$query, $expression, $spare] = $this->rescuableQuery($orgPartner, $leadTime['days']);

        $cost    = "{$this->rescueQuantity($spare, $leadTime['days'], $orgPartner)} * {$this->partnerSkoPrice($orgPartner)}";
        $inOrder = $this->inRescueOrder();

        $counts = $query
            ->selectRaw("$expression as bucket, count(*) as total, count(*) filter (where os.health_rank in ('A', 'B')) as bestsellers, coalesce(sum(s.projected_lost_revenue), 0) as lost, coalesce(sum($cost), 0) as cost, coalesce(sum($cost) filter (where $inOrder), 0) as order_cost, count(*) filter (where $inOrder) as order_lines")
            ->groupByRaw($expression)
            ->get()
            ->keyBy('bucket');

        $exchange = $orgPartner->exchangeToOrgCurrency();
        $leftOut  = $this->leftOut($orgPartner, $leadTime['days']);

        return [
            'order'   => [
                'lines' => (int) $counts->sum('order_lines'),
                'cost'  => round((float) $counts->sum('order_cost') * $exchange, 2),
            ],
            'buckets' => collect(['out', 'w1', 'w2'])->map(fn (string $bucket) => [
                'bucket'      => $bucket,
                'label'       => $bucket === 'out' ? __(self::BUCKETS['out']['label']) : $this->bucketLabel($bucket, $leadTime['days']),
                'tone'        => self::BUCKETS[$bucket]['tone'],
                'count'       => (int) ($counts->get($bucket)->total ?? 0),
                'bestsellers' => (int) ($counts->get($bucket)->bestsellers ?? 0),
                'lost'        => (float) ($counts->get($bucket)->lost ?? 0),
                'cost'        => round((float) ($counts->get($bucket)->cost ?? 0) * $exchange, 2),
                'order_lines' => (int) ($counts->get($bucket)->order_lines ?? 0),
                'order_cost'  => round((float) ($counts->get($bucket)->order_cost ?? 0) * $exchange, 2),
                'left_out'    => (object) ($leftOut[$bucket] ?? []),
            ])->all(),
            'top'     => $this->rescueItems($orgPartner, $leadTime['days'])->limit($topLimit)->get()->map($this->rescueItem(...))->all(),
        ];
    }

    /**
     * Why our out, doomed and critical SKOs are not rescuable, per bucket: each one counted once, under
     * the first reason in the order rescuableQuery checks them, so staff can see why a rescue is small.
     *
     * @return array<string, array<string, int>> bucket => reason => SKOs
     */
    public function leftOut(OrgPartner $orgPartner, int $leadDays): array
    {
        $expression = $this->bucketExpression($leadDays);
        $spare      = "floor(p.quantity_available - ps.predicted_daily_usage * {$this->criticalDays($leadDays)})";
        $onDraft    = "exists (select 1 from purchase_order_transactions pot
                join purchase_orders po on po.id = pot.purchase_order_id and po.deleted_at is null
                where pot.org_stock_id = os.id and pot.deleted_at is null and po.state = '".PurchaseOrderStateEnum::IN_PROCESS->value."'
                    and po.parent_type = 'OrgPartner' and po.parent_id = ".(int) $orgPartner->id.')';
        $reason     = "case
            when coalesce(s.predicted_daily_usage, 0) <= 0 then 'not_selling'
            when p.id is null then 'not_stocked'
            when $onDraft then 'on_draft'
            when {$this->alreadyComingExpression($orgPartner)} then 'coming'
            when $spare is null then 'partner_no_forecast'
            when $spare < 1 then 'partner_short'
            when os.health_rank in ('A', 'B') then 'rescuable'
            when {$this->orgSkoPrice($orgPartner)} <= 0 then 'no_price'
            when {$this->rescueQuantity($spare, $leadDays, $orgPartner)} * {$this->orgSkoPrice($orgPartner)} < ".self::MINIMUM_LINE_VALUE." then 'too_small'
            else 'rescuable' end";

        return DB::table('org_stocks as os')
            ->leftJoin('org_stock_stats as s', 's.org_stock_id', 'os.id')
            ->leftJoin('org_stocks as p', function ($join) use ($orgPartner) {
                $join->on('p.stock_id', 'os.stock_id')
                    ->where('p.organisation_id', $orgPartner->partner_id)
                    ->where('p.state', OrgStockStateEnum::ACTIVE->value);
            })
            ->leftJoin('org_stock_stats as ps', 'ps.org_stock_id', 'p.id')
            ->leftJoin('stocks', 'stocks.id', 'os.stock_id')
            ->leftJoin('stock_families', 'stock_families.id', 'stocks.stock_family_id')
            ->where('os.organisation_id', $orgPartner->organisation_id)
            ->where('os.state', OrgStockStateEnum::ACTIVE->value)
            ->whereRaw('coalesce(os.is_on_demand, false) = false')
            ->whereRaw("$expression in ('out', 'w1', 'w2')")
            ->selectRaw("$expression as bucket, $reason as reason, count(*) as total")
            ->groupByRaw('1, 2')
            ->get()
            ->groupBy('bucket')
            ->map(fn ($rows) => $rows->where('reason', '!=', 'rescuable')->pluck('total', 'reason')->map(fn ($total) => (int) $total)->all())
            ->all();
    }

    /**
     * Every rescuable SKO, worst offenders first: biggest lost sales, then rank, then how out it is.
     */
    public function rescueItems(OrgPartner $orgPartner, ?int $leadDays = null): Builder
    {
        $leadDays ??= GetPartnerLeadTime::run($orgPartner)['days'];
        [$query, $expression, $spare] = $this->rescuableQuery($orgPartner, $leadDays);

        return $query
            ->selectRaw("os.id as org_stock_id, os.slug, os.code, os.name, os.health_rank, os.quantity_available as our_stock, $expression as bucket, $spare as spare, {$this->rescueQuantity($spare, $leadDays, $orgPartner)} as quantity, s.days_of_cover, s.projected_lost_revenue, ".self::hubNameSql('os.stock_id').' as hub_name')
            ->orderByRaw('s.projected_lost_revenue desc nulls last')
            ->orderBy('os.health_rank')
            ->orderByRaw("case $expression when 'out' then 1 when 'w1' then 2 else 3 end")
            ->orderByRaw('s.days_of_cover nulls first')
            ->orderBy('os.id');
    }

    /**
     * @return array<string, mixed>
     */
    public function rescueItem(object $row): array
    {
        return [
            'org_stock_id'  => (int) $row->org_stock_id,
            'slug'          => $row->slug,
            'code'          => $row->code,
            'name'          => $row->name,
            'rank'          => $row->health_rank,
            'bucket'        => $row->bucket,
            'our_stock'     => (float) $row->our_stock,
            'spare'         => (int) $row->spare,
            'quantity'      => (int) $row->quantity,
            'days_of_cover' => $row->days_of_cover === null ? null : (float) $row->days_of_cover,
            'lost'          => $row->projected_lost_revenue === null ? null : (float) $row->projected_lost_revenue,
            'hub_name'      => $row->hub_name,
        ];
    }

    /**
     * What to order to rescue the SKOs in these buckets: by default only the ones that would lose
     * sales and the A/B bestsellers, or every one of them. Purchase order quantities are units, so the
     * SKOs to order are multiplied by the units in each SKO.
     *
     * @param  array<int, string>  $buckets
     * @return array<int, array{org_stock_id: int, quantity: int}>
     */
    public function rescueLines(OrgPartner $orgPartner, array $buckets = ['out', 'w1', 'w2'], bool $worstOnly = true): array
    {
        $leadDays = GetPartnerLeadTime::run($orgPartner)['days'];
        $buckets  = array_values(array_intersect(['out', 'w1', 'w2'], $buckets));
        if (!$buckets) {
            return [];
        }

        return $this->rescueItems($orgPartner, $leadDays)
            ->whereRaw($this->bucketExpression($leadDays)." in ('".implode("', '", $buckets)."')")
            ->when($worstOnly, fn ($query) => $query->whereRaw($this->inRescueOrder()))
            ->addSelect('os.packed_in')
            ->get()
            ->map(fn ($row) => ['org_stock_id' => (int) $row->org_stock_id, 'quantity' => (int) $row->quantity * max(1, (int) $row->packed_in)])
            ->all();
    }

    private function inRescueOrder(): string
    {
        return "(os.quantity_available <= 0 or s.projected_lost_revenue > 0 or os.health_rank in ('A', 'B'))";
    }

    /**
     * What the partner charges for one SKO in its own currency, from the product it sells it alone
     * with in the shop it sells to the other companies from: the same product GetPartnerSellingProduct
     * prices a purchase order line with.
     */
    private function partnerSkoPrice(OrgPartner $orgPartner): string
    {
        $shopIds = GetPartnerSellingShopIds::run($orgPartner->partner) ?: [0];

        return "coalesce((select pr.price / nullif(phos.quantity, 0)
            from product_has_org_stocks phos
            join products pr on pr.id = phos.product_id and pr.state = '".ProductStateEnum::ACTIVE->value."' and pr.shop_id in (".implode(',', $shopIds).")
            where phos.org_stock_id = p.id
                and (select count(*) from product_has_org_stocks bundle where bundle.product_id = pr.id) = 1
            order by ".PartnerSkoPrice::shopPositionSql($shopIds, 'pr.shop_id').", phos.quantity, pr.price
            limit 1), 0)";
    }

    /**
     * Enough to cover our critical threshold and at least a month of sales, raised to a line worth
     * picking (MINIMUM_LINE_VALUE) as long as that is no more than six months of sales, and never more
     * than the partner can spare. Picking it at the partner and putting it away here takes 3 to 4
     * minutes, about 0.70 at 12 an hour (80 s a pick on average, Oct 2026), so a 10 line keeps handling
     * under a tenth of what it brings.
     */
    private function rescueQuantity(string $spare, int $leadDays, OrgPartner $orgPartner): string
    {
        $need     = "ceil(s.predicted_daily_usage * {$this->criticalDays($leadDays)} - greatest(os.quantity_available, 0))";
        $forValue = 'least(coalesce(ceil('.self::MINIMUM_LINE_VALUE.' / nullif('.$this->orgSkoPrice($orgPartner).', 0)), 0), ceil(s.predicted_daily_usage * '.self::MAXIMUM_COVER_DAYS.'))';

        return "least($spare, greatest(1, $need, ceil(s.predicted_daily_usage * ".self::MINIMUM_COVER_DAYS."), $forValue))";
    }

    /**
     * The partner's price for one SKO in our organisation's currency.
     */
    private function orgSkoPrice(OrgPartner $orgPartner): string
    {
        return '('.$this->partnerSkoPrice($orgPartner).' * '.(float) $orgPartner->exchangeToOrgCurrency().')';
    }

    private function criticalDays(int $leadDays): string
    {
        return "coalesce((stock_families.data->'stock_cover'->>'understock_days')::int, 2 * coalesce(p.measured_lead_time_days, p.estimated_lead_time_days, $leadDays))";
    }

    /**
     * Our SKO is already being sorted: on an open purchase order to anyone, on the order being
     * prepared for this partner, in a stock delivery not yet put away, or on the hub shopping list.
     */
    private function alreadyComingExpression(OrgPartner $orgPartner): string
    {
        return "(exists (select 1 from purchase_order_transactions pot
                join purchase_orders po on po.id = pot.purchase_order_id and po.deleted_at is null
                where pot.org_stock_id = os.id and pot.deleted_at is null
                    and po.state not in ('".PurchaseOrderStateEnum::CANCELLED->value."', '".PurchaseOrderStateEnum::NOT_RECEIVED->value."')
                    and (po.state in ('".PurchaseOrderStateEnum::SUBMITTED->value."', '".PurchaseOrderStateEnum::CONFIRMED->value."')
                        or po.delivery_state in ('".PurchaseOrderDeliveryStateEnum::READY_TO_SHIP->value."', '".PurchaseOrderDeliveryStateEnum::DISPATCHED->value."')
                        or (po.state = '".PurchaseOrderStateEnum::IN_PROCESS->value."' and po.parent_type = 'OrgPartner' and po.organisation_id = ".(int) $orgPartner->organisation_id.")))
            or exists (select 1 from stock_delivery_items sdi
                join stock_deliveries sd on sd.id = sdi.stock_delivery_id and sd.deleted_at is null
                where sdi.org_stock_id = os.id and sdi.deleted_at is null
                    and sd.state in ('".implode("', '", [
            StockDeliveryStateEnum::CONFIRMED->value,
            StockDeliveryStateEnum::READY_TO_SHIP->value,
            StockDeliveryStateEnum::DISPATCHED->value,
            StockDeliveryStateEnum::RECEIVED->value,
            StockDeliveryStateEnum::CHECKED->value,
            StockDeliveryStateEnum::BOOKING_IN->value,
        ])."'))
            or exists (select 1 from partner_shopping_list_items sli
                where sli.org_stock_id = os.id and sli.deleted_at is null
                    and sli.state in ('".ShoppingListItemStateEnum::OPEN->value."', '".ShoppingListItemStateEnum::ORDERED->value."')))";
    }

    /**
     * Our selling SKOs that are out, doomed or critical with nothing on order, that the partner can
     * spare: it keeps what its own demand needs over the same critical threshold. Without a demand
     * forecast for the partner's SKO we cannot tell what it needs, so nothing is spare. A line that
     * cannot reach MINIMUM_LINE_VALUE (in our currency) is left out unless the SKO is an A or B
     * bestseller; so is one the partner has no price for.
     *
     * @return array{0: Builder, 1: string, 2: string} query, bucket expression, spare expression
     */
    private function rescuableQuery(OrgPartner $orgPartner, int $leadDays): array
    {
        $expression = $this->bucketExpression($leadDays);
        $spare      = "floor(p.quantity_available - ps.predicted_daily_usage * {$this->criticalDays($leadDays)})";

        $query = $this->scopedQuery($orgPartner)
            ->leftJoin('org_stock_stats as ps', 'ps.org_stock_id', 'p.id')
            ->whereRaw("$spare >= 1")
            ->whereRaw('not '.$this->alreadyComingExpression($orgPartner))
            ->whereRaw('coalesce(s.predicted_daily_usage, 0) > 0')
            ->whereRaw("$expression in ('out', 'w1', 'w2')")
            ->whereRaw("(os.health_rank in ('A', 'B') or {$this->rescueQuantity($spare, $leadDays, $orgPartner)} * {$this->orgSkoPrice($orgPartner)} >= ".self::MINIMUM_LINE_VALUE.')');

        return [$query, $expression, $spare];
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
