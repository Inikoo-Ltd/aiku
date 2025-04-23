<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 23-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Ordering\Order\UI;

use App\Actions\OrgAction;
use App\Actions\Overview\ShowOrganisationOverviewHub;
use App\Actions\Traits\Authorisations\Inventory\WithOrganisationOverviewAuthorisation;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\UI\Ordering\OrdersInBasketTabsEnum;
use App\Http\Resources\Ordering\OrdersResource;
use App\Http\Resources\Sales\OrderResource;
use App\InertiaTable\InertiaTable;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexOrdersInBasketInOrganisation extends OrgAction
{
    use WithOrganisationOverviewAuthorisation;
    use HasIndexOrdersInBasket;

    public function handle(Organisation $organisation, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('orders.reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }


        $query = QueryBuilder::for(Order::class);
        $query->where('orders.state', OrderStateEnum::CREATING);

        $query->where('orders.organisation_id', $organisation->id);
        $query->leftJoin('customers', 'orders.customer_id', '=', 'customers.id');
        $query->leftJoin('organisations', 'orders.organisation_id', '=', 'organisations.id');
        $query->leftJoin('shops', 'orders.shop_id', '=', 'shops.id');


        return $query->defaultSort('-date')
            ->select([
                'orders.id',
                'orders.reference',
                'orders.date',
                'orders.state',
                'orders.slug',
                'orders.net_amount',
                'orders.total_amount',
                'orders.currency_id',
                'orders.created_at',
                'orders.updated_by_customer_at',
                'customers.name as customer_name',
                'customers.slug as customer_slug',
                'shops.name as shop_name',
                'shops.code as shop_code',
                'shops.slug as shop_slug',
                'organisations.name as organisation_name',
                'organisations.code as organisation_code',
                'organisations.slug as organisation_slug',
            ])
            ->allowedSorts(['id', 'reference', 'date','shop_code', 'customer_name', 'net_amount'])
            ->withBetweenDates(['-date'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }



    public function jsonResponse(LengthAwarePaginator $orders): AnonymousResourceCollection
    {
        return OrdersResource::collection($orders);
    }

    public function htmlResponse(LengthAwarePaginator $orders, ActionRequest $request): Response
    {
        $navigation = OrdersInBasketTabsEnum::navigation();
        $subNavigation = null;


        $title      = __('Orders in Basket');
        $model      = '';
        $icon       = [
            'icon'  => ['fal', 'fa-shopping-cart'],
            'title' => $title
        ];
        $afterTitle = $this->organisation->name;
        $iconRight  = 'fal fa-building';
        $actions    = null;


        return Inertia::render(
            'Ordering/Orders',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => $title,
                'pageHead'    => [
                    'title'         => $title,
                    'icon'          => $icon,
                    'model'         => $model,
                    'afterTitle'    => [
                        'label' => $afterTitle,
                        'icon'  => $iconRight,
                    ],
                    'iconRight'     => $iconRight,
                    'subNavigation' => $subNavigation,
                    'actions'       => $actions
                ],
                'data'        => OrderResource::collection($orders),

                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => $navigation,
                ],


                OrdersInBasketTabsEnum::ORDERS->value => $this->tab == OrdersInBasketTabsEnum::ORDERS->value ?
                    fn () => OrdersResource::collection($orders)
                    : Inertia::lazy(fn () => OrdersResource::collection($orders)),
            ]
        )->table($this->tableStructure($this->organisation, OrdersInBasketTabsEnum::ORDERS->value));
    }


    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request)->withTab(OrdersInBasketTabsEnum::values());

        return $this->handle($organisation, prefix: OrdersInBasketTabsEnum::ORDERS->value);
    }


    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = []) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Orders'),
                        'icon'  => 'fal fa-bars'
                    ],
                ],
            ];
        };

        return array_merge(
            ShowOrganisationOverviewHub::make()->getBreadcrumbs($routeParameters),
            $headCrumb(
                [
                    'name'       => $routeName,
                    'parameters' => $routeParameters
                ]
            )
        );
    }
}
