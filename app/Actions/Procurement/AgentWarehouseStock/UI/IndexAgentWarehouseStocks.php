<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 14 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\AgentWarehouseStock\UI;

use App\Actions\GoodsIn\StockDelivery\StoreStockDeliveryFromPurchaseOrder;
use App\Actions\OrgAction;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Actions\Procurement\AgentWarehouseStock\ImportAgentWarehouseStocksFromSheet;
use App\Actions\Procurement\PurchaseOrderTransaction\UI\IndexPurchaseOrderTransactions;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Enums\Procurement\AgentWarehouseStock\AgentWarehouseStockStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Http\Resources\Procurement\PurchaseOrderTransactionResource;
use App\InertiaTable\InertiaTable;
use App\Models\Procurement\AgentWarehouseStock;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Models\SupplyChain\Agent;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * Goods the agent holds in its own warehouse, received from suppliers and not yet stuffed into a container.
 */
class IndexAgentWarehouseStocks extends OrgAction
{
    use WithAgentOrganisation;
    use WithProcurementAuthorisation;
    use WithAgentWarehouseProductRows;

    public function handle(Agent $agent): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $like = '%'.addcslashes((string) $value, '%_\\').'%';
            $query->where(fn ($query) => $query
                ->whereRaw('supplier_products.code collate "C" ilike ?', [$like])
                ->orWhere('supplier_products.name', 'ilike', $like)
                ->orWhere('suppliers.code', 'ilike', $like));
        });

        $query = QueryBuilder::for(AgentWarehouseStock::class)
            ->where('agent_warehouse_stocks.agent_id', $agent->id)
            ->where('agent_warehouse_stocks.quantity', '>', 0)
            ->join('organisations', 'organisations.id', 'agent_warehouse_stocks.organisation_id')
            ->join('supplier_products', 'supplier_products.id', 'agent_warehouse_stocks.supplier_product_id')
            ->leftJoin('suppliers', 'suppliers.id', 'supplier_products.supplier_id')
            ->leftJoin('purchase_orders', 'purchase_orders.id', 'agent_warehouse_stocks.purchase_order_id');

        $stateFilter = AllowedFilter::callback('state', fn ($query, $value) => $query->where('agent_warehouse_stocks.state', $value));
        $organisationFilter = AllowedFilter::callback('organisation', fn ($query, $value) => $query->where('organisations.slug', $value));

        return $this->withLinesReadyToShip($this->withProductImages($query
            ->select([
                'agent_warehouse_stocks.id',
                'agent_warehouse_stocks.state',
                'agent_warehouse_stocks.quantity',
                'agent_warehouse_stocks.notes',
                'agent_warehouse_stocks.purchase_order_transaction_id',
                'supplier_products.code',
                'supplier_products.name',
                'suppliers.code as supplier_code',
                DB::raw('(select slug from org_supplier_products where supplier_product_id = agent_warehouse_stocks.supplier_product_id and organisation_id = agent_warehouse_stocks.organisation_id order by id limit 1) as org_supplier_product_slug'),
                DB::raw('(select slug from org_suppliers where supplier_id = supplier_products.supplier_id and organisation_id = agent_warehouse_stocks.organisation_id order by id limit 1) as org_supplier_slug'),
                'organisations.code as organisation_code',
                'purchase_orders.reference as purchase_order_reference',
                'purchase_orders.slug as purchase_order_slug',
                'purchase_orders.state as purchase_order_state',
                'agent_warehouse_stocks.agent_goods_receipt_id',
                DB::raw($this->productImageIdSql().' as image_id'),
                DB::raw('exists(select 1 from agent_goods_receipt_items where agent_goods_receipt_items.agent_warehouse_stock_id = agent_warehouse_stocks.id and agent_goods_receipt_items.checked_at is null) as is_unchecked'),
            ])
            ->defaultSort('code')
            ->allowedSorts(['code', 'name', 'supplier_code', 'organisation_code', 'quantity', 'state'])
            ->allowedFilters([$globalSearch, $stateFilter, $organisationFilter])
            ->withPaginator(null, tableName: request()->route()?->getName())
            ->withQueryString()
            ->through(fn (AgentWarehouseStock $stock) => [
                'id'                       => $stock->id,
                'state'                    => $stock->state->value,
                'state_icon'               => $stock->state->icon(),
                'quantity'                 => (float) $stock->quantity,
                'notes'                    => $stock->notes,
                'code'                     => $stock->code,
                'name'                     => $stock->name,
                'supplier_code'            => $stock->supplier_code,
                'org_supplier_product_slug' => $stock->org_supplier_product_slug,
                'org_supplier_slug'        => $stock->org_supplier_slug,
                'organisation_code'        => $stock->organisation_code,
                'purchase_order_reference' => $stock->purchase_order_reference,
                'purchase_order_slug'      => $stock->purchase_order_slug,
                'image_id'                 => $stock->image_id,
                'is_unchecked'             => (bool) $stock->is_unchecked,
                'is_from_goods_receipt'    => $stock->agent_goods_receipt_id !== null,
                'purchase_order_transaction_id' => $stock->purchase_order_transaction_id,
                'purchase_order_warning'   => $this->purchaseOrderWarning($stock),
            ])));
    }

    /**
     * A line from the stock sheet has no goods receipt. Its purchase order is a guess when the sheet named one aiku does
     * not have, and likely the wrong order when it is closed, as the goods on the sheet are still here.
     *
     * @return array{kind: 'guessed'|'closed', sheet_order: ?string, label: string, tooltip: string}|null
     */
    private function purchaseOrderWarning(AgentWarehouseStock $stock): ?array
    {
        if (!$stock->purchase_order_reference || $stock->agent_goods_receipt_id || $stock->purchase_order_transaction_id) {
            return null;
        }

        $isClosed = in_array($stock->purchase_order_state, ImportAgentWarehouseStocksFromSheet::CLOSED_ORDER_STATES, true);
        $state    = PurchaseOrderStateEnum::labels()[$stock->purchase_order_state] ?? (string) $stock->purchase_order_state;
        $closed   = $isClosed ? __('This order is already :state: these goods from the stock sheet are probably for a newer order', ['state' => mb_strtolower($state)]) : null;

        if (preg_match('/(?:^| · )PO: (.+)$/u', (string) $stock->notes, $match)) {
            return [
                'kind'        => 'guessed',
                'sheet_order' => $match[1],
                'label'       => __('Guessed'),
                'tooltip'     => collect([
                    __('The stock sheet names ":order", which is not in aiku: this is the newest order with this product, not necessarily the right one', ['order' => $match[1]]),
                    $closed,
                ])->filter()->implode('. '),
            ];
        }

        return $isClosed ? ['kind' => 'closed', 'sheet_order' => null, 'label' => $state, 'tooltip' => $closed] : null;
    }

    /**
     * What each row's purchase order line still has to go in a container, as the purchase order page shows it, so
     * packed goods can be added to a container from here.
     */
    private function withLinesReadyToShip(LengthAwarePaginator $rows): LengthAwarePaginator
    {
        $transactionIds = $rows->getCollection()->pluck('purchase_order_transaction_id')->filter()->unique()->values();

        $transactions = collect();
        if ($transactionIds->isNotEmpty()) {
            $query = PurchaseOrderTransaction::query()
                ->whereIn('purchase_order_transactions.id', $transactionIds)
                ->select('purchase_order_transactions.*');
            IndexPurchaseOrderTransactions::selectAgentWarehouseQuantities($query);
            $transactions = $query->with(['purchaseOrder.agent', 'organisation'])->get()->keyBy('id');
        }

        $rows->getCollection()->transform(function (array $row) use ($transactions) {
            $transaction    = $transactions->get($row['purchase_order_transaction_id']);
            $agentWarehouse = $transaction ? PurchaseOrderTransactionResource::make($transaction)->agentWarehouse($transaction, request()) : null;

            return array_merge($row, [
                'agent_warehouse'          => $agentWarehouse,
                'container_blocked_reason' => $row['state'] !== AgentWarehouseStockStateEnum::RECEIVED->value && !($agentWarehouse['containers_route'] ?? null)
                    ? $this->containerBlockedReason($transaction, $agentWarehouse)
                    : null,
            ]);
        });

        return $rows;
    }

    /**
     * Why a packed line can not be put in a container from here, following the rules of the purchase order page.
     *
     * @param  array<string, mixed>|null  $agentWarehouse
     */
    private function containerBlockedReason(?PurchaseOrderTransaction $transaction, ?array $agentWarehouse): string
    {
        if (!$transaction || !$agentWarehouse) {
            return __('Imported from spreadsheet, no PO line');
        }

        $purchaseOrder = $transaction->purchaseOrder;
        if ($purchaseOrder->state !== PurchaseOrderStateEnum::CONFIRMED) {
            return __('PO is :state, not confirmed', [
                'state' => mb_strtolower(PurchaseOrderStateEnum::labels()[$purchaseOrder->state->value] ?? $purchaseOrder->state->value),
            ]);
        }

        if ($transaction->state !== PurchaseOrderTransactionStateEnum::CONFIRMED) {
            return __('PO line is :state', [
                'state' => mb_strtolower(PurchaseOrderTransactionStateEnum::labels()[$transaction->state->value] ?? $transaction->state->value),
            ]);
        }

        if ($agentWarehouse['quantity_ready_to_ship'] <= StoreStockDeliveryFromPurchaseOrder::QUANTITY_TOLERANCE) {
            return !empty($agentWarehouse['containers'])
                ? __('Already all in a container')
                : __('No goods receipt yet');
        }

        return __('No permission');
    }

    public function tableStructure(Agent $agent): Closure
    {
        return function (InertiaTable $table) use ($agent) {
            $table
                ->withGlobalSearch()
                ->withLabelRecord([__('Product'), __('Products')])
                ->withEmptyState(['title' => __('Nothing in the warehouse')])
                ->column(key: 'organisation_code', label: __('For'), canBeHidden: false, sortable: true)
                ->column(key: 'state', label: __('State'), canBeHidden: false, sortable: true)
                ->column(key: 'image_thumbnail', label: __('Image'), canBeHidden: false)
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true)
                ->column(key: 'supplier_code', label: __('Supplier'), canBeHidden: false, sortable: true)
                ->column(key: 'purchase_order_reference', label: __('Purchase order'), canBeHidden: false)
                ->column(key: 'quantity', label: __('Units'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'containers', label: __('Container'))
                ->column(key: 'notes', label: __('Notes'))
                ->column(key: 'actions', label: '', canBeHidden: false);
        };
    }

    /**
     * @return array{state: array<int, array{label: string, value: string, count: int}>, organisation: array<int, array{label: string, value: string, count: int, tooltip: string}>}
     */
    public function filterOptions(Agent $agent): array
    {
        $stateCounts = AgentWarehouseStock::where('agent_id', $agent->id)->where('quantity', '>', 0)->selectRaw('state, count(*) as total')->groupBy('state')->pluck('total', 'state');

        $organisations = AgentWarehouseStock::where('agent_warehouse_stocks.agent_id', $agent->id)
            ->where('agent_warehouse_stocks.quantity', '>', 0)
            ->join('organisations', 'organisations.id', 'agent_warehouse_stocks.organisation_id')
            ->leftJoin('countries', 'countries.id', 'organisations.country_id')
            ->selectRaw('organisations.slug, organisations.code, organisations.name, countries.name as country_name, count(*) as total')
            ->groupBy('organisations.slug', 'organisations.code', 'organisations.name', 'countries.name')
            ->orderBy('organisations.code')
            ->get();

        return [
            'state'        => collect(AgentWarehouseStockStateEnum::labels())->map(fn ($label, $state) => ['label' => $label, 'value' => $state, 'count' => (int) ($stateCounts[$state] ?? 0)])->values()->all(),
            'organisation' => $organisations->map(fn ($organisation) => ['label' => strtoupper($organisation->code), 'value' => $organisation->slug, 'count' => (int) $organisation->total, 'tooltip' => $organisation->country_name ? "{$organisation->name} ({$organisation->country_name})" : $organisation->name])->all(),
        ];
    }

    /**
     * @return array<int, array{code: string, slug: string, name: string, country_code: ?string, states: array<int, array{state: string, label: string, lines: int, units: float}>, products: array<int, array{code: string, name: string, state: string, quantity: float}>}>
     */
    public function organisationCards(Agent $agent, ActionRequest $request): array
    {
        $state        = $request->input('filter.state');
        $organisation = $request->input('filter.organisation');

        $rows = AgentWarehouseStock::where('agent_warehouse_stocks.agent_id', $agent->id)
            ->where('agent_warehouse_stocks.quantity', '>', 0)
            ->join('organisations', 'organisations.id', 'agent_warehouse_stocks.organisation_id')
            ->leftJoin('countries', 'countries.id', 'organisations.country_id')
            ->join('supplier_products', 'supplier_products.id', 'agent_warehouse_stocks.supplier_product_id')
            ->when($state, fn ($query) => $query->where('agent_warehouse_stocks.state', $state))
            ->when($organisation, fn ($query) => $query->where('organisations.slug', $organisation))
            ->orderBy('organisations.code')
            ->orderBy('supplier_products.code')
            ->get([
                'organisations.code as organisation_code',
                'organisations.slug as organisation_slug',
                'organisations.name as organisation_name',
                'countries.code as country_code',
                'agent_warehouse_stocks.state',
                'agent_warehouse_stocks.quantity',
                'supplier_products.code',
                'supplier_products.name',
            ]);

        return $rows->groupBy('organisation_code')->map(fn ($stocks) => [
            'code'         => $stocks->first()->organisation_code,
            'slug'         => $stocks->first()->organisation_slug,
            'name'         => $stocks->first()->organisation_name,
            'country_code' => $stocks->first()->country_code,
            'states'       => collect(AgentWarehouseStockStateEnum::cases())
                ->map(fn (AgentWarehouseStockStateEnum $stateCase) => [
                    'state' => $stateCase->value,
                    'label' => $stateCase->label(),
                    'lines' => $stocks->where('state', $stateCase)->count(),
                    'units' => (float) $stocks->where('state', $stateCase)->sum('quantity'),
                ])
                ->all(),
            'products'     => $stocks->map(fn ($stock) => [
                'code'     => $stock->code,
                'name'     => $stock->name,
                'state'    => $stock->state->value,
                'quantity' => (float) $stock->quantity,
            ])->all(),
        ])->values()->all();
    }

    public function asController(Organisation $organisation, ActionRequest $request): Agent
    {
        $this->initialisation($organisation, $request);
        $agent = $this->getOrganisationAgent($organisation);
        abort_unless($agent, 404);

        return $agent;
    }

    public function htmlResponse(Agent $agent, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Procurement/AgentWarehouseStocks',
            [
                'title'       => __('Warehouse'),
                'breadcrumbs' => array_merge(
                    ShowProcurementDashboard::make()->getBreadcrumbs($request->route()->originalParameters()),
                    [
                        [
                            'type'   => 'simple',
                            'simple' => [
                                'label' => __('Warehouse'),
                                'route' => [
                                    'name'       => 'grp.org.agent.warehouse.index',
                                    'parameters' => [$this->organisation->slug],
                                ],
                            ],
                        ],
                    ]
                ),
                'pageHead'    => [
                    'icon'  => ['title' => __('Warehouse'), 'icon' => 'fal fa-warehouse-alt'],
                    'title' => __('Warehouse'),
                ],
                'data'          => $this->handle($agent),
                'filterOptions' => $this->filterOptions($agent),
                'cards'         => $this->organisationCards($agent, $request),
                'canPack'       => $this->canEdit,
            ]
        )->table($this->tableStructure($agent));
    }
}
