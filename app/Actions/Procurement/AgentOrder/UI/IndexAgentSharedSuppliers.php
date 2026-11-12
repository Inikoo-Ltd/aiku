<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 19 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\AgentOrder\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * The factories two or more of the agent's clients have open orders with at the same time, so the agent talks
 * to the factory once with the combined volume while each client keeps its own cartons, marks and invoice.
 */
class IndexAgentSharedSuppliers extends OrgAction
{
    use WithAgentOrganisation;
    use WithProcurementAuthorisation;

    private Agent $agent;

    public static function openOrders(Agent $agent): BaseBuilder
    {
        return DB::table('purchase_orders')
            ->where('purchase_orders.agent_id', $agent->id)
            ->where('purchase_orders.currency_id', $agent->organisation->currency_id)
            ->where('purchase_orders.parent_type', 'OrgSupplier')
            ->where(fn (BaseBuilder $query) => $query
                ->whereNotNull('purchase_orders.agent_order_reference')
                ->orWhere('purchase_orders.created_at', '>=', PurchaseOrder::AGENT_TRACKING_STARTED_AT))
            ->whereNotNull('purchase_orders.supplier_id')
            ->whereIn('purchase_orders.state', PurchaseOrderStateEnum::openValues())
            ->whereNull('purchase_orders.deleted_at');
    }

    public function handle(Agent $agent, ?string $prefix = null): LengthAwarePaginator
    {
        $shared = self::openOrders($agent)
            ->groupBy('purchase_orders.supplier_id')
            ->havingRaw('count(distinct purchase_orders.organisation_id) > 1')
            ->select('purchase_orders.supplier_id');

        return QueryBuilder::for(Supplier::class)
            ->whereIn('suppliers.id', $shared)
            ->select(['suppliers.id', 'suppliers.slug', 'suppliers.code', 'suppliers.name'])
            ->selectSub(self::openOrders($agent)->whereColumn('purchase_orders.supplier_id', 'suppliers.id')->selectRaw('coalesce(sum(purchase_orders.cost_items), 0)'), 'total')
            ->defaultSort('-total')
            ->allowedSorts(['code', 'name', 'total'])
            ->allowedFilters([AllowedFilter::callback('global', fn ($query, $value) => $query->where(fn ($query) => $query
                ->whereAnyWordStartWith('suppliers.code', $value)
                ->orWhereAnyWordStartWith('suppliers.name', $value)))])
            ->withPaginator($prefix, tableName: request()->route()?->getName())
            ->withQueryString();
    }

    /**
     * @return Collection<int, Organisation>
     */
    private function clients(): Collection
    {
        return Organisation::whereIn('id', OrgAgent::where('agent_id', $this->agent->id)->select('organisation_id'))->orderBy('code')->get(['id', 'code', 'name']);
    }

    public function tableStructure(?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table->withGlobalSearch()
                ->withEmptyState(['title' => __('No factory has open orders from more than one client')])
                ->column(key: 'code', label: __('Supplier'), canBeHidden: false, sortable: true);

            foreach ($this->clients() as $client) {
                $table->column(key: 'client_'.$client->id, label: $client->code, canBeHidden: false, align: 'right');
            }

            $table->column(key: 'total', label: __('Total'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'moq', label: __('Minimum order'), canBeHidden: false)
                ->column(key: 'orders', label: __('Orders'), canBeHidden: false)
                ->column(key: 'actions', label: '', canBeHidden: false, align: 'right')
                ->defaultSort('-total');
        };
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);
        $agent = $this->getOrganisationAgent($organisation);
        abort_unless($agent, 404);
        $this->agent = $agent;

