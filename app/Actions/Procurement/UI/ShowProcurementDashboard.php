<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 28 May 2024 12:08:07 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\UI;

use App\Enums\Ordering\PreOrder\PreOrderStateEnum;
use App\Models\Ordering\PreOrder;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Actions\Dashboard\ShowOrganisationDashboard;
use App\Actions\OrgAction;
use App\Actions\Procurement\GetOrganisationStockCoverBuckets;
use App\Actions\Procurement\GetStockOutsHistory;
use App\Actions\Procurement\GetStockOutsPipeline;
use App\Actions\Procurement\GetUncostedStockDeliveriesCard;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\UI\WithInertia;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\Dispatching\Shipper;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SupplyChain\Supplier;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowProcurementDashboard extends OrgAction
{
    use WithProcurementAuthorisation;
    use AsAction;
    use WithInertia;
    use WithAgentOrganisation;

    private const array STOCK_LEVELS_FRESH_AND_STALE_SECONDS = [120, 600];



    public function asController(Organisation $organisation, ActionRequest $request): ActionRequest
    {
        $this->initialisation($organisation, $request);

        return $request;
    }


    private function getDashboardNumbers(): array
    {
        $agent = $this->getOrganisationAgent($this->organisation);

        if (!$agent) {
            $procurementStats = $this->organisation->procurementStats;

            return [
                'suppliers'         => $procurementStats->number_active_independent_org_suppliers,
                'supplier_products' => $procurementStats->number_current_org_supplier_products,
                'purchase_orders'   => $procurementStats->number_purchase_orders,
                'stock_deliveries'  => $procurementStats->number_stock_deliveries,
            ];
        }

        return [
            'suppliers'        => Supplier::where('agent_id', $agent->id)->where('status', true)->count(),
            'purchase_orders'  => PurchaseOrder::where('agent_id', $agent->id)->where('parent_type', 'OrgSupplier')->count(),
            'stock_deliveries' => StockDelivery::where('agent_id', $agent->id)->count(),
        ];
    }

    private function getDashboardCards(array $numbers): array
    {
        $organisation = $this->organisation;

        if ($organisation->type === OrganisationTypeEnum::AGENT) {
            return [
                $this->dashboardCard(__('Purchase Orders'), __('Orders our organisations place with us, one per supplier, for products from our suppliers'), 'fal fa-clipboard-list', $numbers['purchase_orders'], 'indigo', 'grp.org.procurement.purchase_orders.index'),
                $this->dashboardCard(__('Stock Deliveries'), __('Shipments of the ordered goods to our organisations'), 'fal fa-truck-container', $numbers['stock_deliveries'], 'sky', 'grp.org.procurement.stock_deliveries.index'),
                $this->dashboardCard(__('Suppliers'), __('Active suppliers we source products from'), 'fal fa-person-dolly', $numbers['suppliers'], 'emerald', 'grp.org.procurement.org_suppliers.index'),
            ];
        }

        if ($organisation->type !== OrganisationTypeEnum::SHOP) {
            return [
                $this->dashboardCard(__('Suppliers'), __('Active suppliers we buy from directly'), 'fal fa-person-dolly', $numbers['suppliers'], 'emerald', 'grp.org.procurement.org_suppliers.index'),
                $this->dashboardCard(__('Supplier Products'), __('Products we can buy from our suppliers'), 'fal fa-box-usd', $numbers['supplier_products'], 'amber', 'grp.org.procurement.org_supplier_products.index'),
                $this->dashboardCard(__('Purchase Orders'), __('Orders we place with suppliers and agents'), 'fal fa-clipboard-list', $numbers['purchase_orders'], 'indigo', 'grp.org.procurement.purchase_orders.index'),
                $this->dashboardCard(__('Stock Deliveries'), __('Incoming goods from suppliers and agents'), 'fal fa-truck-container', $numbers['stock_deliveries'], 'sky', 'grp.org.procurement.stock_deliveries.index'),
            ];
        }

        $stats = $organisation->procurementStats;
        $openPurchaseOrders = $stats->number_purchase_orders_state_in_process
            + $stats->number_purchase_orders_state_submitted
            + $stats->number_purchase_orders_state_confirmed;
        $preparingDeliveries = $stats->number_stock_deliveries_state_in_process
            + $stats->number_stock_deliveries_state_confirmed
            + $stats->number_stock_deliveries_state_ready_to_ship;
        $receivingDeliveries = $stats->number_stock_deliveries_state_received
            + $stats->number_stock_deliveries_state_checked
            + $stats->number_stock_deliveries_state_booking_in
            + $stats->number_stock_deliveries_state_booked_in;
        $activeDeliveries = $preparingDeliveries
            + $stats->number_stock_deliveries_state_dispatched
            + $receivingDeliveries;

        $preOrdersWaiting = PreOrder::where('organisation_id', $organisation->id)->where('state', PreOrderStateEnum::WAITING_FOR_GOODS)->count();

        return array_filter([
            $this->dashboardCard(
                __('Open purchase orders'),
                __('Purchase orders in process, submitted or confirmed'),
                'fal fa-clipboard-list',
                $openPurchaseOrders,
                'indigo',
                'grp.org.procurement.purchase_orders.index',
                [
                    $this->dashboardMetric(__('In process'), $stats->number_purchase_orders_state_in_process, 'grp.org.procurement.purchase_orders.index', ['elements[state]' => 'in_process']),
                    $this->dashboardMetric(__('Submitted'), $stats->number_purchase_orders_state_submitted, 'grp.org.procurement.purchase_orders.index', ['elements[state]' => 'submitted']),
                    $this->dashboardMetric(__('Confirmed'), $stats->number_purchase_orders_state_confirmed, 'grp.org.procurement.purchase_orders.index', ['elements[state]' => 'confirmed']),
                ],
                ['elements[state]' => 'in_process,submitted,confirmed']
            ),
            $this->dashboardCard(
                __('Deliveries in progress'),
                __('Stock deliveries from preparing to booked in'),
                'fal fa-truck-container',
                $activeDeliveries,
                'sky',
                'grp.org.procurement.stock_deliveries.index',
                [
                    $this->dashboardMetric(__('Preparing'), $preparingDeliveries, 'grp.org.procurement.stock_deliveries.index', ['elements[state]' => 'in_process,confirmed,ready_to_ship']),
                    $this->dashboardMetric(__('In transit'), $stats->number_stock_deliveries_state_dispatched, 'grp.org.procurement.stock_deliveries.index', ['elements[state]' => 'dispatched']),
                    $this->dashboardMetric(__('Receiving'), $receivingDeliveries, 'grp.org.procurement.stock_deliveries.index', ['elements[state]' => 'received,checked,booking_in,booked_in']),
                ],
                ['elements[state]' => 'in_process,confirmed,ready_to_ship,dispatched,received,checked,booking_in,booked_in']
            ),
            $preOrdersWaiting ? $this->dashboardCard(
                __('Pre-orders'),
                __('Customer pre-orders waiting for goods, by supplier'),
                'fal fa-hourglass-half',
                $preOrdersWaiting,
                'amber',
                'grp.org.procurement.pre_orders.index'
            ) : null,
        ]);
    }

    private function getStockLevels(?string $source): array
    {
        $pipeline = GetStockOutsPipeline::run($this->organisation, $source);

        return collect(GetOrganisationStockCoverBuckets::run($this->organisation, null, $source))
            ->map(fn (array $bucket) => [
                'bucket' => $bucket['bucket'],
                'label'  => $bucket['label'],
                'description' => $bucket['description'],
                'tone'   => $bucket['tone'],
                'count'  => $bucket['count'],
                ...($pipeline[$bucket['bucket']] ?? ['in_transit' => 0, 'arrivals' => []]),
                'route'  => $this->dashboardRoute('grp.org.procurement.stock_cover.index', ['elements[cover]' => $bucket['bucket']]),
            ])->values()->all();
    }

    private function dashboardCard(
        string $label,
        string $description,
        string $icon,
        int $value,
        string $tone,
        string $routeName,
        array $metrics = [],
        array $query = []
    ): array {
        return [
            'label'       => $label,
            'description' => $description,
            'icon'        => $icon,
            'value'       => $value,
            'tone'        => $tone,
            'route'       => $this->dashboardRoute($routeName, $query),
            'metrics'     => $metrics,
        ];
    }

    private function dashboardMetric(string $label, int $value, string $routeName, array $query = []): array
    {
        return [
            'label' => $label,
            'value' => $value,
            'route' => $this->dashboardRoute($routeName, $query),
        ];
    }

    private function dashboardRoute(string $routeName, array $query = []): array
    {
        $parameters = ['organisation' => $this->organisation->slug];

        if ($query !== []) {
            $parameters['_query'] = $query;
        }

        return [
            'name'       => $routeName,
            'parameters' => $parameters,
        ];
    }

    public function htmlResponse(ActionRequest $request): Response
    {
        $numbers = $this->getDashboardNumbers();
        $source  = GetOrganisationStockCoverBuckets::make()->source($request->input('source'));

        return Inertia::render(
            'Procurement/ProcurementDashboard',
            [
                'breadcrumbs'  => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'        => __('Procurement'),
                'pageHead'     => [
                    'icon'      => [
                        'icon'  => ['fal', 'fa-box-usd'],
                        'title' => __('Procurement')
                    ],
                    'iconRight' => [
                        'icon'  => ['fal', 'fa-chart-network'],
                        'title' => __('Procurement')
                    ],
                    'title' => __('Procurement'),
                ],

                'shippers' => Shipper::query()->get(),
                'dashboardCards' => array_values(array_filter([GetUncostedStockDeliveriesCard::run($this->organisation), ...$this->getDashboardCards($numbers)])),
                'stockLevels' => $this->organisation->type === OrganisationTypeEnum::SHOP
                    ? Cache::flexible("procurement-dashboard:stock-levels:{$this->organisation->id}:".($source ?? 'all'), self::STOCK_LEVELS_FRESH_AND_STALE_SECONDS, fn () => $this->getStockLevels($source))
                    : [],
                'counterparties' => $this->organisation->type === OrganisationTypeEnum::SHOP
                    ? Inertia::defer(fn () => [
                        'currency' => $this->organisation->currency->code,
                        'cards'    => GetProcurementCounterpartyCards::run($this->organisation),
                    ])
                    : null,
                'stockOuts' => $this->organisation->type === OrganisationTypeEnum::SHOP ? GetStockOutsHistory::run($this->organisation, GetStockOutsHistory::make()->period($request->input('period')), $source) : null,

            ]
        );
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        $organisation = Arr::get($routeParameters, 'organisation');
        $organisation = $organisation instanceof Organisation ? $organisation : Organisation::where('slug', $organisation)->first();

        if ($organisation?->type === OrganisationTypeEnum::AGENT) {
            return ShowOrganisationDashboard::make()->getBreadcrumbs(Arr::only($routeParameters, 'organisation'));
        }

        return
            array_merge(
                ShowOrganisationDashboard::make()->getBreadcrumbs(Arr::only($routeParameters, 'organisation')),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.procurement.dashboard',
                                'parameters' => Arr::only($routeParameters, 'organisation')
                            ],
                            'label' => __('Procurement'),
                        ]
                    ]
                ]
            );
    }


}
