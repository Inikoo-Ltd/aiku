<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Apr 2023 16:36:34 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory\UI;

use App\Actions\OrgAction;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\UI\Catalogue\DepartmentTabsEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditFamily extends OrgAction
{
    public function handle(ProductCategory $family): ProductCategory
    {
        return $family;
    }



    public function inOrganisation(Organisation $organisation, ProductCategory $family, ActionRequest $request): ProductCategory
    {
        $this->initialisation($organisation, $request);

        return $this->handle($family);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inShop(Organisation $organisation, Shop $shop, ProductCategory $family, ActionRequest $request): ProductCategory
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($family);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inDepartment(Organisation $organisation, Shop $shop, ProductCategory $department, ProductCategory $family, ActionRequest $request): ProductCategory
    {
        $this->initialisationFromShop($shop, $request)->withTab(DepartmentTabsEnum::values());

        return $this->handle($family);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inSubDepartment(Organisation $organisation, Shop $shop, ProductCategory $department, ProductCategory $subDepartment, ProductCategory $family, ActionRequest $request): ProductCategory
    {

        $this->initialisationFromShop($shop, $request)->withTab(DepartmentTabsEnum::values());

        return $this->handle($family);
    }

    public function htmlResponse(ProductCategory $family, ActionRequest $request): Response
    {
        $departmentIdFormData = [];

        if ($family->parent?->type == ProductCategoryTypeEnum::DEPARTMENT) {
            $departmentIdFormData['department_id'] = [
                'type'     => 'select',
                'label'    => __('Department'),
                'required' => true,
                'options'  => $family->shop->productCategories()
                    ->where('type', ProductCategoryTypeEnum::DEPARTMENT)
                    ->get(['id as value', 'name as label'])
                    ->toArray(),
                'value'   =>  $family->parent_id,
            ];

        }
        return Inertia::render(
            'EditModel',
            [
                'title'       => __('family'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $family,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'                            => [
                    'previous' => $this->getPrevious($family, $request),
                    'next'     => $this->getNext($family, $request),
                ],
                'pageHead'    => [
                    'title'    => $family->code,
                    'actions'  => [
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
                            'fields' => [
                                'code' => [
                                    'type'  => 'input',
                                    'label' => __('code'),
                                    'value' => $family->code
                                ],
                                'name' => [
                                    'type'  => 'input',
                                    'label' => __('name'),
                                    'value' => $family->name
                                ],
                                'description_title' => [
                                    'type'  => 'input',
                                    'label' => __('description title'),
                                    'value' => $family->description_title
                                ],
                                'description' => [
                                    'type'  => 'textEditor',
                                    'label' => __('description'),
                                    'value' => $family->description
                                ],
                                'description_extra' => [
                                    'type'  => 'textEditor',
                                    'label' => __('description extra'),
                                    'value' => $family->description_extra
                                ],
                            ]
                        ],
                        [
                            'label'  => __('Image'),
                            'icon'   => 'fa-light fa-image',
                            'title'  => __('Media'),
                            'fields' => [
                                "image"         => [
                                    "type"    => "image_crop_square",
                                    "label"   => __("Image"),
                                    "value"   => $family->imageSources(720, 480),
                                    "required" => false,
                                ],
                                ...$departmentIdFormData
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
                                            'id' => $family->department?->id,
                                            'code' => $family->department?->code
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
                                    'value'   => $family->department->id ?? null,
                                ]
                            ],

                        ],

                    ],
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.models.product_category.update',
                            'parameters' => [
                                'productCategory'   => $family->id
                            ]
                        ],
                    ]
                ]
            ]
        );
    }


    public function getBreadcrumbs(ProductCategory $family, string $routeName, array $routeParameters): array
    {
        return ShowFamily::make()->getBreadcrumbs(
            $family,
            routeName: preg_replace('/edit$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '(' . __('Editing') . ')'
        );
    }

    public function getPrevious(ProductCategory $family, ActionRequest $request): ?array
    {
        $previous = ProductCategory::where('code', '<', $family->code)->orderBy('code', 'desc')->first();
        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(ProductCategory $family, ActionRequest $request): ?array
    {
        $next = ProductCategory::where('code', '>', $family->code)->orderBy('code')->first();
        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?ProductCategory $family, string $routeName): ?array
    {
        if (!$family) {
            return null;
        }
        return match ($routeName) {
            'shops.families.edit' => [
                'label' => $family->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'department' => $family->slug
                    ]
                ]
            ],
            'shops.show.families.edit' => [
                'label' => $family->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'shop'       => $family->shop->slug,
                        'department' => $family->slug
                    ]
                ]
            ],
            default => []
        };
    }
}
