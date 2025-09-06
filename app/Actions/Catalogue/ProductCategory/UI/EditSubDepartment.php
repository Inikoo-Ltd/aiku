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
                    $request->route()->getName(),
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
                                'name_i8n' => [
                                    'type'  => 'input',
                                    'label' => __('name'),
                                    'value' => $subDepartment->getTranslation('name_i8n', $subDepartment->shop->language->code) ?: $subDepartment->name
                                ],
                                'description_title_i8n' => [
                                    'type'  => 'input',
                                    'label' => __('description title'),
                                    'value' => $subDepartment->getTranslation('description_title_i8n', $subDepartment->shop->language->code) ?: $subDepartment->description_title
                                ],
                                'description_i8n' => [
                                    'type'  => 'textEditor',
                                    'label' => __('description'),
                                    'value' => $subDepartment->getTranslation('description_i8n', $subDepartment->shop->language->code) ?: $subDepartment->description
                                ],
                                'description_extra_i8n' => [
                                    'type'  => 'textEditor',
                                    'label' => __('Extra description'),
                                    'value' => $subDepartment->getTranslation('description_extra_i8n', $subDepartment->shop->language->code) ?: $subDepartment->description_extra
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
                            'label'  => __('Pricing'),
                            'icon'   => 'fa-light fa-money-bill',
                            'fields' => [
                                'cost_price_ratio' => [
                                    'type'          => 'input_number',
                                    'bind' => [
                                        'maxFractionDigits' => 3
                                    ],
                                    'label'         => __('pricing ratio'),
                                    'placeholder'   => __('Cost price ratio'),
                                    'required'      => true,
                                    'value'         => $subDepartment->cost_price_ratio,
                                    'min'           => 0
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


    public function getBreadcrumbs(ProductCategory $subDepartment, string $routeName, array $routeParameters): array
    {
        return ShowSubDepartment::make()->getBreadcrumbs(
            subDepartment: $subDepartment,
            routeName: preg_replace('/edit$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '('.__('Editing').')'
        );
    }


}
