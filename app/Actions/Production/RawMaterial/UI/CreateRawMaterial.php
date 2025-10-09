<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Tue, 14 Mar 2023 09:31:03 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Production\RawMaterial\UI;

use App\Actions\OrgAction;
use App\Enums\Production\RawMaterial\RawMaterialStateEnum;
use App\Enums\Production\RawMaterial\RawMaterialStockStatusEnum;
use App\Enums\Production\RawMaterial\RawMaterialTypeEnum;
use App\Enums\Production\RawMaterial\RawMaterialUnitEnum;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateRawMaterial extends OrgAction
{
    protected Production|Organisation $parent;

    public function handle(ActionRequest $request): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->originalParameters()
                ),
                'title'       => __('New raw material'),
                'pageHead'    => [
                    'title'        => __('New raw material'),
                    'icon'         => [
                        'title' => __('Create raw material'),
                        'icon'  => 'fal fa-industry'
                    ],
                    'actions'      => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('Cancel'),
                            'route' => [
                                'name'       => 'grp.org.productions.show.crafts.raw_materials.index',
                                'parameters' => $request->route()->originalParameters()
                            ],
                        ]
                    ]
                ],
                'formData' => [
                    'blueprint' => [
                        [
                            'title'  => __('Create raw material'),
                            'fields' => [

                                'type' => [
                                    'type'     => 'select',
                                    'options'  => RawMaterialTypeEnum::values(),
                                    'label'    => __('type'),
                                    'value'    => '',
                                    'required' => true
                                ],
                                'state' => [
                                    'type'     => 'select',
                                    'options'  => RawMaterialStateEnum::values(),
                                    'label'    => __('state'),
                                    'value'    => '',
                                    'required' => true
                                ],
                                'code' => [
                                    'type'     => 'input',
                                    'label'    => __('code'),
                                    'value'    => '',
                                    'required' => true
                                ],
                                'description' => [
                                    'type'     => 'input',
                                    'label'    => __('description'),
                                    'value'    => '',
                                    'required' => true
                                ],
                                'unit' => [
                                    'type'      => 'select',
                                    'options'   => RawMaterialUnitEnum::values(),
                                    'label'     => __('unit'),
                                    'value'     => '',
                                    'required'  => true
                                ],
                                'unit_cost' => [
                                    'type'     => 'input',
                                    'label'    => __('unit cost'),
                                    'value'    => '',
                                    'required' => true
                                ],
                                'quantity_on_location' => [
                                    'type'     => 'input',
                                    'label'    => __('quantity on location'),
                                    'value'    => '',
                                    'required' => true
                                ],
                                'stock_status' => [
                                    'type'      => 'select',
                                    'options'   => RawMaterialStockStatusEnum::values(),
                                    'label'     => __('stock status'),
                                    'value'     => '',
                                    'required'  => true
                                ],

                            ]
                        ]
                    ],
                    'route'      => [
                        'name'        => 'grp.models.production.raw-materials.store',
                        'parameters'  => [$this->parent->id]
                    ]
                ],

            ]
        );
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->parent instanceof Organisation) {
            $this->canEdit = $request->user()->authTo('org-supervisor.'.$this->organisation->id);

            return $request->user()->authTo(
                [
                    'productions-view.'.$this->organisation->id,
                    'org-supervisor.'.$this->organisation->id
                ]
            );
        }

        $this->canEdit = $request->user()->authTo("productions_rd.{$this->production->id}.edit");

        return $request->user()->authTo("productions_rd.{$this->production->id}.view");
    }


    public function inOrganisation(Organisation $organisation, ActionRequest $request): Response
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request);

        return $this->handle($request);
    }

    public function asController(Organisation $organisation, Production $production, ActionRequest $request): Response
    {
        $this->parent = $production;
        $this->initialisationFromProduction($production, $request);

        return $this->handle($request);
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            IndexRawMaterials::make()->getBreadcrumbs(request()->route()->getName(), $routeParameters),
            [
                [
                    'type'         => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Creating raw material'),
                    ]
                ]
            ]
        );
    }
}
