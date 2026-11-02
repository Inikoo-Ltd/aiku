<?php

/*
 * author Arya Permana - Kirin
 * created on 15-11-2024-11h-17m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Procurement\PurchaseOrder\UI;

use App\Actions\Procurement\OrgPartner\GetPartnerSellingShopIds;
use App\Actions\Procurement\OrgAgent\GetAgentLeadTimes;
use App\Actions\Procurement\OrgAgent\UI\IndexAgentCoverBucketItems;
use App\Actions\Procurement\OrgPartner\GetPartnerLeadTime;
use App\Actions\Procurement\OrgSupplier\GetSupplierLeadTime;
use App\Actions\Procurement\OrgSupplier\GetSupplierStockCoverBuckets;
use App\Enums\Catalogue\HealthRankEnum;
use App\Models\Inventory\OrgStockDiscontinueSuggestion;
use App\Models\SupplyChain\SupplierProduct;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Actions\Inventory\OrgStock\GetOrgStocksQuarterlyUsage;
use App\Actions\Inventory\OrgStock\GetOrgStocksOpenPurchaseOrders;
use App\Actions\Inventory\OrgStock\GetOrgStocksStockDeliveries;
use App\Actions\OrgAction;
use App\Actions\Procurement\OrgPartner\PartnerSkoPrice;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\Procurement\OrgSupplierProduct\OrgSupplierProductStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Http\Resources\Procurement\PurchaseOrderOrgSupplierProductsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Inventory\OrgStock;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use App\Actions\Procurement\OrgPartner\GetPartnerBuyingPriceFactor;
use App\Actions\Procurement\OrgPartner\GetPartnerLandedCost;

class IndexPurchaseOrderOrgSupplierProducts extends OrgAction
{
    use WithProcurementAuthorisation;
    public function handle(Organisation|OrgSupplier $parent, ?PurchaseOrder $purchaseOrder, $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $orgId = $purchaseOrder?->organisation_id ?? $parent->organisation_id;

        $orgStockSub = $this->orgStockSql($orgId);

        $queryBuilder = $this->baseQuery($parent, $purchaseOrder);

        $paginator = $queryBuilder
            ->defaultSort('supplier_products.code')
            ->select([
                'org_supplier_products.id',
                'supplier_products.code',
                'supplier_products.slug',
                'supplier_products.id as supplier_product_id',
                'supplier_products.name',
                DB::raw('coalesce(purchase_order_transactions.unit_cost, supplier_products.cost) as unit_cost'),
                DB::raw("coalesce((select nullif(packed_in, 0) from org_stocks where id = {$orgStockSub}), supplier_products.units_per_pack) as units_per_pack"),
                'supplier_products.units_per_carton',
                'supplier_products.minimum_carton_order',
                'supplier_products.measured_lead_time_days',
                'supplier_products.estimated_lead_time_days',
                'supplier_products.current_historic_supplier_product_id as historic_id',
                'supplier_currency.code as net_currency',
                'org_currency.code as org_currency',
                'purchase_order_transactions.quantity_ordered as quantity_ordered',
                'purchase_order_transactions.net_amount as net_amount',
                'purchase_order_transactions.org_net_amount as org_net_amount',
                'purchase_order_transactions.org_exchange as org_exchange',
                'purchase_order_transactions.id as purchase_order_transaction_id',
                'suppliers.name as supplier_name',
                'org_suppliers.slug as supplier_slug',
            ])
            ->selectRaw("{$orgStockSub} as org_stock_id")
            ->selectRaw($parent instanceof OrgSupplier ? 'cover.bucket as bucket' : 'null as bucket')
            ->selectRaw($purchaseOrder ? "{$purchaseOrder->id} as purchase_order_id" : 'purchase_order_transactions.purchase_order_id as purchase_order_id')
            ->selectRaw(($purchaseOrder?->org_exchange ?: 1).' as po_org_exchange')
            ->allowedSorts(['code', 'name'])
            ->allowedFilters($this->filters($parent))
            ->withPaginator($prefix, tableName: request()->route()?->getName())
            ->withQueryString();

        $this->attachOrgStockData($paginator, $purchaseOrder);
        $this->attachOtherOpenPurchaseOrders($paginator, $orgId, $purchaseOrder?->id);
        $this->attachOtherOrganisationsDrafts($paginator, $orgId);

        return $paginator;
    }

    /**
     * @return array<int, AllowedFilter>
     */
    private function filters(Organisation|OrgSupplier $parent, bool $coverApplies = true): array
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('supplier_products.code', $value)
                    ->orWhereStartWith('supplier_products.name', $value)
                    ->orWhereExists(fn ($query) => $query->selectRaw('1')
                        ->from('org_stocks')
                        ->join('stock_has_supplier_products', 'stock_has_supplier_products.stock_id', 'org_stocks.stock_id')
                        ->whereColumn('stock_has_supplier_products.supplier_product_id', 'supplier_products.id')
                        ->whereColumn('org_stocks.organisation_id', 'org_supplier_products.organisation_id')
                        ->where('org_stocks.code', 'ilike', addcslashes($value, '%_\\').'%'));
            });
        });

        if (!$parent instanceof OrgSupplier) {
            return [$globalSearch];
        }

        $onOrder = IndexAgentCoverBucketItems::onOrderSql('org_supplier_products', $this->orgStockSql($parent->organisation_id));

        return [
            $globalSearch,
            AllowedFilter::callback('hide_ordered', fn ($query, $value) => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? $query->whereRaw("not $onOrder") : $query),
            AllowedFilter::callback('cover', fn ($query, $value) => $coverApplies ? $query->where('cover.bucket', $value) : $query),
            AllowedFilter::callback('rank', fn ($query, $value) => $coverApplies ? $query->where('cover.health_rank', $value) : $query),
        ];
    }

    private function orgStockSql(int $organisationId): string
    {
        return "(select os.id from org_stocks os
            inner join stock_has_supplier_products shsp on shsp.stock_id = os.stock_id
            where shsp.supplier_product_id = supplier_products.id
                and os.organisation_id = {$organisationId}
            limit 1)";
    }

    private function baseQuery(Organisation|OrgSupplier $parent, ?PurchaseOrder $purchaseOrder): QueryBuilder
    {
        $orgId = $purchaseOrder?->organisation_id ?? $parent->organisation_id;

        $queryBuilder = QueryBuilder::for(OrgSupplierProduct::class);
        $queryBuilder->leftJoin('supplier_products', 'supplier_products.id', 'org_supplier_products.supplier_product_id')
            ->leftJoin('suppliers', 'supplier_products.supplier_id', 'suppliers.id')
            ->leftJoin('org_suppliers', 'org_suppliers.id', 'org_supplier_products.org_supplier_id')
            ->leftJoin('currencies as supplier_currency', 'supplier_currency.id', 'supplier_products.currency_id')
            ->leftJoin('organisations', 'organisations.id', 'org_supplier_products.organisation_id')
            ->leftJoin('currencies as org_currency', 'org_currency.id', 'organisations.currency_id');

        $queryBuilder->leftJoin('purchase_order_transactions', function ($join) use ($purchaseOrder) {
            $join->on('purchase_order_transactions.org_supplier_product_id', '=', 'org_supplier_products.id')
                ->where('purchase_order_transactions.purchase_order_id', $purchaseOrder->id);
        });

        if (class_basename($parent) == 'OrgSupplier') {
            $queryBuilder->where('org_supplier_products.org_supplier_id', $parent->id);
        } else {
            $queryBuilder->where('org_supplier_products.organisation_id', $this->organisation->id);
        }

        $hasDiscontinuingSko = "exists (select 1 from org_stocks os
            inner join stock_has_supplier_products shsp on shsp.stock_id = os.stock_id
            where shsp.supplier_product_id = supplier_products.id
                and os.organisation_id = {$orgId}
                and os.state in ('".OrgStockStateEnum::DISCONTINUING->value."', '".OrgStockStateEnum::DISCONTINUED->value."'))";

        $queryBuilder->where(function ($query) use ($hasDiscontinuingSko) {
            $query->where(fn ($query) => $query->where('org_supplier_products.state', OrgSupplierProductStateEnum::ACTIVE)
                ->where('org_supplier_products.is_available', true)
                ->where('supplier_products.is_available', true)
                ->whereColumn('supplier_products.supplier_id', 'org_suppliers.supplier_id')
                ->whereRaw("not $hasDiscontinuingSko"))
                ->orWhereNotNull('purchase_order_transactions.id');
        });

        if ($parent instanceof OrgSupplier) {
            $queryBuilder->leftJoinSub(GetSupplierStockCoverBuckets::make()->bucketsQuery($parent), 'cover', 'cover.id', 'org_supplier_products.id');
        }

        return $queryBuilder;
    }

    /**
     * The stock group and rank capsules above the table, with how many products sit behind each one.
     * The counts follow the search and the on order tick; the rank counts follow the stock group picked.
     *
     * @return array{currency: string, cover: array<int, array{value: string, label: string, title: string, tone: string, count: int}>, rank: array<int, array{value: string, label: string, count: int}>, active: array{cover: string|null, rank: string|null, hide_ordered: bool}}
     */
    public function orderingFilters(OrgSupplier $orgSupplier, PurchaseOrder $purchaseOrder, string $prefix): array
    {
        InertiaTable::updateQueryBuilderParameters($prefix);

        $counts = $this->baseQuery($orgSupplier, $purchaseOrder)
            ->allowedFilters($this->filters($orgSupplier, false))
            ->groupBy('cover.bucket', 'cover.health_rank')
            ->selectRaw('cover.bucket, cover.health_rank, count(*) as products')
            ->toBase()
            ->get();

        $cover = request()->input($prefix.'_filter.cover');
        $rank  = request()->input($prefix.'_filter.rank');

        $buckets  = GetSupplierStockCoverBuckets::make();
        $leadDays = GetSupplierLeadTime::run($orgSupplier)['days'];
        $inGroup  = $cover ? $counts->where('bucket', $cover) : $counts;

        return [
            'currency' => $purchaseOrder->organisation->currency->code,
            'cover'    => collect(GetSupplierStockCoverBuckets::BUCKETS)->map(fn (array $meta, string $bucket) => [
                'value' => $bucket,
                'label' => explode(':', __($meta['label']))[0],
                'title' => $buckets->bucketLabel($bucket, $leadDays),
                'tone'  => $meta['tone'],
                'count' => (int) $counts->where('bucket', $bucket)->sum('products'),
            ])->values()->all(),
            'rank'     => collect(HealthRankEnum::cases())->map(fn (HealthRankEnum $healthRank) => [
                'value' => $healthRank->value,
                'label' => $healthRank->value,
                'count' => (int) $inGroup->where('health_rank', $healthRank->value)->sum('products'),
            ])->all(),
            'active'   => ['cover' => $cover, 'rank' => $rank, 'hide_ordered' => request()->boolean($prefix.'_filter.hide_ordered')],
        ];
    }

    public function tableStructure($prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }
            $table
                ->withGlobalSearch()
                ->withModelOperations()
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'image', label: __('Image'), canBeHidden: false)
                ->column(key: 'name', label: __('Product'), canBeHidden: false, sortable: true)
                ->column(key: 'bucket', label: __('Stock group'), canBeHidden: false)
                ->column(key: 'subtotals', label: __('Subtotals'), canBeHidden: false)
                ->column(key: 'cartons', label: __('Cartons'), canBeHidden: false, align: 'right')
                ->defaultSort('code');
        };
    }

    public function inOrgSupplier(OrgSupplier $orgSupplier, PurchaseOrder $purchaseOrder, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($orgSupplier->organisation, $request);

        return $this->handle($orgSupplier, $purchaseOrder);
    }

    public function inOrgPartner(OrgPartner $orgPartner, PurchaseOrder $purchaseOrder, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($orgPartner->organisation, $request);

        return $this->partnerOrgStocks($orgPartner, $purchaseOrder);
    }

    /**
     * Our SKOs the partner sells too, priced at what the partner sells one SKO for.
     */
    public function partnerOrgStocks(OrgPartner $orgPartner, PurchaseOrder $purchaseOrder): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('org_stocks.code', $value)
                    ->orWhereAnyWordStartWith('org_stocks.name', $value);
            });
        });

        $pricePerSko       = PartnerSkoPrice::pricePerSkoSql('seller_org_stocks.id', GetPartnerSellingShopIds::run($orgPartner->partner), (string) $orgPartner->id);
        $buyingPricePerSko = GetPartnerLandedCost::appliesTo($orgPartner)
            ? 'coalesce('.GetPartnerLandedCost::perSkoSql('seller_org_stocks.id').", $pricePerSko)"
            : "$pricePerSko * ".GetPartnerBuyingPriceFactor::run($orgPartner);

        $paginator = QueryBuilder::for(OrgStock::class)
            ->join('org_stocks as seller_org_stocks', function ($join) use ($orgPartner) {
                $join->on('seller_org_stocks.stock_id', 'org_stocks.stock_id')
                    ->where('seller_org_stocks.organisation_id', $orgPartner->partner_id)
                    ->where('seller_org_stocks.state', OrgStockStateEnum::ACTIVE->value);
            })
            ->leftJoin('purchase_order_transactions', function ($join) use ($purchaseOrder) {
                $join->on('purchase_order_transactions.org_stock_id', 'org_stocks.id')
                    ->where('purchase_order_transactions.purchase_order_id', $purchaseOrder->id)
                    ->whereNull('purchase_order_transactions.deleted_at');
            })
            ->where('org_stocks.organisation_id', $purchaseOrder->organisation_id)
            ->where(function ($query) use ($pricePerSko) {
                $query->whereNotNull('purchase_order_transactions.id')
                    ->orWhere(fn ($query) => $query->whereIn('org_stocks.state', [OrgStockStateEnum::ACTIVE->value])->whereRaw("$pricePerSko is not null"));
            })
            ->defaultSort('org_stocks.code')
            ->select([
                'org_stocks.id',
                'org_stocks.code',
                'org_stocks.name',
                'org_stocks.id as org_stock_id',
                'org_stocks.packed_in as units_per_pack',
                'purchase_order_transactions.quantity_ordered',
                'purchase_order_transactions.net_amount',
                'purchase_order_transactions.org_net_amount',
                'purchase_order_transactions.org_exchange',
                'purchase_order_transactions.id as purchase_order_transaction_id',
            ])
            ->selectRaw("coalesce(purchase_order_transactions.unit_cost, $buyingPricePerSko / nullif(seller_org_stocks.packed_in, 0)) as unit_cost")
            ->selectRaw('null as units_per_carton')
            ->selectRaw('true as is_partner_org_stock')
            ->selectRaw('? as supplier_name', [$orgPartner->partner->name])
            ->selectRaw('? as net_currency', [$purchaseOrder->currency->code])
            ->selectRaw('? as org_currency', [$purchaseOrder->organisation->currency->code])
            ->selectRaw("{$purchaseOrder->id} as purchase_order_id")
            ->selectRaw(($purchaseOrder->org_exchange ?: 1).' as po_org_exchange')
            ->allowedSorts(['code', 'name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator(null, tableName: request()->route()?->getName())
            ->withQueryString();

        $this->attachOrgStockData($paginator, $purchaseOrder);
        $this->attachOtherOpenPurchaseOrders($paginator, $purchaseOrder->organisation_id, $purchaseOrder->id);

        return $paginator;
    }

    public function jsonResponse(LengthAwarePaginator $orgSupplierProducts): AnonymousResourceCollection
    {
        return PurchaseOrderOrgSupplierProductsResource::collection($orgSupplierProducts);
    }

    private function attachOrgStockData(LengthAwarePaginator $paginator, ?PurchaseOrder $purchaseOrder): void
    {
        $orgStockIds = $paginator->getCollection()->pluck('org_stock_id')->filter()->unique()->values();

        if ($orgStockIds->isEmpty()) {
            return;
        }

        $orgStocks = OrgStock::with('tradeUnits.image', 'stats', 'stock.stockFamily')->whereIn('id', $orgStockIds)->get()->keyBy('id');
        $supplierProducts    = SupplierProduct::whereIn('id', $paginator->getCollection()->pluck('supplier_product_id')->filter()->unique())->get()->keyBy('id');
        $partnerLeadTimeDays = $purchaseOrder?->parent instanceof OrgPartner ? GetPartnerLeadTime::run($purchaseOrder->parent)['days'] : null;
        $fallbackLeadTimeDays = match (true) {
            $purchaseOrder?->parent instanceof OrgSupplier => GetSupplierLeadTime::run($purchaseOrder->parent)['days'],
            $purchaseOrder?->parent instanceof OrgAgent    => GetAgentLeadTimes::run($purchaseOrder->parent)['agent']['days'],
            default                                         => null,
        };

        $quarterlyUsage  = GetOrgStocksQuarterlyUsage::run($orgStockIds);
        $stockDeliveries = GetOrgStocksStockDeliveries::run($orgStockIds);
        $discontinueSuggestions = OrgStockDiscontinueSuggestion::tallyForOrgStocks($orgStockIds);

        $paginator->getCollection()->transform(function ($row) use ($orgStocks, $quarterlyUsage, $stockDeliveries, $discontinueSuggestions, $supplierProducts, $partnerLeadTimeDays, $fallbackLeadTimeDays) {
            $orgStock  = $orgStocks->get($row->org_stock_id);
            $tradeUnit = $orgStock?->tradeUnits->first(fn ($tradeUnit) => $tradeUnit->image_id !== null);

            $row->image_sources      = $tradeUnit?->imageSources(240, 240);
            $row->stock_in_locations = $orgStock?->quantity_in_locations;
            $row->quarterly_usage    = $quarterlyUsage->get($row->org_stock_id) ?? collect();
            $row->stock_cover        = GetOrgStockBuyingSignals::run($orgStock, $supplierProducts->get($row->supplier_product_id ?? null), $partnerLeadTimeDays, $fallbackLeadTimeDays);
            $row->stock_deliveries   = $stockDeliveries->get($row->org_stock_id);
            $row->discontinue_suggestions = $discontinueSuggestions->get($row->org_stock_id) ?? ['times' => 0, 'people' => 0];

            return $row;
        });
    }

    public function attachOtherOpenPurchaseOrders(LengthAwarePaginator $paginator, int $organisationId, ?int $exceptPurchaseOrderId = null): void
    {
        $rows               = $paginator->getCollection();
        $supplierProductIds = $rows->pluck('supplier_product_id')->filter()->unique()->values();
        $orgStockIds        = $rows->pluck('org_stock_id')->filter()->unique()->values();

        if ($supplierProductIds->isEmpty() && $orgStockIds->isEmpty()) {
            return;
        }

        $openPurchaseOrderLines = GetOrgStocksOpenPurchaseOrders::run($organisationId, $supplierProductIds, $orgStockIds, $exceptPurchaseOrderId);

        $rows->transform(function ($row) use ($openPurchaseOrderLines) {
            $row->other_open_purchase_orders = $openPurchaseOrderLines
                ->filter(fn ($line) => ($row->supplier_product_id && $line->supplier_product_id == $row->supplier_product_id)
                    || ($row->org_stock_id && $line->org_stock_id == $row->org_stock_id))
                ->groupBy('slug')
                ->map(fn ($lines) => [
                    'slug'             => $lines->first()->slug,
                    'reference'        => $lines->first()->reference,
                    'state'            => $lines->first()->state,
                    'quantity_ordered' => (float) $lines->sum('quantity_ordered'),
                ])
                ->values();

            return $row;
        });
    }

    /**
     * A supplier's minimum is met by every organisation's order together, so the drafts other
     * organisations hold for the same supplier product count towards it.
     */
    public function attachOtherOrganisationsDrafts(LengthAwarePaginator $paginator, int $organisationId): void
    {
        $rows               = $paginator->getCollection();
        $supplierProductIds = $rows->pluck('supplier_product_id')->filter()->unique()->values();

        if ($supplierProductIds->isEmpty()) {
            return;
        }

        $drafts = DB::table('purchase_orders as po')
            ->join('purchase_order_transactions as pot', fn ($join) => $join->on('pot.purchase_order_id', 'po.id')->whereNull('pot.deleted_at'))
            ->join('organisations as o', 'o.id', 'po.organisation_id')
            ->leftJoin('users as buyer', 'buyer.id', 'po.buyer_id')
            ->where('po.group_id', DB::table('organisations')->where('id', $organisationId)->select('group_id'))
            ->where('po.organisation_id', '!=', $organisationId)
            ->where('po.state', PurchaseOrderStateEnum::IN_PROCESS->value)
            ->whereNull('po.deleted_at')
            ->whereIn('pot.supplier_product_id', $supplierProductIds)
            ->groupBy('pot.supplier_product_id', 'po.id', 'o.id', 'buyer.id')
            ->orderBy('o.code')
            ->selectRaw('pot.supplier_product_id, o.code as organisation_code, o.name as organisation_name, po.reference, buyer.contact_name as buyer_name, sum(pot.quantity_ordered) as units')
            ->get()
            ->groupBy('supplier_product_id');

        $rows->transform(function ($row) use ($drafts) {
            $row->other_organisations_drafts = ($drafts->get($row->supplier_product_id) ?? collect())->map(fn ($draft) => [
                'organisation_code' => $draft->organisation_code,
                'organisation_name' => $draft->organisation_name,
                'reference'         => $draft->reference,
                'buyer_name'        => $draft->buyer_name,
                'units'             => (float) $draft->units,
            ])->values();

            return $row;
        });
    }
}
