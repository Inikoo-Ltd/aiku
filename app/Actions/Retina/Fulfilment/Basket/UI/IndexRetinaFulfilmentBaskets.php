<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 16 Oct 2024 10:47:26 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Fulfilment\Basket\UI;

use App\Actions\Retina\UI\Dashboard\ShowRetinaDashboard;
use App\Actions\RetinaAction;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Http\Resources\Fulfilment\PalletReturnsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Fulfilment\PalletReturn;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use UnexpectedValueException;

class IndexRetinaFulfilmentBaskets extends RetinaAction
{
    private CustomerSalesChannel $customerSalesChannel;
    public function handle(CustomerSalesChannel $customerSalesChannel, $prefix = null): \Illuminate\Pagination\LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('pallet_returns.reference', $value)
                    ->orWhereWith('pallet_returns.reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(PalletReturn::class);
        $query->where('pallet_returns.type', PalletReturnTypeEnum::STORED_ITEM);
        $query->where('pallet_returns.state', PalletReturnStateEnum::IN_PROCESS);
        $query->where('pallet_returns.platform_id', $customerSalesChannel->platform->id);
        $query->where('pallet_returns.customer_sales_channel_id', $customerSalesChannel->id);
        if ($customerSalesChannel->platform->type == PlatformTypeEnum::MANUAL) {
            $query->where('pallet_returns.fulfilment_customer_id', $customerSalesChannel->customer->fulfilmentCustomer->id);
        } elseif ($customerSalesChannel->platform->type == PlatformTypeEnum::SHOPIFY) {
            $query->leftJoin('shopify_user_has_fulfilments', function ($join) {
                $join->on('shopify_user_has_fulfilments.model_id', '=', 'pallet_returns.id')
                        ->where('shopify_user_has_fulfilments.model_type', '=', 'PalletReturn');
            });
        } else {
            throw new UnexpectedValueException('To be implemented');
        }
        $query->leftJoin('currencies', 'pallet_returns.currency_id', '=', 'currencies.id');
        $query->leftJoin('pallet_return_stats', 'pallet_returns.id', '=', 'pallet_return_stats.pallet_return_id');

        $query->select(
            'pallet_returns.id',
            'pallet_returns.slug',
            'pallet_returns.reference',
            'pallet_returns.state',
            'pallet_returns.type',
            'pallet_returns.customer_reference',
            'pallet_return_stats.number_pallets as number_pallets',
            'pallet_return_stats.number_services as number_services',
            'pallet_return_stats.number_physical_goods as number_physical_goods',
            'pallet_returns.date',
            'pallet_returns.total_amount',
            'pallet_returns.created_at',
            'currencies.code as currency_code',
        );
        return $query->defaultSort('id')
            ->allowedSorts(['id'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->customerSalesChannel->customer_id == $this->customer->id) {
            return  true;
        }
        return false;
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): LengthAwarePaginator
    {
        $this->customerSalesChannel = $customerSalesChannel;
        $this->platform = $customerSalesChannel->platform;
        $this->initialisation($request);
        return $this->handle($customerSalesChannel);
    }

    public function htmlResponse(LengthAwarePaginator $palletReturns): Response
    {

        $title = __('Baskets');

        return Inertia::render(
            'Storage/RetinaPalletReturns',
            [
                'breadcrumbs' => $this->getBreadcrumbs($this->customerSalesChannel),
                'title'       => $title,
                'pageHead'    => [
                    'title' => $title,
                    'icon'  => 'fal fa-shopping-basket',
                    'afterTitle' => [
                        'label' => ' @'.$this->platform->name
                    ],
                ],
                'routes' => [
                    'storeClientWithOrderRoute' => [
                        'name' => 'retina.models.customer_sales_channel.fulfilment.customer-client-with-order.store',
                        'parameters' => [
                            $this->customerSalesChannel->id
                        ],
                        'method' => 'post'
                    ],
                    'fetchClientsRoute' => [
                        'name' => 'retina.fulfilment.dropshipping.customer_sales_channels.client.index',
                        'parameters' => [
                            'customerSalesChannel' => $this->customerSalesChannel->slug
                        ]
                    ],
                    'storeBasketRoute' => [
                        'name' => 'retina.models.customer-client.fulfilment_order.store',
                        'parameters' => [
                            // FE put client id here
                        ],
                        'method' => 'post'
                    ]
                ],
                'data' => PalletReturnsResource::collection($palletReturns),
            ]
        )->table($this->tableStructure(prefix: 'pallet_returns'));
    }

    public function tableStructure(?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix . 'Page');
            }


            $table
                ->withModelOperations($modelOperations)
                ->withGlobalSearch()
                ->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon')
                ->column(key: 'created_at', label: __('Created at'), canBeHidden: false, type: 'date')
                ->column(key: 'reference', label: __('reference number'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'customer_reference', label: __('Your reference'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'total_amount', label: __('total amount'), canBeHidden: false, type: 'currency');
        };
    }

    public function getBreadcrumbs(CustomerSalesChannel $customerSalesChannel): array
    {
        return
            array_merge(
                ShowRetinaDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name' => 'retina.fulfilment.dropshipping.customer_sales_channels.basket.index',
                                'parameters' => [
                                    $customerSalesChannel->slug
                                ]
                            ],
                            'label'  => __('Baskets'),
                        ]
                    ]
                ]
            );
    }
}
