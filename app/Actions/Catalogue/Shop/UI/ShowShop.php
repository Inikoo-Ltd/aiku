<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Thu, 18 May 2023 14:27:38 Central European Summer, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Catalogue\Shop\UI;

use App\Actions\Dashboard\ShowOrganisationDashboard;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\OrgAction;
use App\Actions\Retina\UI\Layout\GetPlatformLogo;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Actions\Traits\WithDashboard;
use App\Actions\UI\WithInertia;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\CRM\Customer\CustomerStateEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Enums\UI\Catalogue\ShopTabsEnum;
use App\Http\Resources\Catalogue\ShopResource;
use App\Http\Resources\History\HistoryResource;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowShop extends OrgAction
{
    use WithDashboard;
    use WithInertia;
    use WithCatalogueAuthorisation;
    use GetPlatformLogo;


    public function handle(Shop $shop): Shop
    {
        return $shop;
    }


    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): Shop
    {
        $this->initialisationFromShop($shop, $request)->withTab(ShopTabsEnum::values());

        return $this->handle($shop);
    }

    protected function getOrdersWidgetData(Shop $shop): array|null
    {
        if ($shop->type == ShopTypeEnum::DROPSHIPPING) {
            return null;
        } else {
            return $this->getEcomOrdersWidgetData($shop);
        }
    }



    protected function getEcomOrdersWidgetData(Shop $shop): array
    {
        return [
            'value'       => $shop->orderingIntervals->orders_1w,
            'description' => __('Last Orders (1w)'),
            'type'        => 'number',
            'route'       => [
                'name'       => 'grp.org.shops.show.ordering.orders.index',
                'parameters' => [
                    'organisation'           => $shop->organisation->slug,
                    'shop'                   => $shop->slug,
                    'orders_elements[state]' => 'submitted'
                ]
            ]
        ];
    }

    private function getStatsBox(Shop $shop): array
    {
        $customerChannels = CustomerSalesChannel::where('platform_status', true)
            ->where('shop_id', $shop->id)
            ->get();
        $totalPlatforms   = $customerChannels->count();

        $metas         = [];
        foreach (PlatformTypeEnum::cases() as $platformType) {
            $platformTypeName = $platformType->value;

            $platform = $customerChannels->filter(function ($channel) use ($platformTypeName) {
                return $channel->platform->type->value === $platformTypeName;
            });

            $metas[] = [
                'tooltip'   => __($platformType->labels()[$platformTypeName]),
                'icon'      => [
                    'tooltip' => $platform->count() > 0 ? 'active' : 'inactive',
                    'icon'    => $platform->count() > 0 ? 'fas fa-check-circle' : 'fas fa-times-circle',
                    'class'   => $platform->count() > 0 ? 'text-green-500' : 'text-red-500'
                ],
                'logo_icon' => $platformType->value,
                'count'     => $platform->count(),
            ];
        }

        return [
            [
                'label' => __('Customers Channels'),
                'color' => '#E87928',
                'icon'  => [
                    'icon'          => 'fal fa-code-branch',
                    'tooltip'       => __('Channels'),
                    'icon_rotation' => '90',
                ],
                'value' => $totalPlatforms,

                'metas' => $metas
            ],
        ];
    }

    private function getDashboard(Shop $shop): array
    {


        $widgetComponents[] = $this->getWidget(
            data: [
                'value'       => $shop->crmStats->number_customers_state_active,
                'description' => __('Visitors'),
                'type'        => 'number',
                'route'       => [
                    'name'       => 'grp.org.shops.show.crm.customers.index',
                    'parameters' => [
                        'organisation'              => $shop->organisation->slug,
                        'shop'                      => $shop->slug,
                        'tab'                       => 'customers',
                        'customers_elements[state]' => CustomerStateEnum::ACTIVE->value
                    ]
                ]
            ],
            visual: [
                'label' => __('New Customers'),
                'type'  => 'number_with_label',
                'value' => $shop->orderingIntervals->registrations_1w,
                'route' => [
                    'name'       => 'grp.org.shops.show.crm.customers.index',
                    'parameters' => [
                        'organisation'               => $shop->organisation->slug,
                        'shop'                       => $shop->slug,
                        'tab'                        => 'customers',
                    ]
                ]
            ],
        );

        if ($this->getOrdersWidgetData($shop)) {
            $widgetComponents[] = $this->getWidget(
                data: $this->getOrdersWidgetData($shop),
            );
        }

        return [
            'dashboard_stats' => [
                'widgets' => [
                    'column_count' => 4,
                    'components'   => $widgetComponents
                ]
            ],
            'statsBox'  => $this->getStatsBox($shop),
        ];
    }

    public function htmlResponse(Shop $shop, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Catalogue/Shop',
            [
                'title'       => __('shop'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($shop, $request),
                    'next'     => $this->getNext($shop, $request),
                ],

                'pageHead' => [
                    'title'   => $shop->name,
                    'icon'    => [
                        'title' => __('Shop'),
                        'icon'  => 'fal fa-store-alt'
                    ],
                    'actions' => [
                        $this->canEdit ? [
                            'type'  => 'button',
                            'style' => 'edit',
                            'label' => __('settings'),
                            'icon'  => 'fal fa-sliders-h',
                            'route' => [
                                'name'       => 'grp.org.shops.show.settings.edit',
                                'parameters' => $request->route()->originalParameters()
                            ]
                        ] : false,

                    ]
                ],

                'flatTreeMaps' => [
                    [
                        [
                            'name'  => __('customers'),
                            'icon'  => ['fal', 'fa-user'],
                            'route' => ['grp.org.shops.show.crm.customers.index', $shop->slug],
                            'index' => [
                                'number' => $shop->crmStats->number_customers
                            ]
                        ],
                        [
                            'name'  => __('prospects'),
                            'icon'  => ['fal', 'fa-user'],
                            'route' => ['grp.crm.shops.show.prospects.index', $shop->slug],
                            'index' => [
                                'number' => 'TBD'// $shop->stats->number_customers
                            ]
                        ],
                    ],
                    [
                        [
                            'name'  => __('departments'),
                            'icon'  => ['fal', 'fa-folder-tree'],
                            'route' => ['shops.show.departments.index', $shop->slug],
                            'index' => [
                                'number' => $shop->stats->number_departments
                            ]
                        ],

                        [
                            'name'  => __('families'),
                            'icon'  => ['fal', 'fa-folder'],
                            'route' => ['shops.show.families.index', $shop->slug],
                            'index' => [
                                'number' => $shop->stats->number_families
                            ]
                        ],

                        [
                            'name'  => __('products'),
                            'icon'  => ['fal', 'fa-cube'],
                            'route' => ['shops.show.products.index', $shop->slug],
                            'index' => [
                                'number' => $shop->stats->number_products
                            ]
                        ],
                    ],
                    [
                        [
                            'name'  => __('orders'),
                            'icon'  => ['fal', 'fa-shopping-cart'],
                            'route' => ['grp.crm.shops.show.orders.index', $shop->slug],
                            'index' => [
                                'number' => $shop->orderingStats->number_orders
                            ]
                        ],
                        [
                            'name'  => __('invoices'),
                            'icon'  => ['fal', 'fa-file-invoice'],
                            'route' => ['grp.crm.shops.show.invoices.index', $shop->slug],
                            'index' => [
                                'number' => $shop->orderingStats->number_invoices
                            ]
                        ],
                        [
                            'name'  => __('delivery-notes'),
                            'icon'  => ['fal', 'fa-sticky-note'],
                            'route' => ['grp.crm.shops.show.delivery-notes.index', $shop->slug],
                            'index' => [
                                'number' => $shop->orderingStats->number_delivery_notes
                            ]
                        ]
                    ]
                ],
                'tabs'         => [
                    'current'    => $this->tab,
                    'navigation' => ShopTabsEnum::navigation()
                ],

                ShopTabsEnum::SHOWCASE->value => $this->tab == ShopTabsEnum::SHOWCASE->value
                    ? fn () => $this->getDashboard($shop)
                    : Inertia::lazy(fn () => $this->getDashboard($shop)),

                ShopTabsEnum::HISTORY->value => $this->tab == ShopTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($shop))
                    : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run($shop)))


            ]
        )->table(
            IndexHistory::make()->tableStructure(
                prefix: ShopTabsEnum::HISTORY->value
            )
        );
    }


    public function jsonResponse(Shop $shop): ShopResource
    {
        return new ShopResource($shop);
    }

    public function getBreadcrumbs(array $routeParameters, $suffix = null): array
    {
        $shop = Shop::where('slug', $routeParameters['shop'])->first();

        return
            array_merge(
                ShowOrganisationDashboard::make()->getBreadcrumbs(Arr::only($routeParameters, 'organisation')),
                [
                    [
                        'type'           => 'modelWithIndex',
                        'modelWithIndex' => [
                            'index' => [
                                'route' => [
                                    'name'       => 'grp.org.shops.index',
                                    'parameters' => Arr::only($routeParameters, 'organisation')
                                ],
                                'label' => __('Shops'),
                                'icon'  => 'fal fa-bars'
                            ],
                            'model' => [
                                'route' => [
                                    'name'       => 'grp.org.shops.show.dashboard.show',
                                    'parameters' => Arr::only($routeParameters, ['organisation', 'shop'])
                                ],
                                'label' => $shop->code,
                                'icon'  => 'fal fa-bars'
                            ]


                        ],
                        'suffix'         => $suffix,
                    ]
                ]
            );
    }

    public function getPrevious(Shop $shop, ActionRequest $request): ?array
    {
        $previous = Shop::where('code', '<', $shop->code)->where('organisation_id', $this->organisation->id)->orderBy('code', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(Shop $shop, ActionRequest $request): ?array
    {
        $next = Shop::where('code', '>', $shop->code)->where('organisation_id', $this->organisation->id)->orderBy('code')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?Shop $shop, string $routeName): ?array
    {
        if (!$shop) {
            return null;
        }

        return match ($routeName) {
            'grp.org.shops.show.dashboard.show' => [
                'label' => $shop->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $this->organisation->slug,
                        'shop'         => $shop->slug
                    ]

                ]
            ]
        };
    }


}
