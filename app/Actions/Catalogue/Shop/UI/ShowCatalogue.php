<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Thu, 18 May 2023 14:27:38 Central European Summer, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Catalogue\Shop\UI;

use App\Actions\OrgAction;
use App\Actions\UI\WithInertia;
use App\Http\Resources\Catalogue\DepartmentResource;
use App\Http\Resources\Catalogue\FamilyResource;
use App\Http\Resources\Catalogue\ProductResource;
use App\Http\Resources\Catalogue\ShopResource;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowCatalogue extends OrgAction
{
    use AsAction;
    use WithInertia;


    public function handle(Shop $shop): Shop
    {
        return $shop;
    }

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit   = $request->user()->authTo("products.{$this->shop->id}.edit");
        $this->canDelete = $request->user()->authTo("products.{$this->shop->id}.edit");

        return $request->user()->authTo("products.{$this->shop->id}.view");
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): Shop
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }

    public function htmlResponse(Shop $shop, ActionRequest $request): Response
    {
        $timesUpdate = ['1d', '1w', '1m', '1y', 'all'];

        $topFamily     = [];
        $topDepartment = [];
        $topProduct    = [];

        foreach ($timesUpdate as $timeUpdate) {
            $family = $shop->stats->{'top'.$timeUpdate.'Family'};

            $topFamily[$timeUpdate] = $family ? FamilyResource::make($family) : null;

            $department                 = $shop->stats->{'top'.$timeUpdate.'Department'};
            $topDepartment[$timeUpdate] = $department ? DepartmentResource::make($department) : null;

            $product                 = $shop->stats->{'top'.$timeUpdate.'Product'};
            $topProduct[$timeUpdate] = $product ? ProductResource::make($product) : null;
        }

        $totalProducts = $shop->stats->number_products;

        $productsWithZeroQuantity = $shop->products()
            ->where('available_quantity', 0)
            ->count();

        $percentageWithZeroQuantity = ($totalProducts > 0)
            ? round(($productsWithZeroQuantity / $totalProducts) * 100, 2)
            : 0;


        //        $departmentsData = [
        //            [
        //                'label' => __('Departments'),
        //                'icon'  => 'fal fa-folder-tree',
        //                'value' => $shop->stats->number_departments,
        //            ],
        //            [
        //                'label' => __('Current Departments'),
        //                'icon'  => 'fal fa-folder-tree',
        //                'value' => $shop->stats->number_current_departments,
        //            ],
        //            [
        //                'label' => __('Discontinuing Departments'),
        //                'icon'  => 'fal fa-folder-tree',
        //                'value' => $shop->stats->number_departments_state_discontinuing,
        //            ],
        //            [
        //                'label' => __('Departments In Process'),
        //                'icon'  => 'fal fa-folder-tree',
        //                'value' => $shop->stats->number_departments_state_in_process,
        //            ],
        //
        //        ];


        return Inertia::render(
            'Org/Catalogue/Catalogue',
            [
                'title'       => __('catalogue'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($shop, $request),
                    'next'     => $this->getNext($shop, $request),
                ],
                'pageHead'    => [
                    'title' => __('Catalogue'),
                    'model' => '',
                    'icon'  => [
                        'title' => __('Catalogue'),
                        'icon'  => 'fal fa-books'
                    ],

                ],

                'top_selling' => [
                    'family'     => [
                        'label' => __('Top Family'),
                        'icon'  => 'fal fa-folder',
                        'value' => $topFamily
                    ],
                    'department' => [
                        'label' => __('Top Department'),
                        'icon'  => 'fal fa-folder-tree',
                        'value' => $topDepartment
                    ],
                    'product'    => [
                        'label' => __('Top Product'),
                        'icon'  => 'fal fa-folder-tree',
                        'value' => $topProduct
                    ],
                ],
                'stats'       => [
                    [
                        'label' => __('Departments'),
                        'route' => [
                            'name'       => 'grp.org.shops.show.catalogue.departments.index',
                            'parameters' => [
                                'organisation' => $shop->organisation->slug,
                                'shop'         => $shop->slug
                            ]
                        ],
                        'icon'  => 'fal fa-folder-tree',
                        "color" => "#a3e635",
                        'value' => $shop->stats->number_current_departments,


                        'metaRight'  => [
                            'tooltip' => __('Sub Departments'),
                            'icon'    => [
                                'icon'  => 'fal fa-folder-tree',
                                'class' => ''
                            ],
                            'count'   => $shop->stats->number_current_sub_departments,
                        ],
                        'metas' => [

                            [
                                'tooltip' => __('Active departments'),
                                "icon"    => [
                                    "tooltip" => "active",
                                    "icon"    => "fas fa-check-circle",
                                    "class"   => "text-green-500"
                                ],
                                'count'   => $shop->stats->number_departments_state_active,
                                'route' => [
                                    'name'       => 'grp.org.shops.show.catalogue.departments.index',
                                    'parameters' => [
                                        'organisation' => $shop->organisation->slug,
                                        'shop'         => $shop->slug,
                                        'index_elements[state]' => 'active'
                                    ]
                                ],
                            ],

                            [
                                'tooltip' => __('Discontinuing'),
                                'icon'    => [
                                    'icon'  => 'fas fa-times-circle',
                                    'class' => 'text-amber-500'
                                ],
                                'count'   => $shop->stats->number_departments_state_discontinuing,
                                'route' => [
                                    'name'       => 'grp.org.shops.show.catalogue.departments.index',
                                    'parameters' => [
                                        'organisation' => $shop->organisation->slug,
                                        'shop'         => $shop->slug,
                                        'index_elements[state]' => 'discontinuing'
                                    ]
                                ],
                            ],
                            [
                                'tooltip' => __('Discontinued Departments'),
                                'icon'    => [
                                    'icon'  => 'fas fa-times-circle',
                                    'class' => 'text-red-500'
                                ],
                                'count'   => $shop->stats->number_departments_state_discontinued,
                                'route' => [
                                    'name'       => 'grp.org.shops.show.catalogue.departments.index',
                                    'parameters' => [
                                        'organisation' => $shop->organisation->slug,
                                        'shop'         => $shop->slug,
                                        'index_elements[state]' => 'discontinued'
                                    ]
                                ],
                            ],
                            [
                                'tooltip' => __('In process'),
                                'icon'    => [
                                    'icon'  => 'fal fa-seedling',
                                    'class' => 'text-green-500 animate-pulse'
                                ],
                                'count'   => $shop->stats->number_departments_state_in_process,
                                'route' => [
                                    'name'       => 'grp.org.shops.show.catalogue.departments.index',
                                    'parameters' => [
                                        'organisation' => $shop->organisation->slug,
                                        'shop'         => $shop->slug,
                                        'index_elements[state]' => 'in_process'
                                    ]
                                ],
                            ],
                        ]
                    ],
                    [
                        'label' => __('Families'),
                        'route' => [
                            'name'       => 'grp.org.shops.show.catalogue.families.index',        // TODO
                            'parameters' => [
                                'organisation' => $shop->organisation->slug,
                                'shop'         => $shop->slug
                            ]
                        ],
                        'icon'  => 'fal fa-folder',
                        "color" => "#e879f9",
                        'value' => $shop->stats->number_current_families,
                        'metas' => [
                            [
                                'tooltip' => __('Active families'),
                                "icon"    => [
                                    "tooltip" => "active",
                                    "icon"    => "fas fa-check-circle",
                                    "class"   => "text-green-500"
                                ],
                                'count'   => $shop->stats->number_families_state_active,
                                'route' => [
                                    'name'       => 'grp.org.shops.show.catalogue.families.index',
                                    'parameters' => [
                                        'organisation' => $shop->organisation->slug,
                                        'shop'         => $shop->slug,
                                        'index_elements[state]' => 'active'
                                    ]
                                ],
                            ],
                            [
                                'tooltip' => __('Discontinuing families'),
                                'icon'    => [
                                    'icon'  => 'fas fa-times-circle',
                                    'class' => 'text-amber-500'
                                ],
                                'count'   => $shop->stats->number_families_state_discontinuing,
                                'route' => [
                                    'name'       => 'grp.org.shops.show.catalogue.families.index',
                                    'parameters' => [
                                        'organisation' => $shop->organisation->slug,
                                        'shop'         => $shop->slug,
                                        'index_elements[state]' => 'discontinuing'
                                    ]
                                ],
                            ],
                            [
                                'tooltip' => __('Discontinued families'),
                                'icon'    => [
                                    'icon'  => 'fas fa-times-circle',
                                    'class' => 'text-red-500'
                                ],
                                'count'   => $shop->stats->number_families_state_discontinued,
                                'route' => [
                                    'name'       => 'grp.org.shops.show.catalogue.families.index',
                                    'parameters' => [
                                        'organisation' => $shop->organisation->slug,
                                        'shop'         => $shop->slug,
                                        'index_elements[state]' => 'discontinued'
                                    ]
                                ],
                            ],
                            [
                                'tooltip' => __('Families in process'),
                                'icon'    => [
                                    'icon'  => 'fal fa-seedling',
                                    'class' => 'text-green-500 animate-pulse'
                                ],
                                'count'   => $shop->stats->number_families_state_in_process,
                                'route' => [
                                    'name'       => 'grp.org.shops.show.catalogue.families.index',
                                    'parameters' => [
                                        'organisation' => $shop->organisation->slug,
                                        'shop'         => $shop->slug,
                                        'index_elements[state]' => 'in_process'
                                    ]
                                ],
                            ],
                        ]
                    ],
                    [
                        'label' => __('Current Products'),
                        'route' => [
                            'name'       => 'grp.org.shops.show.catalogue.products.current_products.index',        // TODO
                            'parameters' => [
                                'organisation' => $shop->organisation->slug,
                                'shop'         => $shop->slug
                            ]
                        ],
                        'icon'  => 'fal fa-cube',
                        "color" => "#38bdf8",
                        'value' => $shop->stats->number_current_products,
                        'metaRight'  => [
                            'tooltip' => __('Variants'),
                            'icon'    => [
                                'icon'  => 'fal fa-cubes',
                                'class' => ''
                            ],
                            'count'   => $shop->stats->number_current_product_variants,
                        ],
                        'metas' => [

                            [
                                "icon"    => [
                                    "tooltip" => "active",
                                    "icon"    => "fas fa-check-circle",
                                    "class"   => "text-green-500"
                                ],
                                "count"   => $shop->stats->number_products_state_active,
                                "tooltip" => "Active",
                                'route' => [
                                    'name'       => 'grp.org.shops.show.catalogue.products.current_products.index',
                                    'parameters' => [
                                        'organisation' => $shop->organisation->slug,
                                        'shop'         => $shop->slug,
                                    ]
                                ],
                            ],
                            [
                                "icon"    => [
                                    "tooltip" => "discontinuing",
                                    "icon"    => "fas fa-times-circle",
                                    "class"   => "text-amber-500"
                                ],
                                "count"   => $shop->stats->number_products_state_discontinuing,
                                "tooltip" => "Discontinuing"
                            ],
                            [
                                "icon"    => [
                                    "tooltip" => "discontinued",
                                    "icon"    => "fas fa-times-circle",
                                    "class"   => "text-red-500"
                                ],
                                "count"   => $shop->stats->number_products_state_discontinued,
                                "tooltip" => "Discontinued",
                                'route' => [
                                    'name'       => 'grp.org.shops.show.catalogue.products.discontinued_products.index',
                                    'parameters' => [
                                        'organisation' => $shop->organisation->slug,
                                        'shop'         => $shop->slug,
                                    ]
                                ],
                            ],
                            [
                                "tooltip" => "Products In Process",
                                "icon"    => [
                                    'icon'  => 'fal fa-seedling',
                                    'class' => 'text-green-500 animate-pulse'
                                ],
                                "count"   => $shop->stats->number_products_state_in_process,
                                'route' => [
                                    'name'       => 'grp.org.shops.show.catalogue.products.in_process_products.index',
                                    'parameters' => [
                                        'organisation' => $shop->organisation->slug,
                                        'shop'         => $shop->slug,
                                    ]
                                ],
                            ],
                        ]
                    ],
                    [
                        'label' => __('Collections'),
                        'route' => [
                            'name'       => 'grp.org.shops.show.catalogue.collections.index',        // TODO
                            'parameters' => [
                                'organisation' => $shop->organisation->slug,
                                'shop'         => $shop->slug
                            ]
                        ],
                        'icon'  => 'fal fa-album-collection',
                        "color" => "#4f46e5",
                        'value' => $shop->stats->number_collections,
                    ],
                    [
                        'label' => __('Stray Families'),
                        'route' => [
                            'name'       => 'grp.org.shops.show.catalogue.families.no_department.index',
                            'parameters' => [
                                'organisation' => $shop->organisation->slug,
                                'shop'         => $shop->slug
                            ]
                        ],
                        'icon'  => 'fal fa-folder',
                        "color" => "#ff0000",
                        'value' => $shop->stats->number_families_no_department,
                    ],
                    [
                        'label' => __('Orphan Products'),
                        'route' => [
                            'name'       => 'grp.org.shops.show.catalogue.products.orphan_products.index',
                            'parameters' => [
                                'organisation' => $shop->organisation->slug,
                                'shop'         => $shop->slug
                            ]
                        ],
                        'icon'  => 'fal fa-cube',
                        "color" => "#ff0000",
                        'value' => $shop->stats->number_products_no_family,
                    ],
                ]

            ]
        );
    }


    public function jsonResponse(Shop $shop): ShopResource
    {
        return new ShopResource($shop);
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
            'grp.org.shops.show.catalogue.dashboard' => [
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

    public function getBreadcrumbs(array $routeParameters): array
    {
        return
            array_merge(
                ShowShop::make()->getBreadcrumbs($routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.shops.show.catalogue.dashboard',
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Catalogue'),
                        ]
                    ]
                ]
            );
    }
}
