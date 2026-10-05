<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement;

use App\Actions\Procurement\OrgSupplier\GetSupplierLeadTime;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementFlowEnum;
use App\Enums\Procurement\OrgSupplierProduct\OrgSupplierProductStateEnum;
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
        'w4'     => ['label' => 'Watch', 'description' => 'Out within 4 lead times', 'tone' => 'yellow'],
        'ok'     => ['label' => 'Covered', 'tone' => 'green'],
        'excess' => ['label' => 'Excess', 'description' => 'More than :days days of stock', 'tone' => 'blue'],
        'dead'   => ['label' => 'Dead stock', 'tone' => 'gray'],
    ];

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
            ->orderByRaw('link.local_priority nulls last')
            ->orderBy('link.id')
            ->select([
                'sup.measured_lead_time_days',
                'sup.estimated_lead_time_days',
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

    public function bucketExpression(): string
    {
        $lead       = $this->leadTimeExpression();
        $understock = $this->understockDaysExpression($lead);
        $overstock  = $this->overstockDaysExpression();

        return "case
            when org_stocks.quantity_available <= 0 then 'out'
            when org_stock_stats.days_of_cover <= $lead then 'w1'
            when org_stock_stats.days_of_cover <= $understock then 'w2'
            when org_stock_stats.days_of_cover <= 3 * $lead then 'w3'
            when org_stock_stats.days_of_cover <= 4 * $lead then 'w4'
            when coalesce(org_stock_stats.predicted_daily_usage, 0) = 0 and org_stock_stats.stock_value > 0 then 'dead'
            when org_stock_stats.days_of_cover >= $overstock then 'excess'
            else 'ok' end";
    }

    public function scope(Builder|EloquentBuilder $query, Organisation $organisation, ?OrgStockFamily $orgStockFamily = null, ?string $source = null): Builder|EloquentBuilder
    {
        return $query
            ->leftJoinLateral($this->primarySupplierProduct(), 'sp')
            ->leftJoin('org_stock_stats', 'org_stock_stats.org_stock_id', 'org_stocks.id')
            ->leftJoin('stocks', 'stocks.id', 'org_stocks.stock_id')
            ->leftJoin('stock_families', 'stock_families.id', 'stocks.stock_family_id')
            ->where('org_stocks.organisation_id', $organisation->id)
            ->where('org_stocks.state', OrgStockStateEnum::ACTIVE->value)
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
     * @param array<int, string> $buckets
     */
    public function exportQuery(Organisation $organisation, array $buckets): Builder
    {
        return $this->whereBuckets($this->scope(DB::table('org_stocks'), $organisation), $buckets)
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
