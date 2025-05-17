<?php

/*
 * author Arya Permana - Kirin
 * created on 11-04-2025-15h-30m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Ordering\Order\UI;

use App\Actions\Dropshipping\CustomerHasPlatforms\UI\ShowCustomerHasPlatform;
use App\Actions\Dropshipping\CustomerHasPlatforms\UI\WithCustomerHasPlatformSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMAuthorisation;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Http\Resources\Ordering\OrdersResource;
use App\Http\Resources\Platform\PlatformsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\CRM\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use UnexpectedValueException;

class IndexOrdersInCustomerHasPlatform extends OrgAction
{
    use WithCustomerHasPlatformSubNavigation;
    use WithCRMAuthorisation;

    private CustomerSalesChannel $customerHasPlatform;

    public function asController(Organisation $organisation, Shop $shop, Customer $customer, Platform $platform, ActionRequest $request): LengthAwarePaginator
    {
        $customerHasPlatform = CustomerSalesChannel::where('customer_id', $customer->id)->where('platform_id', $platform->id)->first();
        $this->customerHasPlatform = $customerHasPlatform;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($customerHasPlatform);
    }

    public function handle(CustomerSalesChannel $customerHasPlatform, $prefix = null): LengthAwarePaginator
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
        if ($customerHasPlatform->platform->type == PlatformTypeEnum::MANUAL) {
            $queryBuilder->where('orders.customer_id', $customerHasPlatform->customer->id);
        } elseif ($customerHasPlatform->platform->type == PlatformTypeEnum::SHOPIFY) {
            $queryBuilder->leftJoin('shopify_user_has_fulfilments', function ($join) {
                $join->on('shopify_user_has_fulfilments.model_id', '=', 'orders.id')
                        ->where('shopify_user_has_fulfilments.model_type', '=', 'Order');
            });
            $queryBuilder->where('shopify_user_has_fulfilments.shopify_user_id', $customerHasPlatform->customer->shopifyUser->id);
        } else {
            throw new UnexpectedValueException('To be implemented');
        }


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
                'payments.state as payment_state', 'payments.status as payment_status',
                'currencies.code as currency_code', 'currencies.id as currency_id',
            ])
            ->allowedSorts(['id', 'reference', 'date'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix) {
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
        $icon       = ['fal', 'fa-user'];
        $title         = $this->customerHasPlatform->customer->name.' ('.$this->customerHasPlatform->customer->reference.')';
        $iconRight  = [
            'icon'  => ['fal', 'fa-shopping-cart'],
            'title' => __('orders').' @'.$this->customerHasPlatform->platform->name,
        ];
        $subNavigation = $this->getCustomerPlatformSubNavigation(
            $this->customerHasPlatform,
            $request
        );
        $actions = [];

        $afterTitle = [
            'label' => __('orders').' @'.$this->customerHasPlatform->platform->name,
        ];
        if ($this->customerHasPlatform->platform->type ==  PlatformTypeEnum::MANUAL) {

            $actions[] = [
                    'type'        => 'button',
                    'style'       => 'create',
                    'label'       => 'Add order',
                    'key'         => 'add_order',
                    'fullLoading' => true,
                    'route'       => [
                        'method'     => 'post',
                        'name'       => 'grp.models.customer.platform-order.store',
                        'parameters' => [
                            'customer' => $this->customerHasPlatform->customer_id,
                            'platform' => $this->customerHasPlatform->platform_id
                        ]
                    ]
            ];
        }


        return Inertia::render(
            'Org/Dropshipping/OrdersInCustomerHasPlatform',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $this->customerHasPlatform->platform,
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
                    'actions'       => $actions
                ],
                'platform' => PlatformsResource::make($this->customerHasPlatform->platform),
                'data'        => OrdersResource::collection($orders),

            ]
        )->table($this->tableStructure());
    }

    public function getBreadcrumbs(Platform $platform, $routeName, $routeParameters): array
    {
        return
            array_merge(
                ShowCustomerHasPlatform::make()->getBreadcrumbs($platform, $routeName, $routeParameters),
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
