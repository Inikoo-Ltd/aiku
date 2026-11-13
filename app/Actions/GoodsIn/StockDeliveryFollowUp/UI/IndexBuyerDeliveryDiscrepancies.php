<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDeliveryFollowUp\UI;

use App\Actions\GoodsIn\StockDeliveryFollowUp\ResolveStockDeliveryBuyer;
use App\Actions\OrgAction;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemDiscrepancyEnum;
use App\Http\Resources\GoodsIn\StockDeliveryDiscrepanciesResource;
use App\InertiaTable\InertiaTable;
use App\Models\GoodsIn\StockDeliveryItem;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * "My delivery discrepancies": every checked line that arrived under or over (outside the organisation's
 * tolerance) and is not settled yet, whether or not anyone gave feedback, for the buyer of its purchase order line
 * (else the organisation's delivery buyer). Mine by default, everyone's or the unassigned ones a switch away.
 * Lines are resolved where they always were, on the delivery's under/over delivered tab.
 */
class IndexBuyerDeliveryDiscrepancies extends OrgAction
{
    use WithProcurementAuthorisation;

    public const array OWNERS = ['mine', 'all', 'unassigned'];

    public const int CLOSED_DAYS = 90;

    private string $owner = 'mine';

    private const string OPEN_SQL = "(stock_delivery_items.discrepancy_outcome is null
        or stock_delivery_items.discrepancy_outcome = 'recount_requested'
        or (stock_delivery_claims.id is not null and stock_delivery_claims.state not in ('credit_received', 'rejected')))";

    private const string STATUS_SQL = "case
        when not ".self::OPEN_SQL." then 'closed'
        when stock_delivery_items.discrepancy_outcome = 'recount_requested' then 'awaiting_recount'
        when stock_delivery_claims.state in ('open', 'sent') then 'claim_open'
        else 'needs_decision' end";

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            'needs_decision'   => __('Needs decision'),
            'awaiting_recount' => __('Awaiting recount'),
            'claim_open'       => __('Claim open'),
            'closed'           => __('Closed'),
        ];
    }

    /**
     * The line is flagged the way StockDeliveryItem::discrepancy() flags it: a clean multiple is a possible unit
     * mismatch, anything else inside both tolerances set for the organisation is not a discrepancy.
     */
    private static function flaggedSql(Organisation $organisation): string
    {
        $percentage = (float) Arr::get($organisation->settings, 'procurement.delivery_tolerance_percentage', 0);
        $amount     = (float) Arr::get($organisation->settings, 'procurement.delivery_tolerance_amount', 0);
        if ($percentage <= 0 && $amount <= 0) {
            return 'true';
        }

        $factors  = implode(',', StockDeliveryItemDiscrepancyEnum::UNIT_MISMATCH_FACTORS);
        $expected = 'stock_delivery_items.unit_quantity';
        $received = 'stock_delivery_items.unit_quantity_checked';
        $mismatch = "($expected > 0 and $received > 0 and exists (select 1 from unnest(array[$factors]) as factor where abs(greatest($received, $expected) / least($received, $expected) - factor) < 0.0001))";
        $within   = "($expected > 0"
            .($percentage > 0 ? " and abs($received - $expected) * 100 / $expected <= $percentage" : '')
            .($amount > 0 ? " and abs($received - $expected) * coalesce(stock_delivery_items.org_net_amount, stock_delivery_items.net_amount) / $expected <= $amount" : '')
            .')';

        return "($mismatch or not $within)";
    }

    public static function baseQuery(Organisation $organisation, string $owner, ?User $user): Builder
    {
        $buyer = self::buyerSql($organisation);

        return StockDeliveryItem::query()
            ->join('stock_deliveries', 'stock_deliveries.id', 'stock_delivery_items.stock_delivery_id')
            ->join('organisations', 'organisations.id', 'stock_delivery_items.organisation_id')
            ->leftJoin('org_stocks', 'org_stocks.id', 'stock_delivery_items.org_stock_id')
            ->leftJoin('supplier_products', 'supplier_products.id', 'stock_delivery_items.supplier_product_id')
            ->leftJoin('suppliers', 'suppliers.id', 'supplier_products.supplier_id')
            ->leftJoin('agents', 'agents.id', 'stock_deliveries.agent_id')
            ->leftJoin('stock_delivery_claims', 'stock_delivery_claims.stock_delivery_item_id', 'stock_delivery_items.id')
            ->leftJoin('currencies as claim_currencies', 'claim_currencies.id', 'stock_delivery_claims.currency_id')
            ->where('stock_delivery_items.organisation_id', $organisation->id)
            ->whereNull('stock_delivery_items.deleted_at')
            ->whereNotNull('stock_delivery_items.checked_at')
            ->where('stock_delivery_items.state', '!=', 'cancelled')
            ->whereColumn('stock_delivery_items.unit_quantity_checked', '!=', 'stock_delivery_items.unit_quantity')
            ->whereRaw(self::flaggedSql($organisation))
            ->where(fn ($query) => $query->whereRaw(self::OPEN_SQL)
                ->orWhereRaw('coalesce(stock_delivery_claims.closed_at, stock_delivery_items.discrepancy_resolved_at) >= ?', [now()->subDays(self::CLOSED_DAYS)]))
            ->when($owner === 'mine', fn ($query) => $query->whereRaw("$buyer = ?", [$user?->id]))
            ->when($owner === 'unassigned', fn ($query) => $query->whereRaw("$buyer is null"));
    }

    private static function buyerSql(Organisation $organisation): string
    {
        $poBuyer  = ResolveStockDeliveryBuyer::purchaseOrderBuyerQuery('stock_delivery_items.stock_delivery_id', 'stock_delivery_items.org_stock_id')
            ->select('purchase_orders.buyer_id')->limit(1);
        $fallback = ResolveStockDeliveryBuyer::fallbackBuyerId($organisation);

        return 'coalesce(('.$poBuyer->toRawSql().'), '.($fallback ?? 'null').')';
    }

    /**
     * @return array{counts: array<string, int>, open_claims: array<int, array{currency_code: string, amount: float, number: int}>}
     */
    public static function summary(Organisation $organisation, string $owner, ?User $user): array
    {
        $counts = self::baseQuery($organisation, $owner, $user)
            ->selectRaw(self::STATUS_SQL.' as status, count(*) as total')
            ->groupByRaw(self::STATUS_SQL)
            ->pluck('total', 'status');

        $claims = self::baseQuery($organisation, $owner, $user)
            ->whereIn('stock_delivery_claims.state', ['open', 'sent'])
            ->groupBy('claim_currencies.code')
            ->orderBy('claim_currencies.code')
            ->selectRaw('claim_currencies.code as currency_code, sum(stock_delivery_claims.amount) as amount, count(*) as number')
            ->get()
            ->map(fn ($row) => ['currency_code' => $row->currency_code, 'amount' => (float) $row->amount, 'number' => (int) $row->number])
            ->all();

        return [
            'counts'      => collect(self::statusLabels())->map(fn ($label, $status) => (int) ($counts[$status] ?? 0))->all(),
            'open_claims' => $claims,
        ];
    }

    public function handle(Organisation $organisation, string $owner, ?User $user, ?string $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(self::baseQuery($organisation, $owner, $user));
        $query->whereElementGroup(
            key: 'status',
            allowedElements: array_keys(self::statusLabels()),
            engine: fn ($query, array $elements) => $query->whereRaw('('.self::STATUS_SQL.') in ('.implode(',', array_fill(0, count($elements), '?')).')', array_values($elements)),
            prefix: $prefix,
            default: 'needs_decision,awaiting_recount,claim_open'
        );

        $purchaseOrders = DB::table('purchase_orders')
            ->join('purchase_order_stock_delivery', 'purchase_order_stock_delivery.purchase_order_id', 'purchase_orders.id')
            ->whereColumn('purchase_order_stock_delivery.stock_delivery_id', 'stock_delivery_items.stock_delivery_id')
            ->whereExists(fn ($query) => $query->from('purchase_order_transactions')
                ->whereColumn('purchase_order_transactions.purchase_order_id', 'purchase_orders.id')
                ->whereColumn('purchase_order_transactions.org_stock_id', 'stock_delivery_items.org_stock_id')
                ->whereNull('purchase_order_transactions.deleted_at')
                ->where('purchase_order_transactions.state', '!=', 'cancelled'))
            ->selectRaw("string_agg(purchase_orders.reference, ', ' order by purchase_orders.reference)");

        return $query
            ->defaultSort('checked_at')
            ->select([
                'stock_delivery_items.id',
                'stock_delivery_items.unit_quantity',
                'stock_delivery_items.unit_quantity_checked',
                'stock_delivery_items.discrepancy_outcome',
                'stock_delivery_items.checked_at',
                'organisations.code as organisation_code',
                'organisations.slug as organisation_slug',
                'stock_deliveries.reference as stock_delivery_reference',
                'stock_deliveries.slug as stock_delivery_slug',
                'suppliers.code as supplier_code',
                'suppliers.name as supplier_name',
                'agents.code as agent_code',
                'org_stocks.code as org_stock_code',
                'org_stocks.name as org_stock_name',
                'stock_delivery_claims.amount as claim_amount',
                'stock_delivery_claims.state as claim_state',
                'claim_currencies.code as claim_currency_code',
            ])
            ->selectRaw(self::STATUS_SQL.' as status')
            ->selectRaw(self::buyerSql($organisation).' as buyer_id')
            ->selectSub($purchaseOrders, 'purchase_order_references')
            ->selectSub(DB::table('users')->whereRaw('users.id = '.self::buyerSql($organisation))->select('contact_name'), 'buyer_name')
            ->allowedSorts(['checked_at', 'stock_delivery_reference', 'supplier_code', 'org_stock_code', 'claim_amount', 'buyer_name'])
            ->allowedFilters([
                AllowedFilter::callback('global', fn ($query, $value) => $query->where(fn ($query) => $query->whereStartWith('stock_deliveries.reference', $value)
                    ->orWhereStartWith('org_stocks.code', $value)
                    ->orWhereStartWith('suppliers.code', $value))),
            ])
            ->withPaginator($prefix, tableName: request()->route()?->getName())
            ->withQueryString();
    }

    public function tableStructure(Organisation $organisation, string $owner, ?User $user, ?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($organisation, $owner, $user, $prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $counts = self::summary($organisation, $owner, $user)['counts'];
            $table->elementGroup(
                key: 'status',
                label: __('Status'),
                elements: collect(self::statusLabels())->map(fn (string $label, string $status) => [$label, $counts[$status]])->all()
            );

            $table->withGlobalSearch()
                ->withLabelRecord([__('discrepancy'), __('discrepancies')])
                ->withEmptyState(['title' => __('No delivery discrepancies here')])
                ->column(key: 'organisation_code', label: __('Org'))
                ->column(key: 'stock_delivery_reference', label: __('Delivery'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'purchase_order_references', label: __('PO'))
                ->column(key: 'supplier_code', label: __('Supplier'), sortable: true)
                ->column(key: 'agent_code', label: __('Agent'))
                ->column(key: 'org_stock_code', label: __('SKO'), sortable: true, searchable: true)
                ->column(key: 'expected_quantity', label: __('Expected'), type: 'number')
                ->column(key: 'received_quantity', label: __('Received'), type: 'number')
                ->column(key: 'difference', label: __('Difference'), type: 'number')
                ->column(key: 'claim_amount', label: __('Claim'), sortable: true, type: 'number')
                ->column(key: 'status', label: __('Status'))
                ->column(key: 'age_days', label: __('Age'), type: 'number')
                ->column(key: 'buyer_name', label: __('Buyer'), sortable: true)
                ->defaultSort('checked_at');
        };
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);
        $this->owner = in_array($request->input('owner'), self::OWNERS, true) ? $request->input('owner') : 'mine';

        return $this->handle($organisation, $this->owner, $request->user());
    }

    public function htmlResponse(LengthAwarePaginator $discrepancies, ActionRequest $request): Response
    {
        $title = $this->owner === 'mine' ? __('My delivery discrepancies') : __('Delivery discrepancies');

        return Inertia::render('Org/Procurement/DeliveryDiscrepancies', [
            'breadcrumbs'  => $this->getBreadcrumbs($request->route()->originalParameters()),
            'title'        => $title,
            'pageHead'     => [
                'title' => $title,
                'icon'  => ['icon' => ['fal', 'fa-clipboard-check'], 'title' => $title],
            ],
            'owner'        => $this->owner,
            'owner_routes' => collect(self::OWNERS)->mapWithKeys(fn (string $owner) => [$owner => [
                'name'       => 'grp.org.procurement.delivery_discrepancies.index',
                'parameters' => ['organisation' => $this->organisation->slug, 'owner' => $owner],
            ]])->all(),
            'summary'      => self::summary($this->organisation, $this->owner, $request->user()),
            'data'         => StockDeliveryDiscrepanciesResource::collection($discrepancies),
        ])->table($this->tableStructure($this->organisation, $this->owner, $request->user()));
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowProcurementDashboard::make()->getBreadcrumbs($routeParameters),
            [[
                'type'   => 'simple',
                'simple' => [
                    'label' => __('Delivery discrepancies'),
                    'route' => ['name' => 'grp.org.procurement.delivery_discrepancies.index', 'parameters' => Arr::only($routeParameters, 'organisation')],
                ],
            ]]
        );
    }
}
