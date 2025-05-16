<?php

/*
 * author Arya Permana - Kirin
 * created on 03-04-2025-14h-36m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Fulfilment\PalletReturn\UI;

use App\Actions\Fulfilment\FulfilmentCustomer\UI\ShowFulfilmentCustomerPlatform;
use App\Actions\Fulfilment\WithFulfilmentCustomerPlatformSubNavigation;
use App\Actions\OrgAction;
use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Http\Resources\Fulfilment\PalletReturnsResource;
use App\InertiaTable\InertiaTable;
use App\Models\CRM\CustomerHasPlatform;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\PalletReturn;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use UnexpectedValueException;

class IndexPalletReturnsInPlatform extends OrgAction
{
    use WithFulfilmentCustomerPlatformSubNavigation;
    private CustomerHasPlatform $customerHasPlatform;

    public function asController(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, CustomerHasPlatform $customerHasPlatform, ActionRequest $request): LengthAwarePaginator
    {
        $this->customerHasPlatform = $customerHasPlatform;
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($customerHasPlatform);
    }

    public function handle(CustomerHasPlatform $customerHasPlatform, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('pallet_returns.reference', $value)
                    ->orWhereStartWith('pallet_returns.customer_reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(PalletReturn::class);
        $queryBuilder->where('pallet_returns.type', PalletReturnTypeEnum::STORED_ITEM);
        if ($customerHasPlatform->platform->type == PlatformTypeEnum::MANUAL) {
            $queryBuilder->where('pallet_returns.fulfilment_customer_id', $customerHasPlatform->customer->fulfilmentCustomer->id);
        } elseif ($customerHasPlatform->platform->type == PlatformTypeEnum::SHOPIFY) {
            $queryBuilder->leftJoin('shopify_user_has_fulfilments', function ($join) {
                $join->on('shopify_user_has_fulfilments.model_id', '=', 'pallet_returns.id')
                        ->where('shopify_user_has_fulfilments.model_type', '=', 'PalletReturn');
            });
        } else {
            throw new UnexpectedValueException('To be implemented');
        }
        $queryBuilder->where('platform_id', $customerHasPlatform->platform_id);
        $queryBuilder->leftJoin('pallet_return_stats', 'pallet_return_stats.pallet_return_id', '=', 'pallet_returns.id');
        $queryBuilder->leftJoin('currencies', 'currencies.id', '=', 'pallet_returns.currency_id');

        return $queryBuilder
            ->defaultSort('pallet_returns.reference')
            ->select([
                'pallet_returns.id',
                'pallet_returns.state',
                'pallet_returns.slug',
                'pallet_returns.reference',
                'pallet_returns.customer_reference',
                'pallet_return_stats.number_pallets',
                'pallet_return_stats.number_services',
                'pallet_return_stats.number_physical_goods',
                'pallet_returns.date',
                'pallet_returns.dispatched_at',
                'pallet_returns.type',
                'pallet_returns.total_amount',
                'currencies.code as currency_code',
            ])
            ->allowedSorts(['reference', 'total_amount'])
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
                ->withGlobalSearch()
                ->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon')
                ->column(key: 'reference', label: __('reference'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'customer_reference', label: __('customer reference'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'total_amount', label: __('total'), canBeHidden: false, sortable: true, searchable: true, align: 'right')
                ->column(key: 'date', label: __('date'), canBeHidden: false, sortable: true, searchable: true, align: 'right');
        };
    }

    public function htmlResponse(LengthAwarePaginator $orders, ActionRequest $request): Response
    {
        $icon       = ['fal', 'fa-user'];
        $title      = $this->customerHasPlatform->customer->name;
        $iconRight  = [
            'icon'  => ['fal', 'fa-shopping-cart'],
            'title' => __('orders')
        ];
        $subNavigation = $this->getFulfilmentCustomerPlatformSubNavigation($this->customerHasPlatform, $request);

        if ($this->customerHasPlatform->platform->type ==  PlatformTypeEnum::TIKTOK) {
            $afterTitle = [
                'label' => __('Tiktok Orders')
            ];
        } elseif ($this->customerHasPlatform->platform->type ==  PlatformTypeEnum::SHOPIFY) {
            $afterTitle = [
                'label' => __('Shopify Orders')
            ];
        } else {
            $afterTitle = [
                'label' => __('Orders')
            ];
        }


        return Inertia::render(
            'Org/Fulfilment/FulfilmentCustomerPlatformOrders',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
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
                'data'        => PalletReturnsResource::collection($orders),

            ]
        )->table($this->tableStructure());
    }

    public function getBreadcrumbs($routeParameters): array
    {
        return
            array_merge(
                ShowFulfilmentCustomerPlatform::make()->getBreadcrumbs($this->customerHasPlatform, $routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.fulfilments.show.crm.customers.show.platforms.show.orders.index',
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Orders'),
                        ]
                    ]
                ]
            );
    }
}
