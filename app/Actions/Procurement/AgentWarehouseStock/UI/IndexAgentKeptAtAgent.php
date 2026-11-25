<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Nov 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\AgentWarehouseStock\UI;

use App\Actions\GoodsIn\StockDelivery\StoreStockDeliveryFromPurchaseOrder;
use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrderTransaction\UI\IndexPurchaseOrderTransactions;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Http\Resources\Procurement\PurchaseOrderTransactionResource;
use App\InertiaTable\InertiaTable;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Models\SupplyChain\Agent;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * INI-525. Units of purchase order lines the buyer agreed stay at the agent and that are in no container yet, each
 * with Add to shipment.
 */
class IndexAgentKeptAtAgent extends OrgAction
{
    use WithAgentOrganisation;
    use WithProcurementAuthorisation;

    public static function query(Agent $agent): Builder
    {
        return StoreStockDeliveryFromPurchaseOrder::whereHoldsKeptAtAgent(
            PurchaseOrderTransaction::query()
                ->join('purchase_orders', 'purchase_orders.id', 'purchase_order_transactions.purchase_order_id')
                ->where('purchase_orders.agent_id', $agent->id)
                ->whereNull('purchase_orders.deleted_at')
                ->whereIn('purchase_order_transactions.state', [PurchaseOrderTransactionStateEnum::CONFIRMED, PurchaseOrderTransactionStateEnum::SETTLED])
                ->where('purchase_order_transactions.quantity_kept_at_agent', '>', 0)
        );
    }

    public function handle(Agent $agent): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $like = '%'.addcslashes((string) $value, '%_\\').'%';
            $query->where(fn ($query) => $query
                ->whereRaw('supplier_products.code collate "C" ilike ?', [$like])
                ->orWhere('purchase_orders.reference', 'ilike', $like)
                ->orWhere('purchase_orders.parent_name', 'ilike', $like));
        });

        $query = QueryBuilder::for(self::query($agent))
            ->join('organisations', 'organisations.id', 'purchase_order_transactions.organisation_id')
            ->leftJoin('supplier_products', 'supplier_products.id', 'purchase_order_transactions.supplier_product_id')
            ->select('purchase_order_transactions.*')
            ->addSelect([
                'supplier_products.code as code',
                'supplier_products.name as name',
                'organisations.code as organisation_code',
                'purchase_orders.reference as purchase_order_reference',
                'purchase_orders.slug as purchase_order_slug',
                'purchase_orders.parent_name as supplier_name',
            ]);
        IndexPurchaseOrderTransactions::selectAgentWarehouseQuantities($query);

        $rows = $query
            ->defaultSort('code')
            ->allowedSorts(['code', 'organisation_code', 'purchase_order_reference'])
            ->allowedFilters([$globalSearch])
            ->withPaginator(null, tableName: request()->route()?->getName())
            ->withQueryString();

        $userIds   = $rows->getCollection()->flatMap(fn (PurchaseOrderTransaction $transaction) => Arr::pluck(Arr::get($transaction->data, 'kept_at_agent', []), 'user_id'))->filter()->unique();
        $userNames = User::whereIn('id', $userIds)->selectRaw('id, coalesce(contact_name, username) as name')->pluck('name', 'id');

        return $rows->through(function (PurchaseOrderTransaction $transaction) use ($userNames) {
            $agentWarehouse = PurchaseOrderTransactionResource::make($transaction)->agentWarehouse($transaction, request());
            $kept           = collect(Arr::get($transaction->data, 'kept_at_agent', []))->whereNull('undone_at')->values();

            return [
                'id'                       => $transaction->id,
                'code'                     => $transaction->code,
                'name'                     => $transaction->name,
                'organisation_code'        => $transaction->organisation_code,
                'supplier_name'            => $transaction->supplier_name,
                'purchase_order_reference' => $transaction->purchase_order_reference,
                'purchase_order_slug'      => $transaction->purchase_order_slug,
                'quantity'                 => $agentWarehouse['kept_at_agent']['quantity'] ?? 0,
                'notes'                    => $kept->map(fn (array $entry) => [
                    'note' => $entry['note'],
                    'by'   => $userNames->get($entry['user_id'] ?? null),
                    'at'   => $entry['at'],
                ])->all(),
                'agent_warehouse'          => $agentWarehouse,
            ];
        });
    }

    public function tableStructure(): Closure
    {
        return function (InertiaTable $table) {
            $table
                ->withGlobalSearch()
                ->withLabelRecord([__('Product'), __('Products')])
                ->withEmptyState(['title' => __('Nothing kept at the agent')])
                ->column(key: 'organisation_code', label: __('For'), canBeHidden: false, sortable: true)
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true)
                ->column(key: 'supplier_name', label: __('Supplier'), canBeHidden: false)
                ->column(key: 'purchase_order_reference', label: __('Purchase order'), canBeHidden: false, sortable: true)
                ->column(key: 'quantity', label: __('Units'), canBeHidden: false, align: 'right')
                ->column(key: 'notes', label: __('Why'))
                ->column(key: 'actions', label: '', canBeHidden: false);
        };
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);
        $agent = $this->getOrganisationAgent($organisation);
        abort_unless($agent, 404);

        return $this->handle($agent);
    }

    public function htmlResponse(LengthAwarePaginator $rows, ActionRequest $request): Response
    {
        $title = __('Kept at agent');

        return Inertia::render(
            'Org/Procurement/AgentKeptAtAgent',
            [
                'title'       => $title,
                'breadcrumbs' => array_merge(
                    ShowProcurementDashboard::make()->getBreadcrumbs($request->route()->originalParameters()),
                    [
                        [
                            'type'   => 'simple',
                            'simple' => [
                                'label' => __('Warehouse'),
                                'route' => ['name' => 'grp.org.agent.warehouse.index', 'parameters' => [$this->organisation->slug]],
                            ],
                        ],
                        [
                            'type'   => 'simple',
                            'simple' => [
                                'label' => $title,
                                'route' => ['name' => 'grp.org.agent.warehouse.kept_at_agent', 'parameters' => [$this->organisation->slug]],
                            ],
                        ],
                    ]
                ),
                'pageHead'    => [
                    'model' => __('Warehouse'),
                    'icon'  => ['title' => $title, 'icon' => 'fal fa-warehouse'],
                    'title' => $title,
                ],
                'data'        => $rows,
            ]
        )->table($this->tableStructure());
    }
}
