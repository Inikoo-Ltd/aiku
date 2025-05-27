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
    private Shop|ProductCategory $parent;

    public function handle(Shop|ProductCategory $parent, ActionRequest $request): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'    => __('new collection'),
                'pageHead' => [
                    'title'        => __('new collection'),
                    'actions'      => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('cancel'),
                            'route' => [
                                'name'       => 'grp.org.shops.show.catalogue.collections.index',
                                'parameters' => array_values($request->route()->originalParameters())
                            ],
                        ]
                    ]
                ],
                'formData' => [
                    'fullLayout' => true,
                    'blueprint'  =>
                        [
                            [
                                'title'  => __('New Collection'),
                                'fields' => [
                                    'code' => [
                                        'type'       => 'input',
                                        'label'      => __('code'),
                                        'required'   => true
                                    ],
                                    'name' => [
                                        'type'     => 'input',
                                        'label'    => __('name'),
                                        'required' => true,
                                    ],
                                    'description' => [
                                        'type'     => 'textEditor',
                                        'label'    => __('description'),
                                        'required' => false,
                                    ],
                                        "image"         => [
                                        "type"    => "image_crop_square",
                                        "label"   => __("Image"),
                                        "required" => false,
                                    ],

                                ]
                            ]
                        ],
                    'route' => $parent instanceof Shop ? [
                        'name'       => 'grp.models.org.catalogue.collections.store',
                        'parameters' => [
                            'organisation' => $parent->organisation_id,
                            'shop'         => $parent->id,
                        ]
                    ] : [
                        'name'       => 'grp.models.product_category.collection.store',
                        'parameters' => [
                            'productCategory'         => $parent->id,
                        ]
                    ]
                ],

            ]
        );
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): Response
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $request);
    }

    public function inDepartment(Organisation $organisation, Shop $shop, ProductCategory $department, ActionRequest $request): Response
    {
        $this->parent = $department;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($department, $request);
    }

    public function inFamily(Organisation $organisation, Shop $shop, ProductCategory $family, ActionRequest $request): Response
    {
        $this->parent = $family;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($family, $request);
    }

    public function inFamilyInDepartment(Organisation $organisation, Shop $shop, ProductCategory $department, ProductCategory $family, ActionRequest $request): Response
    {
        $this->parent = $family;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($family, $request);
    }

    public function inFamilyInSubDepartmentInDepartment(Organisation $organisation, Shop $shop, ProductCategory $department, ProductCategory $subDepartment, ProductCategory $family, ActionRequest $request): Response
    {
        $this->parent = $family;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($family, $request);
    }

    public function inSubDepartment(Organisation $organisation, Shop $shop, ProductCategory $department, ProductCategory $subDepartment, ActionRequest $request): Response
    {
        $this->parent = $subDepartment;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($subDepartment, $request);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return array_merge(
            IndexCollection::make()->getBreadcrumbs(
                parent: $this->parent,
                routeName: preg_replace('/create$/', 'index', $routeName),
                routeParameters: $routeParameters,
            ),
            [
                [
                    'type'         => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Creating collection'),
                    ]
                ]
            ]
        );
    }
}
