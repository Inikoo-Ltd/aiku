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
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\LaravelOptions\Options;

class CreateMasterDepartment extends OrgAction
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

    public function handle(MasterShop $parent, ActionRequest $request): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                 'breadcrumbs' => $this->getBreadcrumbs(
                     $request->route()->getName(),
                     $request->route()->originalParameters()
                 ),
                'title'       => __('New Master department'),
                'pageHead'    => [
                    'title'   => __('new master department'),
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('cancel'),
                            'route' => [
                                'name'       => 'grp.masters.master_shops.show.master_departments.index',
                                'parameters' => array_values($request->route()->originalParameters())
                            ],
                        ]
                    ]
                ],
                'formData'    => [
                    'blueprint' =>
                        [
                            [
                                'title'  => __('Master department'),
                                'fields' => [
                                    'type' => [
                                        'hidden'   => true,
                                        'type'     => 'select',
                                        'label'    => __('type'),
                                        'required' => true,
                                        'options'  => Options::forEnum(ProductCategoryTypeEnum::class),
                                        'value'    => ProductCategoryTypeEnum::DEPARTMENT->value,
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
                    'route' => [
                        'name' => 'grp.models.master_shops.master_department.store',
                        'parameters' => [
                            'masterShop' => $parent->id
                        ]
                    ]
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
                        'label' => __('Creating department'),
                    ]
                ]
            ]
        );
    }
}
