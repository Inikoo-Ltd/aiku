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
use App\Http\Resources\Fulfilment\RetinaDropshippingOrdersInPlatformResources;
use App\Http\Resources\Helpers\CurrencyResource;
use App\InertiaTable\InertiaTable;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Ordering\Order;
use App\Services\QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexRetinaDropshippingOrdersInPlatform extends RetinaAction
{
    private CustomerSalesChannel $customerSalesChannel;
    public function handle(CustomerSalesChannel $customerSalesChannel, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('orders.reference', $value)
                    ->orWhereWith('orders.reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(Order::class);
        $query->where('orders.platform_id', $customerSalesChannel->platform->id);
        $query->where('orders.customer_sales_channel_id', $customerSalesChannel->id);
        $query->whereNotIn('orders.state', [OrderStateEnum::CREATING, OrderStateEnum::CANCELLED]);

        $query->leftJoin('currencies', 'orders.currency_id', '=', 'currencies.id');
        $query->leftJoin('order_stats', 'orders.id', '=', 'order_stats.order_id');

        $query->select(
            'orders.id',
            'orders.slug',
            'orders.reference',
            'orders.state',
            'orders.customer_reference',
            'order_stats.number_item_transactions as number_item_transactions',
            'orders.date',
            'orders.total_amount',
            'currencies.code as currency_code',
        );
        return $query->defaultSort('id')
            ->allowedSorts(['id'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }


    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): LengthAwarePaginator
    {
        $this->customerSalesChannel = $customerSalesChannel;
        $this->initialisationFromPlatform($customerSalesChannel->platform, $request);
        return $this->handle($customerSalesChannel);
    }

    // /** @noinspection PhpUnusedParameterInspection */
    // public function inPupil(Platform $platform, ActionRequest $request): LengthAwarePaginator
    // {
    //     $this->platform = $platform;
    //     $this->platformUser = $request->user();
    //     $this->asAction     = true;
    //     $this->initialisationFromPupil($request);
    //     $shopifyUser = $this->shopifyUser;

    //     return $this->handle($shopifyUser);
    // }

    public function htmlResponse(LengthAwarePaginator $orders, ActionRequest $request): Response
    {
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
                'orders'   => RetinaDropshippingOrdersInPlatformResources::collection($orders)
            ]
        )->table(IndexRetinaDropshippingOrders::make()->tableStructure($this->platform, 'orders'));
    }


    public function getBreadcrumbs(): array
    {
        return
            array_merge(
                ShowRetinaDashboard::make()->getBreadcrumbs(),
                // [
                //     [
                //         'type'   => 'simple',
                //         'simple' => [
                //             'route' => [
                //                 'name' => 'retina.dropshipping.orders.index'
                //             ],
                //             'label' => __('Orders'),
                //         ]
                //     ]
                // ]
            );
    }
}
