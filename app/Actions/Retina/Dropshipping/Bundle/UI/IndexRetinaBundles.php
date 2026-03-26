<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 14 Oct 2024 14:05:52 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Bundle\UI;

use App\Actions\Retina\Platform\ShowRetinaCustomerSalesChannelDashboard;
use App\Actions\RetinaAction;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Actions\Traits\WithPlatformStatusCheck;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Enums\UI\Portfolio\CustomerSalesChannelPortfolioTabsEnum;
use App\Http\Resources\Dropshipping\DropshippingBundlesResource;
use App\Http\Resources\Dropshipping\DropshippingPortfoliosResource;
use App\InertiaTable\InertiaTable;
use App\Models\Bundle;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\AmazonUser;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\MagentoUser;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Dropshipping\WooCommerceUser;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexRetinaBundles extends RetinaAction
{
    use WithPlatformStatusCheck;

    private CustomerSalesChannel $customerSalesChannel;


    public function handle(CustomerSalesChannel $customerSalesChannel, $prefix = null, bool $disabled = false): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->where(function ($query) use ($value) {
                    $query->whereStartWith('products.reference', $value)
                        ->orWhereWith('products.item_code', $value)
                        ->orWhereWith('products.item_name', $value);
                });
            });
        });

        $unUploadedFilter = AllowedFilter::callback('un_upload', function ($query) {
            $query->whereNull('bundles.platform_product_id');
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(Bundle::class);

        $query->where('bundles.customer_sales_channel_id', $customerSalesChannel->id);

        if ($disabled) {
            $query->where('bundles.status', false);
        } else {
            $query->where('bundles.status', true);
        }

        if ($customerSalesChannel->platform->type == PlatformTypeEnum::SHOPIFY) {
            $query->with(['customerSalesChannel']);
        }
        $query->with(['bundleable', 'items.item']);

        $query->where('bundles.bundleable_type', class_basename(Product::class))
            ->leftJoin('products', 'products.id', 'bundles.bundleable_id')
            ->select(
                'bundles.*',
                'products.state as product_state',
                'products.id as product_id',
                'products.name as product_name',
                'products.code as product_code',
                'products.description as product_description',
                'products.is_for_sale',
            );

        return $query->defaultSort('-bundles.id')
            ->allowedFilters([$unUploadedFilter, $globalSearch, $this->getStateFilter(), $this->getPlatformStatusFilter(), $this->getForSaleFilter()])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function getStateFilter(): AllowedFilter
    {
        return AllowedFilter::callback('status', function ($query, $value) {
            $query->where('products.status', $value);
        });
    }

    public function getPlatformStatusFilter(): AllowedFilter
    {
        return AllowedFilter::callback('platform_status', function ($query, $value) {
            $query->where('platform_status', $value)
                ->orWhere('products.state', $value);
        });
    }

    public function getForSaleFilter(): AllowedFilter
    {
        return AllowedFilter::callback('is_for_sale', function ($query, $value) {
            $query->where('products.is_for_sale', true);
        });
    }

    public function authorize(ActionRequest $request): bool
    {
        $customerSalesChannel = $request->route('customerSalesChannel');
        if ($customerSalesChannel->customer_id == $this->customer->id) {
            return true;
        }

        return false;
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): LengthAwarePaginator
    {
        $this->customerSalesChannel = $customerSalesChannel;

        $this->initialisation($request)->withTab(CustomerSalesChannelPortfolioTabsEnum::values());

        return $this->handle($customerSalesChannel, 'bundles');
    }

    public function jsonResponse(LengthAwarePaginator $bundles): AnonymousResourceCollection
    {
        return DropshippingBundlesResource::collection($bundles);
    }

    public function htmlResponse(LengthAwarePaginator $bundles, ActionRequest $request): Response
    {
        $manual = false;
        if (isset($this->platform) && $this->platform->type == PlatformTypeEnum::MANUAL) {
            $manual = true;
        }

        $title = __('My Products');

        $channels = $this->customer->customerSalesChannels()
            ->whereNot('id', $this->customerSalesChannel->id)
            ->whereNot('number_portfolios', 0)
            ->get();

        /** @var ShopifyUser|WooCommerceUser|AmazonUser|MagentoUser $platformUser */
        $platformUser = $this->customerSalesChannel->user;

        if ($this->customerSalesChannel->platform->type == PlatformTypeEnum::MANUAL) {
            $countProductsNotSync = 0;
        } else {
            $countProductsNotSync = $this->customerSalesChannel->bundles()->where('bundles.status', true)
                ->whereExists(function ($q) {
                    $q->selectRaw(1)
                        ->from('products as p')
                        ->whereColumn('p.id', 'products.item_id')
                        ->whereNot('p.state', ProductStateEnum::DISCONTINUED->value)
                        ->where('p.is_for_sale', true);
                })
                ->where('bundles.platform_status', false)
                ->count();
        }

        return Inertia::render(
            'Dropshipping/Bundles',
            [
                'breadcrumbs'    => $this->getBreadcrumbs($this->customerSalesChannel),
                'title'          => $title,
                'is_manual'      => $manual,
                'pageHead'       => [
                    'title'      => $title,
                    'afterTitle' => [
                        'label' => '@'.$this->customerSalesChannel->name,
                    ],
                    'icon'       => 'fal fa-cube',
                    'actions'    => []
                ],
                'is_closed' => $this->customerSalesChannel->status == CustomerSalesChannelStatusEnum::CLOSED,
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => CustomerSalesChannelPortfolioTabsEnum::navigation()
                ],
                'bundles' => DropshippingPortfoliosResource::collection($bundles)
            ]
        )->table($this->tableStructure($this->customerSalesChannel, prefix: 'bundles'));
    }

    public function tableStructure(CustomerSalesChannel $parent, ?array $modelOperations = null, $prefix = null): \Closure
    {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }
            $table->withLabelRecord([__('product'), __('products')]);
            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState([
                    'title' => "No products found",
                    'count' => $parent->number_bundles
                ]);

            $table->column(key: 'image', label:'Image', canBeHidden: false, searchable: true);
            $table->column(key: 'name', label: __('Product'), canBeHidden: false, sortable: true, searchable: true);

            // if ($parent->platform->type == PlatformTypeEnum::MANUAL) {
            //     $table->column(key: 'product_state', label: '', canBeHidden: false);
            // }

            // if ($parent->status !== CustomerSalesChannelStatusEnum::CLOSED) {
            //     $table->column(key: 'actions', label: '', canBeHidden: false);
            // }

            // if ($parent->platform->type !== PlatformTypeEnum::MANUAL) {
            //     $table->column(key: 'status', label: __('Status'));
            //     $table->column(key: 'message', label: '', canBeHidden: false);

            //     $matchesLabel = __($parent->platform->name . ' product');

            //     $table->column(key: 'matches', label: $matchesLabel, canBeHidden: false);
            //     $table->column(key: 'create_new', label: '', canBeHidden: false);
            // }


            $table->column(key: 'delete', label: '', canBeHidden: false);
        };
    }

    public function getBreadcrumbs(CustomerSalesChannel $customerSalesChannel): array
    {
        return
            array_merge(
                ShowRetinaCustomerSalesChannelDashboard::make()->getBreadcrumbs($customerSalesChannel),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'retina.dropshipping.customer_sales_channels.portfolios.index',
                                'parameters' => [
                                    $customerSalesChannel->slug
                                ]
                            ],
                            'label' => __('My Products'),
                        ]
                    ]
                ]
            );
    }
}
