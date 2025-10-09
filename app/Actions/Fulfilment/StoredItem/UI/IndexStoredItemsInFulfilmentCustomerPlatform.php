<?php

/*
 * author Arya Permana - Kirin
 * created on 03-04-2025-11h-17m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Fulfilment\StoredItem\UI;

use App\Actions\Dropshipping\CustomerSalesChannel\UI\ShowCustomerSalesChannelInFulfilment;
use App\Actions\Fulfilment\WithFulfilmentCustomerPlatformSubNavigation;
use App\Actions\OrgAction;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Http\Resources\Fulfilment\StoredItemsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\StoredItem;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexStoredItemsInFulfilmentCustomerPlatform extends OrgAction
{
    use WithFulfilmentCustomerPlatformSubNavigation;

    private CustomerSalesChannel $customerSalesChannel;

    public function handle(CustomerSalesChannel $customerSalesChannel, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('stored_items.reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(Portfolio::class);
        $query->where('portfolios.customer_id', $customerSalesChannel->customer->id)
            ->where('portfolios.customer_sales_channel_id', $customerSalesChannel->id)
            ->where('portfolios.item_type', class_basename(StoredItem::class));

        if ($customerSalesChannel->platform->type === PlatformTypeEnum::MANUAL) {
            $query->leftJoin('stored_items', function ($join) {
                $join->on('portfolios.item_id', '=', 'stored_items.id')
                    ->where('portfolios.item_type', '=', class_basename(StoredItem::class));
            });

        } elseif ($customerSalesChannel->platform->type === PlatformTypeEnum::SHOPIFY) {
            $query->leftJoin('stored_items', function ($join) {
                $join->on('portfolios.item_id', '=', 'stored_items.id')
                    ->where('portfolios.item_type', '=', class_basename(StoredItem::class));
            });

            // repair this bacuse is damaged
        } elseif ($customerSalesChannel->platform->type === PlatformTypeEnum::TIKTOK) {
            $query->leftJoin('stored_items', function ($join) {
                $join->on('portfolios.item_id', '=', 'stored_items.id')
                    ->where('portfolios.item_type', '=', class_basename(StoredItem::class));
            });

            $query->leftJoin('tiktok_user_has_products', function ($join) {
                $join->on('tiktok_user_has_products.product_id', '=', 'stored_items.id')
                    ->where('tiktok_user_has_products.product_type', '=', class_basename(StoredItem::class));
            });

            $query->where('tiktok_user_has_products.shopify_user_id', $customerSalesChannel->customer->tiktokUser->id);
        }

        return $query
            ->defaultSort('stored_items.id')
            ->select(
                'stored_items.id',
                'stored_items.slug',
                'stored_items.reference',
                'stored_items.state',
                'stored_items.name',
                'stored_items.total_quantity'
            )
            ->allowedSorts(['reference', 'name', 'total_quantity'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function htmlResponse(LengthAwarePaginator $portfolios, ActionRequest $request): Response
    {
        $subNavigation = $this->getFulfilmentCustomerPlatformSubNavigation($this->customerSalesChannel, $request);
        $icon          = ['fal', 'fa-user'];
        $title         = $this->customerSalesChannel->customer->name;
        $iconRight     = [
            'icon'  => ['fal', 'fa-user-friends'],
            'title' => __('Portfolios')
        ];
        $afterTitle    = [

            'label' => __('Portfolios')
        ];

        return Inertia::render(
            'Org/Fulfilment/FulfilmentCustomerPlatformPortfolios',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $this->customerSalesChannel,
                    $request->route()->originalParameters()
                ),
                'title'       => __('Channels'),
                'pageHead'    => [
                    'title'         => $title,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'icon'          => $icon,
                    'subNavigation' => $subNavigation,
                ],
                'data'        => StoredItemsResource::collection($portfolios),
            ]
        )->table($this->tableStructure());
    }


    public function tableStructure(array $modelOperations = null, $prefix = null): Closure
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
                ->column(key: 'state', label: __('State'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'reference', label: __('Reference'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'total_quantity', label: __('Quantity'), canBeHidden: false, sortable: true, searchable: true)
                ->defaultSort('reference');
        };
    }

    public function asController(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, CustomerSalesChannel $customerSalesChannel, ActionRequest $request): LengthAwarePaginator
    {
        $this->customerSalesChannel = $customerSalesChannel;
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($customerSalesChannel);
    }

    public function getBreadcrumbs(CustomerSalesChannel $customerSalesChannel, array $routeParameters): array
    {
        return array_merge(
            ShowCustomerSalesChannelInFulfilment::make()->getBreadcrumbs($customerSalesChannel, $routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.fulfilments.show.crm.customers.show.customer_sales_channels.show.portfolios.index',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Portfolios'),
                        'icon'  => 'fal fa-bars',
                    ],

                ]
            ]
        );
    }
}
