<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement;

use App\Actions\GoodsIn\StockDelivery\StoreStockDeliveryFromPurchaseOrder;
use App\Actions\Procurement\OrgSupplier\GetSupplierLeadTime;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Enums\Goods\Stock\StockStateEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementFlowEnum;
use App\Enums\Procurement\OrgSupplierProduct\OrgSupplierProductStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockFamily;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetOrganisationStockCoverBuckets
{
    use AsObject;

    public const EXCESS_DAYS = 120;

    public const BUCKETS = [
        'out'    => ['label' => 'Out of stock', 'tone' => 'red-deep'],
        'w1'     => ['label' => 'Doomed', 'description' => 'Gone before any delivery lands', 'tone' => 'red'],
        'w2'     => ['label' => 'Critical', 'description' => 'Out within 2 lead times', 'tone' => 'orange'],
        'w3'     => ['label' => 'Danger', 'description' => 'Out within 3 lead times', 'tone' => 'amber'],
        'w4'     => ['label' => 'On watch', 'description' => 'Out within 4 lead times', 'tone' => 'yellow'],
        'ok'     => ['label' => 'Covered', 'tone' => 'green'],
        'excess' => ['label' => 'Excess', 'description' => 'More than :days days of stock', 'tone' => 'orange-light'],
        'dead'   => ['label' => 'Dead stock', 'tone' => 'red-light'],
    ];

    public const NO_SUPPLIER = 'none';

    public const SOURCES = [
        'hub'      => 'Manufacturing hub',
        'agent'    => 'Agents',
        'supplier' => 'Independent suppliers',
        'none'     => 'No supplier',
    ];

    /**
     * The manufacturing hub is named after the hub organisations themselves.
     *
     * @return array<string, string>
     */
    public function sourceOptions(int $groupId): array
    {
        $hubNames = Organisation::where('group_id', $groupId)->where('is_manufacturing_hub', true)->orderBy('id')->pluck('name');

        return array_merge(
            array_map(fn (string $label) => __($label), self::SOURCES),
            $hubNames->isEmpty() ? [] : ['hub' => $hubNames->implode(', ')]
        );
    }

    public function source(?string $source): ?string
    {
        return array_key_exists((string) $source, self::SOURCES) ? $source : null;
    }

    /**
     * Where an org stock comes from: a manufacturing hub partner that keeps the same stock, else the
     * agent or independent supplier of its primary supplier product; needs the "sp" lateral join.
     */
    public function sourceExpression(): string
    {
        return "case
            when exists (
                select 1 from org_partners
                join organisations as partner_organisations on partner_organisations.id = org_partners.partner_id
                join org_stocks as partner_org_stocks on partner_org_stocks.organisation_id = org_partners.partner_id and partner_org_stocks.stock_id = org_stocks.stock_id and partner_org_stocks.state = '".OrgStockStateEnum::ACTIVE->value."'
                where org_partners.organisation_id = org_stocks.organisation_id and partner_organisations.is_manufacturing_hub
            ) then 'hub'
            when sp.supplier_code is null then 'none'
            when sp.supplier_agent_id is not null then 'agent'
            else 'supplier' end";
    }

    public function bucketDescription(string $bucket): ?string
    {
        $description = self::BUCKETS[$bucket]['description'] ?? null;

        return $description ? __($description, ['days' => self::EXCESS_DAYS]) : null;
    }

    public function bucketLabel(string $bucket): string
    {
        return __(self::BUCKETS[$bucket]['label'], ['days' => self::EXCESS_DAYS]);
    }

    /**
     * The supplier product that answers for an org stock: the live link with the best local priority.
     */
    public function primarySupplierProduct(): Builder
    {
        return DB::table('org_stock_has_org_supplier_products as link')
            ->join('org_supplier_products as osp', 'osp.id', 'link.org_supplier_product_id')
            ->join('supplier_products as sup', 'sup.id', 'osp.supplier_product_id')
            ->join('suppliers', 'suppliers.id', 'sup.supplier_id')
            ->whereColumn('link.org_stock_id', 'org_stocks.id')
            ->where('link.status', true)
            ->whereNull('sup.deleted_at')
            ->orderByRaw("(osp.state = '".OrgSupplierProductStateEnum::ACTIVE->value."') desc")
            ->orderByRaw('link.local_priority desc nulls last')
            ->orderBy('link.id')
            ->select([
                'sup.measured_lead_time_days',
                'sup.estimated_lead_time_days',
                'suppliers.id as supplier_id',
                'suppliers.slug as supplier_slug',
                'suppliers.code as supplier_code',
                'suppliers.agent_id as supplier_agent_id',
            ])
            ->limit(1);
    }

    /**
     * An org stock counts for stock outs only when it feeds a product on sale in an open shop of its
     * organisation and it has received stock at least once: a new SKO waiting for its first delivery
     * is not a stock out.
     */
    public function forSaleProducts(): Builder
    {
        return DB::table('product_has_org_stocks')
            ->join('products', 'products.id', 'product_has_org_stocks.product_id')
            ->join('shops', 'shops.id', 'products.shop_id')
            ->whereColumn('product_has_org_stocks.org_stock_id', 'org_stocks.id')
            ->whereColumn('shops.organisation_id', 'org_stocks.organisation_id')
            ->where('products.is_for_sale', true)
            ->whereNull('products.deleted_at')
            ->where('shops.state', ShopStateEnum::OPEN->value)
            ->selectRaw('1')
            ->limit(1);
    }

    public function stockReceived(?Carbon $before = null): Builder
    {
        return DB::table('org_stock_movements')
            ->whereColumn('org_stock_movements.org_stock_id', 'org_stocks.id')
            ->where('org_stock_movements.flow', OrgStockMovementFlowEnum::IN->value)
            ->when($before, fn ($query) => $query->where('org_stock_movements.date', '<', $before))
            ->selectRaw('1')
            ->limit(1);
    }

    /**
     * Scalar subqueries so each empty SKO is looked up through its own indexes; as EXISTS inside an OR
     * the planner hashes every product on sale first. An on demand SKO is made or bought when ordered,
     * so an empty shelf is its normal state, never a stock out.
     */
    public function whereCountsAsStockOut(Builder|EloquentBuilder $query, ?Carbon $before = null): Builder|EloquentBuilder
    {
        $forSale  = $this->forSaleProducts();
        $received = $this->stockReceived($before);

        return $query
            ->whereRaw('coalesce(org_stocks.is_on_demand, false) = false')
            ->whereRaw('('.$forSale->toSql().') is not null', $forSale->getBindings())
            ->whereRaw('('.$received->toSql().') is not null', $received->getBindings());
    }

    public function leadTimeExpression(): string
    {
        return 'coalesce(org_stocks.measured_lead_time_days, org_stocks.estimated_lead_time_days, sp.measured_lead_time_days, sp.estimated_lead_time_days, '.GetSupplierLeadTime::DEFAULT_DAYS.')';
    }

    /**
     * Per-family overrides live in stock_families.data->stock_cover, keyed understock_days and
     * overstock_days; a caller's join must be aliased exactly "stock_families" for these to resolve.
     */
    public function understockDaysExpression(string $lead): string
    {
        return "coalesce((stock_families.data->'stock_cover'->>'understock_days')::int, 2 * $lead)";
    }

    public function overstockDaysExpression(): string
    {
        return "coalesce((stock_families.data->'stock_cover'->>'overstock_days')::int, ".self::EXCESS_DAYS.')';
    }

    /**
     * SKOs already on their way to an org stock: what open stock deliveries still have to put away
     * and what submitted purchase orders hold that is not on a delivery yet, the same sum the
     * ordering rows show as coming.
     */
    public static function comingSkosExpression(string $orgStocks): string
    {
        $arriving = implode("', '", [
            StockDeliveryStateEnum::IN_PROCESS->value,
            StockDeliveryStateEnum::CONFIRMED->value,
            StockDeliveryStateEnum::READY_TO_SHIP->value,
            StockDeliveryStateEnum::DISPATCHED->value,
            StockDeliveryStateEnum::RECEIVED->value,
            StockDeliveryStateEnum::CHECKED->value,
            StockDeliveryStateEnum::BOOKING_IN->value,
        ]);
        $counted  = "'".StockDeliveryStateEnum::CHECKED->value."', '".StockDeliveryStateEnum::BOOKING_IN->value."'";
        $ordered  = PurchaseOrderStateEnum::openSql();
        $deadLines = "'".PurchaseOrderTransactionStateEnum::CANCELLED->value."', '".PurchaseOrderTransactionStateEnum::NOT_RECEIVED->value."'";
        $onDelivery = StoreStockDeliveryFromPurchaseOrder::quantityOnDelivery()->toRawSql();

        return "((coalesce((select sum(greatest(case when stock_deliveries.state in ($counted) then stock_delivery_items.unit_quantity_checked else stock_delivery_items.unit_quantity end - coalesce(stock_delivery_items.unit_quantity_placed, 0), 0))
                from stock_delivery_items
                join stock_deliveries on stock_deliveries.id = stock_delivery_items.stock_delivery_id and stock_deliveries.deleted_at is null
                where stock_delivery_items.org_stock_id = $orgStocks.id and stock_delivery_items.deleted_at is null
                    and stock_deliveries.state in ('$arriving')), 0)
            + coalesce((select sum(greatest(purchase_order_transactions.quantity_ordered - ($onDelivery), 0))
                from purchase_order_transactions
                join purchase_orders on purchase_orders.id = purchase_order_transactions.purchase_order_id and purchase_orders.deleted_at is null
                where purchase_order_transactions.org_stock_id = $orgStocks.id and purchase_order_transactions.deleted_at is null
                    and purchase_order_transactions.state not in ($deadLines)
                    and purchase_orders.state in ($ordered)), 0))
            / greatest(coalesce($orgStocks.packed_in, 1), 1))";
    }

    /**
     * SKOs of an org stock on one purchase order still being drafted. Its page judges the stock
     * groups with them, as the line verdict does, so a line already ordered is not called critical
     * next to its own overstock verdict (HELP-4508). A submitted order is already coming.
     */
    public static function draftOrderSkosExpression(string $orgStocks, int $purchaseOrderId): string
    {
        $deadLines = "'".PurchaseOrderTransactionStateEnum::CANCELLED->value."', '".PurchaseOrderTransactionStateEnum::NOT_RECEIVED->value."'";

        return "(coalesce((select sum(purchase_order_transactions.quantity_ordered)
                from purchase_order_transactions
                join purchase_orders on purchase_orders.id = purchase_order_transactions.purchase_order_id and purchase_orders.deleted_at is null
                where purchase_order_transactions.org_stock_id = $orgStocks.id and purchase_order_transactions.deleted_at is null
                    and purchase_order_transactions.purchase_order_id = $purchaseOrderId
                    and purchase_order_transactions.state not in ($deadLines)
                    and purchase_orders.state = '".PurchaseOrderStateEnum::IN_PROCESS->value."'), 0)
            / greatest(coalesce($orgStocks.packed_in, 1), 1))";
    }

    /**
     * The stock group of a SKO from the days it lasts counting what is already coming, so a SKO with
     * enough on the way is not called doomed. The cases read cover.days; it is worked out once a row
     * (the offset keeps the planner from repeating it in every case) and what is coming is only
     * looked up inside the widest window: a SKO covered by its shelf alone needs no second look.
     * cover.days_landed leaves out $draftOrderSkos: a draft purchase order lands a full lead time from
     * now at the earliest, so it cannot save a SKO from being doomed (HELP-4572), only from the later groups.
     */
    public static function bucketOnCoverDaysExpression(string $orgStocks, string $stats, string $widestWindowDays, string $cases, string $alsoComingSkos = '0', string $draftOrderSkos = '0'): string
    {
        return "(select case $cases end from (select landed.days as days_landed,
                landed.days + case when $stats.predicted_daily_usage > 0 and $stats.days_of_cover <= $widestWindowDays
                    then ($draftOrderSkos) / $stats.predicted_daily_usage else 0 end as days
            from (select case when $stats.predicted_daily_usage > 0 and $stats.days_of_cover <= $widestWindowDays
                then $stats.days_of_cover + (".self::comingSkosExpression($orgStocks)." + $alsoComingSkos) / $stats.predicted_daily_usage
                else $stats.days_of_cover end as days offset 0) as landed offset 0) as cover)";
    }

    /**
     * A SKO with nothing on the shelf is out of stock, unless what is already coming lasts past its
     * overstock edge: the ordering card calls that not needed, so it is judged on its cover instead.
     */
    public static function outOfStockCase(string $orgStocks, string $stats, string $overstock, string $alsoComingSkos = '0'): string
    {
        return "when $orgStocks.quantity_available < 1 and coalesce((".self::comingSkosExpression($orgStocks)." + $alsoComingSkos) / nullif($stats.predicted_daily_usage, 0) < $overstock, true) then 'out'";
    }

    /**
     * The at-risk groups of a SKO from the days it lasts. A SKO past its overstock edge is never at
     * risk, the ordering card calls it not needed; doomed still comes first, as the card's order now.
     */
    public static function atRiskCases(string $lead, string $understock, string $overstock, string $watchDays): string
    {
        return "when cover.days_landed <= $lead then 'w1'
                when cover.days <= least($understock, $overstock) then 'w2'
                when cover.days <= least(3 * $lead, $overstock) then 'w3'
                when cover.days <= least($watchDays, $overstock) then 'w4'";
    }

    public function bucketExpression(): string
    {
        $lead       = $this->leadTimeExpression();
        $understock = $this->understockDaysExpression($lead);
        $overstock  = $this->overstockDaysExpression();

        return "case
            ".self::outOfStockCase('org_stocks', 'org_stock_stats', $overstock)."
            else ".self::bucketOnCoverDaysExpression('org_stocks', 'org_stock_stats', "greatest(4 * $lead, $understock)", "
                ".self::atRiskCases($lead, $understock, $overstock, "4 * $lead")."
                when coalesce(org_stock_stats.predicted_daily_usage, 0) = 0 and org_stock_stats.stock_value > 0 then 'dead'
                when cover.days >= $overstock then 'excess'
                else 'ok'").' end';
    }

    /**
     * A bucket is decided row by row from the SKO's own lead time, stats and family, so several
     * organisations can be scoped at once and each SKO still lands where its own organisation puts it.
     *
     * @param Organisation|array<int, int> $organisation one organisation, or the ids of several
     */
    public function scope(Builder|EloquentBuilder $query, Organisation|array $organisation, ?OrgStockFamily $orgStockFamily = null, ?string $source = null): Builder|EloquentBuilder
    {
        return $query
            ->leftJoinLateral($this->primarySupplierProduct(), 'sp')
            ->leftJoin('org_stock_stats', 'org_stock_stats.org_stock_id', 'org_stocks.id')
            ->leftJoin('stocks', 'stocks.id', 'org_stocks.stock_id')
            ->leftJoin('stock_families', 'stock_families.id', 'stocks.stock_family_id')
            ->when(
                is_array($organisation),
                fn ($query) => $query->whereIn('org_stocks.organisation_id', $organisation),
                fn ($query) => $query->where('org_stocks.organisation_id', $organisation->id)
            )
            ->where('org_stocks.state', OrgStockStateEnum::ACTIVE->value)
            ->where(fn ($query) => $query->whereNull('stocks.state')->orWhereNotIn('stocks.state', [StockStateEnum::DISCONTINUING->value, StockStateEnum::DISCONTINUED->value]))
            ->whereRaw('coalesce(org_stocks.is_on_demand, false) = false')
            ->where(fn ($query) => $query->where('org_stocks.quantity_available', '>', 0)->orWhere(fn ($query) => $this->whereCountsAsStockOut($query)))
            ->when($orgStockFamily, fn ($query) => $query->where('org_stocks.org_stock_family_id', $orgStockFamily->id))
            ->when($source, fn ($query) => $query->whereRaw($this->sourceExpression().' = ?', [$source]));
    }

    public function whereBuckets(Builder|EloquentBuilder $query, array $buckets): Builder|EloquentBuilder
    {
        return $query->whereRaw($this->bucketExpression().' in ('.implode(',', array_fill(0, count($buckets), '?')).')', array_values($buckets));
    }

    /**
     * Keeps the SKOs whose primary supplier is the one with this slug, or with "none" those without a supplier.
     */
    public function whereSupplier(Builder|EloquentBuilder $query, ?string $supplier): Builder|EloquentBuilder
    {
        return $query->when($supplier, fn ($query) => $supplier === self::NO_SUPPLIER
            ? $query->whereNull('sp.supplier_id')
            : $query->where('sp.supplier_slug', $supplier));
    }

    /**
     * @return array<string, string> supplier slug => code, of every supplier answering for an SKO of the organisation
     */
    public function supplierOptions(Organisation $organisation): array
    {
        return $this->scope(DB::table('org_stocks'), $organisation)
            ->whereNotNull('sp.supplier_id')
            ->distinct()
            ->orderBy('sp.supplier_code')
            ->pluck('sp.supplier_code', 'sp.supplier_slug')
            ->all();
    }

    /**
     * @param array<int, string> $buckets
     */
    public function exportQuery(Organisation $organisation, array $buckets, ?string $supplier = null): Builder
    {
        return $this->whereSupplier($this->whereBuckets($this->scope(DB::table('org_stocks'), $organisation), $buckets), $supplier)
            ->leftJoin('org_stock_families', 'org_stock_families.id', 'org_stocks.org_stock_family_id')
            ->select([
                'org_stocks.code',
                'org_stocks.name',
                'org_stock_families.code as family_code',
                'org_stocks.health_rank',
                'org_stocks.quantity_available',
                'org_stock_stats.days_of_cover',
                'org_stock_stats.stock_value',
                'org_stock_stats.on_the_way_po_count',
                'org_stock_stats.recommended_order_quantity',
                'sp.supplier_code',
            ])
            ->selectRaw($this->leadTimeExpression().' as lead_time_days')
            ->selectRaw($this->bucketExpression().' as bucket')
            ->orderBy('org_stocks.code');
    }

    public function bucketOf(OrgStock $orgStock): ?string
    {
        return $this->scope(DB::table('org_stocks'), $orgStock->organisation)
            ->where('org_stocks.id', $orgStock->id)
            ->selectRaw($this->bucketExpression().' as bucket')
            ->value('bucket');
    }

    /**
     * @return array<int, array{bucket: string, label: string, description: string|null, tone: string, count: int, stock_value: float}>
     */
    public function handle(Organisation $organisation, ?OrgStockFamily $orgStockFamily = null, ?string $source = null): array
    {
        $expression = $this->bucketExpression();

        $rows = $this->scope(DB::table('org_stocks'), $organisation, $orgStockFamily, $source)
            ->selectRaw("$expression as bucket, count(*) as total, coalesce(sum(org_stock_stats.stock_value), 0) as stock_value")
            ->groupByRaw($expression)
            ->get()
            ->keyBy('bucket');

        return collect(self::BUCKETS)->map(fn ($meta, $bucket) => [
            'bucket'      => $bucket,
            'label'       => $this->bucketLabel($bucket),
            'description' => $this->bucketDescription($bucket),
            'tone'        => $meta['tone'],
            'count'       => (int) ($rows->get($bucket)->total ?? 0),
            'stock_value' => (float) ($rows->get($bucket)->stock_value ?? 0),
        ])->values()->all();
    }
}
