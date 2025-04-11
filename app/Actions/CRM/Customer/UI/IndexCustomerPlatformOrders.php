<?php
/*
 * author Arya Permana - Kirin
 * created on 11-04-2025-15h-30m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\CRM\Customer\UI;

use App\Actions\Fulfilment\FulfilmentCustomer\UI\ShowFulfilmentCustomerPlatform;
use App\Actions\Fulfilment\WithFulfilmentCustomerPlatformSubNavigation;
use App\Actions\OrgAction;
use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Http\Resources\Fulfilment\PalletReturnsResource;
use App\Http\Resources\Ordering\OrdersResource;
use App\Http\Resources\Sales\OrderResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Ordering\ModelHasPlatform;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexCustomerPlatformOrders extends OrgAction
{
    use WithCustomerPlatformSubNavigation;
    private ModelHasPlatform $modelHasPlatform;

    public function asController(Organisation $organisation, Shop $shop, Customer $customer, ModelHasPlatform $modelHasPlatform, ActionRequest $request): LengthAwarePaginator
    {
        $this->modelHasPlatform = $modelHasPlatform;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($modelHasPlatform);
    }

    public function handle(ModelHasPlatform $modelHasPlatform, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('orders.reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Order::class);
        if ($modelHasPlatform->platform->type == PlatformTypeEnum::AIKU) {
            $queryBuilder->where('orders.customer_id', $modelHasPlatform->model->id);
        } elseif ($modelHasPlatform->platform->type == PlatformTypeEnum::SHOPIFY) {
            $queryBuilder->leftJoin('shopify_user_has_fulfilments', function ($join) {
                $join->on('shopify_user_has_fulfilments.model_id', '=', 'orders.id')
                        ->where('shopify_user_has_fulfilments.model_type', '=', 'Order');
            });
            $queryBuilder->where('shopify_user_has_fulfilments.shopify_user_id', $modelHasPlatform->model->shopifyUser->id);
        } else {
            throw new \Exception('To be implemented');
        }
        $queryBuilder->leftJoin('customers', 'orders.customer_id', '=', 'customers.id');
        $queryBuilder->leftJoin('customer_clients', 'orders.customer_client_id', '=', 'customer_clients.id');

        $queryBuilder->leftJoin('model_has_payments', function ($join) {
            $join->on('orders.id', '=', 'model_has_payments.model_id')
                ->where('model_has_payments.model_type', '=', 'Order');
        })
            ->leftJoin('payments', 'model_has_payments.payment_id', '=', 'payments.id');

        $queryBuilder->leftJoin('currencies', 'orders.currency_id', '=', 'currencies.id');
        $queryBuilder->leftJoin('order_stats', 'orders.id', 'order_stats.order_id');

        return $queryBuilder
            ->defaultSort('orders.id')
            ->select([
                'orders.id', 'orders.reference', 'orders.date', 'orders.state',
                'orders.created_at', 'orders.updated_at', 'orders.slug',
                'orders.net_amount', 'orders.total_amount',
                'customers.name as customer_name', 'customers.slug as customer_slug',
                'customer_clients.name as client_name', 'customer_clients.ulid as client_ulid',
                'payments.state as payment_state', 'payments.status as payment_status',
                'currencies.code as currency_code', 'currencies.id as currency_id',
            ])
            ->allowedSorts(['id', 'reference', 'date'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure($parent, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withModelOperations($modelOperations)
                ->withGlobalSearch();

            $table->column(key: 'state', label: '', canBeHidden: false, searchable: true, type: 'icon');
            $table->column(key: 'reference', label: __('reference'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'date', label: __('date'), canBeHidden: false, sortable: true, searchable: true, type: 'date');
            $table->column(key: 'payment_status', label: __('payment'), canBeHidden: false, searchable: true);
            $table->column(key: 'net_amount', label: __('net'), canBeHidden: false, searchable: true, type: 'currency');
        };
    }

    public function htmlResponse(LengthAwarePaginator $orders, ActionRequest $request): Response
    {
        // $scope = $this->parent;
        $icon       = ['fal', 'fa-user'];
        $title      = $this->modelHasPlatform->model->name;
        $iconRight  = [
            'icon'  => ['fal', 'fa-shopping-cart'],
            'title' => __('orders')
        ];
        $subNavigation = $this->getCustomerPlatformSubNavigation($this->modelHasPlatform, $this->modelHasPlatform->model, $request);

        if ($this->modelHasPlatform->platform->type ==  PlatformTypeEnum::TIKTOK) {
            $afterTitle = [
                'label' => __('Tiktok Orders')
            ];
        } elseif ($this->modelHasPlatform->platform->type ==  PlatformTypeEnum::SHOPIFY) {
            $afterTitle = [
                'label' => __('Shopify Orders')
            ];
        } else {
            $afterTitle = [
                'label' => __('Orders')
            ];
        }


        return Inertia::render(
            'Org/Shop/CRM/CustomerPlatformOrders',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters(),
                ),
                'title'       => __('orders'),
                'pageHead'    => [
                    'title'         => $title,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'icon'          => $icon,
                    'subNavigation' => $subNavigation,
                ],
                'data'        => OrdersResource::collection($orders),

            ]
        )->table($this->tableStructure($this->parent));
    }

    public function getBreadcrumbs($routeName, $routeParameters): array
    {
        return
            array_merge(
                ShowCustomerPlatform::make()->getBreadcrumbs($this->modelHasPlatform, $routeName, $routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.shops.show.crm.customers.show.platforms.show.orders.index',
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Orders'),
                        ]
                    ]
                ]
            );
    }
}
