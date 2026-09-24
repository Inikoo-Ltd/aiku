<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement;

use App\Actions\Procurement\OrgSupplier\GetSupplierLeadTime;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\Procurement\OrgSupplierProduct\OrgSupplierProductStateEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockFamily;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetOrganisationStockCoverBuckets
{
    use AsObject;

    public const EXCESS_DAYS = 120;

    public const BUCKETS = [
        'out'    => ['label' => 'Out of stock', 'tone' => 'red-deep'],
        'w1'     => ['label' => 'Doomed: gone before any delivery lands', 'tone' => 'red'],
        'w2'     => ['label' => 'Critical: out within 2 lead times', 'tone' => 'orange'],
        'w3'     => ['label' => 'Danger: out within 3 lead times', 'tone' => 'amber'],
        'w4'     => ['label' => 'Watch: out within 4 lead times', 'tone' => 'yellow'],
        'ok'     => ['label' => 'Covered', 'tone' => 'green'],
        'excess' => ['label' => 'Excess: more than :days days of stock', 'tone' => 'blue'],
        'dead'   => ['label' => 'Dead stock', 'tone' => 'gray'],
    ];

    public function bucketLabel(string $bucket): string
    {
        return __(self::BUCKETS[$bucket]['label'], ['days' => self::EXCESS_DAYS]);
    }

    /**
     * The supplier product that answers for an org stock: the live link with the best local priority.
     */
    private function primarySupplierProduct(): Builder
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
            ])
            ->limit(1);
    }

    public function leadTimeExpression(): string
    {
        return 'coalesce(org_stocks.measured_lead_time_days, org_stocks.estimated_lead_time_days, sp.measured_lead_time_days, sp.estimated_lead_time_days, '.GetSupplierLeadTime::DEFAULT_DAYS.')';
    }

    public function bucketExpression(): string
    {
        $lead = $this->leadTimeExpression();

        return "case
            when org_stocks.quantity_available <= 0 then 'out'
            when org_stock_stats.days_of_cover <= $lead then 'w1'
            when org_stock_stats.days_of_cover <= 2 * $lead then 'w2'
            when org_stock_stats.days_of_cover <= 3 * $lead then 'w3'
            when org_stock_stats.days_of_cover <= 4 * $lead then 'w4'
            when coalesce(org_stock_stats.predicted_daily_usage, 0) = 0 and org_stock_stats.stock_value > 0 then 'dead'
            when org_stock_stats.days_of_cover >= ".self::EXCESS_DAYS." then 'excess'
            else 'ok' end";
    }

    public function scope(Builder|EloquentBuilder $query, Organisation $organisation, ?OrgStockFamily $orgStockFamily = null): Builder|EloquentBuilder
    {
        return $query
            ->leftJoinLateral($this->primarySupplierProduct(), 'sp')
            ->leftJoin('org_stock_stats', 'org_stock_stats.org_stock_id', 'org_stocks.id')
            ->where('org_stocks.organisation_id', $organisation->id)
            ->where('org_stocks.state', OrgStockStateEnum::ACTIVE->value)
            ->whereRaw('coalesce(org_stocks.is_on_demand, false) = false')
            ->when($orgStockFamily, fn ($query) => $query->where('org_stocks.org_stock_family_id', $orgStockFamily->id));
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
     * @return array<int, array{bucket: string, label: string, tone: string, count: int, stock_value: float}>
     */
    public function handle(Organisation $organisation, ?OrgStockFamily $orgStockFamily = null): array
    {
        $expression = $this->bucketExpression();

        $rows = $this->scope(DB::table('org_stocks'), $organisation, $orgStockFamily)
            ->selectRaw("$expression as bucket, count(*) as total, coalesce(sum(org_stock_stats.stock_value), 0) as stock_value")
            ->groupByRaw($expression)
            ->get()
            ->keyBy('bucket');

        return collect(self::BUCKETS)->map(fn ($meta, $bucket) => [
            'bucket'      => $bucket,
            'label'       => $this->bucketLabel($bucket),
            'tone'        => $meta['tone'],
            'count'       => (int) ($rows->get($bucket)->total ?? 0),
            'stock_value' => (float) ($rows->get($bucket)->stock_value ?? 0),
        ])->values()->all();
    }
}
