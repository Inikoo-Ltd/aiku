<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 08 Jun 2025 12:07:53 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Portfolio;

use App\Actions\Retina\UI\Dashboard\ShowRetinaDashboard;
use App\Actions\RetinaAction;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Enums\UI\Catalogue\ProductTabsEnum;
use App\Http\Resources\Dropshipping\PupilPortfolioResource;
use App\Http\Resources\Platform\PlatformsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use App\Models\Fulfilment\StoredItem;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexPupilPortfolios extends RetinaAction
{
    private CustomerSalesChannel $customerSalesChannel;
    public function handle(CustomerSalesChannel $customerSalesChannel, $prefix = null): LengthAwarePaginator
    {
        $unUploadedFilter = AllowedFilter::callback('un_upload', function ($query) {
            $query->whereNull('platform_product_id');
        });

        $query = QueryBuilder::for(Portfolio::class);
        $query->where('customer_sales_channel_id', $customerSalesChannel->id);
        $query->where('status', true);

        if ($customerSalesChannel->platform->type == PlatformTypeEnum::SHOPIFY) {
            $query->with(['shopifyPortfolio', 'customerSalesChannel']);
        }
        $query->with(['item']);

        if ($this->customer->is_fulfilment) {
            $query->where('item_type', class_basename(StoredItem::class));
        } else {
            $query->where('item_type', class_basename(Product::class));
        }

        return $query->defaultSort('-id')
            ->allowedFilters([$unUploadedFilter])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
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

        $this->initialisation($request);

        return $this->handle($customerSalesChannel, 'products');
    }

    public function jsonResponse(LengthAwarePaginator $portfolios): \Illuminate\Http\Resources\Json\AnonymousResourceCollection|\Illuminate\Http\Resources\Json\JsonResource
    {
        return PupilPortfolioResource::collection($portfolios);
    }

    public function htmlResponse(LengthAwarePaginator $portfolios): Response
    {
        $manual = false;
        if (isset($this->platform) && $this->platform->type == PlatformTypeEnum::MANUAL) {
            $manual = true;
        }

        $title = __('My Portfolio');


        $platformName = $this->customerSalesChannel->name;

        if ($this->customerSalesChannel->platform->type == PlatformTypeEnum::MANUAL) {
            $platformName = __('Manual');
        }

        return Inertia::render(
            'Dropshipping/Portfolios',
            [
                'breadcrumbs' => $this->getBreadcrumbs($this->customerSalesChannel),
                'title'       => $title,
                'is_manual'   => $manual,
                'pageHead'    => [
                    'title'   => $title,
                    'model'   =>  $platformName,
                    'icon'    => 'fal fa-cube'
                ],
                'routes'    => [
                    'bulk_upload'  => match ($this->customerSalesChannel->platform->type) {
                        PlatformTypeEnum::SHOPIFY => [
                            'name'       => 'retina.models.dropshipping.shopify.batch_upload',
                            'parameters' => [
                                'shopifyUser' => $this->customerSalesChannel->user->id
                            ]
                        ],
                        PlatformTypeEnum::WOOCOMMERCE => [
                            'name'       => 'retina.models.dropshipping.woo.batch_upload',
                            'parameters' => [
                                'wooCommerceUser' => $this->customerSalesChannel->user->id
                            ]
                        ],
                        default => false
                    },
                    'itemRoute' => [
                        'name' => 'retina.dropshipping.customer_sales_channels.portfolios.filtered_products.index',
                        'parameters' => [
                            'customerSalesChannel' => $this->customerSalesChannel->slug
                        ]
                    ],
                    'addPortfolioRoute' => [
                        'name' => 'retina.models.customer_sales_channel.customer.product.store',
                        'parameters' => [
                            'customerSalesChannel' => $this->customerSalesChannel->id
                        ]
                    ],
                    'updatePortfolioRoute' => [
                        'name' => 'retina.models.portfolio.update',
                        'parameters' => []
                    ],
                    'deletePortfolioRoute' => [
                        'name' => 'retina.models.portfolio.delete',
                        'parameters' => []
                    ],
                    'batchDeletePortfolioRoute' => [
                        'name' => 'retina.models.customer_sales_channel.portfolio.batch.delete',
                        'parameters' => [
                            'customerSalesChannel' => $this->customerSalesChannel->id
                        ],
                        'method' => 'post'
                    ],
                ],
                'order_route' => isset($this->platform) && $this->platform->type === PlatformTypeEnum::MANUAL ? [
                    'name'       => 'retina.models.customer.order.platform.store',
                    'parameters' => [
                        'customer' => $this->customer->id,
                        'platform' => $this->platform->id
                    ]
                ] : [],
                'content' => [
                    'portfolio_empty' => [
                        'title' => __("You don't have any items in your portfolio"),
                        'description' => __("To get started, add products to your portfolios."),
                        'separation' => __("or"),
                        'add_button' => __("Add Portfolio"),
                    ]
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => ProductTabsEnum::navigation()
                ],

                'step' => [
                    'current' => match ($this->customerSalesChannel->platform->type) {
                        PlatformTypeEnum::SHOPIFY, PlatformTypeEnum::WOOCOMMERCE => $this->customerSalesChannel->portfolios()->whereNull('platform_product_id')->count() === 0 ? 0 : 1,
                        default => 0
                    }
                ],
                'platform_user_id' => $this->customerSalesChannel->user?->id,
                'platform_data' => PlatformsResource::make($this->customerSalesChannel->platform)->toArray(request()),
                'products' => PupilPortfolioResource::collection($portfolios)
            ]
        )->table($this->tableStructure(prefix: 'products'));
    }

    public function tableStructure(?array $modelOperations = null, $prefix = null): \Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState([
                    'title' => "No products found",
                    'count' => 0
                ]);

            $table->column(key: 'slug', label: __('code'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'category', label: __('category'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'quantity_left', label: __('stock'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'weight', label: __('weight'), canBeHidden: false, sortable: true, searchable: true, align: 'right');
            $table->column(key: 'price', label: __('price'), canBeHidden: false, sortable: true, searchable: true, align: 'right');
            $table->column(key: 'customer_price', label: __('RRP'), tooltip: __('Recommended retail price'), canBeHidden: false, sortable: true, searchable: true, align: 'right');
            $table->column(key: 'status', label: __('status'));
            $table->column(key: 'actions', label: __('action'), canBeHidden: false);
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
                                'name' => 'retina.dropshipping.customer_sales_channels.basket.index',
                                'parameters' => [
                                    $customerSalesChannel->slug
                                ]
                            ],
                            'label' => __('My Portfolio'),
                        ]
                    ]
                ]
            );
    }
}
