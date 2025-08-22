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
use App\Models\SysAdmin\Group;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditMasterDepartment extends OrgAction
{
    /**
     * @var \App\Models\Masters\MasterShop
     */
    private MasterShop|Group $parent;

    public function asController(MasterShop $masterShop, MasterProductCategory $masterDepartment, ActionRequest $request): Response
    {
        $this->parent = $masterShop;
        $group        = group();
        $this->initialisationFromGroup($group, $request);

        return $this->handle($masterDepartment, $request);
    }

    public function handle(MasterProductCategory $masterProductCategory, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                 'breadcrumbs' => $this->getBreadcrumbs(
                     $this->parent,
                     $masterProductCategory,
                     $request->route()->getName(),
                     $request->route()->originalParameters()
                 ),
                'title'       => __('Edit Master Department'),
                'pageHead'    => [
                    'title'   => __('edit master department'),
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
                    'blueprint' => [
                        [
                            'label'  => __('Name/Description'),
                            'icon'   => 'fa-light fa-tag',
                            'fields' => [
                                'code' => [
                                    'type'  => 'input',
                                    'label' => __('code'),
                                    'value' => $masterProductCategory->code
                                ],
                                'name' => [
                                    'type'  => 'input',
                                    'label' => __('name'),
                                    'value' => $masterProductCategory->name
                                ],
                                'description_title' => [
                                    'type'  => 'input',
                                    'label' => __('description title'),
                                    'value' => $masterProductCategory->description_title
                                ],
                                'description' => [
                                    'type'  => 'textEditor',
                                    'label' => __('description'),
                                    'value' => $masterProductCategory->description
                                ],
                                'description_extra' => [
                                    'type'  => 'textEditor',
                                    'label' => __('description extra'),
                                    'value' => $masterProductCategory->description_extra
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
                                    "value"   => $masterProductCategory->imageSources(720, 480),
                                    "required" => false,
                                    'noSaveButton' => true,
                                    "full"         => true
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


    public function getBreadcrumbs(MasterShop|Group $parent, MasterProductCategory $masterDepartment,  string $routeName, array $routeParameters): array
    {
        return ShowMasterDepartment::make()->getBreadcrumbs(
            parent: $parent,
            masterDepartment: $masterDepartment,
            routeName: preg_replace('/edit$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '('.__('Editing').')'
        );
    }
}
