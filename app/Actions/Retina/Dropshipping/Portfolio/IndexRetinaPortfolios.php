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
use App\Models\CRM\Customer;
use App\Models\CRM\WebUser;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Dropshipping\TiktokUser;
use App\Models\Fulfilment\StoredItem;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexRetinaPortfolios extends RetinaAction
{
    public function handle(ShopifyUser|TiktokUser|Customer|WebUser $scope, $prefix = null): LengthAwarePaginator
    {
        $query = QueryBuilder::for(Portfolio::class);

        if ($scope instanceof ShopifyUser || $scope instanceof TiktokUser) {
            $customer = $scope->customer;
            $query->where('customer_id', $customer->id);
        } elseif ($scope instanceof WebUser) {
            $query->where('customer_id', $scope->customer->id);
        } elseif ($scope instanceof Customer) {
            $query->where('customer_id', $scope->id);
        }

        $query->where('platform_id', $this->platform->id);

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
        if ($this->asAction) {
            return true;
        }

        return $request->user()->is_root;
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $customer = $request->user()->customer;

        $this->initialisation($request);

        return $this->handle($customer);
    }

    public function inPlatform(Platform $platform, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromPlatform($platform, $request);

        return $this->handle($this->platformUser);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inPupil(Platform $platform, ActionRequest $request): LengthAwarePaginator
    {
        $this->platformUser = $request->user();
        $this->platform = $platform;
        $this->asAction = true;
        $this->initialisationFromPupil($request);

        return $this->handle($this->shopifyUser);
    }

    public function htmlResponse(LengthAwarePaginator $portfolios): Response
    {
        $manual = false;
        if (isset($this->platform) && $this->platform->type == PlatformTypeEnum::MANUAL) {
            $manual = true;
        }

        $title = __('My Portfolio');

        return Inertia::render(
            'Dropshipping/Portfolios',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => $title,
                'is_manual'   => $manual,
                'pageHead'    => [
                    'title'   => $title,
                    'icon'    => 'fal fa-cube',
                    'actions' => [
                        $this->customer->is_fulfilment && ($this->platformUser instanceof ShopifyUser) ? [
                            'type'  => 'button',
                            'style' => 'create',
                            'label' => 'Sync Items',
                            'route' => [
                                'name'       => $this->asPupil ? 'pupil.models.dropshipping.shopify_user.product.sync' : 'retina.models.dropshipping.shopify_user.product.sync',
                                'parameters' => [
                                    'shopifyUser' => $this->platformUser->id
                                ]
                            ]
                        ] : [],
                        $this->customer->is_fulfilment && ($this->platformUser instanceof TiktokUser) ? [
                            'type'  => 'button',
                            'style' => 'create',
                            'label' => 'Sync Items',
                            'route' => [
                                'name'       => 'retina.models.dropshipping.tiktok.product.sync',
                                'parameters' => [
                                    'tiktokUser' => $this->platformUser->id
                                ]
                            ]
                        ] : [],
                    ]
                ],
                'order_route' => isset($this->platform) && $this->platform->type === PlatformTypeEnum::MANUAL ? [
                    'name'       => 'retina.models.customer.order.platform.store',
                    'parameters' => [
                        'customer' => $this->customer->id,
                        'platform' => $this->platform->id
                    ]
                ] : [],
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
            $table->column(key: 'type', label: __('type'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'quantity_left', label: __('quantity'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'action', label: __('action'), canBeHidden: false);
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
