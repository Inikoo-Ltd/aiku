<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 28 May 2024 12:08:07 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\UI;

use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Actions\Dashboard\ShowOrganisationDashboard;
use App\Actions\OrgAction;
use App\Actions\Procurement\GetOrganisationStockCoverBuckets;
use App\Actions\Procurement\OrgPartner\UI\GetPartnerMiniCart;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\Search\GetSearchDemandOpportunities;
use App\Actions\UI\WithInertia;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\Dispatching\Shipper;
use App\Models\GoodsIn\StockDelivery;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\ShoppingListItem;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
            'suppliers'         => OrgSupplier::where('agent_id', $agent->id)->count(),
            'supplier_products' => OrgSupplierProduct::whereIn(
                'org_agent_id',
                OrgAgent::where('agent_id', $agent->id)->select('id')
            )->count(),
            'purchase_orders'   => PurchaseOrder::where('agent_id', $agent->id)->count(),
            'stock_deliveries'  => StockDelivery::where('agent_id', $agent->id)->count(),
        ];
    }

    private function getDashboardCards(array $numbers): array
    {
        $organisation = $this->organisation;

        if ($organisation->type !== OrganisationTypeEnum::SHOP) {
            return [
                $this->dashboardCard(__('Suppliers'), __('Current suppliers'), 'fal fa-person-dolly', $numbers['suppliers'], 'emerald', 'grp.org.procurement.org_suppliers.index'),
                $this->dashboardCard(__('Supplier Products'), __('Current supplier products'), 'fal fa-box-usd', $numbers['supplier_products'], 'amber', 'grp.org.procurement.org_supplier_products.index'),
                $this->dashboardCard(__('Purchase Orders'), __('Purchase orders'), 'fal fa-clipboard-list', $numbers['purchase_orders'], 'indigo', 'grp.org.procurement.purchase_orders.index'),
                $this->dashboardCard(__('Stock Deliveries'), __('Stock deliveries'), 'fal fa-truck-container', $numbers['stock_deliveries'], 'sky', 'grp.org.procurement.stock_deliveries.index'),
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

        return [
            $this->dashboardCard(
                __('Agents'),
                __('Active purchasing agents'),
                'fal fa-people-arrows',
                $stats->number_active_org_agents,
                'violet',
                'grp.org.procurement.org_agents.index',
                [
                    $this->dashboardMetric(
                        __('Agent Suppliers'),
                        $stats->number_active_org_suppliers_in_agents,
                        'grp.org.procurement.org_agent_suppliers.index'
                    ),
                ]
            ),
            $this->dashboardCard(
                __('Suppliers'),
                __('Active free suppliers'),
                'fal fa-person-dolly',
                $stats->number_active_independent_org_suppliers,
                'emerald',
                'grp.org.procurement.org_suppliers.index'
            ),
            $this->dashboardCard(
                __('Supplier Products'),
                __('Current supplier products'),
                'fal fa-box-usd',
                $stats->number_current_org_supplier_products,
                'amber',
                'grp.org.procurement.org_supplier_products.index',
                [
                    $this->dashboardMetric(__('Active'), $stats->number_org_supplier_products_state_active, 'grp.org.procurement.org_supplier_products.index', ['elements[state]' => 'active']),
                    $this->dashboardMetric(__('Discontinuing'), $stats->number_org_supplier_products_state_discontinuing, 'grp.org.procurement.org_supplier_products.index', ['elements[state]' => 'discontinuing']),
                ],
                ['elements[state]' => 'active,discontinuing']
            ),
            $this->dashboardCard(
                __('Purchase Orders'),
                __('Open purchase orders'),
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
                __('Stock Deliveries'),
                __('Deliveries in progress'),
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
        ];
    }

    public function stockOutPeriodOptions(): array
    {
        return [
            '1m'  => __('1 Month'),
            '1q'  => __('1 Quarter'),
            '6m'  => __('6 Months'),
            '1y'  => __('1 Year'),
            '3y'  => __('3 Years'),
            'all' => __('All'),
        ];
    }

    private function getStockOuts(string $period): array
    {
        $today = today();
        $from  = match ($period) {
            '1m'    => $today->copy()->subMonth(),
            '1q'    => $today->copy()->subMonths(3),
            '6m'    => $today->copy()->subMonths(6),
            '1y'    => $today->copy()->subYear(),
            '3y'    => $today->copy()->subYears(3),
            default => null,
        };

        $rows = DB::table('organisation_stock_histories')
            ->where('organisation_id', $this->organisation->id)
            ->when($from, fn ($query) => $query->where('date', '>=', $from->toDateString()))
            ->orderBy('date')
            ->get(['date', 'number_out_of_stock_org_stocks', 'number_org_stocks', 'estimated_lost_revenue_org_currency']);

        $latest = $rows->last() ?? DB::table('organisation_stock_histories')
            ->where('organisation_id', $this->organisation->id)
            ->orderByDesc('date')
            ->first(['date', 'number_out_of_stock_org_stocks', 'number_org_stocks', 'estimated_lost_revenue_org_currency']);

        $from ??= $rows->isNotEmpty() ? Carbon::parse($rows->first()->date) : $today;
        $end  = $latest ? Carbon::parse($latest->date) : $today;
        $unit = match (true) {
            $from->diffInDays($end) <= 92  => 'day',
            $from->diffInDays($end) <= 731 => 'week',
            default                        => 'month',
        };

        $lostTotal = 0.0;
        $series    = $rows->groupBy(fn ($row) => Carbon::parse($row->date)->startOf($unit)->toDateString())
            ->map(function ($bucketRows, string $bucketStart) use ($unit, $from, $end, &$lostTotal) {
                $skos        = $bucketRows->sum('number_org_stocks');
                $lostRows    = $bucketRows->whereNotNull('estimated_lost_revenue_org_currency');
                $lostPerDay  = $lostRows->isEmpty() ? null : round($lostRows->avg('estimated_lost_revenue_org_currency'), 2);
                $bucketStart = Carbon::parse($bucketStart);
                $daysCovered = (int)$bucketStart->copy()->max($from)->diffInDays($bucketStart->copy()->endOf($unit)->min($end)) + 1;
                $lostTotal   += ($lostPerDay ?? 0) * $daysCovered;

                return [
                    'date'         => $bucketStart->toDateString(),
                    'out_of_stock' => (int)round($bucketRows->avg('number_out_of_stock_org_stocks')),
                    'percentage'   => $skos ? round($bucketRows->sum('number_out_of_stock_org_stocks') / $skos * 100, 1) : 0,
                    'lost_per_day' => $lostPerDay,
                ];
            })->values()->all();

        return [
            'period'     => $period,
            'periods'    => $this->stockOutPeriodOptions(),
            'unit'       => $unit,
            'currency'   => $this->organisation->currency->code,
            'lost_total' => round($lostTotal),
            'now'        => $latest ? [
                'date'         => $latest->date,
                'out_of_stock' => (int)$latest->number_out_of_stock_org_stocks,
                'percentage'   => $latest->number_org_stocks ? round($latest->number_out_of_stock_org_stocks / $latest->number_org_stocks * 100, 1) : 0,
                'lost_per_day' => $latest->estimated_lost_revenue_org_currency === null ? null : (float)$latest->estimated_lost_revenue_org_currency,
            ] : null,
            'series'     => $series,
        ];
    }

    private function getStockLevels(): array
    {
        return collect(GetOrganisationStockCoverBuckets::run($this->organisation))
            ->map(fn (array $bucket) => [
                'bucket' => $bucket['bucket'],
                'label'  => $bucket['label'],
                'tone'   => $bucket['tone'],
                'count'  => $bucket['count'],
                'route'  => $this->dashboardRoute('grp.org.procurement.stock_cover.index', ['elements[cover]' => $bucket['bucket']]),
            ])->values()->all();
    }

    private function getShoppingLists(): array
    {
        $withItems = [];
        $empty     = [];

        foreach (OrgPartner::where('organisation_id', $this->organisation->id)->get() as $orgPartner) {
            $miniCart = GetPartnerMiniCart::run($orgPartner);

            if ($miniCart['count'] > 0) {
                $withItems[] = $miniCart;

                continue;
            }

            $shopId = Arr::get($orgPartner->partner->settings, 'procurement.shop_id');

            $empty[] = [
                'name'  => $miniCart['partner_name'],
                'route' => [
                    'name'       => $shopId ? 'grp.org.procurement.org_partners.show.browse.index' : 'grp.org.procurement.org_partners.show.shopping_list.index',
                    'parameters' => [$this->organisation->slug, $orgPartner->id],
                ],
            ];
        }

        foreach (OrgAgent::where('organisation_id', $this->organisation->id)->where('status', true)->with('agent')->get() as $orgAgent) {
            $openItems = ShoppingListItem::query()
                ->join('supplier_products', 'supplier_products.id', 'shopping_list_items.supplier_product_id')
                ->where('shopping_list_items.organisation_id', $this->organisation->id)
                ->where('shopping_list_items.agent_id', $orgAgent->agent_id)
                ->where('shopping_list_items.state', ShoppingListItemStateEnum::OPEN->value)
                ->select([
                    'shopping_list_items.id',
                    'shopping_list_items.quantity_units',
                    'supplier_products.code',
                    'supplier_products.name',
                ])
                ->orderByDesc('shopping_list_items.created_at')
                ->get();

            if ($openItems->isEmpty()) {
                $empty[] = [
                    'name'  => $orgAgent->agent->name,
                    'route' => $this->dashboardRoute('grp.org.procurement.shopping_list.index'),
                ];

                continue;
            }

            $withItems[] = [
                'partner_name' => $orgAgent->agent->name,
                'count'        => $openItems->count(),
                'total'        => 0,
                'currency'     => $this->organisation->currency->code,
                'items'        => $openItems->take(10)->map(fn (ShoppingListItem $item) => [
                    'id'             => $item->id,
                    'quantity'       => $item->quantity_units,
                    'org_stock_code' => $item->code,
                    'org_stock_name' => $item->name,
                    'family_name'    => null,
                ])->values()->all(),
                'listRoute'    => $this->dashboardRoute('grp.org.procurement.shopping_list.index'),
            ];
        }

        return [
            'withItems' => $withItems,
            'empty'     => $empty,
        ];
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

    private function stockOutPeriod(ActionRequest $request): string
    {
        $period = (string)$request->input('period');

        return array_key_exists($period, $this->stockOutPeriodOptions()) ? $period : '1y';
    }

    public function htmlResponse(ActionRequest $request): Response
    {
        $numbers = $this->getDashboardNumbers();

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
                'search_demand' => GetSearchDemandOpportunities::run($this->group, $this->organisation),
                'dashboardCards' => $this->getDashboardCards($numbers),
                'shoppingLists' => $this->getShoppingLists(),
                'stockLevels' => $this->organisation->type === OrganisationTypeEnum::SHOP ? $this->getStockLevels() : [],
                'stockOuts' => $this->organisation->type === OrganisationTypeEnum::SHOP ? $this->getStockOuts($this->stockOutPeriod($request)) : null,

            ]
        );
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
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
