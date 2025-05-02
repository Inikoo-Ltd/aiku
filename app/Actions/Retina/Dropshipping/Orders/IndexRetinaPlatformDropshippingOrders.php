<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 14 Oct 2024 14:05:52 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Actions\Retina\UI\Dashboard\ShowRetinaDashboard;
use App\Actions\RetinaAction;
use App\Enums\UI\Catalogue\ProductTabsEnum;
use App\Http\Resources\Fulfilment\RetinaDropshippingFulfilmentOrdersResources;
use App\Http\Resources\Ordering\OrdersResource;
use App\InertiaTable\InertiaTable;
use App\Models\CRM\Customer;
use App\Models\CRM\WebUser;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Dropshipping\TiktokUser;
use App\Models\Ordering\Order;
use App\Models\ShopifyUserHasFulfilment;
use App\Models\TiktokUserHasOrder;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexRetinaPlatformDropshippingOrders extends RetinaAction
{
    public function handle(ShopifyUser|Customer|TiktokUser|WebUser $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('pallets.reference', $value)
                    ->orWhereWith('pallets.reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }
        if ($this->platformUser instanceof ShopifyUser) {
            $query = QueryBuilder::for(ShopifyUserHasFulfilment::class);
        } elseif ($this->platformUser instanceof TiktokUser) {
            $query = QueryBuilder::for(TiktokUserHasOrder::class);
        } else {
            $query = QueryBuilder::for(Order::class);
        }

        if ($this->platformUser instanceof ShopifyUser) {
            $query->where('shopify_user_has_fulfilments.shopify_user_id', $parent->id);
        } elseif ($this->platformUser instanceof TiktokUser) {
            $query->where('tiktok_user_has_orders.tiktok_user_id', $parent->id);
        } else {
            $query->where('orders.customer_id', $this->customer->id);
        }

        if (!($this->platformUser instanceof WebUser)) {
            $query->with('model');
        }
        $query->defaultSort('id');

        return $query->defaultSort('id')
            ->allowedSorts(['id'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->is_root;
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);

        $shopifyUser = $request->user()->customer->shopifyUser;

        return $this->handle($shopifyUser);
    }

    public function inPlatform(Platform $platform, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromPlatform($platform, $request);

        return $this->handle($this->platformUser);
    }

    public function inPupil(Platform $platform, ActionRequest $request): LengthAwarePaginator
    {
        $this->platformUser = $request->user();
        $this->asAction = true;
        $this->initialisationFromPupil($request);
        $shopifyUser = $this->shopifyUser;

        return $this->handle($shopifyUser);
    }

    public function htmlResponse(LengthAwarePaginator $orders): Response
    {
        if (!($this->platformUser instanceof WebUser)) {
            $resource = RetinaDropshippingFulfilmentOrdersResources::collection($orders);
        } else {
            $resource = OrdersResource::collection($orders);
        }
        return Inertia::render(
            'Dropshipping/Orders',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => __('Orders'),
                'pageHead'    => [
                    'title' => __('Orders'),
                    'icon'  => 'fal fa-money-bill-wave'
                ],
                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => ProductTabsEnum::navigation()
                ],

                'orders' => $resource
            ]
        )->table($this->tableStructure('orders'));
    }

    public function tableStructure($prefix = null, $modelOperations = []): Closure
    {
        return function (InertiaTable $table) use ($prefix, $modelOperations) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $emptyStateData = [
                'icons' => ['fal fa-pallet'],
                'title' => __("No order exist"),
                'count' => 0
            ];

            $table->withGlobalSearch()
                ->withEmptyState($emptyStateData)
                ->withModelOperations($modelOperations);

            $table ->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon');
            // $table->column(key: 'model', label: __('model'), canBeHidden: false, searchable: true);
            $table->column(key: 'reference', label: __('reference'), canBeHidden: false, searchable: true);

            if ($this->platformUser instanceof ShopifyUser) {
                $table->column(key: 'shopify_order_id', label: __('shopify order id'), canBeHidden: false, searchable: true);
            }

            if ($this->platformUser instanceof TiktokUser) {
                $table->column(key: 'tiktok_order_id', label: __('tiktok order id'), canBeHidden: false, searchable: true);
            }

            // $table->column(key: 'client_name', label: __('client'), canBeHidden: false, searchable: true);
            $table->column(key: 'reason_notes', label: __('reason message'), canBeHidden: false, searchable: true);
            // $table->column(key: 'actions', label: __('actions'), canBeHidden: false, searchable: true);
        };
    }

    public function getBreadcrumbs(): array
    {
        return
            array_merge(
                ShowRetinaDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name' => 'retina.dropshipping.orders.index'
                            ],
                            'label'  => __('Orders'),
                        ]
                    ]
                ]
            );
    }
}
