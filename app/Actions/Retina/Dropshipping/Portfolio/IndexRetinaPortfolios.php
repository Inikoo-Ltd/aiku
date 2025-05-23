<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 14 Oct 2024 14:05:52 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Portfolio;

use App\Actions\Retina\UI\Dashboard\ShowRetinaDashboard;
use App\Actions\RetinaAction;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Enums\UI\Catalogue\ProductTabsEnum;
use App\Http\Resources\Catalogue\DropshippingPortfolioResource;
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

class IndexRetinaPortfolios extends RetinaAction
{
    private CustomerSalesChannel $customerSalesChannel;
    public function handle(CustomerSalesChannel $customerSalesChannel, $prefix = null): LengthAwarePaginator
    {
        $query = QueryBuilder::for(Portfolio::class);
        $query->where('customer_sales_channel_id', $customerSalesChannel->id);

        if ($customerSalesChannel->platform->type == PlatformTypeEnum::SHOPIFY) {
            $query->with(['shopifyPortfolio']);
        }

        $query->with(['item']);

        if ($this->customer->is_fulfilment) {
            $query->where('item_type', class_basename(StoredItem::class));
        } else {
            $query->where('item_type', class_basename(Product::class));
        }

        return $query->withPaginator($prefix, tableName: request()->route()->getName())
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

        return $this->handle($customerSalesChannel);
    }

    public function jsonResponse(LengthAwarePaginator $portfolios): \Illuminate\Http\Resources\Json\AnonymousResourceCollection|\Illuminate\Http\Resources\Json\JsonResource
    {
        return DropshippingPortfolioResource::collection($portfolios);
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
                    'icon'    => 'fal fa-cube',
                    'actions' => [
                        $this->customerSalesChannel->platform->type !== PlatformTypeEnum::MANUAL ? [
                            'type'  => 'button',
                            'style' => 'create',
                            'label' => 'Upload Products to ' . $this->customerSalesChannel->platform->name,
                            'route' => [
                                'name'       => 'retina.models.customer_sales_channel.shopify.batch_upload',
                                'parameters' => [
                                    'customerSalesChannel' => $this->customerSalesChannel->id
                                ]
                            ]
                        ] : [],
                    ]
                ],
                'routes'    => [
                    'itemRoute' => [
                        'name' => 'retina.dropshipping.customer_sales_channels.portfolios.filtered_products.index',
                        'parameters' => [
                            'customerSalesChannel' => $this->customerSalesChannel->slug
                        ]
                    ],
                    // 'syncAllRoute' => $syncAllRoute,
                    'addPortfolioRoute' => [
                        'name' => match ($this->customerSalesChannel->platform->type) {
                            PlatformTypeEnum::WOOCOMMERCE => 'retina.models.customer_sales_channel.woo.product.store',
                            default => 'retina.models.customer_sales_channel.customer.product.store'
                        },
                        'parameters' => [
                            'customerSalesChannel' => $this->customerSalesChannel->id
                        ]
                    ]
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

                'products' => DropshippingPortfolioResource::collection($portfolios)
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
            if ($this->customerSalesChannel->platform->type == PlatformTypeEnum::SHOPIFY) {
                $table->column(key: 'platform_product_id', label: __('Platform Product Id'), canBeHidden: false, sortable: true, searchable: true);
            }
            $table->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'quantity_left', label: __('stock'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'weight', label: __('weight'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'price', label: __('price'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'action', label: __('action'), canBeHidden: false);
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
