<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 29 Dec 2024 03:14:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\UI;

use App\Actions\Inventory\OrgStock\DiscontinueOrgStocks;
use App\Actions\OrgAction;
use App\Actions\Procurement\GetOrganisationStockCoverBuckets;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Actions\UI\WithInertia;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemReplacementReasonEnum;
use App\Enums\Ordering\Transaction\UpcomingTransactionTypeEnum;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\SupplyChain\SupplierSubmission\SupplierSubmissionStateEnum;
use App\Enums\SupplyChain\SupplierSubmission\SupplierSubmissionTypeEnum;
use App\Enums\SysAdmin\Authorisation\GroupPermissionsEnum;
use App\Models\Goods\Stock;
use App\Models\Inventory\OrgStockDiscontinueSuggestion;
use App\Models\Helpers\Currency;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use App\InertiaTable\InertiaTable;
use Closure;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * One row per group stock with its stock, condition and state in every organisation that carries it,
 * the group sales of a selectable period and the KPIs of whatever the filters leave in view.
 *
 * The whole catalogue takes a few seconds to compute, so it is built once, cached for a few minutes
 * and filtered, sorted and paged in memory; KPIs and rows then always come from the same figures.
 * DiscontinueOrgStocks forgets the cache so a status change shows on the next load. Editing rights
 * depend on the requesting user and are never cached.
 */
class ShowGoodsDashboard extends OrgAction
{
    use AsAction;
    use WithInertia;

    private const int PER_PAGE = 50;

    private const int CACHE_MINUTES = 10;

    public const array DELIVERY_DAYS_TO_ARRIVE = [
        StockDeliveryStateEnum::CONFIRMED->value     => 21,
        StockDeliveryStateEnum::READY_TO_SHIP->value => 14,
        StockDeliveryStateEnum::DISPATCHED->value    => 7,
        StockDeliveryStateEnum::RECEIVED->value      => 3,
        StockDeliveryStateEnum::CHECKED->value       => 2,
        StockDeliveryStateEnum::BOOKING_IN->value    => 1,
    ];

    public const int DEFAULT_LEAD_TIME_DAYS = 14;

    public const int DISPATCHED_DAYS_AT_SEA = 60;

    public const array IN_WAREHOUSE_DELIVERY_STATES = [
        StockDeliveryStateEnum::RECEIVED->value,
        StockDeliveryStateEnum::CHECKED->value,
        StockDeliveryStateEnum::BOOKING_IN->value,
    ];

    private const int ID_CHUNK_SIZE = 10000;

    public const array PERIODS = ['30d', '90d', 'quarter', 'year'];

    public const string DEFAULT_PERIOD = '90d';

    public const array CONDITIONS = ['oos', 'oos_no_po', 'ns', 'low', 'low_no_po', 'over', 'dead', 'off', 'sell', 'hold', 'ret', 'ok', 'suggested', 'price_request', 'supplier_price_change'];

    public const array SORTS = ['code', 'sales', 'trend', 'cover', 'suggestions', 'complaints', 'feedback'];

    public const array NOT_ORDERED_YEARS = [1, 2, 3, 4, 5];

    public const int COMPLAINT_MONTHS = 12;

    /**
     * Bad feedback is judged against how often the product is ordered: the lowest share, in percent,
     * of the orders that carried it in the last year that ended with a customer compensated. A best
     * seller with 39 compensated customers in 2,925 orders is mild; 5 in 30 is not.
     *
     * ponytail: thresholds are a first guess, tune them once supply chain has used the list
     */
    public const array FEEDBACK_LEVELS = ['mild' => 0.0, 'bad' => 2.0, 'severe' => 5.0, 'disaster' => 10.0];

    /**
     * Below this many customers a rate is noise (one refund on the only order is 100%), so the
     * level stays mild whatever the rate.
     */
    public const int FEEDBACK_MIN_CUSTOMERS = 3;


    /**
     * Replacements sent for these reasons say nothing about the product itself.
     */
    private const array REPLACEMENT_REASONS_NOT_THE_PRODUCT = [
        DeliveryNoteItemReplacementReasonEnum::DAMAGED_IN_TRANSIT->value,
        DeliveryNoteItemReplacementReasonEnum::LOST_IN_TRANSIT->value,
        DeliveryNoteItemReplacementReasonEnum::WRONG_ITEM_SENT->value,
        DeliveryNoteItemReplacementReasonEnum::MISSING_FROM_PARCEL->value,
        DeliveryNoteItemReplacementReasonEnum::CUSTOMER_ERROR->value,
    ];

    public const array DEFERRED_PROPS = ['built_at', 'organisations', 'families', 'supplier_countries', 'missing_rates', 'rates', 'kpis', 'rows', 'pagination', 'editable_organisations', 'can_change_group'];

    public static function cacheKey(int $groupId): string
    {
        return 'product-command-control:v3:'.$groupId;
    }

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->authTo([GroupPermissionsEnum::SUPPLY_CHAIN->value, GroupPermissionsEnum::SUPPLY_CHAIN_EDIT->value]);

