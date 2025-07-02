<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 14 Oct 2024 14:05:52 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Portfolio;

use App\Actions\Retina\Platform\ShowRetinaCustomerSalesChannelDashboard;
use App\Actions\RetinaAction;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Enums\UI\Catalogue\ProductTabsEnum;
use App\Http\Resources\Dropshipping\DropshippingPortfoliosResource;
use App\Http\Resources\Platform\PlatformsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexRetinaPortfolios extends RetinaAction
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

        $query->where('item_type', class_basename(Product::class));


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
        return DropshippingPortfoliosResource::collection($portfolios);
    }

    public function htmlResponse(LengthAwarePaginator $portfolios, ActionRequest $request): Response
    {
        $manual = false;
        if (isset($this->platform) && $this->platform->type == PlatformTypeEnum::MANUAL) {
            $manual = true;
        }

        $title = __('My Products');


        return Inertia::render(
            'Dropshipping/Portfolios',
            [
                'breadcrumbs'    => $this->getBreadcrumbs($this->customerSalesChannel),
                'title'          => $title,
                'is_manual'      => $manual,
                'pageHead'       => [
                    'title'      => $title,
                    'afterTitle' => [
                        'label' => '@'.$this->customerSalesChannel->name,
                    ],
                    'icon'       => 'fal fa-cube'
                ],
                'routes'         => [
                    'bulk_upload'               => match ($this->customerSalesChannel->platform->type) {
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
                        PlatformTypeEnum::EBAY => [
                            'name'       => 'retina.models.dropshipping.ebay.batch_upload',
                            'parameters' => [
                                'ebayUser' => $this->customerSalesChannel->user->id
                            ]
                        ],
                        PlatformTypeEnum::AMAZON => [
                            'name'       => 'retina.models.dropshipping.amazon.batch_upload',
                            'parameters' => [
                                'amazonUser' => $this->customerSalesChannel->user->id
                            ]
                        ],
                        PlatformTypeEnum::MAGENTO => [
                            'name'       => 'retina.models.dropshipping.magento.batch_upload',
                            'parameters' => [
                                'magentoUser' => $this->customerSalesChannel->user->id
                            ]
                        ],
                        default => false
                    },
                    'itemRoute'                 => [
                        'name'       => 'retina.dropshipping.customer_sales_channels.filtered_products.index',
                        'parameters' => [
                            'customerSalesChannel' => $this->customerSalesChannel->slug
                        ]
                    ],
                    'addPortfolioRoute'         => [
                        'name'       => 'retina.models.customer_sales_channel.customer.product.store',
                        'parameters' => [
                            'customerSalesChannel' => $this->customerSalesChannel->id
                        ]
                    ],
                    'updatePortfolioRoute'      => [
                        'name'       => 'retina.models.portfolio.update',
                        'parameters' => []
                    ],
                    'deletePortfolioRoute'      => [
                        'name'       => 'retina.models.portfolio.delete',
                        'parameters' => []
                    ],
                    'batchDeletePortfolioRoute' => [
                        'name'       => 'retina.models.customer_sales_channel.portfolio.batch.delete',
                        'parameters' => [
                            'customerSalesChannel' => $this->customerSalesChannel->id
                        ],
                        'method'     => 'post'
                    ],
                ],
                'download_route' => [
                    'xlsx'   => [
                        'name'       => 'retina.dropshipping.customer_sales_channels.portfolios.download',
                        'parameters' => [
                            'customerSalesChannel' => $this->customerSalesChannel->slug,
                            'type'                 => 'portfolio_xlsx'
                        ]
                    ],
                    'csv'    => [
                        'name'       => 'retina.dropshipping.customer_sales_channels.portfolios.download',
                        'parameters' => [
                            'customerSalesChannel' => $this->customerSalesChannel->slug,
                            'type'                 => 'portfolio_csv'
                        ]
                    ],
                    'json'   => [
                        'name'       => 'retina.dropshipping.customer_sales_channels.portfolios.download',
                        'parameters' => [
                            'customerSalesChannel' => $this->customerSalesChannel->slug,
                            'type'                 => 'portfolio_json'
                        ]
                    ],
                    'images' => [
                        'name'       => 'retina.dropshipping.customer_sales_channels.portfolios.download',
                        'parameters' => [
                            'customerSalesChannel' => $this->customerSalesChannel->slug,
                            'type'                 => 'portfolio_images'
                        ]
                    ]
                ],
                'order_route'    => isset($this->platform) && $this->platform->type === PlatformTypeEnum::MANUAL ? [
                    'name'       => 'retina.models.customer.order.platform.store',
                    'parameters' => [
                        'customer' => $this->customer->id,
                        'platform' => $this->platform->id
                    ]
                ] : [],
                'content'        => [
                    'portfolio_empty' => [
                        'title'       => __("You don't have any items in your portfolio"),
                        'description' => __("To get started, add products to your portfolios."),
                        'separation'  => __("or"),
                        'add_button'  => __("Add Product"),
                    ]
                ],
                'tabs'           => [
                    'current'    => $this->tab,
                    'navigation' => ProductTabsEnum::navigation()
                ],

                'step'             => [
                    'current' => match ($this->customerSalesChannel->platform->type) {
                        PlatformTypeEnum::SHOPIFY, PlatformTypeEnum::WOOCOMMERCE => $this->customerSalesChannel->portfolios()->whereNull('platform_product_id')->count() === 0 ? 0 : 1,
                        default => 0
                    }
                ],
                'platform_user_id' => $this->customerSalesChannel->user?->id,
                'platform_data'    => PlatformsResource::make($this->customerSalesChannel->platform)->toArray(request()),
                'products'         => DropshippingPortfoliosResource::collection($portfolios)
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
            $table->withLabelRecord([__('portfolio'), __('portfolios')]);
            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState([
                    'title' => "No products found",
                    'count' => 0
                ]);

            $table->column(key: 'image', label: __(''), canBeHidden: false, searchable: true);
            $table->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'quantity_left', label: __('stock'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'weight', label: __('weight'), canBeHidden: false, sortable: true, searchable: true, align: 'right');
            $table->column(key: 'price', label: __('price'), canBeHidden: false, sortable: true, searchable: true, align: 'right');
            $table->column(key: 'customer_price', label: __('RRP'), tooltip: __('Recommended retail price'), canBeHidden: false, sortable: true, searchable: true, align: 'right');

            if ($this->customerSalesChannel->platform->type !== PlatformTypeEnum::MANUAL) {
                $table->column(key: 'status', label: __('status'));
            }

            $table->column(key: 'actions', label: '', canBeHidden: false);
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
