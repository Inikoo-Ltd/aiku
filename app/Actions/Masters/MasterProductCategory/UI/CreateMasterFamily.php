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
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Masters\MasterProductCategory;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\LaravelOptions\Options;

class CreateMasterFamily extends OrgAction
{
    public function asController(MasterProductCategory $masterDepartment, ActionRequest $request): Response
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($masterDepartment, $request);
    }

    public function handle(MasterProductCategory $masterDepartment, ActionRequest $request): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                 'breadcrumbs' => $this->getBreadcrumbs(
                     $masterDepartment,
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
                                'name'       => 'grp.masters.master_departments.show.master_sub_departments.index',
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
                    'route'     => [
                        'name'       => 'grp.models.master_family.store',
                        'parameters' => [
                            'masterDepartment' => $masterDepartment->id
                        ]
                    ]
                ]
            ]
        );
    }

    public function getBreadcrumbs(MasterProductCategory $masterProductCategory, string $routeName, array $routeParameters): array
    {
        return array_merge(
            IndexMasterFamilies::make()->getBreadcrumbs(
                parent: $masterProductCategory,
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
