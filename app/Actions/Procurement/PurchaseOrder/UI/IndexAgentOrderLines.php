<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 29 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrder\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\AgentWarehouseStock\UI\WithAgentWarehouseProductRows;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStageEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\InertiaTable\InertiaTable;
use App\Models\GoodsIn\StockDeliveryItem;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Models\SupplyChain\Agent;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedSort;

/**
 * The product lines behind one number of the agent's orders backlog: the purchase order lines of a purchase order
 * stage, or the shipment lines of a shipment stage, so the lines box next to each count opens what it counted.
 */
class IndexAgentOrderLines extends OrgAction
{
    use WithAgentOrganisation;
    use WithProcurementAuthorisation;
    use WithAgentWarehouseProductRows;

    private Agent $agent;

    private string $stage;

    private ?string $factoryStage;

    private function isShipmentStage(): bool
    {
        return isset(ShowAgentOrdersBacklog::SHIPMENT_STAGES[$this->stage]);
    }

    private function lines(): Builder
    {
        if ($this->isShipmentStage()) {
            $shipmentLines = DB::table('stock_delivery_items')
                ->join('stock_deliveries', 'stock_deliveries.id', 'stock_delivery_items.stock_delivery_id')
                ->where('stock_deliveries.agent_id', $this->agent->id)
                ->whereNull('stock_deliveries.deleted_at')
                ->whereIn('stock_deliveries.state', ShowAgentOrdersBacklog::SHIPMENT_STAGES[$this->stage][0])
                ->where('stock_delivery_items.state', '!=', StockDeliveryItemStateEnum::CANCELLED->value)
                ->select([
                    'stock_delivery_items.id',
                    'stock_delivery_items.organisation_id',
                    'stock_delivery_items.supplier_product_id',
                    DB::raw('null::bigint as purchase_order_id'),
                    'stock_delivery_items.unit_quantity as quantity',
                    'stock_deliveries.reference as container_reference',
                    'stock_deliveries.slug as container_slug',
                ]);

            return StockDeliveryItem::query()->fromSub($shipmentLines, 'lines')->select(['lines.id', 'lines.quantity', 'lines.container_reference', 'lines.container_slug']);
        }

        $purchaseOrders = DB::table('purchase_orders')
            ->select('purchase_orders.id')
            ->where('purchase_orders.agent_id', $this->agent->id)
            ->whereNull('purchase_orders.deleted_at')
            ->whereIn('purchase_orders.state', ['submitted', 'confirmed'])
            ->whereIn(DB::raw(PurchaseOrderStageEnum::progressSql()), ShowAgentOrdersBacklog::PURCHASE_ORDER_STAGES[$this->stage][0])
            ->when($this->factoryStage, fn ($query) => $query->whereRaw(PurchaseOrderStageEnum::factoryStageSql().' = ?', [$this->factoryStage]));

        $purchaseOrderLines = DB::table('purchase_order_transactions')
            ->whereIn('purchase_order_transactions.purchase_order_id', $purchaseOrders)
            ->whereNotIn('purchase_order_transactions.state', [PurchaseOrderTransactionStateEnum::CANCELLED->value, PurchaseOrderTransactionStateEnum::NOT_RECEIVED->value])
            ->select([
                'purchase_order_transactions.id',
                'purchase_order_transactions.organisation_id',
                'purchase_order_transactions.supplier_product_id',
                'purchase_order_transactions.purchase_order_id',
                'purchase_order_transactions.quantity_ordered as quantity',
            ]);

        return PurchaseOrderTransaction::query()->fromSub($purchaseOrderLines, 'lines')->select(['lines.id', 'lines.quantity']);
    }

