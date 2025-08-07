<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 07 Aug 2025 07:47:47 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory\UI;

use App\Actions\OrgAction;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\UI\Catalogue\DepartmentTabsEnum;
use App\Models\Catalogue\Shop;
use App\Models\Masters\MasterProductCategory;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditMasterFamily extends OrgAction
{
    public function handle(MasterProductCategory $family): MasterProductCategory
    {
        return $family;
    }


    public function inOrganisation(Organisation $organisation, MasterProductCategory $family, ActionRequest $request): MasterProductCategory
    {
        $this->initialisation($organisation, $request);

        return $this->handle($family);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inShop(Organisation $organisation, Shop $shop, MasterProductCategory $family, ActionRequest $request): MasterProductCategory
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($family);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inDepartment(Organisation $organisation, Shop $shop, MasterProductCategory $department, MasterProductCategory $family, ActionRequest $request): MasterProductCategory
    {
        $this->initialisationFromShop($shop, $request)->withTab(DepartmentTabsEnum::values());

        return $this->handle($family);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inSubDepartment(Organisation $organisation, Shop $shop, MasterProductCategory $department, MasterProductCategory $subDepartment, MasterProductCategory $family, ActionRequest $request): MasterProductCategory
    {

        $this->initialisationFromShop($shop, $request)->withTab(DepartmentTabsEnum::values());

        return $this->handle($family);
    }

    public function htmlResponse(MasterProductCategory $masterFamily, ActionRequest $request): Response
    {
        $departmentIdFormData = [];

        if ($masterFamily->parent?->type == MasterProductCategoryTypeEnum::DEPARTMENT) {
            $departmentIdFormData['department_id'] = [
                'type'     => 'select',
                'label'    => __('Department'),
                'required' => true,
                'options'  => $masterFamily->masterShop->masterProductCategories()
                    ->where('type', MasterProductCategoryTypeEnum::DEPARTMENT)
                    ->get(['id as value', 'name as label'])
                    ->toArray(),
                'value'   =>  $masterFamily->master_parent_id,
            ];

        }
        return Inertia::render(
            'EditModel',
            [
                'title'       => __('Master Family'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $masterFamily,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'                            => [
                    'previous' => $this->getPrevious($masterFamily, $request),
                    'next'     => $this->getNext($masterFamily, $request),
                ],
                'pageHead'    => [
                    'title'    => $masterFamily->code,
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
                                    'value' => $masterFamily->code
                                ],
                                'name' => [
                                    'type'  => 'input',
                                    'label' => __('name'),
                                    'value' => $masterFamily->name
                                ],
                                'description_title' => [
                                    'type'  => 'input',
                                    'label' => __('description title'),
                                    'value' => $masterFamily->description_title
                                ],
                                'description' => [
                                    'type'  => 'textEditor',
                                    'label' => __('description'),
                                    'value' => $masterFamily->description
                                ],
                                'description_extra' => [
                                    'type'  => 'textEditor',
                                    'label' => __('description extra'),
                                    'value' => $masterFamily->description_extra
                                ],
                            ]
                        ],
                        [
                            'label'  => __('Properties'),
                            'icon'   => 'fa-light fa-fingerprint',
                            'title'  => __('id'),
                            'fields' => [
                                "image"         => [
                                    "type"    => "image_crop_square",
                                    "label"   => __("Image"),
                                    "value"   => $masterFamily->imageSources(720, 480),
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
                                            'id' => $masterFamily->department?->id,
                                            'code' => $masterFamily->department?->code
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
                                    'value'   => $masterFamily->department->id ?? null,
                                ]
                            ],

                        ],

                    ],
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.models.product_category.update',
                            'parameters' => [
                                'MasterProductCategory'   => $masterFamily->id
                            ]
                        ],
                    ]
                ]
            ]
        );
    }


    public function getBreadcrumbs(MasterProductCategory $family, string $routeName, array $routeParameters): array
    {
        return ShowMasterFamily::make()->getBreadcrumbs(
            $family,
            routeName: preg_replace('/edit$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '(' . __('Editing') . ')'
        );
    }

    public function getPrevious(MasterProductCategory $family, ActionRequest $request): ?array
    {
        $previous = MasterProductCategory::where('code', '<', $family->code)->orderBy('code', 'desc')->first();
        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(MasterProductCategory $family, ActionRequest $request): ?array
    {
        $next = MasterProductCategory::where('code', '>', $family->code)->orderBy('code')->first();
        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?MasterProductCategory $family, string $routeName): ?array
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
