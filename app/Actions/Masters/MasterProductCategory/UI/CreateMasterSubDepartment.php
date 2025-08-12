<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 26-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Masters\MasterProductCategory\UI;

use App\Actions\Masters\MasterShop\UI\IndexMasterShops;
use App\Actions\OrgAction;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\UI\SupplyChain\MasterSubDepartmentTabsEnum;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\LaravelOptions\Options;

class CreateMasterSubDepartment extends OrgAction
{
    /**
     * @var \App\Models\Masters\MasterProductCategory|\App\Models\Masters\MasterShop
     */
    private MasterShop|MasterProductCategory $parent;

    public function asController(MasterShop $masterShop, ActionRequest $request): Response
    {
        $this->parent = $masterShop;
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($masterShop, $request);
    }

    public function inMasterDepartment(MasterShop $masterShop, MasterProductCategory $masterDepartment, ActionRequest $request): Response
    {
        $this->parent = $masterDepartment;
        $group        = group();
        $this->initialisationFromGroup($group, $request)->withTab(MasterSubDepartmentTabsEnum::values());

        return $this->handle($masterDepartment, $request);
    }

    public function handle(MasterProductCategory|MasterShop $parent, ActionRequest $request): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                 'breadcrumbs' => $this->getBreadcrumbs(
                     $request->route()->getName(),
                     $request->route()->originalParameters()
                 ),
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
                    'route' => match ($parent::class) {
                        MasterShop::class => [
                            'name' => 'grp.models.master_shops.master_sub_department.store',
                            'parameters' => [
                                'masterShop' => $parent->id
                            ]
                        ],
                        MasterProductCategory::class => [
                            'name' => 'grp.models.master_sub_department.store',
                            'parameters' => [
                                'masterDepartment' => $parent->id
                            ]
                        ],
                        default => null
                    }
                ]
            ]
        );
    }


    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return array_merge(
            match ($this->parent::class) {
                MasterShop::class => IndexMasterShops::make()->getBreadcrumbs(),
                MasterProductCategory::class => IndexMasterSubDepartments::make()->getBreadcrumbs(
                    parent: $this->parent,
                    routeName: preg_replace('/create$/', 'index', $routeName),
                    routeParameters: $routeParameters,
                )
            },
            [
                [
                    'type' => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Creating sub-department'),
                    ]
                ]
            ]
        );
    }
}
