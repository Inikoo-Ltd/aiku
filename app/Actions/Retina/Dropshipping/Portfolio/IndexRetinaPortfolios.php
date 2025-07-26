<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 14 Oct 2024 14:05:52 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Portfolio;

use App\Actions\Retina\Platform\ShowRetinaCustomerSalesChannelDashboard;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithPlatformStatusCheck;
use App\Enums\Dropshipping\CustomerSalesChannelConnectionStatusEnum;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Http\Resources\CRM\RetinaCustomerSalesChannelResource;
use App\Http\Resources\Dropshipping\DropshippingPortfoliosResource;
use App\Http\Resources\Platform\PlatformsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\AmazonUser;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\MagentoUser;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Dropshipping\WooCommerceUser;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use App\Http\Resources\CRM\CustomerSalesChannelsResourceTOFIX;

class IndexRetinaPortfolios extends RetinaAction
{
    use WithPlatformStatusCheck;

    private CustomerSalesChannel $customerSalesChannel;


    public function handle(CustomerSalesChannel $customerSalesChannel, $prefix = null, bool $disabled = false): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('portfolios.item_code', $value)
                    ->orWhereWith('portfolios.item_name', $value);
            });
        });

        $unUploadedFilter = AllowedFilter::callback('un_upload', function ($query) {
            $query->whereNull('platform_product_id');
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(Portfolio::class);

        $query->where('portfolios.customer_sales_channel_id', $customerSalesChannel->id);

        if ($disabled) {
            $query->where('portfolios.status', false);
        } else {
            $query->where('portfolios.status', true);
        }

        if ($customerSalesChannel->platform->type == PlatformTypeEnum::SHOPIFY) {
            $query->with(['customerSalesChannel']);
        }
        $query->with(['item']);

        $query->where('item_type', class_basename(Product::class));


        return $query->defaultSort('-portfolios.id')
            ->allowedFilters([$unUploadedFilter, $globalSearch, $this->getStateFilter()])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function getStateFilter(): AllowedFilter
    {
        return AllowedFilter::callback('status', function ($query, $value) {
            $query->whereHas('item', function ($subQuery) use ($value) {
                $subQuery->where('item_type', 'Product')
                        ->whereIn('status', (array)$value);
            });
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

        $platform       = Platform::where('type', PlatformTypeEnum::MANUAL)->first();
        $manualChannels = $this->customer->customerSalesChannels()
            ->where('platform_id', $platform->id)
            ->where('status', CustomerSalesChannelStatusEnum::OPEN)
            ->get();

        /** @var ShopifyUser|WooCommerceUser|AmazonUser|MagentoUser $platformUser */
        $platformUser = $this->customerSalesChannel->user;

        // Button: Brave mode
        $bulkUploadRoute = false;
        if ($platformUser) {
            $bulkUploadRoute = match ($this->customerSalesChannel->platform->type) {
                PlatformTypeEnum::SHOPIFY => [
                    'name'       => 'retina.models.dropshipping.shopify.batch_upload',
                    'parameters' => [
                        'shopifyUser' => $platformUser->id
                    ]
                ],
                PlatformTypeEnum::WOOCOMMERCE => [
                    'name'       => 'retina.models.dropshipping.woo.batch_brave',
                    'parameters' => [
                        'wooCommerceUser' => $platformUser->id
                    ]
                ],
                PlatformTypeEnum::EBAY => [
                    'name'       => 'retina.models.dropshipping.ebay.batch_upload',
                    'parameters' => [
                        'ebayUser' => $platformUser->id
                    ]
                ],
                PlatformTypeEnum::AMAZON => [
                    'name'       => 'retina.models.dropshipping.amazon.batch_upload',
                    'parameters' => [
                        'amazonUser' => $platformUser->id
                    ]
                ],
                PlatformTypeEnum::MAGENTO => [
                    'name'       => 'retina.models.dropshipping.magento.batch_upload',
                    'parameters' => [
                        'magentoUser' => $platformUser->id
                    ]
                ],
                default => false
            };
        }

        // Button: Create new product to platform
        $duplicateRoute = false;
        if ($platformUser) {
            $duplicateRoute = match ($this->customerSalesChannel->platform->type) {
                PlatformTypeEnum::WOOCOMMERCE => [
                    'name'       => 'retina.models.dropshipping.woo.batch_upload',
                    'parameters' => [
                        'wooCommerceUser' => $platformUser->id
                    ]
                ],
                default => false
            };
        }

        // Button: Sync all to platform
        $batchSyncRoute = false;
        if ($platformUser) {
            $batchSyncRoute = match ($this->customerSalesChannel->platform->type) {
                PlatformTypeEnum::WOOCOMMERCE => [
                    'name'       => 'retina.models.dropshipping.woo.batch_sync',
                    'parameters' => [
                        'wooCommerceUser' => $platformUser->id
                    ]
                ],
                default => false
            };
        };


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
                    'bulk_upload'               => $bulkUploadRoute,
                    'batch_sync'                => $batchSyncRoute,
                    'duplicate'                 => $duplicateRoute,
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
                    'clonePortfolioRoute'       => [
                        'method'     => 'post',
                        'name'       => 'retina.models.customer_sales_channel.portfolio.clone_manual',
                        'parameters' => [
                            'targetCustomerSalesChannel' => $this->customerSalesChannel->id
                        ]
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
                        'name'       => 'retina.json.dropshipping.customer_sales_channel.portfolio_images_zip',
                        'parameters' => [
                            'customerSalesChannel' => $this->customerSalesChannel->id,
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
                        'description' => __("To get started, add products to your channel."),
                        'separation'  => __("or"),
                        'add_button'  => __("Add Product"),
                    ]
                ],


                'step' => [
                    'current' => match ($this->customerSalesChannel->platform->type) {
                        // PlatformTypeEnum::SHOPIFY, PlatformTypeEnum::WOOCOMMERCE => $this->customerSalesChannel->portfolios()->whereNull('platform_product_id')->count() === 0 ? 0 : 1,
                        default => 0
                    }
                ],


                'product_count' => $this->customerSalesChannel->number_portfolios,


                'count_product_not_synced' => $this->customerSalesChannel->portfolios()->whereNull('platform_product_id')->count(),
                'platform_user_id'         => $platformUser?->id,
                'platform_data'            => PlatformsResource::make($this->customerSalesChannel->platform)->toArray(request()),
                'products'                 => DropshippingPortfoliosResource::collection($portfolios),
                'is_platform_connected'    => $this->customerSalesChannel->connection_status === CustomerSalesChannelConnectionStatusEnum::CONNECTED,
                'customer_sales_channel'   => RetinaCustomerSalesChannelResource::make($this->customerSalesChannel)->toArray(request()),
                'manual_channels'          => CustomerSalesChannelsResourceTOFIX::collection($manualChannels)//  Do now use the resource. Use an array of necessary data
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
            $table->withLabelRecord([__('product'), __('products')]);
            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState([
                    'title' => "No products found",
                    'count' => $this->customerSalesChannel->number_portfolios
                ]);

            $table->column(key: 'image', label: __(''), canBeHidden: false, searchable: true);
            $table->column(key: 'name', label: __('Product'), canBeHidden: false, sortable: true, searchable: true);


            if ($this->customerSalesChannel->platform->type !== PlatformTypeEnum::MANUAL) {

                $table->column(key: 'status', label: __('status'));

                $matchesLabel = __('Matches');
                if ($this->customerSalesChannel->platform->type == PlatformTypeEnum::SHOPIFY) {
                    $matchesLabel = __('Shopify product');
                }

                $table->column(key: 'matches', label: $matchesLabel, canBeHidden: false);
                $table->column(key: 'create_new', label:'', canBeHidden: false);
            }


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
