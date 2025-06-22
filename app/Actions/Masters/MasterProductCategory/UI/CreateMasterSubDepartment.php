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
use App\Models\Catalogue\Shop;
use App\Models\Masters\MasterProductCategory;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\LaravelOptions\Options;

class CreateMasterSubDepartment extends OrgAction
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
                // 'breadcrumbs' => $this->getBreadcrumbs(
                //     $request->route()->getName(),
                //     $request->route()->originalParameters()
                // ),
                'title'       => __('New Master Sub-department'),
                'pageHead'    => [
                    'title'   => __('new master Sub-department'),
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
                                'title'  => __('Master Sub-department'),
                                'fields' => [
                                    'type' => [
                                        'hidden'   => true,
                                        'type'     => 'select',
                                        'label'    => __('type'),
                                        'required' => true,
                                        'options'  => Options::forEnum(ProductCategoryTypeEnum::class),
                                        'value'    => ProductCategoryTypeEnum::SUB_DEPARTMENT->value,
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
                                ]
                            ]
                        ],
                    'route'     => [
                        'name'       => 'grp.models.master_product.master_sub_departments.store',
                        'parameters' => [
                            'masterDepartment' => $masterDepartment->id
                        ]
                    ]
                ]
            ]
        );
    }



    // public function getBreadcrumbs(string $routeName, array $routeParameters): array
    // {
    //     return array_merge(
    //         IndexDepartments::make()->getBreadcrumbs(
    //             routeName: preg_replace('/create$/', 'index', $routeName),
    //             routeParameters: $routeParameters,
    //         ),
    //         [
    //             [
    //                 'type'         => 'creatingModel',
    //                 'creatingModel' => [
    //                     'label' => __('Creating department'),
    //                 ]
    //             ]
    //         ]
    //     );
    // }
}