        return $this->handle($agent);
    }

    public function htmlResponse(LengthAwarePaginator $suppliers, ActionRequest $request): Response
    {
        $supplierIds = $suppliers->getCollection()->pluck('id')->all();
        $clients     = $this->clients()->keyBy('id');

        $orders = self::openOrders($this->agent)
            ->whereIn('purchase_orders.supplier_id', $supplierIds)
            ->join('organisations', 'organisations.id', 'purchase_orders.organisation_id')
            ->orderBy('purchase_orders.date')
            ->get(['purchase_orders.supplier_id', 'purchase_orders.organisation_id', 'purchase_orders.slug', 'purchase_orders.reference', 'purchase_orders.state', 'purchase_orders.date', 'purchase_orders.cost_items', 'organisations.code as client_code'])
            ->groupBy('supplier_id');

        $belowMinimum = DB::table('purchase_order_transactions')
            ->joinSub(self::openOrders($this->agent)->whereIn('purchase_orders.supplier_id', $supplierIds)->select('purchase_orders.id', 'purchase_orders.supplier_id'), 'open_orders', 'open_orders.id', 'purchase_order_transactions.purchase_order_id')
            ->join('supplier_products', 'supplier_products.id', 'purchase_order_transactions.supplier_product_id')
            ->whereNull('purchase_order_transactions.deleted_at')
            ->whereNotIn('purchase_order_transactions.state', [PurchaseOrderTransactionStateEnum::CANCELLED->value, PurchaseOrderTransactionStateEnum::NOT_RECEIVED->value])
            ->where('supplier_products.minimum_carton_order', '>', 0)
            ->where('supplier_products.units_per_carton', '>', 0)
            ->groupBy('open_orders.supplier_id', 'supplier_products.id', 'supplier_products.minimum_carton_order', 'supplier_products.units_per_carton')
            ->selectRaw('open_orders.supplier_id, sum(purchase_order_transactions.quantity_ordered) / supplier_products.units_per_carton < supplier_products.minimum_carton_order as is_below')
            ->get()
            ->groupBy('supplier_id');

        return Inertia::render(
            'Procurement/AgentSharedSuppliers',
            [
                'breadcrumbs' => array_merge(
                    ShowProcurementDashboard::make()->getBreadcrumbs(['organisation' => $this->organisation->slug]),
                    [[
                        'type'   => 'simple',
                        'simple' => [
                            'label' => __('Shared suppliers'),
                            'icon'  => 'fal fa-people-carry',
                            'route' => ['name' => 'grp.org.agent.purchase_orders.shared_suppliers', 'parameters' => [$this->organisation->slug]],
                        ],
                    ]]
                ),
                'title'         => __('Shared suppliers'),
                'pageHead'      => [
                    'title' => __('Shared suppliers'),
                    'icon'  => ['icon' => ['fal', 'fa-people-carry'], 'title' => __('Shared suppliers')],
                ],
                'currency_code' => $this->organisation->currency->code,
                'clients'       => $clients->map(fn (Organisation $client) => ['id' => $client->id, 'code' => $client->code])->values()->all(),
                'data'          => $suppliers->through(function (Supplier $supplier) use ($orders, $belowMinimum, $clients) {
                    $supplierOrders = $orders->get($supplier->id, collect());
                    $products       = $belowMinimum->get($supplier->id, collect());

                    return array_merge(
                        $clients->mapWithKeys(fn (Organisation $client, int $id) => [
                            'client_'.$id => round((float) $supplierOrders->where('organisation_id', $id)->sum('cost_items'), 2),
                        ])->all(),
                        [
                            'id'                     => $supplier->id,
                            'code'                   => $supplier->code,
                            'name'                   => $supplier->name,
                            'total'                  => round((float) $supplier->total, 2),
                            'products_with_minimum'  => $products->count(),
                            'products_below_minimum' => $products->where('is_below', true)->count(),
                            'orders'                 => $supplierOrders->map(fn ($order) => [
                                'client_code'           => $order->client_code,
                                'reference'             => $order->reference,
                                'state_label'           => PurchaseOrderStateEnum::from($order->state)->labels()[$order->state],
                                'date'                  => $order->date,
                                'amount'                => round((float) $order->cost_items, 2),
                                'route'                 => ['name' => 'grp.org.agent.purchase_orders.show', 'parameters' => [$this->organisation->slug, $order->slug]],
                            ])->values()->all(),
                            'sheet_route'            => ['name' => 'grp.org.agent.purchase_orders.shared_suppliers.sheet', 'parameters' => [$this->organisation->slug, $supplier->slug]],
                        ]
                    );
                }),
            ]
        )->table($this->tableStructure());
    }
}