        return $request->user()->authTo('goods.view');
    }

    public function rules(): array
    {
        return [
            'organisation' => ['sometimes', 'nullable', 'string'],
            'family'       => ['sometimes', 'nullable', 'string'],
            'condition'    => ['sometimes', 'nullable', Rule::in(self::CONDITIONS)],
            'state'        => ['sometimes', 'nullable', Rule::in(OrgStockStateEnum::values())],
            'feedback'     => ['sometimes', 'nullable', Rule::in(array_keys(self::FEEDBACK_LEVELS))],
            'supplier_country'  => ['sometimes', 'nullable', 'string', 'size:2'],
            'no_stock'          => ['sometimes', 'nullable', 'boolean'],
            'not_ordered_years' => ['sometimes', 'nullable', 'integer', Rule::in(self::NOT_ORDERED_YEARS)],
            'search'       => ['sometimes', 'nullable', 'string', 'max:255'],
            'sort'         => ['sometimes', 'nullable', Rule::in(array_merge(self::SORTS, array_map(fn ($sort) => '-'.$sort, self::SORTS)))],
            'period'       => ['sometimes', 'nullable', Rule::in(self::PERIODS)],
            'page'         => ['sometimes', 'nullable', 'integer', 'min:1'],
            'perPage'      => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }

    public function asController(ActionRequest $request): array
    {
        $this->initialisationFromGroup(app('group'), $request);

        return $this->validatedData;
    }

    public function forGroup(Group $group): static
    {
        $this->group = $group;

        return $this;
    }

    public function handle(array $filters): array
    {
        ['dataset' => $dataset, 'period' => $period, 'inView' => $inView, 'filtered' => $rows] = $this->rowsForFilters($filters);

        $organisation = Arr::get($filters, 'organisation');
        $total        = count($rows);
        $perPage      = min(max((int) (Arr::get($filters, 'perPage') ?: self::PER_PAGE), config('ui.table.min_records_per_page')), config('ui.table.max_records_per_page'));
        $lastPage     = max(1, (int) ceil($total / $perPage));
        $page         = min(max(1, (int) Arr::get($filters, 'page', 1)), $lastPage);
        $pageRows     = array_slice($rows, ($page - 1) * $perPage, $perPage);

        [$editableOrganisations, $canChangeGroup] = $this->editingRights();

        $organisationsMeta = collect($dataset['organisations']);
        foreach ($pageRows as &$row) {
            $row['action'] = $this->rowAction($row, $organisationsMeta, $editableOrganisations, $canChangeGroup);
        }
        unset($row);

        $evidence = OrgStockDiscontinueSuggestion::evidenceForStocks(array_column(array_filter($pageRows, fn (array $row) => $row['suggestions'] > 0), 'id'));
        $images   = $this->imagesByStock(array_column($pageRows, 'id'));
        foreach ($pageRows as &$row) {
            $row['suggestion_evidence'] = $evidence[$row['id']] ?? null;
            $row['image_thumbnail']     = $images[$row['id']]['thumbnail'] ?? null;
            $row['image_full']          = $images[$row['id']]['full'] ?? null;
        }
        unset($row);

        return [
            'built_at'      => $dataset['built_at'],
            'organisations' => $dataset['organisations'],
            'families'      => $dataset['families'],
            'supplier_countries' => $dataset['supplier_countries'],
            'missing_rates' => $dataset['missing_rates'],
            'rates'         => $dataset['rates'],
            'period'        => $period,
            'periods'       => self::PERIODS,
            'kpis'          => $this->kpis($inView, $organisation),
            'rows'          => $pageRows,
            'pagination'    => [
                'page'      => $page,
                'last_page' => $lastPage,
                'total'     => $total,
                'per_page'  => $perPage,
            ],
            'filters'       => $this->normalisedFilters($filters),
            'editable_organisations' => $editableOrganisations,
            'can_change_group'       => $canChangeGroup,
        ];
    }

    /**
     * @return array{organisation: ?string, family: ?string, condition: ?string, state: ?string, feedback: ?string, supplier_country: ?string, no_stock: bool, not_ordered_years: ?int, search: ?string, sort: string, period: string}
     */
    private function normalisedFilters(array $filters): array
    {
        $notOrderedYears = Arr::get($filters, 'not_ordered_years');

        return [
            'organisation' => Arr::get($filters, 'organisation'),
            'family'       => Arr::get($filters, 'family'),
            'condition'    => Arr::get($filters, 'condition'),
            'state'        => Arr::get($filters, 'state'),
            'feedback'     => Arr::get($filters, 'feedback'),
            'supplier_country'  => Arr::get($filters, 'supplier_country') ? strtoupper(Arr::get($filters, 'supplier_country')) : null,
            'no_stock'          => (bool) Arr::get($filters, 'no_stock'),
            'not_ordered_years' => $notOrderedYears ? (int) $notOrderedYears : null,
            'search'       => Arr::get($filters, 'search'),
            'sort'         => Arr::get($filters, 'sort') ?: '-sales',
            'period'       => Arr::get($filters, 'period') ?: self::DEFAULT_PERIOD,
        ];
    }

    /**
     * All rows matching the filters and period, unpaginated and with no per-row action, for the
     * export action to stream. The action must set $this->group itself via initialisationFromGroup.
     *
     * @return array{organisations: array, rows: array}
     */
    public function exportData(array $filters): array
    {
        $result = $this->rowsForFilters($filters);

        return ['organisations' => $result['dataset']['organisations'], 'rows' => $result['filtered']];
    }

    /**
     * @param  array<int, int>  $stockIds
     * @return array<int, array{thumbnail: array<string, mixed>|null, full: array<string, string>|null}>
     */
    private function imagesByStock(array $stockIds): array
    {
        return Stock::query()
            ->with(['image', 'tradeUnits' => fn ($query) => $query->whereNotNull('trade_units.image_id')->with('image')])
            ->whereIn('id', $stockIds)
            ->get(['id', 'image_id'])
            ->mapWithKeys(function (Stock $stock) {
                $imageOwner = $stock->image_id ? $stock : $stock->tradeUnits->first();

                return [$stock->id => [
                    'thumbnail' => $imageOwner?->imageSources(120, 120),
                    'full'      => $imageOwner?->image?->fullResolutionUrls(),
                ]];
            })
            ->all();
    }

    /**
     * @return array{dataset: array, period: string, inView: array, filtered: array}
     */
    private function rowsForFilters(array $filters): array
    {
        $dataset = $this->dataset();
        $period  = Arr::get($filters, 'period') ?: self::DEFAULT_PERIOD;
        $rows    = $this->flattenPeriod($dataset['rows'], $period);

        $suggestions   = OrgStockDiscontinueSuggestion::tallyByStock($this->group->id);
        $priceRequests = ShowGoodsStockPriceRequests::tallyByStock($this->group->id);
        $supplierPriceChanges = $this->pendingSupplierPriceChangesByStock();
        foreach ($rows as &$row) {
            $row['price_requests']    = $priceRequests[$row['id']] ?? 0;
            $row['supplier_price_changes'] = $supplierPriceChanges[$row['id']] ?? 0;
            $row['suggestions']       = $suggestions[$row['id']]['times'] ?? 0;
            $row['suggestion_people'] = $suggestions[$row['id']]['people'] ?? 0;
            $row['complaint_rate']    = $row['orders'] > 0 ? round($row['complaint_customers'] / $row['orders'] * 100, 1) : null;
            $row['feedback_level']    = $this->feedbackLevel($row);
            $row['feedback']          = $row['feedback_level'] === null ? 0.0 : array_search($row['feedback_level'], array_keys(self::FEEDBACK_LEVELS)) * 1000 + min(999.0, (float) $row['complaint_rate']);
        }
        unset($row);

        $inView = array_values(array_filter($rows, fn (array $row) => $this->matches($row, $filters)));

        $condition    = Arr::get($filters, 'condition');
        $organisation = Arr::get($filters, 'organisation');
        $filtered     = $condition ? array_values(array_filter($inView, fn (array $row) => $this->hasCondition($row, $condition, $organisation))) : $inView;

        $feedback = Arr::get($filters, 'feedback');
        if ($feedback) {
            $filtered = array_values(array_filter($filtered, fn (array $row) => $row['feedback_level'] === $feedback));
        }

        $this->sort($filtered, Arr::get($filters, 'sort') ?: '-sales');

        return compact('dataset', 'period', 'inView', 'filtered');
    }

    /**
     * The decoded, cached catalogue this page is built from: one row per group stock with its
     * conditions, sales and organisation cells. ShowGoodsAnalysis reuses this instead of duplicating
     * the query so exceptions, urgent actions and promotion candidates always agree with this page.
     *
     * ponytail: the whole catalogue lives in one gzipped cache entry; move to a table if it grows tenfold
     */
    public function dataset(): array
    {
        return json_decode(gzuncompress(Cache::remember(
            self::cacheKey($this->group->id),
            now()->addMinutes(self::CACHE_MINUTES),
            fn () => gzcompress(json_encode($this->buildDataset()))
        )), true);
    }

    private function flattenPeriod(array $rows, string $period): array
    {
        return array_map(function (array $row) use ($period) {
            $row['sales'] = $row['sales_by_period'][$period]['amount'];
            $row['trend'] = $row['sales_by_period'][$period]['trend'];
            unset($row['sales_by_period']);

            foreach ($row['organisations'] as &$cell) {
                $cell['sales'] = $cell['sales_by_period'][$period]['amount'] ?? 0.0;
                $cell['trend'] = $cell['sales_by_period'][$period]['trend'] ?? null;
                unset($cell['sales_by_period']);
            }
            unset($cell);

            return $row;
        }, $rows);
    }

    /**
     * @return array{0: array<int, string>, 1: bool}
     */
    private function editingRights(): array
    {
        $user = Auth::user();

        if (!$user) {
            return [[], false];
        }

        $canChangeGroup = DiscontinueOrgStocks::canChangeGroupStatus($user);

        $editable = Organisation::where('group_id', $this->group->id)
            ->get(['id', 'code'])
            ->filter(fn (Organisation $organisation) => DiscontinueOrgStocks::canChangeStatus($user, $organisation))
            ->pluck('code')
            ->values()
            ->all();

        return [$editable, $canChangeGroup];
    }

    private function rowAction(array $row, Collection $organisationsMeta, array $editableOrganisations, bool $canChangeGroup): ?array
    {
        foreach ($organisationsMeta as $organisation) {
            $cell = $row['organisations'][$organisation['code']] ?? null;
            if (!$cell) {
                continue;
            }
            if ($canChangeGroup || in_array($organisation['code'], $editableOrganisations, true)) {
                return [
                    'org_stock_id' => $cell['org_stock_id'],
                    'organisation' => $organisation['slug'],
                    'warehouse'    => $organisation['warehouse_slug'],
                ];
            }
        }

        return null;
    }

    private function buildDataset(): array
    {
        $organisations = $this->organisationsWithStock();

        $rates = $this->currencyRates($organisations, $this->group->currency);

        $cells       = $this->orgStockCells($organisations->keys()->all());
        $inbound     = $this->inboundByOrgStock($cells->pluck('id')->all());
        $lastOrdered = $this->lastOrderedAtByOrgStock();

        $cellsByStock = [];
        foreach ($cells as $cell) {
            $cell->inbound_quantity = $inbound[$cell->id]['quantity'] ?? 0.0;
            $cell->next_expected_at = $inbound[$cell->id]['eta'] ?? null;
            $cell->last_ordered_at  = $lastOrdered[$cell->id] ?? null;
            $cellsByStock[$cell->stock_id][] = $cell;
        }

        $organisationsSorted = $organisations->sortBy('id')->values();
        $complaints          = $this->complaintsByStock();
        $supplierCountries   = $this->supplierCountriesByStock();

        $rows = collect($cellsByStock)
            ->map(fn (array $stockCells, int $stockId) => array_merge(
                $this->row($stockCells, $organisationsSorted, $rates),
                $complaints[$stockId] ?? self::NO_COMPLAINTS,
                ['supplier_countries' => $supplierCountries[$stockId] ?? []]
            ))
            ->values();

        return [
            'built_at'      => now()->toIso8601String(),
            'organisations' => $organisations->map(fn (Organisation $organisation) => [
                'code'           => $organisation->code,
                'name'           => $organisation->name,
                'slug'           => $organisation->slug,
                'warehouse_slug' => $organisation->warehouses->first()?->slug,
            ])->values()->all(),
            'families'      => $rows->pluck('family_code')->filter()->unique()->sort()->values()->all(),
            'supplier_countries' => $this->supplierCountryOptions($rows),
            'missing_rates' => $organisations->filter(fn (Organisation $organisation) => ($rates->get($organisation->id)['rate'] ?? null) === null)
                ->pluck('code')->values()->all(),
            'rates'         => $organisations->map(fn (Organisation $organisation) => array_merge(
                ['organisation' => $organisation->code, 'currency' => $organisation->currency->code],
                $rates->get($organisation->id)
            ))->values()->all(),
            'rows'          => $rows->all(),
        ];
    }

    /**
     * The last time each org stock was ordered from a supplier: the latest purchase order or stock
     * delivery that carried it, cancelled ones left out. Old deliveries brought over without their
     * purchase order still count.
     *
     * @return array<int, string> date by org stock id
     */
    private function lastOrderedAtByOrgStock(): array
    {
        $purchaseOrderLines = DB::table('purchase_order_transactions')
            ->join('purchase_orders', 'purchase_orders.id', 'purchase_order_transactions.purchase_order_id')
            ->where('purchase_order_transactions.group_id', $this->group->id)
            ->whereNull('purchase_order_transactions.deleted_at')
            ->whereNull('purchase_orders.deleted_at')
            ->where('purchase_orders.state', '!=', PurchaseOrderStateEnum::CANCELLED->value)
            ->selectRaw('purchase_order_transactions.org_stock_id, coalesce(purchase_orders.submitted_at, purchase_orders.date) as ordered_at');

        $deliveryLines = DB::table('stock_delivery_items')
            ->join('stock_deliveries', 'stock_deliveries.id', 'stock_delivery_items.stock_delivery_id')
            ->where('stock_delivery_items.group_id', $this->group->id)
            ->whereNull('stock_delivery_items.deleted_at')
            ->whereNull('stock_deliveries.deleted_at')
            ->where('stock_deliveries.state', '!=', StockDeliveryStateEnum::CANCELLED->value)
            ->selectRaw('stock_delivery_items.org_stock_id, stock_deliveries.date as ordered_at');

        return DB::query()
            ->fromSub($purchaseOrderLines->unionAll($deliveryLines), 'ordered')
            ->whereNotNull('org_stock_id')
            ->groupBy('org_stock_id')
            ->selectRaw('org_stock_id, max(ordered_at) as last_ordered_at')
            ->pluck('last_ordered_at', 'org_stock_id')
            ->map(fn ($date) => $date ? Carbon::parse($date)->toDateString() : null)
            ->filter()
            ->all();
    }

    /**
     * The countries of the suppliers each stock can be bought from, from the supplier's address.
     *
     * @return array<int, array<int, string>> country codes by stock id
     */
    private function supplierCountriesByStock(): array
    {
        $countries = [];

        DB::table('stock_has_supplier_products')
            ->join('supplier_products', 'supplier_products.id', 'stock_has_supplier_products.supplier_product_id')
            ->join('suppliers', 'suppliers.id', 'supplier_products.supplier_id')
            ->join('addresses', 'addresses.id', 'suppliers.address_id')
            ->where('supplier_products.group_id', $this->group->id)
            ->whereNull('supplier_products.deleted_at')
            ->whereNull('suppliers.deleted_at')
            ->whereNotNull('addresses.country_code')
            ->distinct()
            ->select(['stock_has_supplier_products.stock_id', 'addresses.country_code'])
            ->get()
            ->each(function ($row) use (&$countries) {
                $countries[$row->stock_id][] = $row->country_code;
            });

        return $countries;
    }

    /**
     * @return array<int, array{code: string, name: string, count: int}>
     */
    private function supplierCountryOptions(Collection $rows): array
    {
        $counts = array_count_values($rows->reject(fn (array $row) => $row['all_retired'])->pluck('supplier_countries')->flatten()->all());
        $names  = DB::table('countries')->whereIn('code', array_keys($counts))->pluck('name', 'code');

        return collect($counts)
            ->map(fn (int $count, string $code) => ['code' => $code, 'name' => $names[$code] ?? $code, 'count' => $count])
            ->sortBy('name')
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Organisation>
     */
    private function organisationsWithStock(): Collection
    {
        return Organisation::where('group_id', $this->group->id)
            ->whereHas('orgStocks', fn ($query) => $query->whereIn('state', [
                OrgStockStateEnum::ACTIVE->value,
                OrgStockStateEnum::DISCONTINUING->value,
                OrgStockStateEnum::SUSPENDED->value,
                OrgStockStateEnum::DISCONTINUED->value,
            ]))
            ->with(['currency', 'warehouses'])
            ->orderBy('id')
            ->get()
            ->keyBy('id');
    }

    public function tableStructure(): Closure
    {
        return function (InertiaTable $table) {
            $table
                ->withEmptyState(['title' => __('No products match these filters.')])
                ->column(key: 'image_thumbnail', label: '', canBeHidden: false)
                ->column(key: 'code', label: __('Code — description'), canBeHidden: false, sortable: true)
                ->column(key: 'sales', label: __('Sales'), canBeHidden: false, sortable: true, align: 'right');

            $table
                ->column(key: 'stock', label: __('Group stock'), tooltip: __('Available in all organisations, what is on the way in green, and one dot per organisation coloured by its stock condition. Hover a row for each organisation'), canBeHidden: false, align: 'right')
                ->column(
                    key: 'feedback',
                    label: __('Bad feedback'),
                    tooltip: __('First, counted on its own over the last 12 months: level, share of orders that ended with the customer compensated, customers compensated / orders that carried the product. Then what staff reported by hand. Counted: refunds of goods sent, replacements (not when the courier, the warehouse or the customer was at fault), items added free to a next order and claims to the supplier at goods in'),
                    sortable: true
                )
                ->column(key: 'state', label: __('Status'), tooltip: __('Group product status'), canBeHidden: false)
                ->defaultSort('-sales');
        };
    }

    private const array NO_COMPLAINTS = ['complaints' => 0, 'complaint_customers' => 0, 'refunds' => 0, 'replacements' => 0, 'next_order' => 0, 'supplier_claims' => 0, 'orders' => 0];

    /**
     * What customers and deliveries already said about each stock in the last year, with nobody
     * having to report it: refunds of goods that were dispatched, replacements sent (unless the
     * courier, the warehouse or the customer was at fault), claimed items added free to a next
     * order, and claims raised against the supplier at goods in, beside the number of orders that
     * carried the stock in the same year. A product made of several stocks counts against each of them.
     *
     * @return array<int, array{complaints: int, complaint_customers: int, refunds: int, replacements: int, next_order: int, supplier_claims: int, orders: int}>
     */
    private function complaintsByStock(): array
    {
        $since = now()->subMonths(self::COMPLAINT_MONTHS);

        $refunds = DB::table('invoice_transactions as it')
            ->join('transactions as t', 't.id', 'it.transaction_id')
            ->join('product_has_org_stocks as phos', 'phos.product_id', 'it.model_id')
            ->join('org_stocks as os', 'os.id', 'phos.org_stock_id')
            ->where('it.group_id', $this->group->id)
            ->where('it.is_refund', true)
            ->where('it.model_type', 'Product')
            ->whereNull('it.deleted_at')
            ->where('it.date', '>=', $since)
            ->where('t.quantity_dispatched', '>', 0)
            ->selectRaw("os.stock_id, it.customer_id, 'refunds' as kind");

        $replacements = DB::table('delivery_note_items as dni')
            ->join('delivery_notes as dn', 'dn.id', 'dni.delivery_note_id')
            ->join('org_stocks as os', 'os.id', 'dni.org_stock_id')
            ->where('dn.group_id', $this->group->id)
            ->where('dn.type', DeliveryNoteTypeEnum::REPLACEMENT->value)
            ->whereNull('dn.deleted_at')
            ->where('dn.date', '>=', $since)
            ->where(fn ($query) => $query->whereNull('dni.replacement_reason')->orWhereNotIn('dni.replacement_reason', self::REPLACEMENT_REASONS_NOT_THE_PRODUCT))
            ->selectRaw("os.stock_id, dn.customer_id, 'replacements' as kind");

        $nextOrder = DB::table('upcoming_transactions as ut')
            ->join('product_has_org_stocks as phos', 'phos.product_id', 'ut.product_id')
            ->join('org_stocks as os', 'os.id', 'phos.org_stock_id')
            ->where('ut.group_id', $this->group->id)
            ->where('ut.type', UpcomingTransactionTypeEnum::FOLLOW_ON->value)
            ->where('ut.created_at', '>=', $since)
            ->selectRaw("os.stock_id, ut.customer_id, 'next_order' as kind");

        $complaints = [];
        foreach (DB::query()->fromSub($refunds->unionAll($replacements)->unionAll($nextOrder), 'events')
            ->whereNotNull('stock_id')
            ->groupBy('stock_id')
            ->selectRaw("stock_id, count(*) as complaints, count(distinct customer_id) as complaint_customers, count(*) filter (where kind = 'refunds') as refunds, count(*) filter (where kind = 'replacements') as replacements, count(*) filter (where kind = 'next_order') as next_order")
            ->get() as $row) {
            $complaints[$row->stock_id] = [
                'complaints'          => (int) $row->complaints,
                'complaint_customers' => (int) $row->complaint_customers,
                'refunds'             => (int) $row->refunds,
                'replacements'        => (int) $row->replacements,
                'next_order'          => (int) $row->next_order,
                'supplier_claims'     => 0,
                'orders'              => 0,
            ];
        }

        foreach (DB::table('stock_delivery_claims as sdc')
            ->join('org_stocks as os', 'os.id', 'sdc.org_stock_id')
            ->where('sdc.group_id', $this->group->id)
            ->where('sdc.created_at', '>=', $since)
            ->whereNotNull('os.stock_id')
            ->groupBy('os.stock_id')
            ->selectRaw('os.stock_id, count(*) as supplier_claims')
            ->get() as $row) {
            $complaints[$row->stock_id] ??= self::NO_COMPLAINTS;
            $complaints[$row->stock_id]['supplier_claims'] = (int) $row->supplier_claims;
        }

        foreach (DB::table('delivery_note_items as dni')
            ->join('delivery_notes as dn', 'dn.id', 'dni.delivery_note_id')
            ->join('org_stocks as os', 'os.id', 'dni.org_stock_id')
            ->where('dn.group_id', $this->group->id)
            ->where('dn.type', DeliveryNoteTypeEnum::ORDER->value)
            ->where('dn.state', '!=', DeliveryNoteStateEnum::CANCELLED->value)
            ->whereNull('dn.deleted_at')
            ->where('dn.date', '>=', $since)
            ->whereIn('os.stock_id', array_keys($complaints))
            ->groupBy('os.stock_id')
            ->selectRaw('os.stock_id, count(distinct dn.id) as orders')
            ->get() as $row) {
            $complaints[$row->stock_id]['orders'] = (int) $row->orders;
        }

        return $complaints;
    }

    private function currencyRates(Collection $organisations, Currency $groupCurrency): Collection
    {
        $pivotCode   = config('app.currency_exchange.pivot');
        $currencyIds = $organisations->pluck('currency_id')->push($groupCurrency->id)->unique()->filter()->values();

        $latest = DB::table('currency_exchanges')
            ->whereIn('currency_id', $currencyIds)
            ->orderByDesc('date')
            ->get()
            ->unique('currency_id')
            ->keyBy('currency_id');

        $groupExchange = $groupCurrency->code === $pivotCode ? 1.0 : (float) ($latest->get($groupCurrency->id)->exchange ?? 0);

        return $organisations->mapWithKeys(function (Organisation $organisation) use ($latest, $pivotCode, $groupExchange, $groupCurrency) {
            if ($organisation->currency->code === $groupCurrency->code) {
                return [$organisation->id => ['rate' => 1.0, 'date' => null, 'source' => null]];
            }

            $row         = $latest->get($organisation->currency_id);
            $orgExchange = $organisation->currency->code === $pivotCode ? 1.0 : (float) ($row->exchange ?? 0);

            if (!$orgExchange || !$groupExchange) {
                return [$organisation->id => ['rate' => null, 'date' => null, 'source' => null]];
            }

            return [$organisation->id => [
                'rate'   => round($groupExchange / $orgExchange, 6),
                'date'   => $row->date ?? null,
                'source' => $row->source ?? null,
            ]];
        });
    }

    private function salesSubquery(): Builder
    {
        $now         = now();
        $d30         = $now->copy()->subDays(30)->startOfDay();
        $d30Prev     = $now->copy()->subDays(60)->startOfDay();
        $d90         = $now->copy()->subDays(90)->startOfDay();
        $d90Prev     = $now->copy()->subDays(180)->startOfDay();
        $dYear       = $now->copy()->subDays(365)->startOfDay();
        $dYearPrev   = $now->copy()->subDays(730)->startOfDay();
        $d180        = $now->copy()->subDays(180)->startOfDay();
        $quarterStart = $now->copy()->startOfQuarter()->startOfDay();
        $daysElapsed  = (int) $quarterStart->diffInDays($now->copy()->startOfDay()) + 1;
        $quarterPrevStart = $quarterStart->copy()->subQuarterNoOverflow();
        $quarterPrevEnd   = $quarterPrevStart->copy()->addDays($daysElapsed);

        return DB::table('org_stock_time_series')
            ->join('org_stock_time_series_records as r', function ($join) use ($dYearPrev) {
                $join->on('r.org_stock_time_series_id', 'org_stock_time_series.id')
                    ->where('r.frequency', TimeSeriesFrequencyEnum::DAILY->singleLetter())
                    ->where('r.from', '>=', $dYearPrev);
            })
            ->where('org_stock_time_series.frequency', TimeSeriesFrequencyEnum::DAILY->value)
            ->groupBy('org_stock_time_series.org_stock_id')
            ->select('org_stock_time_series.org_stock_id')
            ->selectRaw('coalesce(sum(r.sales_grp_currency_external) filter (where r.from >= ?), 0) as sales_30d', [$d30])
            ->selectRaw('coalesce(sum(r.sales_grp_currency_external) filter (where r.from >= ? and r.from < ?), 0) as sales_30d_prev', [$d30Prev, $d30])
            ->selectRaw('coalesce(sum(r.sales_grp_currency_external) filter (where r.from >= ?), 0) as sales_90d', [$d90])
            ->selectRaw('coalesce(sum(r.sales_grp_currency_external) filter (where r.from >= ? and r.from < ?), 0) as sales_90d_prev', [$d90Prev, $d90])
            ->selectRaw('coalesce(sum(r.sales_grp_currency_external) filter (where r.from >= ?), 0) as sales_year', [$dYear])
            ->selectRaw('coalesce(sum(r.sales_grp_currency_external) filter (where r.from >= ? and r.from < ?), 0) as sales_year_prev', [$dYearPrev, $dYear])
            ->selectRaw('coalesce(sum(r.sales_grp_currency_external) filter (where r.from >= ?), 0) as sales_quarter', [$quarterStart])
            ->selectRaw('coalesce(sum(r.sales_grp_currency_external) filter (where r.from >= ? and r.from < ?), 0) as sales_quarter_prev', [$quarterPrevStart, $quarterPrevEnd])
            ->selectRaw('coalesce(sum(r.sales_grp_currency_external) filter (where r.from >= ?), 0) as sales_180d', [$d180]);
    }

    private function orgStockCells(array $organisationIds): Collection
    {
        $covers = GetOrganisationStockCoverBuckets::make();
        $sales  = $this->salesSubquery();

        $isForSale = $covers->forSaleProducts();

        $isRawMaterial = DB::table('raw_materials')
            ->whereColumn('raw_materials.org_stock_id', 'org_stocks.id')
            ->whereNull('raw_materials.deleted_at')
            ->selectRaw('1');

        return DB::table('org_stocks')
            ->join('stocks', 'stocks.id', 'org_stocks.stock_id')
            ->leftJoin('stock_families', 'stock_families.id', 'stocks.stock_family_id')
            ->leftJoin('org_stock_stats', 'org_stock_stats.org_stock_id', 'org_stocks.id')
            ->leftJoinLateral($covers->primarySupplierProduct(), 'sp')
            ->leftJoinSub($sales, 'sales', 'sales.org_stock_id', 'org_stocks.id')
            ->whereIn('org_stocks.organisation_id', $organisationIds)
            ->whereNull('org_stocks.deleted_at')
            ->whereIn('org_stocks.state', [
                OrgStockStateEnum::ACTIVE->value,
                OrgStockStateEnum::DISCONTINUING->value,
                OrgStockStateEnum::SUSPENDED->value,
                OrgStockStateEnum::DISCONTINUED->value,
            ])
            ->select([
                'org_stocks.id',
                'org_stocks.stock_id',
                'org_stocks.organisation_id',
                'org_stocks.slug',
                'org_stocks.state',
                'org_stocks.is_on_demand',
                'org_stocks.quantity_available',
                'org_stocks.quantity_in_locations',
                'stocks.slug',
                'stocks.code',
                'stocks.name',
                'stock_families.code as family_code',
                'org_stock_stats.stock_value',
                'org_stock_stats.stock_commercial_value',
                'org_stock_stats.predicted_daily_usage',
                'sales.sales_30d',
                'sales.sales_30d_prev',
                'sales.sales_90d',
                'sales.sales_90d_prev',
                'sales.sales_quarter',
                'sales.sales_quarter_prev',
                'sales.sales_year',
                'sales.sales_year_prev',
                'sales.sales_180d',
            ])
            ->selectRaw($covers->bucketExpression().' as bucket')
            ->selectRaw('exists ('.$isForSale->toSql().') as is_for_sale', $isForSale->getBindings())
            ->selectRaw('exists ('.$isRawMaterial->toSql().') as is_raw_material', $isRawMaterial->getBindings())
            ->get();
    }

    /**
     * @param  array<int, int>  $orgStockIds
     * @return array<int, array{quantity: float, eta: string|null}>
     */
    public function inboundByOrgStock(array $orgStockIds): array
    {
        if (!$orgStockIds) {
            return [];
        }

        $inbound = [];
        foreach (array_merge($this->stockDeliveryInboundLines($orgStockIds), $this->purchaseOrderInboundLines($orgStockIds)) as $line) {
            $id = $line['org_stock_id'];
            $inbound[$id]['quantity'] = ($inbound[$id]['quantity'] ?? 0.0) + $line['quantity'];
            if ($line['eta'] !== null && (!isset($inbound[$id]['eta']) || $line['eta'] < $inbound[$id]['eta'])) {
                $inbound[$id]['eta'] = $line['eta'];
            }
        }

        return $inbound;
    }

    /**
     * @param  array<int, int>  $orgStockIds
     * @return array<int, array{org_stock_id: int, document: string, quantity: float, eta: string, is_late: bool}>
     */
    public function stockDeliveryInboundLines(array $orgStockIds): array
    {
        $lines = [];

        foreach (array_chunk($orgStockIds, self::ID_CHUNK_SIZE) as $chunk) {
            $lines = array_merge($lines, DB::table('stock_delivery_items')
                ->join('stock_deliveries', 'stock_deliveries.id', 'stock_delivery_items.stock_delivery_id')
                ->whereIn('stock_delivery_items.org_stock_id', $chunk)
                ->whereNull('stock_delivery_items.deleted_at')
                ->whereNull('stock_deliveries.deleted_at')
                ->whereIn('stock_deliveries.state', array_keys(self::DELIVERY_DAYS_TO_ARRIVE))
                ->select([
                    'stock_delivery_items.org_stock_id',
                    'stock_delivery_items.stock_delivery_id',
                    ...$this->stockDeliveryTimingColumns(),
                    DB::raw('(stock_delivery_items.unit_quantity - coalesce(stock_delivery_items.unit_quantity_placed, 0)) as quantity'),
                ])
                ->get()
                ->filter(fn ($row) => $row->quantity > 0)
                ->map(fn ($row) => [
                    'org_stock_id' => $row->org_stock_id,
                    'document'     => 'sd:'.$row->stock_delivery_id,
                    'quantity'     => (float) $row->quantity,
                    ...$this->stockDeliveryTiming($row),
                ])
                ->all());
        }

        return $lines;
    }

    /**
     * PO lines are dropped once their purchase order already has a stock delivery, either through
     * the (often empty, for agent deliveries) pivot or a stock delivery sharing its reference in the
     * same organisation, so nothing already counted above is counted twice.
     *
     * @param  array<int, int>  $orgStockIds
     * @return array<int, array{org_stock_id: int, document: string, quantity: float, eta: string|null, is_late: bool}>
     */
    public function purchaseOrderInboundLines(array $orgStockIds): array
    {
        $lines = [];

        foreach (array_chunk($orgStockIds, self::ID_CHUNK_SIZE) as $chunk) {
            $lines = array_merge($lines, DB::table('purchase_order_transactions')
                ->join('purchase_orders', 'purchase_orders.id', 'purchase_order_transactions.purchase_order_id')
                ->join('org_stocks', 'org_stocks.id', 'purchase_order_transactions.org_stock_id')
                ->whereIn('purchase_order_transactions.org_stock_id', $chunk)
                ->whereNull('purchase_order_transactions.deleted_at')
                ->whereNotIn('purchase_order_transactions.state', [PurchaseOrderTransactionStateEnum::CANCELLED->value, PurchaseOrderTransactionStateEnum::NOT_RECEIVED->value])
                ->whereNull('purchase_orders.deleted_at')
                ->whereNotIn('purchase_orders.state', [
                    PurchaseOrderStateEnum::IN_PROCESS->value,
                    PurchaseOrderStateEnum::CANCELLED->value,
                    PurchaseOrderStateEnum::NOT_RECEIVED->value,
                ])
                ->whereNotIn('purchase_orders.delivery_state', [
                    PurchaseOrderDeliveryStateEnum::PLACED->value,
                    PurchaseOrderDeliveryStateEnum::CANCELLED->value,
                    PurchaseOrderDeliveryStateEnum::NOT_RECEIVED->value,
                ])
                ->whereNotIn('purchase_orders.id', function ($query) {
                    $query->select('purchase_order_stock_delivery.purchase_order_id')
                        ->from('purchase_order_stock_delivery')
                        ->join('stock_deliveries', 'stock_deliveries.id', 'purchase_order_stock_delivery.stock_delivery_id')
                        ->whereNull('stock_deliveries.deleted_at')
                        ->whereNotIn('stock_deliveries.state', [
                            StockDeliveryStateEnum::IN_PROCESS->value,
                            StockDeliveryStateEnum::CANCELLED->value,
                            StockDeliveryStateEnum::NOT_RECEIVED->value,
                        ]);
                })
                ->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('stock_deliveries as sd')
                        ->whereColumn('sd.organisation_id', 'purchase_orders.organisation_id')
                        ->whereColumn('sd.reference', 'purchase_orders.reference')
                        ->whereNull('sd.deleted_at')
                        ->whereNotIn('sd.state', [
                            StockDeliveryStateEnum::IN_PROCESS->value,
                            StockDeliveryStateEnum::CANCELLED->value,
                            StockDeliveryStateEnum::NOT_RECEIVED->value,
                        ]);
                })
                ->select([
                    'purchase_order_transactions.org_stock_id',
                    'purchase_order_transactions.purchase_order_id',
                    'purchase_orders.submitted_at',
                    'purchase_orders.estimated_received_at',
                    'org_stocks.measured_lead_time_days',
                    DB::raw('(coalesce(purchase_order_transactions.quantity_ordered, 0) - coalesce(purchase_order_transactions.quantity_cancelled, 0)) as quantity'),
                ])
                ->get()
                ->filter(fn ($row) => $row->quantity > 0)
                ->map(fn ($row) => [
                    'org_stock_id' => $row->org_stock_id,
                    'document'     => 'po:'.$row->purchase_order_id,
                    'quantity'     => (float) $row->quantity,
                    'eta'          => $this->purchaseOrderEta($row),
                    'is_late'      => $this->isPurchaseOrderLate($row),
                ])
                ->all());
        }

        return $lines;
    }

    /**
     * @return array<int, \Illuminate\Contracts\Database\Query\Expression|string>
     */
    public function stockDeliveryTimingColumns(): array
    {
        return [
            'stock_deliveries.state',
            DB::raw("case stock_deliveries.state
                when 'confirmed' then stock_deliveries.confirmed_at
                when 'ready_to_ship' then coalesce(stock_deliveries.ready_to_ship_at, stock_deliveries.confirmed_at)
                when 'dispatched' then coalesce(stock_deliveries.dispatched_at, stock_deliveries.ready_to_ship_at, stock_deliveries.confirmed_at)
                end as state_entered_at"),
            'stock_deliveries.date as delivery_date',
            DB::raw('(select max(purchase_orders.estimated_received_at) from purchase_order_stock_delivery join purchase_orders on purchase_orders.id = purchase_order_stock_delivery.purchase_order_id where purchase_order_stock_delivery.stock_delivery_id = stock_deliveries.id) as purchase_order_eta'),
        ];
    }

    /**
     * Goods already received, checked or being booked in are in the warehouse: due tomorrow, never late.
     * Before it ships a delivery is due when its purchase order says, else the usual days after it entered
     * its state; once dispatched its own dispatch date counts, plus the time a container takes at sea.
     *
     * @return array{eta: string, is_late: bool}
     */
    public function stockDeliveryTiming(object $row): array
    {
        $tomorrow = now()->addDay();

        if (in_array($row->state, self::IN_WAREHOUSE_DELIVERY_STATES, true)) {
            return ['eta' => $tomorrow->toDateString(), 'is_late' => false];
        }

        $enteredAt = \Illuminate\Support\Carbon::parse($row->state_entered_at ?? $row->delivery_date ?? now());
        $expected  = match (true) {
            $row->state === StockDeliveryStateEnum::DISPATCHED->value => $enteredAt->addDays(self::DISPATCHED_DAYS_AT_SEA),
            (bool) $row->purchase_order_eta                           => \Illuminate\Support\Carbon::parse($row->purchase_order_eta),
            default                                                   => $enteredAt->addDays(self::DELIVERY_DAYS_TO_ARRIVE[$row->state]),
        };

        return ['eta' => $expected->copy()->max($tomorrow)->toDateString(), 'is_late' => $expected->lt(today())];
    }

    /**
     * A purchase order whose expected arrival has passed, or that has no date to expect it by: its eta is
     * pushed to tomorrow, but it is late.
     */
    public function isPurchaseOrderLate(object $row): bool
    {
        $expected = $row->estimated_received_at
            ? \Illuminate\Support\Carbon::parse($row->estimated_received_at)
            : ($row->submitted_at ? \Illuminate\Support\Carbon::parse($row->submitted_at)->addDays((int) ($row->measured_lead_time_days ?? self::DEFAULT_LEAD_TIME_DAYS)) : null);

        return $expected === null || $expected->lt(today());
    }

    private function purchaseOrderEta(object $row): ?string
    {
        $eta = $row->estimated_received_at
            ? \Illuminate\Support\Carbon::parse($row->estimated_received_at)
            : ($row->submitted_at
                ? \Illuminate\Support\Carbon::parse($row->submitted_at)->addDays((int) ($row->measured_lead_time_days ?? self::DEFAULT_LEAD_TIME_DAYS))
                : null);

        if (!$eta) {
            return null;
        }

        return $eta->max(now()->addDay())->toDateString();
    }

    private function periodColumn(string $period): string
    {
        return match ($period) {
            '30d'     => 'sales_30d',
            '90d'     => 'sales_90d',
            'quarter' => 'sales_quarter',
            'year'    => 'sales_year',
        };
    }

    /**
     * Plain arrays and foreach throughout: this runs once per stock in the whole catalogue
     * (tens of thousands of times), where Collection closures measurably added seconds.
     *
     * @param  array<int, object>  $cells
     */
    private function row(array $cells, Collection $organisationsSorted, Collection $rates): array
    {
        $first = $cells[0];

        [$state, $allRetired] = self::groupState($cells);

        $sums = [];
        foreach (self::PERIODS as $period) {
            $column          = $this->periodColumn($period);
            $prevColumn      = $column.'_prev';
            $sums[$column]     = 0.0;
            $sums[$prevColumn] = 0.0;
        }
        $dailyUsage = 0.0;
        $available  = 0.0;

        $cellsByOrgId = [];
        foreach ($cells as $cell) {
            foreach (self::PERIODS as $period) {
                $column     = $this->periodColumn($period);
                $prevColumn = $column.'_prev';
                $sums[$column]     += (float) $cell->{$column};
                $sums[$prevColumn] += (float) $cell->{$prevColumn};
            }
            $dailyUsage += (float) $cell->predicted_daily_usage;
            $available  += max(0.0, (float) $cell->quantity_available);
            $cellsByOrgId[$cell->organisation_id] = $cell;
        }

        $salesByPeriod = [];
        foreach (self::PERIODS as $period) {
            $column   = $this->periodColumn($period);
            $current  = $sums[$column];
            $previous = $sums[$column.'_prev'];
            $salesByPeriod[$period] = [
                'amount' => round($current, 2),
                'trend'  => $previous > 0 ? round(($current - $previous) / $previous * 100) : null,
            ];
        }

        $organisationsOut = [];
        foreach ($organisationsSorted as $organisation) {
            $cell = $cellsByOrgId[$organisation->id] ?? null;
            if (!$cell) {
                continue;
            }

            $rate = $rates->get($organisation->id)['rate'] ?? null;

            $organisationsOut[$organisation->code] = [
                'org_stock_id'     => $cell->id,
                'state'            => $cell->state,
                'on_hand'          => (float) $cell->quantity_in_locations,
                'available'        => (float) $cell->quantity_available,
                'allocated'        => max(0.0, (float) $cell->quantity_in_locations - (float) $cell->quantity_available),
                'inbound'          => (float) $cell->inbound_quantity,
                'sales_by_period'  => $this->cellSalesByPeriod($cell),
                'cover_weeks'      => (float) $cell->predicted_daily_usage > 0 ? round(max(0.0, (float) $cell->quantity_available) / (float) $cell->predicted_daily_usage / 7, 1) : null,
                'next_expected_at' => $cell->next_expected_at,
                'has_po'           => (float) $cell->inbound_quantity > 0,
                'last_ordered_at'  => $cell->last_ordered_at,
                'condition'        => $this->condition($cell),
                'stock_value'      => $rate === null ? null : round((float) $cell->stock_value * $rate, 2),
                'commercial_value' => $rate === null ? null : round((float) $cell->stock_commercial_value * $rate, 2),
            ];
        }

        return [
            'id'              => $first->stock_id,
            'slug'            => $first->slug,
            'code'            => $first->code,
            'name'            => $first->name,
            'family_code'     => $first->family_code,
            'sales_by_period' => $salesByPeriod,
            'cover_weeks'     => $dailyUsage > 0 ? round($available / $dailyUsage / 7, 1) : null,
            'state'           => $state,
            'all_retired'     => $allRetired,
            'organisations'   => $organisationsOut,
        ];
    }

    /**
     * @param  array<int, object>  $cells
     * @return array{0: string, 1: bool}
     */
    public static function groupState(array $cells): array
    {
        $priority = [
            OrgStockStateEnum::ACTIVE->value        => 0,
            OrgStockStateEnum::SUSPENDED->value     => 1,
            OrgStockStateEnum::DISCONTINUING->value => 2,
            OrgStockStateEnum::DISCONTINUED->value  => 3,
        ];

        $counts = [];
        foreach ($cells as $cell) {
            if ($cell->state === OrgStockStateEnum::DISCONTINUED->value) {
                continue;
            }
            $counts[$cell->state] = ($counts[$cell->state] ?? 0) + 1;
        }

        if (!$counts) {
            return [OrgStockStateEnum::DISCONTINUED->value, true];
        }

        $max        = max($counts);
        $candidates = array_keys($counts, $max, true);
        usort($candidates, fn ($a, $b) => ($priority[$a] ?? 99) <=> ($priority[$b] ?? 99));

        return [$candidates[0], false];
    }

    private function condition(object $cell): string
    {
        if ($cell->state === OrgStockStateEnum::DISCONTINUING->value) {
            return 'sell';
        }
        if ($cell->state === OrgStockStateEnum::SUSPENDED->value) {
            return 'hold';
        }
        if ($cell->state === OrgStockStateEnum::DISCONTINUED->value) {
            return 'ret';
        }
        if ((bool) $cell->is_on_demand) {
            return 'ok';
        }

        if ((float) $cell->quantity_available <= 0) {
            return ((float) $cell->sales_180d > 0 || (float) $cell->inbound_quantity > 0) ? 'oos' : 'ns';
        }

        if (!$cell->is_for_sale && !$cell->is_raw_material) {
            return 'off';
        }

        return match (true) {
            in_array($cell->bucket, ['w1', 'w2']) => 'low',
            $cell->bucket === 'excess'            => 'over',
            $cell->bucket === 'dead'              => 'dead',
            default                               => 'ok',
        };
    }

    /**
     * Price and MOQ changes suppliers proposed from their portal that still wait for our decision.
     *
     * @return array<int, int> open proposals by group stock id
     */
    private function pendingSupplierPriceChangesByStock(): array
    {
        return DB::table('supplier_submissions')
            ->join('stock_has_supplier_products', 'stock_has_supplier_products.supplier_product_id', 'supplier_submissions.supplier_product_id')
            ->where('supplier_submissions.group_id', $this->group->id)
            ->where('supplier_submissions.type', SupplierSubmissionTypeEnum::PRICE_CHANGE->value)
            ->whereIn('supplier_submissions.state', [SupplierSubmissionStateEnum::RECEIVED->value, SupplierSubmissionStateEnum::UNDER_REVIEW->value])
            ->groupBy('stock_has_supplier_products.stock_id')
            ->selectRaw('stock_has_supplier_products.stock_id, count(distinct supplier_submissions.id) as pending')
            ->pluck('pending', 'stock_id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    private function scopedCells(array $row, ?string $organisation): array
    {
        return $organisation ? Arr::only($row['organisations'], [$organisation]) : $row['organisations'];
    }

    private function matches(array $row, array $filters): bool
    {
        $organisation = Arr::get($filters, 'organisation');
        $family       = Arr::get($filters, 'family');
        $state        = Arr::get($filters, 'state');
        $search       = trim((string) Arr::get($filters, 'search'));
        $supplierCountry = Arr::get($filters, 'supplier_country');

        if ($supplierCountry && !in_array(strtoupper($supplierCountry), $row['supplier_countries'], true)) {
            return false;
        }

        $scopedCells = $this->scopedCells($row, $organisation);

        if (Arr::get($filters, 'no_stock') && !$this->hasNoStock($scopedCells)) {
            return false;
        }

        $notOrderedYears = (int) Arr::get($filters, 'not_ordered_years');
        if ($notOrderedYears && !$this->notOrderedSince($scopedCells, now()->subYears($notOrderedYears)->toDateString())) {
            return false;
        }

        if ($row['all_retired'] && $state !== OrgStockStateEnum::DISCONTINUED->value && $search === '') {
            return false;
        }

        return (!$organisation || isset($row['organisations'][$organisation]))
            && (!$family || $row['family_code'] === $family)
            && (!$state || $row['state'] === $state)
            && ($search === '' || Str::contains($row['code'].' '.$row['name'].' '.$row['family_code'], $search, ignoreCase: true));
    }

    private function hasNoStock(array $cells): bool
    {
        foreach ($cells as $cell) {
            if ($cell['on_hand'] > 0 || $cell['inbound'] > 0) {
                return false;
            }
        }

        return true;
    }

    private function notOrderedSince(array $cells, string $since): bool
    {
        foreach ($cells as $cell) {
            if (($cell['last_ordered_at'] ?? null) && $cell['last_ordered_at'] >= $since) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, array{amount: float, trend: ?float}>
     */
    private function cellSalesByPeriod(object $cell): array
    {
        $sales = [];
        foreach (self::PERIODS as $period) {
            $column   = $this->periodColumn($period);
            $current  = (float) $cell->{$column};
            $previous = (float) $cell->{$column.'_prev'};

            $sales[$period] = [
                'amount' => round($current, 2),
                'trend'  => $previous > 0 ? round(($current - $previous) / $previous * 100) : null,
            ];
        }

        return $sales;
    }

    /**
     * The level of what was counted on its own (customers compensated, supplier claims). What staff
     * reported by hand is shown beside it, never mixed in, so each can be judged for what it is.
     */
    private function feedbackLevel(array $row): ?string
    {
        if (!$row['complaint_customers'] && !$row['supplier_claims']) {
            return null;
        }

        $level = 'mild';
        if ($row['complaint_customers'] >= self::FEEDBACK_MIN_CUSTOMERS) {
            foreach (self::FEEDBACK_LEVELS as $name => $fromRate) {
                if ($row['complaint_rate'] === null || $row['complaint_rate'] >= $fromRate) {
                    $level = $name;
                }
            }
        }

        return $level;
    }

    private function hasCondition(array $row, string $condition, ?string $organisation): bool
    {
        if ($condition === 'suggested') {
            return $row['suggestions'] > 0;
        }

        if ($condition === 'price_request') {
            return $row['price_requests'] > 0;
        }

        if ($condition === 'supplier_price_change') {
            return $row['supplier_price_changes'] > 0;
        }

        foreach ($this->scopedCells($row, $organisation) as $cell) {
            $matches = match ($condition) {
                'oos_no_po' => $cell['condition'] === 'oos' && !$cell['has_po'],
                'low_no_po' => $cell['condition'] === 'low' && !$cell['has_po'],
                default     => $cell['condition'] === $condition,
            };
            if ($matches) {
                return true;
            }
        }

        return false;
    }

    private function sort(array &$rows, string $sort): void
    {
        $descending = str_starts_with($sort, '-');
        $field      = ['code' => 'code', 'sales' => 'sales', 'trend' => 'trend', 'cover' => 'cover_weeks', 'suggestions' => 'suggestions', 'complaints' => 'complaint_customers', 'feedback' => 'feedback'][ltrim($sort, '-')];

        usort($rows, function (array $a, array $b) use ($field, $descending) {
            if ($a[$field] === null || $b[$field] === null) {
                return ($a[$field] === null) <=> ($b[$field] === null);
            }

            $comparison = $field === 'code' ? strnatcasecmp($a[$field], $b[$field]) : $a[$field] <=> $b[$field];

            return $descending ? -$comparison : $comparison;
        });
    }

    private function kpis(array $rows, ?string $organisation): array
    {
        $kpis = [
            'stock_value'             => 0.0,
            'commercial_value'        => 0.0,
            'out_of_stock'            => 0,
            'out_of_stock_without_po' => 0,
            'low'                     => 0,
            'low_without_po'          => 0,
            'overstock'               => 0,
            'overstock_value'         => 0.0,
            'offline'                 => 0,
            'offline_value'           => 0.0,
            'suggested'               => 0,
            'suggestions'             => 0,
            'price_requested'         => 0,
            'price_requests'          => 0,
            'supplier_price_changed'  => 0,
            'feedback_levels'         => array_fill_keys(array_keys(self::FEEDBACK_LEVELS), 0),
        ];

        foreach ($rows as $row) {
            $kpis['suggested']   += (int) ($row['suggestions'] > 0);
            $kpis['suggestions'] += $row['suggestions'];
            $kpis['price_requested'] += (int) ($row['price_requests'] > 0);
            $kpis['price_requests']  += $row['price_requests'];
            $kpis['supplier_price_changed'] += (int) ($row['supplier_price_changes'] > 0);
            if ($row['feedback_level']) {
                $kpis['feedback_levels'][$row['feedback_level']]++;
            }

            $flags = [];
            foreach ($this->scopedCells($row, $organisation) as $cell) {
                if (in_array($cell['condition'], ['ns', 'ret'], true)) {
                    continue;
                }

                $kpis['stock_value']      += $cell['stock_value'] ?? 0;
                $kpis['commercial_value'] += $cell['commercial_value'] ?? 0;

                $flags[$cell['condition']] = true;
                if (!$cell['has_po']) {
                    $flags[$cell['condition'].'_no_po'] = true;
                }
                if ($cell['condition'] === 'over') {
                    $kpis['overstock_value'] += $cell['stock_value'] ?? 0;
                }
                if ($cell['condition'] === 'off') {
                    $kpis['offline_value'] += $cell['stock_value'] ?? 0;
                }
            }

            $kpis['out_of_stock']            += (int) isset($flags['oos']);
            $kpis['out_of_stock_without_po'] += (int) isset($flags['oos_no_po']);
            $kpis['low']                     += (int) isset($flags['low']);
            $kpis['low_without_po']          += (int) isset($flags['low_no_po']);
            $kpis['overstock']               += (int) isset($flags['over']);
            $kpis['offline']                 += (int) isset($flags['off']);
        }

        return $kpis;
    }

    public function htmlResponse(array $filters, ActionRequest $request): Response
    {
        $dashboard = null;
        $deferred  = [];
        foreach (self::DEFERRED_PROPS as $prop) {
            $deferred[$prop] = Inertia::defer(function () use (&$dashboard, $filters, $prop) {
                $dashboard ??= $this->handle($filters);

                if ($prop === 'rows') {
                    return new LengthAwarePaginator(
                        $dashboard['rows'],
                        $dashboard['pagination']['total'],
                        $dashboard['pagination']['per_page'],
                        $dashboard['pagination']['page'],
                        ['path' => request()->url(), 'query' => request()->query()]
                    );
                }

                return $dashboard[$prop];
            }, 'dashboard');
        }

        $normalisedFilters = $this->normalisedFilters($filters);

        return Inertia::render(
            'Goods/ProductCommandControl',
            array_merge($deferred, [
                'period'      => $normalisedFilters['period'],
                'periods'     => self::PERIODS,
                'filters'     => $normalisedFilters,
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => __('Product Command & Control'),
                'pageHead'    => [
                    'icon'  => [
                        'icon'  => ['fal', 'fa-sliders-h'],
                        'title' => __('Product Command & Control'),
                    ],
                    'title' => __('Product Command & Control'),
                ],
                'currency_code' => $this->group->currency->code,
                'can_edit'      => $this->canEdit,
            ])
        )->table($this->tableStructure());
    }

    public function getBreadcrumbs(): array
    {
        return array_merge(
            ShowGroupDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name' => 'grp.goods.dashboard',
                        ],
                        'label' => __('Goods'),
                    ],
                ],
            ]
        );
    }
}
