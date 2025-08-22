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
use App\Enums\UI\SupplyChain\MasterSubDepartmentTabsEnum;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditMasterSubDepartment extends OrgAction
{
    /**
     * @var \App\Models\Masters\MasterProductCategory|\App\Models\Masters\MasterShop
     */
    private MasterProductCategory|MasterShop $parent;

    public function inMasterDepartment(MasterShop $masterShop, MasterProductCategory $masterDepartment, MasterProductCategory $masterSubDepartment, ActionRequest $request): Response
    {
        $this->parent = $masterDepartment;
        $group        = group();
        $this->initialisationFromGroup($group, $request)->withTab(MasterSubDepartmentTabsEnum::values());

        return $this->handle($masterSubDepartment, $request);
    }

    public function asController(MasterShop $masterShop, MasterProductCategory $masterSubDepartment, ActionRequest $request): Response
    {
        $this->parent = $masterSubDepartment;
        $group        = group();
        $this->initialisationFromGroup($group, $request)->withTab(MasterSubDepartmentTabsEnum::values());

        return $this->handle($masterSubDepartment, $request);
    }

    public function handle(MasterProductCategory $masterProductCategory, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                 'breadcrumbs' => $this->getBreadcrumbs(
                     $masterProductCategory,
                     $request->route()->getName(),
                     $request->route()->originalParameters()
                 ),
                'title'       => __('Edit Master Sub-department'),
                'pageHead'    => [
                    'title'   => __('edit master Sub-department'),
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('cancel'),
                            'route' => [
                                'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ]
                    ]
                ],
                'formData'    => [
                    'blueprint' =>
                        [
                            [
                                'title'  => __('Master Sub-department'),
                                'fields' => [
                                    'code' => [
                                        'type'     => 'input',
                                        'label'    => __('code'),
                                        'value'    => $masterProductCategory->code,
                                        'required' => true
                                    ],
                                    'name' => [
                                        'type'     => 'input',
                                        'label'    => __('name'),
                                        'value'    => $masterProductCategory->name,
                                        'required' => true
                                    ],
                                ]
                            ]
                        ],
                    'args'      => [
                        'updateRoute' => [
                            'name' => 'grp.models.master_product.update',
                            'parameters' => [
                                'masterProductCategory' => $masterProductCategory->id
                            ]
                        ],
                    ],
                ]
            ]
        );
    }


    public function getBreadcrumbs(MasterProductCategory $masterSubDepartment, string $routeName, array $routeParameters): array
    {
        return array_merge(
            match ($this->parent::class) {
                MasterShop::class => IndexMasterShops::make()->getBreadcrumbs(),
                MasterProductCategory::class => ShowMasterSubDepartment::make()->getBreadcrumbs(
                    masterSubDepartment: $masterSubDepartment,
                    routeName: $routeName,
                    routeParameters: $routeParameters,
                )
            },
            [
                [
                    'type' => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Editing sub-department'),
                    ]
                ]
            ]
        );
    }
}
