<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 14 Oct 2024 14:05:52 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Actions\Retina\UI\Dashboard\ShowRetinaDashboard;
use App\Actions\RetinaAction;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Http\Resources\Fulfilment\RetinaDropshippingOrdersResources;
use App\Http\Resources\Helpers\CurrencyResource;
use App\InertiaTable\InertiaTable;
use App\Models\CRM\Customer;
use App\Models\CRM\WebUser;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Dropshipping\ShopifyUserHasFulfilment;
use App\Models\Dropshipping\TiktokUser;
use App\Models\Dropshipping\TiktokUserHasOrder;
use App\Models\Dropshipping\WooCommerceUser;
use App\Models\Ordering\Order;
use App\Services\QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexRetinaDropshippingOrdersInPlatform extends RetinaAction
{
    public function handle(ShopifyUser|Customer|TiktokUser|WebUser|WooCommerceUser $parent, $prefix = null): LengthAwarePaginator
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

            $query->leftJoin('orders', 'orders.id', '=', 'shopify_user_has_fulfilments.model_id');
            if ($this->customer->is_dropshipping) {
                $query->where('shopify_user_has_fulfilments.model_type', '=', class_basename(Order::class));
            }
            $query->addSelect('shopify_user_has_fulfilments.id as platform_order_id');

        } elseif ($this->platformUser instanceof TiktokUser) {
            $query->where('tiktok_user_has_orders.tiktok_user_id', $parent->id);
        } else {
            $query->where('orders.customer_id', $this->customer->id);
            $query->where('orders.state', '!=', OrderStateEnum::CREATING);
        }

        $query->leftJoin('customer_clients', 'customer_clients.id', '=', 'orders.customer_client_id');
        $query->leftJoin('order_stats', 'orders.id', '=', 'order_stats.order_id');

        if (!($this->platformUser instanceof WebUser)) {
            $query->with('model');
        }
        $query->defaultSort('orders.id');

        $query->select([
            'orders.id',
            'orders.date',
            'orders.reference',
            'orders.slug',
            'customer_clients.name as client_name',
            'orders.state',
            'orders.total_amount',
            'order_stats.number_item_transactions as number_item_transactions'
        ]);

        return $query->defaultSort('orders.id')
            ->allowedSorts(['orders.id'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }


    public function asController(Platform $platform, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromPlatform($platform, $request);
        if ($platform->type == PlatformTypeEnum::MANUAL) {
            return IndexRetinaDropshippingOrders::run($this->customer, $platform);
        } else {
            return $this->handle($this->platformUser);
        }
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inPupil(Platform $platform, ActionRequest $request): LengthAwarePaginator
    {
        $this->platform = $platform;
        $this->platformUser = $request->user();
        $this->asAction     = true;
        $this->initialisationFromPupil($request);
        $shopifyUser = $this->shopifyUser;

        return $this->handle($shopifyUser);
    }

    public function htmlResponse(LengthAwarePaginator $orders, ActionRequest $request): Response
    {
        // dd(RetinaDropshippingOrdersResources::collection($orders)->resolve());
        return Inertia::render(
            'Dropshipping/RetinaOrders',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => __('Orders'),
                'pageHead'    => [
                    'title'      => __('Orders'),
                    'icon'       => 'fal fa-shopping-cart',
                    'afterTitle' => [
                        'label' => ' @'.$this->platform->name,
                    ]
                ],

                'currency' => CurrencyResource::make($this->shop->currency)->getArray(),
                'orders'   => RetinaDropshippingOrdersResources::collection($orders)
            ]
        )->table(IndexRetinaDropshippingOrders::make()->tableStructure($this->platform));
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
                            'label' => __('Orders'),
                        ]
                    ]
                ]
            );
    }
}
