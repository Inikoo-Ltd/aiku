<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 19 May 2025 16:09:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Portfolio;

use App\Actions\Retina\UI\Dashboard\ShowRetinaDashboard;
use App\Actions\RetinaAction;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Enums\UI\Catalogue\ProductTabsEnum;
use App\Http\Resources\Catalogue\DropshippingPortfolioResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Product;
use App\Models\CRM\Customer;
use App\Models\CRM\WebUser;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Dropshipping\TiktokUser;
use App\Models\Dropshipping\WooCommerceUser;
use App\Models\Fulfilment\StoredItem;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexRetinaFulfilmentPortfolios extends RetinaAction
{
    /**
     * @var CustomerSalesChannel
     */
    private CustomerSalesChannel $customerSalesChannel;

    public function handle( CustomerSalesChannel $customerSalesChannel,$prefix = null): LengthAwarePaginator
    {
        $query = QueryBuilder::for(Portfolio::class);
        $query->where('customer_sales_channel_id', $customerSalesChannel->id);

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
        $customerSalesChannel= $request->route('customerSalesChannel');
        if($customerSalesChannel->customer_id == $this->customer->id) {
            return true;
        }
        return false;
    }

    public function asController(CustomerSalesChannel $customerSalesChannel,ActionRequest $request): LengthAwarePaginator
    {
        $this->customerSalesChannel=$customerSalesChannel;
        $this->initialisationFromPlatform($customerSalesChannel->platform, $request);
        return $this->handle($customerSalesChannel);
    }




    public function htmlResponse(LengthAwarePaginator $portfolios): Response
    {
        $title = __('Portfolio');
        $syncAllRoute = [];

        if ($this->customer->is_fulfilment) {
            $syncAllRoute = [
                'name' => 'retina.models.customer_sales_channel.sync_all_stored_items',
                'parameters' => [
                    'customerSalesChannel' => $this->customerSalesChannel->id
                ],
                'method' => 'post'
            ];
        }
        
        return Inertia::render(
            'Dropshipping/Portfolios',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => $title,
                'pageHead'    => [
                    'title'   => $title,
                    'icon'    => 'fal fa-cube',
                    'afterTitle' => [
                        'label' => ' @'.$this->customerSalesChannel->reference
                    ],
                'actions' => [
                        !empty($portfolios) ? [
                            'type'  => 'button',
                            'style' => 'tertiary',
                            'icon'  => 'fas fa-sync-alt',
                            'label' => 'Sync All Items',
                            'route' => [
                                'name'       => 'retina.models.customer_sales_channel.sync_all_stored_items',
                                'parameters' => [
                                    'customerSalesChannel' => $this->customerSalesChannel->id
                                ],
                                'method'     => 'post'
                            ]
                        ] : null,
                    ]
                ],
                'routes'    => [
                    'itemRoute' => [
                        'name' => 'retina.fulfilment.itemised_storage.stored_items.index',
                    ],
                    'syncAllRoute' => $syncAllRoute,
                    'addPortfolioRoute' => [
                        'name' => 'retina.models.customer_sales_channel.customer.product.store',
                        'parameters' => [
                            'customerSalesChannel' => $this->customerSalesChannel->id
                        ]
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
            $table->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'quantity_left', label: __('stock'), canBeHidden: false, sortable: true, align: 'right', searchable: true);
            $table->column(key: 'actions', label: __('actions'), canBeHidden: false);
        };
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
                                'name' => 'retina.dropshipping.portfolios.index'
                            ],
                            'label' => __('My Portfolio'),
                        ]
                    ]
                ]
            );
    }
}
