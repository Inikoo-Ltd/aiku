<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Apr 2023 16:36:34 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueEditAuthorisation;
use App\Enums\UI\Catalogue\DepartmentTabsEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditSubDepartment extends OrgAction
{

    use WithCatalogueEditAuthorisation;

    public function handle(ProductCategory $subDepartment): ProductCategory
    {
        return $subDepartment;
    }



    public function inOrganisation(Organisation $organisation, ProductCategory $subDepartment, ActionRequest $request): ProductCategory
    {
        $this->initialisation($organisation, $request);

        return $this->handle($subDepartment);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inShop(Organisation $organisation, Shop $shop, ProductCategory $subDepartment, ActionRequest $request): ProductCategory
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($subDepartment);
    }

    public function inDepartment(Organisation $organisation, Shop $shop, ProductCategory $department, ProductCategory $subDepartment, ActionRequest $request): ProductCategory
    {
        $this->initialisationFromShop($shop, $request)->withTab(DepartmentTabsEnum::values());

        return $this->handle($subDepartment);
    }

    public function htmlResponse(ProductCategory $subDepartment, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'title'       => __('Sub-department'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $subDepartment,
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => ShowSubDepartment::make()->getPrevious($subDepartment, $request),
                    'next'     => ShowSubDepartment::make()->getNext($subDepartment, $request),
                ],
                'pageHead'    => [
                    'title'   => $subDepartment->code,
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ]
                    ]
                ],

                'formData' => [
                    'blueprint' => [
                        [
                            'label'  => __('Name/Description'),
                            'icon'   => 'fa-light fa-tag',
                            'title'  => __('id'),
                            'fields' => [
                                'code' => [
                                    'type'  => 'input',
                                    'label' => __('code'),
                                    'value' => $subDepartment->code
                                ],
                                'name' => [
                                    'type'  => 'input',
                                    'label' => __('name'),
                                    'value' => $subDepartment->name
                                ],
                                'description' => [
                                    'type'  => 'textEditor',
                                    'label' => __('description'),
                                    'value' => $subDepartment->description
                                ],
                                'description_title' => [
                                    'type'  => 'input',
                                    'label' => __('description title'),
                                    'value' => $subDepartment->description_title
                                ],
                                'description_extra' => [
                                    'type'  => 'textEditor',
                                    'label' => __('description extra'),
                                    'value' => $subDepartment->description_extra
                                ],
                            ]
                        ],
                        [
                            'label'  => __('Properties'),
                            'icon'   => 'fa-light fa-fingerprint',
                            'fields' => [
                                "image"         => [
                                    "type"    => "crop-image-full",
                                    "label"   => __("Image"),
                                    "value"   => $subDepartment->imageSources(720, 480),
                                    "required" => false,
                                    'noSaveButton' => true,
                                    "full"         => true
                                ],
                            ]
                        ],
                        [
                            'label'  => __('Department'),
                            'icon'   => 'fa-light fa-box',
                            'fields' => [
                                'department_id'  =>  [
                                    'type'    => 'select_infinite',
                                    'label'   => __('Department'),
                                    'options'   => [
                                        [
                                            'id' => $subDepartment->department?->id,
                                            'code' => $subDepartment->department?->code
                                        ]
                                    ],
                                    'fetchRoute'    => [
                                        'name'       => 'grp.org.shops.show.catalogue.departments.index',
                                        'parameters' => [
                                            'organisation' => $this->organisation->slug,
                                            'shop' => $this->shop->slug
                                        ]
                                    ],
                                    'valueProp' => 'id',
                                    'labelProp' => 'code',
                                    'required' => false,
                                    'value'   => $subDepartment->department->id ?? null,
                                ]
                            ],

                        ],


                    ],
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.models.product_category.update',
                            'parameters' => [
                                'productCategory' => $subDepartment->id
                            ]
                        ],
                    ]
                ]
            ]
        );
    }


    public function getBreadcrumbs(ProductCategory $subDepartment, array $routeParameters): array
    {
        return ShowSubDepartment::make()->getBreadcrumbs(
            subDepartment: $subDepartment,
            routeParameters: $routeParameters,
            suffix: '('.__('Editing').')'
        );
    }


}
