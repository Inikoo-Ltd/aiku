<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 12 Oct 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgAgent\UI;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\OrgAction;
use App\Actions\Procurement\OrgAgent\PdfAgentOrderedPurchaseOrders;
use App\Actions\Procurement\OrgAgent\WithAgentShoppingSubNavigation;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Enums\Procurement\OrgAgent\OrgAgentOrderContainerEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\InertiaTable\InertiaTable;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Helpers\Currency;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilderContract;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class ShowAgentOrderStage extends OrgAction
{
    use WithAgentShoppingSubNavigation;
    use WithProcurementAuthorisation;

    public const string ORDERED    = 'ordered';
    public const string PREPARING  = 'preparing';
    public const string ON_THE_WAY = 'on-the-way';
    public const string DELIVERED  = 'delivered';

    public const array STAGES = [self::ORDERED, self::PREPARING, self::ON_THE_WAY, self::DELIVERED];

    public const array AGENT_INVOICE_STAGES = [self::ON_THE_WAY, self::DELIVERED];

    public const array AGENT_INVOICE_FILTERS = ['missing', 'unpaid', 'paid'];

    private const string AGENT_INVOICE_EXISTS_SQL = 'exists (select 1 from agent_invoices ai where ai.stock_delivery_id = sd.id)';

    private const string AGENT_INVOICE_BALANCE_SQL = '(select coalesce(sum(ai.total_amount), 0) from agent_invoices ai where ai.stock_delivery_id = sd.id)
        - (select coalesce(sum(ap.amount), 0) from agent_payments ap where ap.stock_delivery_id = sd.id and ap.type = \'transfer_received\' and ap.deleted_at is null)
        - (select coalesce(sum(da.amount), 0) from stock_delivery_deposit_applications da where da.stock_delivery_id = sd.id and da.deleted_at is null)';

    private const array STOCK_DELIVERY_STATES = [
        self::PREPARING  => [StockDeliveryStateEnum::IN_PROCESS, StockDeliveryStateEnum::CONFIRMED, StockDeliveryStateEnum::READY_TO_SHIP],
        self::ON_THE_WAY => [StockDeliveryStateEnum::DISPATCHED],
        self::DELIVERED  => [StockDeliveryStateEnum::RECEIVED, StockDeliveryStateEnum::CHECKED, StockDeliveryStateEnum::BOOKING_IN, StockDeliveryStateEnum::BOOKED_IN, StockDeliveryStateEnum::PLACED],
    ];

    private const array STOCK_DELIVERY_DATE_COLUMNS = [
        self::PREPARING  => 'sd.date',
        self::ON_THE_WAY => 'sd.dispatched_at',
        self::DELIVERED  => 'sd.received_at',
    ];

    private OrgAgent $orgAgent;

    private string $stage;

    private ?string $agentInvoice;

    /**
     * What the stage lists for the organisation: purchase orders the agent is working on and has not put on
     * a stock delivery yet, or its stock deliveries by how far they have travelled.
     */
    public static function documents(OrgAgent $orgAgent, string $stage): Builder
    {
        if ($stage === self::ORDERED) {
            return PurchaseOrder::query()->withoutGlobalScopes()->from('purchase_orders as po')
                ->where('po.organisation_id', $orgAgent->organisation_id)
                ->where('po.agent_id', $orgAgent->agent_id)
                ->where('po.parent_type', 'OrgSupplier')
                ->whereIn('po.state', [PurchaseOrderStateEnum::SUBMITTED->value, PurchaseOrderStateEnum::CONFIRMED->value])
                ->whereNull('po.deleted_at')
                ->whereNotExists(fn (QueryBuilderContract $query) => $query->selectRaw('1')
                    ->from('purchase_order_stock_delivery as posd')
                    ->whereColumn('posd.purchase_order_id', 'po.id'));
        }

        return StockDelivery::query()->withoutGlobalScopes()->from('stock_deliveries as sd')
            ->where('sd.organisation_id', $orgAgent->organisation_id)
            ->where('sd.agent_id', $orgAgent->agent_id)
            ->whereIn('sd.state', array_column(self::STOCK_DELIVERY_STATES[$stage], 'value'))
            ->whereNull('sd.deleted_at');
    }

    public static function countOurs(OrgAgent $orgAgent, string $stage): int
    {
        return self::documents($orgAgent, $stage)->count();
    }

    /**
     * Whether the agent's invoice for the container is on the stock delivery and whether it is paid off.
     */
    public static function filterByAgentInvoice(Builder $query, string $filter): Builder
    {
        return match ($filter) {
            'missing' => $query->whereRaw('not '.self::AGENT_INVOICE_EXISTS_SQL),
            'unpaid'  => $query->whereRaw(self::AGENT_INVOICE_EXISTS_SQL)->whereRaw(self::AGENT_INVOICE_BALANCE_SQL.' > 0'),
            'paid'    => $query->whereRaw(self::AGENT_INVOICE_EXISTS_SQL)->whereRaw(self::AGENT_INVOICE_BALANCE_SQL.' <= 0'),
        };
    }

    /**
     * @return array<string, int>
     */
    public static function agentInvoiceCounts(OrgAgent $orgAgent, string $stage): array
    {
        return collect(self::AGENT_INVOICE_FILTERS)->mapWithKeys(fn (string $filter) => [
            $filter => self::filterByAgentInvoice(self::documents($orgAgent, $stage), $filter)->count(),
        ])->all();
    }

    public function handle(OrgAgent $orgAgent, string $stage, ?string $agentInvoice = null): LengthAwarePaginator
    {
        $alias = $stage === self::ORDERED ? 'po' : 'sd';
        $query = $stage === self::ORDERED ? $this->purchaseOrderRows($orgAgent) : $this->stockDeliveryRows($orgAgent, $stage);

        if ($agentInvoice && in_array($stage, self::AGENT_INVOICE_STAGES, true)) {
            self::filterByAgentInvoice($query, $agentInvoice);
        }

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) use ($alias, $stage) {
            $like = '%'.addcslashes((string) $value, '%_\\').'%';
            $query->where(fn ($query) => $query
                ->whereRaw("$alias.reference ilike ?", [$like])
                ->when($stage !== self::ORDERED, fn ($query) => $query->orWhereRaw("sd.data->>'shipper_reference' ilike ? or sd.data->>'tracking_number' ilike ?", [$like, $like]))
                ->when($stage === self::ORDERED, fn ($query) => $query->orWhereRaw('sup.code ilike ? or sup.name ilike ?', [$like, $like])));
        });

        $sortBy = fn (string $name, string $expression) => AllowedSort::callback(
            $name,
            fn ($query, bool $descending) => $query->orderByRaw($expression.($descending ? ' desc' : ' asc').' nulls last')
        );

        $dateColumn = $stage === self::ORDERED ? 'po.submitted_at' : self::STOCK_DELIVERY_DATE_COLUMNS[$stage];

        $rows = QueryBuilder::for($query)
            ->allowedSorts([
                $sortBy('reference', "$alias.reference collate \"C\""),
                $sortBy('date', $dateColumn),
                ...($stage === self::ORDERED
                    ? [$sortBy('supplier_code', 'sup.code collate "C"')]
                    : [$sortBy('purchase_orders', 'sd.number_purchase_orders'), $sortBy('eta', "sd.data->>'estimated_receiving_date'")]),
            ])
            ->defaultSort($stage === self::ORDERED ? $sortBy('supplier_code', 'sup.code collate "C"') : $sortBy('-date', $dateColumn))
            ->allowedFilters([$globalSearch])
            ->orderByDesc("$alias.id")
            ->withPaginator(null, tableName: request()->route()?->getName())
            ->withQueryString();

        $organisationCurrency = $orgAgent->organisation->currency;
        $exchanges            = collect($rows->items())->pluck('currency_id')->filter()->unique()->mapWithKeys(fn (int $currencyId) => [
            $currencyId => $currencyId === $organisationCurrency->id ? null : GetCurrencyExchange::run(Currency::find($currencyId), $organisationCurrency),
        ]);

        $totals      = $this->totals($stage, collect($rows->items())->pluck('id')->all());
        $stateLabels = $stage === self::ORDERED ? PurchaseOrderStateEnum::labels() : StockDeliveryStateEnum::labels();
        $today       = Carbon::today();

        $organisationSlug = $orgAgent->organisation->slug;

        $rows->getCollection()->transform(function ($row) use ($orgAgent, $organisationSlug, $stage, $exchanges, $totals, $stateLabels, $today) {
            $total  = $totals->get($row->id);
            $cbm    = $row->cbm_recorded ?? $total?->cbm;
            $eta    = ($row->eta ?? null) ? Carbon::parse($row->eta) : null;

            return [
                'id'                => $row->id,
                'reference'         => $row->reference,
                'url'               => match ($stage) {
                    self::ORDERED   => route('grp.org.procurement.org_agents.show.purchase-orders.show', [$organisationSlug, $orgAgent->slug, $row->slug]),
                    self::PREPARING => route('grp.org.procurement.org_agents.show.shopping.container', [$organisationSlug, $orgAgent->slug, $row->slug]),
                    default         => route('grp.org.procurement.stock_deliveries.show', [$organisationSlug, $row->slug]),
                },
                'supplier_code'     => $row->supplier_code ?? null,
                'supplier_name'     => $row->supplier_name ?? null,
                'purchase_orders'   => isset($row->number_purchase_orders) ? (int) $row->number_purchase_orders : null,
                'state_label'       => $stateLabels[$row->getRawOriginal('state')] ?? $row->getRawOriginal('state'),
                'lines'             => (int) ($total->lines ?? 0),
                'cartons'           => ($total->cartons ?? null) !== null ? round((float) $total->cartons, 2) : null,
                'amount'            => (float) ($total->amount ?? 0),
                'currency'          => $row->currency,
                'org_exchange'      => $exchanges->get($row->currency_id),
                'cbm'               => $cbm !== null ? round((float) $cbm, 2) : null,
                'date'              => $row->date,
                'eta'               => $eta?->toDateString(),
                'shipper_reference' => $row->shipper_reference ?? null,
                'tracking_number'   => $row->tracking_number ?? null,
                'shipping_agent'    => $row->shipping_agent ?? null,
                'agent_invoice'     => match (true) {
                    !in_array($stage, self::AGENT_INVOICE_STAGES, true) => null,
                    !$row->has_agent_invoice                              => 'missing',
                    (float) $row->agent_invoice_balance > 0               => 'unpaid',
                    default                                               => 'paid',
                },
                'days_late'         => $stage === self::ON_THE_WAY && $eta?->lt($today) ? (int) $eta->diffInDays($today) : null,
            ];
        });

        return $rows;
    }

    public function tableStructure(string $stage): Closure
    {
        return function (InertiaTable $table) use ($stage) {
            $isOrdered = $stage === self::ORDERED;

            $table
                ->withGlobalSearch()
                ->defaultSort($isOrdered ? 'supplier_code' : '-date')
                ->withLabelRecord($isOrdered ? [__('Purchase order'), __('Purchase orders')] : [__('Stock delivery'), __('Stock deliveries')])
                ->withEmptyState(['title' => $isOrdered ? __('No purchase orders here') : __('No stock deliveries here')])
                ->column(key: 'reference', label: $isOrdered ? __('Purchase order') : __('Stock delivery'), canBeHidden: false, sortable: true);

            $isOrdered
                ? $table->column(key: 'supplier_code', label: __('Supplier'), canBeHidden: false, sortable: true)
                : $table->column(key: 'purchase_orders', label: __('Purchase orders'), canBeHidden: false, sortable: true, align: 'right');

            $table->column(key: 'date', label: self::stages()[$stage]['date'], canBeHidden: false, sortable: true);

            if (in_array($stage, [self::PREPARING, self::ON_THE_WAY], true)) {
                $table->column(key: 'eta', label: __('Expected'), canBeHidden: false, sortable: true);
            }

            if ($stage === self::ON_THE_WAY) {
                $table->column(key: 'tracking_number', label: __('Tracking'), canBeHidden: false);
            }

            if (in_array($stage, self::AGENT_INVOICE_STAGES, true)) {
                $table->column(key: 'agent_invoice', label: __('Agent invoice'), canBeHidden: false);
            }

            $table
                ->column(key: 'lines', label: __('Products'), canBeHidden: false, align: 'right')
                ->column(key: 'cartons', label: __('Cartons'), canBeHidden: false, align: 'right')
                ->column(key: 'amount', label: __('Total'), canBeHidden: false, align: 'right')
                ->column(key: 'cbm', label: __('Volume'), canBeHidden: false, align: 'right');
        };
    }

    private function purchaseOrderRows(OrgAgent $orgAgent): Builder
    {
        return self::documents($orgAgent, self::ORDERED)
            ->join('suppliers as sup', 'sup.id', 'po.supplier_id')
            ->leftJoin('currencies as cur', 'cur.id', 'po.currency_id')
            ->selectRaw('po.id, po.slug, po.reference, po.state, po.submitted_at as date, null as cbm_recorded,
                sup.code as supplier_code, sup.name as supplier_name, cur.code as currency, cur.id as currency_id');
    }

    private function stockDeliveryRows(OrgAgent $orgAgent, string $stage): Builder
    {
        $dateColumn = self::STOCK_DELIVERY_DATE_COLUMNS[$stage];

        return self::documents($orgAgent, $stage)
            ->leftJoin('currencies as cur', 'cur.id', 'sd.currency_id')
            ->selectRaw("sd.id, sd.slug, sd.reference, sd.state, sd.number_purchase_orders, $dateColumn as date, sd.cbm as cbm_recorded,
                sd.data->>'estimated_receiving_date' as eta,
                sd.data->>'shipper_reference' as shipper_reference, sd.data->>'tracking_number' as tracking_number, sd.data->>'shipping_agent' as shipping_agent,
                ".self::AGENT_INVOICE_EXISTS_SQL.' as has_agent_invoice, '.self::AGENT_INVOICE_BALANCE_SQL." as agent_invoice_balance,
                cur.code as currency, cur.id as currency_id");
    }

    /**
     * Products, cartons, amount and volume of the documents on the page, keyed by document.
     *
     * @param  array<int, int>  $ids
     */
    private function totals(string $stage, array $ids): Collection
    {
        [$table, $line, $key, $units, $cancelled] = $stage === self::ORDERED
            ? ['purchase_order_transactions', 'line', 'purchase_order_id', 'quantity_ordered', PurchaseOrderTransactionStateEnum::CANCELLED->value]
            : ['stock_delivery_items', 'sdi', 'stock_delivery_id', 'unit_quantity', StockDeliveryItemStateEnum::CANCELLED->value];

        $cbm = $stage === self::ORDERED
            ? "line.$units / nullif(sp.units_per_carton, 0) * sp.cbm"
            : ShowAgentContainerInPreparation::CBM_SQL;

        return DB::table("$table as $line")
            ->leftJoin('supplier_products as sp', 'sp.id', "$line.supplier_product_id")
            ->whereIn("$line.$key", $ids)
            ->whereNull("$line.deleted_at")
            ->where("$line.state", '!=', $cancelled)
            ->groupBy("$line.$key")
            ->selectRaw("$line.$key as document_id,
                count(*) as lines,
                sum($line.$units / nullif(sp.units_per_carton, 0)) as cartons,
                coalesce(sum($line.net_amount), 0) as amount,
                sum($cbm) as cbm")
            ->get()
            ->keyBy('document_id');
    }

    /**
     * @return array<string, array{label: string, title: string, icon: string, date: string, note: string}>
     */
    public static function stages(): array
    {
        return [
            self::ORDERED    => [
                'label' => __('Ordered'),
                'title' => __('Purchase orders the agent is working on'),
                'icon'  => 'fa-clipboard-list-check',
                'date'  => __('Submitted'),
                'note'  => __('Purchase orders submitted to :agent or confirmed by it that are not on a stock delivery yet. A purchase order leaves this list when :agent puts it on a stock delivery.'),
            ],
            self::PREPARING  => [
                'label' => __('Preparing'),
                'title' => __('Stock deliveries in preparation'),
                'icon'  => 'fa-box-open',
                'date'  => __('Created'),
                'note'  => __('Stock deliveries :agent is putting together and has not dispatched yet.'),
            ],
            self::ON_THE_WAY => [
                'label' => __('On the way'),
                'title' => __('Stock deliveries on the way'),
                'icon'  => 'fa-ship',
                'date'  => __('Dispatched'),
                'note'  => __('Stock deliveries :agent has dispatched and that have not been received yet.'),
            ],
            self::DELIVERED  => [
                'label' => __('Delivered'),
                'title' => __('Stock deliveries delivered'),
                'icon'  => 'fa-warehouse-alt',
                'date'  => __('Received'),
                'note'  => __('Every stock delivery through :agent that has been received, newest first.'),
            ],
        ];
    }

    public function asController(Organisation $organisation, OrgAgent $orgAgent, string $stage, ActionRequest $request): LengthAwarePaginator
    {
        $this->orgAgent = $orgAgent;
        $this->stage    = $stage;
        $this->initialisation($organisation, $request);

        $this->agentInvoice = in_array($request->input('agent_invoice'), self::AGENT_INVOICE_FILTERS, true) ? $request->input('agent_invoice') : null;

        return $this->handle($orgAgent, $stage, $this->agentInvoice);
    }

    public function htmlResponse(LengthAwarePaginator $rows, ActionRequest $request): Response|RedirectResponse
    {
        if ($this->stage === self::PREPARING && !$request->query()) {
            $ours = self::documents($this->orgAgent, self::PREPARING)->where('sd.organisation_id', $this->orgAgent->organisation_id)->limit(2)->pluck('sd.slug');

            if ($ours->count() === 1) {
                return redirect()->route('grp.org.procurement.org_agents.show.shopping.container', [$this->organisation->slug, $this->orgAgent->slug, $ours->first()]);
            }
        }

        $stage = self::stages()[$this->stage];

        return Inertia::render(
            'Procurement/AgentOrderStage',
            [
                'breadcrumbs' => $this->getBreadcrumbs($this->orgAgent, $this->stage, $request->route()->originalParameters()),
                'title'       => $stage['title'],
                'pageHead'    => [
                    'icon'          => ['icon' => ['fal', $stage['icon']], 'title' => $stage['title']],
                    'model'         => $this->orgAgent->agent->name,
                    'title'         => $stage['title'],
                    'subNavigation' => $this->getAgentShoppingNavigation($this->orgAgent),
                    'actions'       => $this->stage === self::ORDERED ? [
                        [
                            'type'   => 'button',
                            'style'  => 'tertiary',
                            'label'  => __('Combined PDF'),
                            'target' => '_blank',
                            'icon'   => 'fal fa-file-pdf',
                            'key'    => 'pdf',
                            'route'  => [
                                'name'       => 'grp.org.procurement.org_agents.show.shopping.ordered.pdf',
                                'parameters' => [$this->organisation->slug, $this->orgAgent->slug, PdfAgentOrderedPurchaseOrders::make()->filename($this->orgAgent)],
                            ],
                        ],
                    ] : [],
                ],
                'stage'       => $this->stage,
                'isOrdered'   => $this->stage === self::ORDERED,
                'note'        => __($stage['note'], ['agent' => $this->orgAgent->agent->name]),
                'agentName'   => $this->orgAgent->agent->name,
                'orgCurrency' => $this->organisation->currency->code,
                'container'   => [
                    'label' => OrgAgentOrderContainerEnum::labels()[$this->orgAgent->order_container->value],
                    'cbm'   => $this->orgAgent->order_container->cbm(),
                ],
                'agentInvoice' => in_array($this->stage, self::AGENT_INVOICE_STAGES, true) ? [
                    'selected' => $this->agentInvoice,
                    'counts'   => self::agentInvoiceCounts($this->orgAgent, $this->stage),
                ] : null,
                'rows'        => JsonResource::collection($rows),
            ]
        )->table($this->tableStructure($this->stage));
    }

    public function getBreadcrumbs(OrgAgent $orgAgent, string $stage, array $routeParameters): array
    {
        return array_merge(
            ShowAgentShoppingDashboard::make()->getBreadcrumbs($orgAgent, array_slice($routeParameters, 0, 2)),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.procurement.org_agents.show.shopping.stage',
                            'parameters' => $routeParameters,
                        ],
                        'label' => self::stages()[$stage]['label'],
                        'icon'  => 'fal '.self::stages()[$stage]['icon'],
                    ],
                ],
            ]
        );
    }
}
