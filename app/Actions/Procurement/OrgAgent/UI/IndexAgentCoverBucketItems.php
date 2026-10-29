<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 31 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgAgent\UI;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Inventory\OrgStock\GetOrgStocksQuarterlyUsage;
use App\Actions\Inventory\OrgStock\GetOrgStocksStockDeliveries;
use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\UI\GetOrgStockBuyingSignals;
use App\Actions\Procurement\PurchaseOrder\UI\IndexPurchaseOrderOrgSupplierProducts;
use App\InertiaTable\InertiaTable;
use App\Actions\Procurement\OrgAgent\GetAgentLeadTimes;
use App\Actions\Procurement\OrgAgent\GetAgentOrderCapacity;
use App\Actions\Procurement\OrgAgent\GetAgentStockCoverBuckets;
use App\Actions\Procurement\OrgAgent\WithAgentShoppingSubNavigation;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\Inventory\OrgStockDiscontinueSuggestion;
use App\Models\Helpers\Currency;
use App\Models\Inventory\OrgStock;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class IndexAgentCoverBucketItems extends OrgAction
{
    use WithProcurementAuthorisation;
    use WithAgentShoppingSubNavigation;

    private OrgAgent $orgAgent;
    private ?string $bucket;
    private ?string $rank;
    private ?int $supplierId;

    private bool $onDraftsOnly = false;

    private bool $hideOnOrder = false;

    private bool $onAnyDraftOnly = false;

    private bool $hideDiscontinued = false;

    private ?string $prefix = null;

    private int $agentLeadDays = 0;

    /**
     * The Ordering rows of one supplier, kept to the products any organisation of the group has on a
     * draft: a supplier goes to the agent as one job, so a draft is read next to what the others stack on it.
     */
    public function onSupplierDrafts(OrgAgent $orgAgent, int $supplierId, string $prefix): LengthAwarePaginator
    {
        $this->orgAgent       = $orgAgent;
        $this->bucket         = null;
        $this->rank           = null;
        $this->supplierId     = $supplierId;
        $this->onAnyDraftOnly = true;
        $this->prefix         = $prefix;

        InertiaTable::updateQueryBuilderParameters($prefix);

        return $this->handle($orgAgent, null, null, $supplierId);
    }

    /**
     * Every product of one supplier the organisation can order through the agent, as the Ordering rows.
     */
    public function onSupplier(OrgAgent $orgAgent, int $supplierId, string $prefix): LengthAwarePaginator
    {
        $this->orgAgent   = $orgAgent;
        $this->bucket     = null;
        $this->rank       = null;
        $this->supplierId       = $supplierId;
        $this->prefix           = $prefix;
        $this->hideDiscontinued = true;

        InertiaTable::updateQueryBuilderParameters($prefix);
        $this->readOrderingFilters($prefix);

        return $this->handle($orgAgent, $this->bucket, $this->rank, $supplierId);
    }

    private function readOrderingFilters(string $prefix): void
    {
        $cover             = request()->input($prefix.'_filter.cover');
        $rank              = request()->input($prefix.'_filter.rank');
        $this->bucket      = array_key_exists((string) $cover, GetAgentStockCoverBuckets::BUCKETS) ? $cover : null;
        $this->rank        = in_array($rank, ['A', 'B', 'C', 'D', 'Z'], true) ? $rank : null;
        $this->hideOnOrder = request()->boolean($prefix.'_filter.hide_ordered');
    }

    /**
     * The stock group and rank capsules above a supplier's products on an agent purchase order, in the
     * shape the supplier purchase order uses.
     *
     * @return array{currency: string, cover: array<int, array{value: string, label: string, title: string, tone: string, count: int}>, rank: array<int, array{value: string, label: string, count: int}>, active: array{cover: string|null, rank: string|null, hide_ordered: bool}}
     */
    public function orderingFilters(OrgAgent $orgAgent, int $supplierId, string $currency, string $prefix): array
    {
        $this->orgAgent         = $orgAgent;
        $this->supplierId       = $supplierId;
        $this->hideDiscontinued = true;
        $this->readOrderingFilters($prefix);

        $options = $this->filterOptions(request()->input($prefix.'_filter.global', ''));

        return [
            'currency' => $currency,
            'cover'    => collect($options['cover'])->map(fn (array $option) => [
                ...$option,
                'title' => GetAgentStockCoverBuckets::make()->bucketLabel($option['value']),
                'tone'  => GetAgentStockCoverBuckets::BUCKETS[$option['value']]['tone'],
            ])->all(),
            'rank'     => $options['rank'],
            'active'   => ['cover' => $this->bucket, 'rank' => $this->rank, 'hide_ordered' => $this->hideOnOrder],
        ];
    }

    public function handle(OrgAgent $orgAgent, ?string $bucket, ?string $rank, ?int $supplierId): LengthAwarePaginator
    {
        $buckets             = GetAgentStockCoverBuckets::make();
        $bucketSql           = $buckets->bucketSql($orgAgent);
        $this->agentLeadDays = GetAgentLeadTimes::run($orgAgent)['agent']['days'];

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $like = '%'.addcslashes((string) $value, '%_\\').'%';
            $query->whereRaw('(sp.code collate "C" ilike ? or sp.name ilike ?)', [$like, $like]);
        });
        $supplierFilter = AllowedFilter::callback('supplier', fn ($query, $value) => $query->where('sp.supplier_id', (int) $value));

        $sortBy = fn (string $name, string $expression) => AllowedSort::callback(
            $name,
            fn ($query, bool $descending) => $query->orderByRaw($expression.($descending ? ' desc' : ' asc').' nulls last')
        );

        $queryBuilder = QueryBuilder::for($buckets->scope(OrgSupplierProduct::query()->from('org_supplier_products as osp'), $orgAgent, !$this->onAnyDraftOnly, $supplierId))
            ->join('suppliers as sup', 'sup.id', 'sp.supplier_id')
            ->leftJoin('currencies as cur', 'cur.id', 'sp.currency_id')
            ->when($bucket, fn ($query) => $query->whereRaw("$bucketSql = ?", [$bucket]))
            ->when($rank, fn ($query) => $query->where('os.health_rank', $rank))
            ->when($supplierId, fn ($query) => $query->where('sp.supplier_id', $supplierId))
            ->when($this->onDraftsOnly, fn ($query) => $query->whereRaw($this->onDraftSql()))
            ->when($this->hideOnOrder, fn ($query) => $query->whereRaw('not '.$this->onOrderSql()))
            ->when($this->hideDiscontinued, fn ($query) => $query->whereRaw($this->stillOrderableSql()))
            ->when($this->onAnyDraftOnly, fn ($query) => $query->whereExists(
                fn ($drafts) => $drafts->selectRaw('1')
                    ->fromSub(ShowAgentDraftsToSend::drafts($orgAgent), 'po')
                    ->join('purchase_order_transactions as pot', fn ($join) => $join->on('pot.purchase_order_id', 'po.id')->whereNull('pot.deleted_at'))
                    ->whereColumn('pot.supplier_product_id', 'osp.supplier_product_id')
            ))
            ->select([
                'osp.id',
                'osp.slug',
                'osp.supplier_product_id',
                'sp.code',
                'sp.name',
                'sp.cost',
                'os.packed_in as units_per_pack',
                'sp.units_per_carton',
                'sp.minimum_carton_order',
                'sup.code as supplier_code',
                'sup.slug as supplier_slug',
                'cur.code as currency',
                'sp.currency_id',
                'os.id as org_stock_id',
                'os.quantity_available',
                's.days_of_cover',
                's.recommended_order_quantity',
            ])
            ->selectRaw("$bucketSql as bucket")
            ->selectRaw(GetAgentStockCoverBuckets::leadTimeExpression($this->agentLeadDays).' as lead_time_days')
            ->selectRaw(GetAgentStockCoverBuckets::leadTimeMeasuredExpression().' as lead_time_measured');

        if (!request()->filled(($this->prefix ? $this->prefix.'_' : '').'sort')) {
            $queryBuilder
                ->orderByRaw("(os.id is not null and os.state = '".OrgStockStateEnum::ACTIVE->value."') desc")
                ->orderByRaw("case os.health_rank when 'A' then 1 when 'B' then 2 when 'C' then 3 when 'D' then 4 when 'Z' then 5 end nulls last")
                ->when(
                    $bucket === 'dead',
                    fn ($query) => $query->orderByRaw('s.stock_value desc nulls last'),
                    fn ($query) => $query->orderByRaw('s.days_of_cover nulls last')
                )
                ->orderByRaw('sp.code collate "C"');
        }

        return $this->hydrate(
            $queryBuilder
                ->allowedSorts([
                    $sortBy('code', 'sp.code collate "C"'),
                    $sortBy('name', 'sp.name'),
                    $sortBy('supplier_code', 'sup.code collate "C"'),
                ])
                ->allowedFilters([$globalSearch, $supplierFilter, ...array_map(fn (string $applied) => AllowedFilter::callback($applied, fn () => null), ['cover', 'rank', 'hide_ordered'])])
                ->orderBy('osp.id')
                ->withPaginator($this->prefix, tableName: request()->route()?->getName())
                ->withQueryString()
        );
    }

    private function hydrate(LengthAwarePaginator $paginator): LengthAwarePaginator
    {
        $rows = collect($paginator->items());

        $webImages = DB::table('product_has_org_stocks')
            ->join('products', 'products.id', 'product_has_org_stocks.product_id')
            ->whereIn('product_has_org_stocks.org_stock_id', $rows->pluck('org_stock_id')->filter())
            ->whereNull('products.deleted_at')
            ->whereNotNull('products.web_images->main')
            ->orderByRaw('(select count(*) from product_has_org_stocks as siblings where siblings.product_id = products.id) desc')
            ->orderBy('products.id')
            ->pluck('products.web_images', 'product_has_org_stocks.org_stock_id');

        $draftQuantities = $this->draftLines()
            ->whereIn('pot.org_supplier_product_id', $rows->pluck('id'))
            ->groupBy('pot.org_supplier_product_id')
            ->selectRaw('pot.org_supplier_product_id, sum(pot.quantity_ordered) as quantity')
            ->pluck('quantity', 'org_supplier_product_id');

        $otherOrganisationsDrafts = ShowAgentDraftsToSend::drafts($this->orgAgent)
            ->where('po.organisation_id', '!=', $this->orgAgent->organisation_id)
            ->join('purchase_order_transactions as pot', fn ($join) => $join->on('pot.purchase_order_id', 'po.id')->whereNull('pot.deleted_at'))
            ->join('organisations as o', 'o.id', 'po.organisation_id')
            ->leftJoin('users as buyer', 'buyer.id', 'po.buyer_id')
            ->whereIn('pot.supplier_product_id', $rows->pluck('supplier_product_id'))
            ->groupBy('pot.supplier_product_id', 'po.id', 'po.reference', 'o.id', 'o.code', 'o.name', 'buyer.id', 'buyer.contact_name')
            ->orderBy('o.code')
            ->selectRaw('pot.supplier_product_id, o.code, o.name, po.reference, buyer.contact_name as buyer_name, sum(pot.quantity_ordered) as units')
            ->get()
            ->groupBy('supplier_product_id');

        $orgStockIds      = $rows->pluck('org_stock_id')->filter()->unique()->values();
        $orgStocks        = OrgStock::with('stats', 'stock.stockFamily')->whereIn('id', $orgStockIds)->get()->keyBy('id');
        $supplierProducts = SupplierProduct::whereIn('id', $rows->pluck('supplier_product_id')->unique())->get()->keyBy('id');
        $quarterlyUsage   = $orgStockIds->isEmpty() ? collect() : GetOrgStocksQuarterlyUsage::run($orgStockIds);
        $stockDeliveries  = $orgStockIds->isEmpty() ? collect() : GetOrgStocksStockDeliveries::run($orgStockIds);
        $discontinueSuggestions = OrgStockDiscontinueSuggestion::tallyForOrgStocks($orgStockIds);

        IndexPurchaseOrderOrgSupplierProducts::make()->attachOtherOpenPurchaseOrders($paginator, $this->orgAgent->organisation_id);

        $organisationCurrency = $this->orgAgent->organisation->currency;
        $exchanges            = $rows->pluck('currency_id')->filter()->unique()->mapWithKeys(fn (int $currencyId) => [
            $currencyId => GetCurrencyExchange::run(Currency::find($currencyId), $organisationCurrency),
        ]);

        $paginator->getCollection()->transform(function (OrgSupplierProduct $row) use ($draftQuantities, $otherOrganisationsDrafts, $webImages, $orgStocks, $supplierProducts, $quarterlyUsage, $stockDeliveries, $discontinueSuggestions, $exchanges) {
            $images   = json_decode($webImages->get($row->org_stock_id) ?? 'null', true);
            $orgStock = $orgStocks->get($row->org_stock_id);

            return [
                'id'                   => $row->id,
                'slug'                 => $row->slug,
                'code'                 => $row->code,
                'name'                 => $row->name,
                'image'                => Arr::get($images, 'main.gallery') ?? Arr::get($images, 'main.thumbnail'),
                'bucket'               => $row->bucket,
                'supplier_code'        => $row->supplier_code,
                'supplier_slug'        => $row->supplier_slug,
                'lead_time_days'       => (int) $row->lead_time_days,
                'lead_time_measured'   => (bool) $row->lead_time_measured,
                'our_stock'            => $row->quantity_available !== null ? (float) $row->quantity_available : null,
                'our_days_of_cover'    => $row->days_of_cover !== null ? (int) $row->days_of_cover : null,
                'recommended_quantity' => $row->recommended_order_quantity !== null ? (int) ceil((float) $row->recommended_order_quantity) : null,
                'units_per_carton'     => $row->units_per_carton ? (int) $row->units_per_carton : null,
                'minimum_carton_order' => $row->minimum_carton_order ? (int) $row->minimum_carton_order : null,
                'cost'                 => $row->cost !== null ? (float) $row->cost : null,
                'currency'             => $row->currency,
                'org_exchange'         => $exchanges->get($row->currency_id),
                'ordered_quantity'     => (float) ($draftQuantities[$row->id] ?? 0),
                'quantity_ordered'     => (float) ($draftQuantities[$row->id] ?? 0),
                'units_per_pack'       => $row->units_per_pack ? (float) $row->units_per_pack : 1,
                'has_org_stock'        => $row->org_stock_id !== null,
                'org_stock_id'         => $row->org_stock_id,
                'org_stock_code'       => $orgStock?->code,
                'discontinue_suggestions' => $discontinueSuggestions->get($row->org_stock_id) ?? ['times' => 0, 'people' => 0],
                'stock_in_locations'   => $orgStock?->quantity_in_locations === null ? null : trimDecimalZeros($orgStock->quantity_in_locations),
                'quarterly_usage'      => $quarterlyUsage->get($row->org_stock_id) ?? [],
                'stock_cover'          => GetOrgStockBuyingSignals::run($orgStock, $supplierProducts->get($row->supplier_product_id), fallbackLeadTimeDays: $this->agentLeadDays),
                'stock_deliveries'     => $stockDeliveries->get($row->org_stock_id),
                'other_open_purchase_orders' => $row->other_open_purchase_orders ?? [],
                'other_organisations_drafts' => ($otherOrganisationsDrafts->get($row->supplier_product_id) ?? collect())->map(fn ($draft) => [
                    'organisation_code' => $draft->code,
                    'organisation_name' => $draft->name,
                    'reference'         => $draft->reference,
                    'buyer_name'        => $draft->buyer_name,
                    'units'             => (float) $draft->units,
                ])->values()->all(),
            ];
        });

        return $paginator;
    }

    public function tableStructure(?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page')->withGlobalSearch();
            }

            $table
                ->withLabelRecord([__('Product'), __('Products')])
                ->withEmptyState(['title' => __('No products found')])
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true)
                ->column(key: 'image', label: __('Image'), canBeHidden: false)
                ->column(key: 'name', label: __('Product'), canBeHidden: false, sortable: true);

            if (($this->bucket ?? null) === null) {
                $table->column(key: 'bucket', label: __('Stock group'), canBeHidden: false);
            }

            $table
                ->column(key: 'supplier_code', label: __('Supplier'), canBeHidden: false, sortable: true)
                ->column(key: 'subtotals', label: __('Subtotals'), canBeHidden: false)
                ->column(key: 'cartons', label: __('Cartons'), canBeHidden: false, align: 'right');
        };
    }

    private function draftLines(): Builder
    {
        return DB::table('purchase_order_transactions as pot')
            ->join('purchase_orders as po', 'po.id', 'pot.purchase_order_id')
            ->where('po.organisation_id', $this->orgAgent->organisation_id)
            ->where('po.agent_id', $this->orgAgent->agent_id)
            ->where('po.parent_type', 'OrgSupplier')
            ->where('po.state', PurchaseOrderStateEnum::IN_PROCESS->value)
            ->whereNull('po.deleted_at')
            ->whereNull('pot.deleted_at');
    }

    /**
     * @param  array{list: array{value: float, lines: int, units: float}, currency: string}  $orderCapacity
     * @return array{count: int, total: float, currency: string, listRoute: array{name: string, parameters: array<int, string>}}
     */
    private function miniCart(array $orderCapacity): array
    {
        return [
            'count'     => $orderCapacity['list']['lines'],
            'total'     => $orderCapacity['list']['value'],
            'currency'  => $orderCapacity['currency'],
            'listRoute' => [
                'name'       => 'grp.org.procurement.org_agents.show.shopping.drafts.index',
                'parameters' => [$this->orgAgent->organisation->slug, $this->orgAgent->slug],
            ],
        ];
    }

    private function bucketLabel(): string
    {
        return $this->bucket === null ? __('Ordering') : GetAgentStockCoverBuckets::make()->bucketLabel($this->bucket);
    }

    /**
     * A purchase order lists only what can still be bought: not a product whose SKO is being
     * discontinued or one the supplier no longer has, as on a supplier purchase order. One already
     * on a draft stays, so it can be taken off.
     */
    private function stillOrderableSql(): string
    {
        return "((sp.is_available and (os.state is null or os.state not in ('".OrgStockStateEnum::DISCONTINUING->value."', '".OrgStockStateEnum::DISCONTINUED->value."')))
            or ".$this->onDraftSql().')';
    }

    private function onDraftSql(): string
    {
        return "exists (select 1 from purchase_order_transactions pot
            join purchase_orders po on po.id = pot.purchase_order_id and po.deleted_at is null
            where pot.org_supplier_product_id = osp.id and pot.deleted_at is null
                and po.state = '".PurchaseOrderStateEnum::IN_PROCESS->value."')";
    }

    /**
     * A product the organisation already has on a submitted or confirmed purchase order, or on a
     * stock delivery that is not booked in yet. It is wider than the rows shown under the stepper,
     * which drop a purchase order line once its delivery exists and a delivery once it is all placed.
     */
    public static function onOrderSql(string $product = 'osp', string $orgStockId = 'os.id'): string
    {
        $openOrders       = "'".PurchaseOrderStateEnum::SUBMITTED->value."', '".PurchaseOrderStateEnum::CONFIRMED->value."'";
        $finishedDelivery = "'".implode("', '", [
            StockDeliveryStateEnum::BOOKED_IN->value,
            StockDeliveryStateEnum::PLACED->value,
            StockDeliveryStateEnum::CANCELLED->value,
            StockDeliveryStateEnum::NOT_RECEIVED->value,
        ])."'";

        return "(exists (select 1 from purchase_order_transactions pot
                join purchase_orders po on po.id = pot.purchase_order_id and po.deleted_at is null
                where pot.supplier_product_id = $product.supplier_product_id and pot.deleted_at is null
                    and po.organisation_id = $product.organisation_id and po.state in ($openOrders))
            or exists (select 1 from purchase_order_transactions pot
                join purchase_orders po on po.id = pot.purchase_order_id and po.deleted_at is null
                where pot.org_stock_id = $orgStockId and pot.deleted_at is null
                    and po.organisation_id = $product.organisation_id and po.state in ($openOrders))
            or exists (select 1 from stock_delivery_items sdi
                join stock_deliveries sd on sd.id = sdi.stock_delivery_id and sd.deleted_at is null
                where sdi.org_stock_id = $orgStockId and sdi.deleted_at is null and sd.state not in ($finishedDelivery)))";
    }

    /**
     * What each filter offers, with how many products sit behind every choice. The stock group and
     * rank counts follow the supplier, the search and the drafts tick, so they say what picking one
     * would show.
     *
     * @return array{cover: array<int, array{value: string, label: string, count: int}>, rank: array<int, array{value: string, label: string, count: int}>, supplier: array<int, array{value: int, label: string}>}
     */
    private function filterOptions(?string $search): array
    {
        $buckets = GetAgentStockCoverBuckets::make();

        $search = trim((string) $search);
        $like   = '%'.addcslashes($search, '%_\\').'%';

        $counts = $buckets->scopedQuery($this->orgAgent, $this->supplierId)
            ->when($this->supplierId, fn ($query) => $query->where('sp.supplier_id', $this->supplierId))
            ->when($this->onDraftsOnly, fn ($query) => $query->whereRaw($this->onDraftSql()))
            ->when($this->hideOnOrder, fn ($query) => $query->whereRaw('not '.$this->onOrderSql()))
            ->when($this->hideDiscontinued, fn ($query) => $query->whereRaw($this->stillOrderableSql()))
            ->when($search !== '', fn ($query) => $query->whereRaw('(sp.code collate "C" ilike ? or sp.name ilike ?)', [$like, $like]))
            ->groupByRaw($buckets->bucketSql($this->orgAgent).', os.health_rank')
            ->selectRaw($buckets->bucketSql($this->orgAgent).' as bucket, os.health_rank, count(*) as products')
            ->get();

        $inGroup = $this->bucket === null ? $counts : $counts->where('bucket', $this->bucket);

        return [
            'cover'    => collect(GetAgentStockCoverBuckets::BUCKETS)->map(fn (array $meta, string $bucket) => [
                'value' => $bucket,
                'label' => explode(':', __($meta['label']))[0],
                'count' => (int) $counts->where('bucket', $bucket)->sum('products'),
            ])->values()->all(),
            'rank'     => collect(['A', 'B', 'C', 'D', 'Z'])->map(fn (string $rank) => [
                'value' => $rank,
                'label' => $rank,
                'count' => (int) $inGroup->where('health_rank', $rank)->sum('products'),
            ])->all(),
            'supplier' => $buckets->scopedQuery($this->orgAgent)
                ->join('suppliers as sup', 'sup.id', 'sp.supplier_id')
                ->groupBy('sup.id', 'sup.code', 'sup.name')
                ->orderByRaw('sup.code collate "C"')
                ->selectRaw('sup.id, sup.code, sup.name, count(*) as products')
                ->get()
                ->map(fn ($supplier) => ['value' => (int) $supplier->id, 'label' => $supplier->code.' · '.$supplier->name.' ('.$supplier->products.')'])
                ->all(),
        ];
    }

    public function asController(Organisation $organisation, OrgAgent $orgAgent, ActionRequest $request): LengthAwarePaginator
    {
        $this->orgAgent   = $orgAgent;
        $isBrowse         = $request->routeIs('*.shopping.browse.index');
        $cover            = (string) $request->input('cover', $isBrowse ? '' : 'out');
        $this->bucket     = array_key_exists($cover, GetAgentStockCoverBuckets::BUCKETS) ? $cover : ($isBrowse ? null : 'out');
        $rank             = $request->input('rank');
        $this->rank       = in_array($rank, ['A', 'B', 'C', 'D', 'Z'], true) ? $rank : null;
        $this->supplierId   = ((int) ($request->input('filter.supplier') ?: $request->input('supplier'))) ?: null;
        $this->onDraftsOnly = $request->boolean('drafts');
        $this->hideOnOrder  = $request->boolean('hide_ordered');

        $this->initialisation($organisation, $request);

        return $this->handle($orgAgent, $this->bucket, $this->rank, $this->supplierId);
    }

    public function htmlResponse(LengthAwarePaginator $items, ActionRequest $request): Response
    {
        $orderCapacity = GetAgentOrderCapacity::run($this->orgAgent);

        return Inertia::render(
            'Procurement/AgentCoverBucketItems',
            [
                'breadcrumbs' => $this->getBreadcrumbs($this->orgAgent, $request->route()->originalParameters()),
                'title'       => $this->bucketLabel(),
                'pageHead'    => [
                    'icon'          => [
                        'icon'  => ['fal', 'fa-shopping-basket'],
                        'title' => $this->bucketLabel(),
                    ],
                    'model'         => $this->orgAgent->agent->name,
                    'title'         => $this->bucketLabel().($this->rank ? ' · '.$this->rank : ''),
                    'subNavigation' => $this->getAgentShoppingNavigation($this->orgAgent),
                ],
                'orgAgent'    => [
                    'id'       => $this->orgAgent->id,
                    'slug'     => $this->orgAgent->slug,
                    'currency' => $orderCapacity['currency'],
                ],
                'addRoute'    => [
                    'name'       => 'grp.org.procurement.org_agents.show.shopping.drafts.line',
                    'parameters' => [$this->orgAgent->organisation->slug, $this->orgAgent->slug],
                ],
                'bucket'      => $this->bucket,
                'bucketLabel' => $this->bucketLabel(),
                'bucketLabels' => collect(GetAgentStockCoverBuckets::BUCKETS)->map(fn (array $meta) => ['label' => __($meta['label']), 'tone' => $meta['tone']])->all(),
                'filterOptions' => fn () => $this->filterOptions($request->input('filter.global')),
                'active'      => [
                    'cover'    => $this->bucket,
                    'rank'     => $this->rank,
                    'supplier' => $this->supplierId,
                    'drafts'   => $this->onDraftsOnly,
                    'hide_ordered' => $this->hideOnOrder,
                    'search'   => (string) $request->input('filter.global', ''),
                ],
                'rank'        => $this->rank,
                'items'       => JsonResource::collection($items),
                'miniCart'    => $this->miniCart($orderCapacity),
            ]
        )->table($this->tableStructure());
    }

    public function getBreadcrumbs(OrgAgent $orgAgent, array $routeParameters): array
    {
        return array_merge(
            ShowAgentShoppingDashboard::make()->getBreadcrumbs($orgAgent, $routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => $this->bucket === null ? 'grp.org.procurement.org_agents.show.shopping.browse.index' : 'grp.org.procurement.org_agents.show.shopping.items.index',
                            'parameters' => $routeParameters,
                        ],
                        'label' => $this->bucketLabel(),
                        'icon'  => 'fal fa-shopping-basket',
                    ],
                ],
            ]
        );
    }
}
