<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Mon, 13 Mar 2023 15:05:41 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Catalogue\Collection\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateCollection extends OrgAction
{
    use WithCatalogueAuthorisation;

    public function handle(Shop|ProductCategory $parent, ActionRequest $request): Response
    {

        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $parent,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('new collection'),
                'pageHead'    => [
                    'title'   => __('new collection'),
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('cancel'),
                            'route' => [
                                'name'       => preg_replace('/.create/', '.index', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ],
                        ]
                    ]
                ],
                'formData'    => [
                    'fullLayout' => true,
                    'blueprint'  =>
                        [
                            [
                                'title'  => __('New Collection'),
                                'fields' => [
                                    'code'        => [
                                        'type'     => 'input',
                                        'label'    => __('code'),
                                        'required' => true
                                    ],
                                    'name'        => [
                                        'type'     => 'input',
                                        'label'    => __('name'),
                                        'required' => true,
                                    ],
                                    'description' => [
                                        'type'     => 'textarea',
                                        'label'    => __('description'),
                                        'required' => false,
                                    ],
                                    "image"       => [
                                        "type"     => "image_crop_square",
                                        "label"    => __("Image"),
                                        "required" => false,
                                    ],

                                ]
                            ]
                        ],
                    'route'      => $parent instanceof Shop
                        ? [
                            'name'       => 'grp.models.org.catalogue.collections.store',
                            'parameters' => [
                                'organisation' => $parent->organisation_id,
                                'shop'         => $parent->id,
                            ]
                        ]
                        : [
                            'name'       => 'grp.models.product_category.collection.store',
                            'parameters' => [
                                'productCategory' => $parent->id,
                            ]
                        ]
                ],

            ]
        );
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): Response
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $request);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inDepartment(Organisation $organisation, Shop $shop, ProductCategory $department, ActionRequest $request): Response
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($department, $request);
    }


    /** @noinspection PhpUnusedParameterInspection */
    public function inSubDepartment(Organisation $organisation, Shop $shop, ProductCategory $department, ProductCategory $subDepartment, ActionRequest $request): Response
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($subDepartment, $request);
    }

    public function getBreadcrumbs(Shop|ProductCategory $parent, string $routeName, array $routeParameters): array
    {
        $label = __('Creating collection');

        return match ($routeName) {
            'grp.org.shops.show.catalogue.collections.create' => array_merge(
                IndexCollections::make()->getBreadcrumbs(
                    $routeName,
                    $routeParameters,
                ),
                [
                    [
                        'type'          => 'creatingModel',
                        'creatingModel' => [
                            'label' => $label,
                        ]
                    ]
                ]
            ),
            'grp.org.shops.show.catalogue.departments.show.collection.create' => array_merge(
                IndexCollectionsInProductCategory::make()->getBreadcrumbs(
                    $parent,
                    'grp.org.shops.show.catalogue.departments.show.collection.index',
                    $routeParameters,
                ),
                [
                    [
                        'type'          => 'creatingModel',
                        'creatingModel' => [
                            'label' => $label,
                        ]
                    ]
                ]
            ),
            'grp.org.shops.show.catalogue.departments.show.sub_departments.show.collection.create' => array_merge(
                IndexCollectionsInProductCategory::make()->getBreadcrumbs(
                    $parent,
                    'grp.org.shops.show.catalogue.departments.show.sub_departments.show.collection.index',
                    $routeParameters,
                ),
                [
                    [
                        'type'          => 'creatingModel',
                        'creatingModel' => [
                            'label' => $label,
                        ]
                    ]
                ]
            ),
        };
    }
}
