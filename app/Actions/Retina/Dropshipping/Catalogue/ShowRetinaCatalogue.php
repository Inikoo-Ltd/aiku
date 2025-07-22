<?php


namespace App\Actions\Retina\Dropshipping\Catalogue;

use App\Actions\OrgAction;
use App\Actions\Retina\UI\Dashboard\ShowRetinaDashboard;
use App\Actions\RetinaAction;
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

class ShowRetinaCatalogue extends RetinaAction
{
    use AsAction;
    use WithInertia;


    public function handle(Shop $shop): Shop
    {
        return $shop;
    }

    public function asController(ActionRequest $request): Shop
    {
        $this->initialisation($request);

        return $this->handle($this->shop);
    }

    public function htmlResponse(Shop $shop, ActionRequest $request): Response
    {
        return Inertia::render(
            'Catalogue/RetinaCatalogue',
            [
                'title'       => __('catalogue'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->originalParameters()
                ),
                'pageHead'    => [
                    'title' => __('Catalogue'),
                    'model' => '',
                    'icon'  => [
                        'title' => __('Catalogue'),
                        'icon'  => 'fal fa-books'
                    ],

                ],


                'stats' => [
                    [
                        'label' => __('Departments'),
                        'route' => [
                            'name'       => 'retina.catalogue.departments.index',
                            'parameters' => [
                            ]
                        ],
                        'icon'  => 'fal fa-folder-tree',
                        "color" => "#a3e635",
                        'value' => $shop->stats->number_current_departments,



                        'metas'     => [

                            [
                                'hide'    => $shop->stats->number_departments_state_discontinuing==0,
                                'tooltip' => __('Active departments'),
                                "icon"    => [
                                    "tooltip" => "active",
                                    "icon"    => "fas fa-check-circle",
                                    "class"   => "text-green-500"
                                ],
                                'count'   => $shop->stats->number_departments_state_active,
                                'route'   => [
                                    // 'name'       => 'retina.catalogue.sub_departments.index',
                                    // 'parameters' => [
                                    //     'organisation' => $shop->organisation->slug,
                                    //     'shop'         => $shop->slug,
                                    //     'index_elements[state]' => 'active'
                                    // ]
                                ],
                            ],

                            [
                                'hide'    => $shop->stats->number_departments_state_discontinuing==0,
                                'tooltip' => __('Discontinuing'),
                                'icon'    => [
                                    'icon'  => 'fas fa-times-circle',
                                    'class' => 'text-amber-500'
                                ],
                                'count'   => $shop->stats->number_departments_state_discontinuing,
                                'route'   => [
                                    // 'name'       => 'grp.org.shops.show.catalogue.departments.index',
                                    // 'parameters' => [
                                    //     'organisation' => $shop->organisation->slug,
                                    //     'shop'         => $shop->slug,
                                    //     'index_elements[state]' => 'discontinuing'
                                    // ]
                                ],
                            ],
                        ]
                    ],
                    [
                        'label' => __('Sub-Departments'),
                        'route' => [
                            'name'       => 'retina.catalogue.sub_departments.index',
                            'parameters' => [
                                'organisation' => $shop->organisation->slug,
                                'shop'         => $shop->slug
                            ]
                        ],
                        'icon'  => 'fal fa-folder-tree',
                        "color" => "#690000",
                        'value' => $shop->stats->number_sub_departments,



                        'metas'     => [

                            [
                                'hide'    => $shop->stats->number_departments_state_discontinuing==0,
                                'tooltip' => __('Active departments'),
                                "icon"    => [
                                    "tooltip" => "active",
                                    "icon"    => "fas fa-check-circle",
                                    "class"   => "text-green-500"
                                ],
                                'count'   => $shop->stats->number_departments_state_active,
                                'route'   => [
                                    // 'name'       => 'grp.org.shops.show.catalogue.departments.index',
                                    // 'parameters' => [
                                    //     'organisation' => $shop->organisation->slug,
                                    //     'shop'         => $shop->slug,
                                    //     'index_elements[state]' => 'active'
                                    // ]
                                ],
                            ],

                            [
                                'hide'    => $shop->stats->number_departments_state_discontinuing==0,
                                'tooltip' => __('Discontinuing'),
                                'icon'    => [
                                    'icon'  => 'fas fa-times-circle',
                                    'class' => 'text-amber-500'
                                ],
                                'count'   => $shop->stats->number_departments_state_discontinuing,
                                'route'   => [
                                    // 'name'       => 'grp.org.shops.show.catalogue.departments.index',
                                    // 'parameters' => [
                                    //     'organisation' => $shop->organisation->slug,
                                    //     'shop'         => $shop->slug,
                                    //     'index_elements[state]' => 'discontinuing'
                                    // ]
                                ],
                            ],
                        ]
                    ],
                    [
                        'label' => __('Families'),
                        'route' => [
                            'name'       => 'retina.catalogue.families.index',        // TODO
                            'parameters' => [
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
                                'route'   => [
                                    // 'name'       => 'grp.org.shops.show.catalogue.families.index',
                                    // 'parameters' => [
                                    //     'organisation' => $shop->organisation->slug,
                                    //     'shop'         => $shop->slug,
                                    //     'index_elements[state]' => 'active'
                                    // ]
                                ],
                            ],
                            [
                                'tooltip' => __('Discontinuing families'),
                                'icon'    => [
                                    'icon'  => 'fas fa-times-circle',
                                    'class' => 'text-amber-500'
                                ],
                                'count'   => $shop->stats->number_families_state_discontinuing,
                                'route'   => [
                                    // 'name'       => 'grp.org.shops.show.catalogue.families.index',
                                    // 'parameters' => [
                                    //     'organisation' => $shop->organisation->slug,
                                    //     'shop'         => $shop->slug,
                                    //     'index_elements[state]' => 'discontinuing'
                                    // ]
                                ],
                            ],

                        ]
                    ],
                    [
                        'label'     => __('Products'),
                        'route'     => [
                            'name'       => 'retina.catalogue.products.index',        // TODO
                            'parameters' => [
                            ]
                        ],
                        'icon'      => 'fal fa-cube',
                        "color"     => "#38bdf8",
                        'value'     => $shop->stats->number_current_products,

                        'metas'     => [

                            [
                                "icon"    => [
                                    "tooltip" => "active",
                                    "icon"    => "fas fa-check-circle",
                                    "class"   => "text-green-500"
                                ],
                                "count"   => $shop->stats->number_products_state_active,
                                "tooltip" => "Active",
                                'route'   => [
                                    // 'name'       => 'grp.org.shops.show.catalogue.products.current_products.index',
                                    // 'parameters' => [
                                    //     'organisation' => $shop->organisation->slug,
                                    //     'shop'         => $shop->slug,
                                    // ]
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

                        ]
                    ],
                    [
                        'label' => __('Collections'),
                        'route' => [
                            'name'       => 'retina.catalogue.collections.index',        // TODO
                            'parameters' => [
                                'organisation' => $shop->organisation->slug,
                                'shop'         => $shop->slug
                            ]
                        ],
                        'icon'  => 'fal fa-album-collection',
                        "color" => "#4f46e5",
                        'value' => $shop->stats->number_collections,
                    ],

                ]

            ]
        );
    }


    public function jsonResponse(Shop $shop): ShopResource
    {
        return new ShopResource($shop);
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
                                'name' => 'retina.catalogue.dashboard'
                            ],
                            'label' => __('Catalogue'),
                        ]
                    ]
                ]
            );
    }
}
