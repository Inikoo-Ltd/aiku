<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 26-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Masters\MasterMasterProductCategory\UI;

use App\Actions\OrgAction;
use App\Enums\UI\Catalogue\DepartmentTabsEnum;
use App\Models\Catalogue\Shop;
use App\Models\Masters\MasterProductCategory;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditMasterSubDepartment extends OrgAction
{
    public function handle(MasterProductCategory $subDepartment): MasterProductCategory
    {
        return $subDepartment;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->parent instanceof Organisation) {
            $this->canEdit = $request->user()->authTo(
                [
                    'org-supervisor.'.$this->organisation->id,
                ]
            );

            return $request->user()->authTo(
                [
                    'org-supervisor.'.$this->organisation->id,
                    'shops-view'.$this->organisation->id,
                ]
            );
        } else {
            $this->canEdit = $request->user()->authTo("products.{$this->shop->id}.edit");

            return $request->user()->authTo("products.{$this->shop->id}.view");
        }
    }

    public function inOrganisation(Organisation $organisation, MasterProductCategory $subDepartment, ActionRequest $request): MasterProductCategory
    {
        $this->initialisation($organisation, $request);

        return $this->handle($subDepartment);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inShop(Organisation $organisation, Shop $shop, MasterProductCategory $subDepartment, ActionRequest $request): MasterProductCategory
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($subDepartment);
    }

    public function inDepartment(Organisation $organisation, Shop $shop, MasterProductCategory $department, MasterProductCategory $subDepartment, ActionRequest $request): MasterProductCategory
    {
        $this->initialisationFromShop($shop, $request)->withTab(DepartmentTabsEnum::values());

        return $this->handle($subDepartment);
    }

    public function htmlResponse(MasterProductCategory $subDepartment, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'title'       => __('Sub-department'),
                // 'breadcrumbs' => $this->getBreadcrumbs(
                //     $subDepartment,
                //     $request->route()->originalParameters()
                // ),
                // 'navigation'  => [
                //     'previous' => ShowSubDepartment::make()->getPrevious($subDepartment, $request),
                //     'next'     => ShowSubDepartment::make()->getNext($subDepartment, $request),
                // ],
                'pageHead'    => [
                    'title'   => $subDepartment->code,
                    'actions' => [
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
                            'title'  => __('id'),
                            'fields' => [
                                'code' => [
                                    'type'  => 'input',
                                    'label' => __('code'),
                                    'value' => $subDepartment->code
                                ],
                                'name' => [
                                    'type'  => 'input',
                                    'label' => __('name'),
                                    'value' => $subDepartment->name
                                ],
                                "image"         => [
                                    "type"    => "image_crop_square",
                                    "label"   => __("Image"),
                                    "value"   => $subDepartment->imageSources(720, 480),
                                ],
                            ]
                        ]

                    ],
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.models.sub-department.update',
                            'parameters' => [
                                'MasterProductCategory' => $subDepartment->id
                            ]
                        ],
                    ]
                ]
            ]
        );
    }


    // public function getBreadcrumbs(MasterProductCategory $subDepartment, array $routeParameters): array
    // {
    //     return ShowSubDepartment::make()->getBreadcrumbs(
    //         subDepartment: $subDepartment,
    //         routeParameters: $routeParameters,
    //         suffix: '('.__('Editing').')'
    //     );
    // }


}
