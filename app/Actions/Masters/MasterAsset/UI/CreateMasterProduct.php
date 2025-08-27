<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 08 Apr 2024 09:52:43 Central Indonesia Time, Bali Office, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset\UI;

use App\Actions\GrpAction;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;

class CreateMasterProduct extends GrpAction
{
    private MasterProductCategory $parent;

    public function handle(MasterProductCategory $parent, ActionRequest $request): Response
    {
        $request->route()->getName();

        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'    => __('new master product'),
                'pageHead' => [
                    'title'        => __('new master product'),
                    'actions'      => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('cancel'),
                            'route' => [
                                'name' => preg_replace('/create$/', 'index', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ],
                        ]
                    ]
                ],
                'formData' => [
                    'fullLayout' => true,
                    'blueprint'  =>
                        [
                            [
                                'title'  => __('Create Master Product'),
                                'fields' => [
                                    'trade_units' => [
                                        'type'         => 'list-selector',
                                        'label'        => __('Trade units'),
                                        'withQuantity' => true,
                                        'key_quantity' => 'quantity',
                                        'routeFetch'  => [
                                            'name'       => 'grp.json.master-product-category.recommended-trade-units',
                                            'parameters' => [
                                                'masterProductCategory' => $parent->slug,
                                            ]
                                        ],
                                        'value'        => []
                                    ],
                                    'code' => [
                                        'type'       => 'input',
                                        'label'      => __('code'),
                                        'required'   => true
                                    ],
                                    'name' => [
                                        'type'       => 'input',
                                        'label'      => __('name'),
                                        'required'   => true
                                    ],
                                    'price' => [
                                        'type'       => 'input',
                                        'label'      => __('price'),
                                        'required'   => true
                                    ],



                                ]
                            ]
                        ],
                    'route' => [
                        'name'       => 'grp.models.master_family.store-assets',
                        'parameters' => [
                            'masterFamily' => $parent->id,
                        ]
                    ]
                ],

            ]
        );
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterFamilyInMasterShop(MasterShop $masterShop, MasterProductCategory $masterFamily, ActionRequest $request): Response
    {
        $group        = group();

        $this->parent = $masterFamily;
        $this->initialisation($group, $request);

        return $this->handle($masterFamily, $request);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterFamilyInMasterSubDepartmentInMasterShop(MasterShop $masterShop, MasterProductCategory $masterSubDepartment, MasterProductCategory $masterFamily, ActionRequest $request): Response
    {
        $group        = group();

        $this->parent = $masterFamily;
        $this->initialisation($group, $request);

        return $this->handle($masterFamily, $request);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterFamilyInMasterSubDepartmentInMasterDepartmentInMasterShop(MasterShop $masterShop, MasterProductCategory $masterDepartment, MasterProductCategory $masterSubDepartment, MasterProductCategory $masterFamily, ActionRequest $request): Response
    {
        $group        = group();

        $this->parent = $masterFamily;
        $this->initialisation($group, $request);

        return $this->handle($masterFamily, $request);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterFamilyInMasterDepartmentInMasterShop(MasterShop $masterShop, MasterProductCategory $masterDepartment, MasterProductCategory $masterFamily, ActionRequest $request): Response
    {
        $group        = group();

        $this->parent = $masterFamily;
        $this->initialisation($group, $request);

        return $this->handle($masterFamily, $request);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return array_merge(
            IndexMasterProducts::make()->getBreadcrumbs(
                parent: $this->parent,
                routeName: preg_replace('/create$/', 'index', $routeName),
                routeParameters: $routeParameters,
            ),
            [
                [
                    'type'         => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Creating master product'),
                    ]
                ]
            ]
        );
    }

}