    public function handle(): LengthAwarePaginator
    {
        return $this->withProductImages(QueryBuilder::for($this->joinProductRowDetails($this->lines(), 'lines'))
            ->defaultSort('code')
            ->allowedSorts([
                AllowedSort::field('code', 'supplier_products.code'),
                AllowedSort::field('name', 'supplier_products.name'),
                AllowedSort::field('supplier_code', 'suppliers.code'),
                AllowedSort::field('organisation_code', 'organisations.code'),
                AllowedSort::field('quantity', 'lines.quantity'),
            ])
            ->allowedFilters($this->productRowFilters())
            ->withPaginator(null, tableName: request()->route()?->getName())
            ->withQueryString()
            ->through(fn ($line) => array_merge($this->productRow($line), [
                'id'                  => $line->id,
                'container_reference' => $line->container_reference ?? null,
                'container_slug'      => $line->container_slug ?? null,
                'location_icon'       => ['icon' => 'fal fa-truck-container'],
            ])));
    }

    public function tableStructure(): Closure
    {
        return function (InertiaTable $table) {
            $table
                ->withGlobalSearch()
                ->withLabelRecord([__('Product line'), __('Product lines')])
                ->withEmptyState(['title' => __('No product lines here')])
                ->column(key: 'organisation_code', label: __('Client'), canBeHidden: false, sortable: true)
                ->column(key: 'image_thumbnail', label: __('Image'), canBeHidden: false)
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true)
                ->column(key: 'supplier_code', label: __('Supplier'), canBeHidden: false, sortable: true)
                ->column(key: $this->isShipmentStage() ? 'location' : 'purchase_order_reference', label: $this->isShipmentStage() ? __('Shipment') : __('Purchase order'), canBeHidden: false)
                ->column(key: 'quantity', label: __('Units'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'skos', label: __('SKO'), align: 'right')
                ->defaultSort('code');
        };
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);
        $agent = $this->getOrganisationAgent($organisation);
        abort_unless($agent, 404);
        $this->agent = $agent;

        $this->stage = (string) $request->input('stage');
        abort_unless(isset(ShowAgentOrdersBacklog::PURCHASE_ORDER_STAGES[$this->stage]) || $this->isShipmentStage(), 404);
        $this->factoryStage = $this->isShipmentStage() ? null : $request->input('factory');
        abort_if($this->factoryStage && !isset(PurchaseOrderStageEnum::factoryStageLabels()[$this->factoryStage]), 404);

        return $this->handle();
    }

    public function htmlResponse(LengthAwarePaginator $rows, ActionRequest $request): Response
    {
        $stages = $this->isShipmentStage() ? ShowAgentOrdersBacklog::SHIPMENT_STAGES : ShowAgentOrdersBacklog::PURCHASE_ORDER_STAGES;
        $title  = __($stages[$this->stage][1]).($this->factoryStage ? ': '.PurchaseOrderStageEnum::factoryStageLabels()[$this->factoryStage] : '');

        $organisationCounts = $this->joinProductRowDetails($this->lines(), 'lines')
            ->leftJoin('countries', 'countries.id', 'organisations.country_id')
            ->reorder()
            ->select([])
            ->selectRaw('organisations.slug, organisations.code, organisations.name, countries.name as country_name, count(*) as total')
            ->groupBy('organisations.slug', 'organisations.code', 'organisations.name', 'countries.name')
            ->toBase()
            ->get();

        return Inertia::render(
            'Org/Procurement/AgentWarehouseProducts',
            [
                'title'         => $title,
                'kind'          => 'order_lines',
                'breadcrumbs'   => array_merge(
                    ShowProcurementDashboard::make()->getBreadcrumbs($request->route()->originalParameters()),
                    [
                        [
                            'type'   => 'simple',
                            'simple' => [
                                'label' => __('Orders'),
                                'route' => [
                                    'name'       => 'grp.org.agent.orders.index',
                                    'parameters' => [$this->organisation->slug],
                                ],
                            ],
                        ],
                    ]
                ),
                'pageHead'      => [
                    'model' => __('Product lines'),
                    'icon'  => ['title' => $title, 'icon' => $stages[$this->stage][2]],
                    'title' => $title,
                ],
                'data'          => $rows,
                'filterOptions' => ['organisation' => $this->organisationOptions($organisationCounts)],
            ]
        )->table($this->tableStructure());
    }
}
