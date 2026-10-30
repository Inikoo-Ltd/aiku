<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 31 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgAgent;

use App\Actions\Procurement\GetOrganisationStockCoverBuckets;
use App\Actions\Procurement\ProjectOrganisationStockOuts;
use App\Enums\Catalogue\HealthRankEnum;
use App\Enums\Goods\Stock\StockStateEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\Procurement\OrgSupplierProduct\OrgSupplierProductStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Actions\Procurement\OrgSupplier\GetSupplierLeadTime;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgSupplier;
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
        'gone'   => ['label' => 'Discontinued: we stocked it, we stopped', 'tone' => 'slate'],
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
     *
     * Two edges are held to what the stock-out projection sees, so a product it expects to lose
     * sales on is never called covered: less than one SKO left is out of stock, there is nothing to
     * sell, and with a lead time of a few days the watch window still reaches as far as the
     * projection looks ahead.
     */
    private function bucketExpression(int $leadDays): string
    {
        $lead       = self::leadTimeExpression($leadDays);
        $understock = "coalesce((stock_families.data->'stock_cover'->>'understock_days')::int, 2 * $lead)";

        return "case
            when os.id is null then 'never'
            when os.state <> '".OrgStockStateEnum::ACTIVE->value."' then 'gone'
            ".GetOrganisationStockCoverBuckets::outOfStockCase('os', 's', GetOrganisationStockCoverBuckets::make()->overstockDaysExpression())."
            else ".GetOrganisationStockCoverBuckets::bucketOnCoverDaysExpression('os', 's', "greatest(4 * $lead, $understock, least(".ProjectOrganisationStockOuts::DAYS.", $lead + ".ProjectOrganisationStockOuts::REORDER_DAYS."))", "
                ".GetOrganisationStockCoverBuckets::atRiskCases($lead, $understock, GetOrganisationStockCoverBuckets::make()->overstockDaysExpression(), "greatest(4 * $lead, least(".ProjectOrganisationStockOuts::DAYS.", $lead + ".ProjectOrganisationStockOuts::REORDER_DAYS."))")."
                when coalesce(s.predicted_daily_usage, 0) = 0 and s.stock_value > 0 then 'dead'
                else 'ok'").' end';
    }

    /**
     * The lead time a row is judged on, the same order the ordering card reads it: the SKO's own,
     * then the supplier product's, then the agent's.
     */
    public static function leadTimeExpression(int $agentLeadDays): string
    {
        return "coalesce(os.measured_lead_time_days, os.estimated_lead_time_days, sp.measured_lead_time_days, sp.estimated_lead_time_days, $agentLeadDays)";
    }

    public static function leadTimeMeasuredExpression(): string
    {
        return '(os.measured_lead_time_days is not null or (os.estimated_lead_time_days is null and sp.measured_lead_time_days is not null))';
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
    public static function bestOrgStock(string $orgSupplierProduct = 'osp'): Builder
    {
        return DB::table('org_stock_has_org_supplier_products as link')
            ->join('org_stocks', 'org_stocks.id', 'link.org_stock_id')
            ->whereColumn('link.org_supplier_product_id', $orgSupplierProduct.'.id')
            ->whereRaw('link.status')
            ->orderByDesc('link.local_priority')
            ->orderByRaw("(org_stocks.state = '".OrgStockStateEnum::ACTIVE->value."') desc, org_stocks.quantity_available desc nulls last")
            ->select(['org_stocks.id', 'org_stocks.stock_id', 'org_stocks.state', 'org_stocks.quantity_available', 'org_stocks.health_rank', 'org_stocks.is_excluded_from_auto_ordering', 'org_stocks.packed_in', 'org_stocks.measured_lead_time_days', 'org_stocks.estimated_lead_time_days'])
            ->limit(1);
    }

    public function scopedQuery(OrgAgent $orgAgent, ?int $supplierId = null): Builder
    {
        return $this->scope(DB::table('org_supplier_products as osp'), $orgAgent, supplierId: $supplierId);
    }

    /**
     * The catalogue scope on any query whose table is aliased osp, so a listing built with the
     * standard query builder judges the same products as the tiles do. A supplier's catalogue (the
     * supplier sales views) is only ever read whole, orderable or not. Narrowed to one supplier, an
     * SKO another supplier is preferred for still shows: the buyer chose this supplier.
     *
     * @template TQuery of Builder|\Illuminate\Database\Eloquent\Builder
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    public function scope(Builder|\Illuminate\Database\Eloquent\Builder $query, OrgAgent|OrgSupplier $relationship, bool $orderableOnly = true, ?int $supplierId = null): Builder|\Illuminate\Database\Eloquent\Builder
    {
        $query
            ->join('supplier_products as sp', 'sp.id', 'osp.supplier_product_id')
            ->leftJoinLateral(self::bestOrgStock(), 'os')
            ->leftJoin('org_stock_stats as s', 's.org_stock_id', 'os.id')
            ->leftJoin('stocks', 'stocks.id', 'os.stock_id')
            ->leftJoin('stock_families', 'stock_families.id', 'stocks.stock_family_id')
            ->whereNull('sp.deleted_at');

        if ($relationship instanceof OrgSupplier) {
            return $query
                ->where('osp.org_supplier_id', $relationship->id)
                ->where('sp.supplier_id', $relationship->supplier_id);
        }

        $query->where('osp.org_agent_id', $relationship->id);

        if (!$orderableOnly) {
            return $query;
        }

        return $query
            ->where('osp.state', OrgSupplierProductStateEnum::ACTIVE->value)
            ->where('osp.is_available', true)
            ->whereRaw('not (os.id is null and ('.self::everySkoDiscontinuedExpression().' or '.self::boughtUnderAnotherCodeExpression().'))')
            ->whereRaw('not '.self::outrankedForItsOrgStockExpression($supplierId));
    }

    /**
     * An SKO the agent can supply under several supplier products is one thing to order, not one
     * per supplier: it is listed under its preferred supplier product only, the link with the
     * highest priority, so it cannot be ordered twice from two rows that show the same stock.
     * Within one supplier only the rivals of that supplier count.
     */
    private static function outrankedForItsOrgStockExpression(?int $supplierId = null): string
    {
        $sameSupplier = $supplierId === null ? '' : ' and rival_sp.supplier_id = '.$supplierId;

        return "exists (select 1 from org_stock_has_org_supplier_products mine
            join org_stock_has_org_supplier_products rival_link on rival_link.org_stock_id = mine.org_stock_id and rival_link.status
            join org_supplier_products rival on rival.id = rival_link.org_supplier_product_id
                and rival.org_agent_id = osp.org_agent_id
                and rival.id <> osp.id
                and rival.state = '".OrgSupplierProductStateEnum::ACTIVE->value."'
                and rival.is_available
            join supplier_products rival_sp on rival_sp.id = rival.supplier_product_id and rival_sp.deleted_at is null$sameSupplier
            where mine.org_supplier_product_id = osp.id and mine.org_stock_id = os.id and mine.status
                and (coalesce(rival_link.local_priority, 0) > coalesce(mine.local_priority, 0)
                    or (coalesce(rival_link.local_priority, 0) = coalesce(mine.local_priority, 0) and rival.id < osp.id))
                and os.id = (select rival_stock.id from (".self::bestOrgStock('rival')->toRawSql().') rival_stock))';
    }

    public function bucketSql(OrgAgent|OrgSupplier $relationship): string
    {
        return $this->bucketExpression($relationship instanceof OrgSupplier
            ? GetSupplierLeadTime::run($relationship)['days']
            : GetAgentLeadTimes::run($relationship)['agent']['days']);
    }

    /**
     * A supplier product we do not stock whose SKOs are all discontinued is not something we never
     * got round to stocking, it is a dead code: its price and availability have not been looked at
     * in years. It stays out of the catalogue.
     */
    private static function everySkoDiscontinuedExpression(): string
    {
        return "(exists (select 1 from stock_has_supplier_products shs where shs.supplier_product_id = sp.id)
            and not exists (select 1 from stock_has_supplier_products shs
                join stocks st on st.id = shs.stock_id
                where shs.supplier_product_id = sp.id and st.state <> '".StockStateEnum::DISCONTINUED->value."'))";
    }

    /**
     * An old code for an SKO the organisation already buys from this agent under another supplier
     * product: the stock is watched there, counting it here as never stocked would be double.
     * That other product must still be orderable, or the SKO could be ordered from neither.
     */
    private static function boughtUnderAnotherCodeExpression(): string
    {
        return "exists (select 1 from stock_has_supplier_products shs
            join org_stocks other_os on other_os.stock_id = shs.stock_id
                and other_os.organisation_id = osp.organisation_id
                and other_os.state = '".OrgStockStateEnum::ACTIVE->value."'
            join org_stock_has_org_supplier_products other_link on other_link.org_stock_id = other_os.id and other_link.status
            join org_supplier_products other_osp on other_osp.id = other_link.org_supplier_product_id
                and other_osp.org_agent_id = osp.org_agent_id
                and other_osp.id <> osp.id
                and other_osp.state = '".OrgSupplierProductStateEnum::ACTIVE->value."'
                and other_osp.is_available
            join supplier_products other_sp on other_sp.id = other_osp.supplier_product_id and other_sp.deleted_at is null
            where shs.supplier_product_id = sp.id)";
    }

    private function onShoppingListExpression(): string
    {
        return "exists (select 1 from shopping_list_items sli
            where sli.org_supplier_product_id = osp.id
                and sli.state = '".ShoppingListItemStateEnum::OPEN->value."'
                and sli.deleted_at is null)";
    }

    /**
     * On a purchase order that has not landed yet, a draft included: already dealt with, so it must
     * not come back as needing action or be ordered a second time.
     */
    public static function onOpenPurchaseOrderExpression(): string
    {
        return "exists (select 1 from purchase_order_transactions pot
            join purchase_orders po on po.id = pot.purchase_order_id and po.deleted_at is null
            where pot.org_stock_id = os.id and pot.deleted_at is null
                and po.state in ('".implode("', '", [
            PurchaseOrderStateEnum::IN_PROCESS->value,
            PurchaseOrderStateEnum::SUBMITTED->value,
            PurchaseOrderStateEnum::CONFIRMED->value,
        ])."'))";
    }

    /**
     * What is on those orders for the product, in the organisation's currency.
     */
    private static function onOpenPurchaseOrderValueExpression(): string
    {
        return "(select sum(pot.net_amount * coalesce(po.org_exchange, 1)) from purchase_order_transactions pot
            join purchase_orders po on po.id = pot.purchase_order_id and po.deleted_at is null
            where pot.org_stock_id = os.id and pot.deleted_at is null
                and po.state in ('".implode("', '", [
            PurchaseOrderStateEnum::IN_PROCESS->value,
            PurchaseOrderStateEnum::SUBMITTED->value,
            PurchaseOrderStateEnum::CONFIRMED->value,
        ])."'))";
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
                count(*) filter (where coalesce(s.on_the_way_po_count, 0) > 0 or ".self::onOpenPurchaseOrderExpression().") as on_the_way,
                count(*) filter (where coalesce(s.on_the_way_po_count, 0) > 0 or ".self::onOpenPurchaseOrderExpression().' or '.$this->onShoppingListExpression().") as handled,
                coalesce(sum(".self::onOpenPurchaseOrderValueExpression()."), 0) as on_order_value,
                count(distinct sp.supplier_id) as suppliers,
                coalesce(sum(s.stock_value), 0) as stock_value,
                coalesce(sum(s.projected_lost_revenue), 0) as lost,
                coalesce(sum(s.projected_lost_revenue) filter (where not (coalesce(s.on_the_way_po_count, 0) > 0 or ".self::onOpenPurchaseOrderExpression().' or '.$this->onShoppingListExpression().")), 0) as lost_untouched")
            ->groupByRaw("$expression, os.health_rank")
            ->get()
            ->groupBy('bucket');

        $buckets = collect(self::BUCKETS)->map(function ($meta, $bucket) use ($rows) {
            $bucketRows = $rows->get($bucket, collect());
            $byRank     = $bucketRows->keyBy('health_rank');

            return [
                'bucket'      => $bucket,
                'label'       => $this->bucketLabel($bucket),
                'tone'        => $meta['tone'],
                'count'       => (int) $bucketRows->sum('total'),
                'on_list'     => (int) $bucketRows->sum('on_list'),
                'on_the_way'  => (int) $bucketRows->sum('on_the_way'),
                'on_order_value' => round((float) $bucketRows->sum('on_order_value'), 2),
                'untouched'   => (int) max(0, $bucketRows->sum('total') - $bucketRows->sum('handled')),
                'suppliers'   => (int) $bucketRows->max('suppliers'),
                'stock_value' => (float) $bucketRows->sum('stock_value'),
                'lost'        => (float) $bucketRows->sum('lost'),
                'lost_untouched' => (float) $bucketRows->sum('lost_untouched'),
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
