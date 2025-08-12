<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 26-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Masters\MasterProductCategory\UI;

use App\Actions\OrgAction;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\LaravelOptions\Options;

class CreateMasterFamily extends OrgAction
{
    public function asController(MasterShop $masterShop, ActionRequest $request): Response
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($masterShop, $request);
    }

    public function inMasterDepartment(MasterProductCategory $masterDepartment, ActionRequest $request): Response
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($masterDepartment, $request);
    }

    public function inMasterSubDepartment(MasterProductCategory $masterSubDepartment, ActionRequest $request): Response
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($masterSubDepartment, $request);
    }

    public function inMasterSubDepartmentInMasterDepartment(MasterProductCategory $masterDepartment, MasterProductCategory $masterSubDepartment, ActionRequest $request): Response
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($masterSubDepartment, $request);
    }

    public function handle(MasterProductCategory|MasterShop $parent, ActionRequest $request): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                 'breadcrumbs' => $this->getBreadcrumbs(
                     $parent,
                     $request->route()->getName(),
                     $request->route()->originalParameters()
                 ),
                'title'       => __('New Master family'),
                'pageHead'    => [
                    'title'   => __('new master family'),
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('cancel'),
                            'route' => [
                                'name'       => preg_replace('/create$/', 'index', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ],
                        ]
                    ]
                ],
                'formData'    => [
                    'blueprint' =>
                        [
                            [
                                'title'  => __('Master family'),
                                'fields' => [
                                    'type' => [
                                        'hidden'   => true,
                                        'type'     => 'select',
                                        'label'    => __('type'),
                                        'required' => true,
                                        'options'  => Options::forEnum(ProductCategoryTypeEnum::class),
                                        'value'    => ProductCategoryTypeEnum::FAMILY->value,
                                        'readonly' => true
                                    ],
                                    'code' => [
                                        'type'     => 'input',
                                        'label'    => __('code'),
                                        'required' => true
                                    ],
                                    'name' => [
                                        'type'     => 'input',
                                        'label'    => __('name'),
                                        'required' => true
                                    ],
                                    'description' => [
                                        'type'     => 'textarea',
                                        'label'    => __('description'),
                                        'required' => true
                                    ],
                                    "image"         => [
                                        "type"    => "image_crop_square",
                                        "label"   => __("Image"),
                                        "required" => false,
                                    ],
                                ]
                            ]
                        ],
                    'route' => match ($parent::class) {
                        MasterShop::class => [
                            'name' => 'grp.models.master_shops.master_family.store',
                            'parameters' => [
                                'masterShop' => $parent->id
                            ]
                        ],
                        MasterProductCategory::class => $parent->type == MasterProductCategoryTypeEnum::DEPARTMENT 
                            ? [
                                'name' => 'grp.models.master_family.store',
                                'parameters' => [
                                    'masterDepartment' => $parent->id
                                ]
                            ]
                            : [
                                'name' => 'grp.models.master-sub-department.master_family.store',
                                'parameters' => [
                                    'masterSubDepartment' => $parent->id
                                ]
                            ],
                        default => null
                    }
                ]
            ]
        );
    }

    public function getBreadcrumbs(MasterProductCategory|MasterShop $parent, string $routeName, array $routeParameters): array
    {
        return array_merge(
            IndexMasterFamilies::make()->getBreadcrumbs(
                parent: $parent,
                routeName: preg_replace('/create$/', 'index', $routeName),
                routeParameters: $routeParameters,
            ),
            [
                [
                    'type'         => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Creating families'),
                    ]
                ]
            ]
        );
    }
}
