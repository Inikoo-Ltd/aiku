<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Tue, 14 Mar 2023 09:31:03 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Production\ManufactureTask\UI;

use App\Actions\OrgAction;
use App\Enums\Production\ManufactureTask\ManufactureTaskOperativeRewardAllowanceTypeEnum;
use App\Enums\Production\ManufactureTask\ManufactureTaskOperativeRewardTermsEnum;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateManufactureTask extends OrgAction
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
                'title'       => __('New manufacture task'),
                'pageHead'    => [
                    'title'        => __('New manufacture task'),
                    'icon'         => [
                        'title' => __('Create manufacture task'),
                        'icon'  => 'fal fa-industry'
                    ],
                    'actions'      => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('Cancel'),
                            'route' => [
                                'name'       => 'grp.org.productions.show.crafts.manufacture_tasks.index',
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
                                'code' => [
                                    'type'     => 'input',
                                    'label'    => __('code'),
                                    'value'    => '',
                                    'required' => true
                                ],
                                'name' => [
                                    'type'     => 'input',
                                    'label'    => __('name'),
                                    'value'    => '',
                                    'required' => true
                                ],
                                'task_materials_cost' => [
                                    'type'     => 'input',
                                    'label'    => __('task materials cost'),
                                    'value'    => '',
                                    'required' => true
                                ],
                                'task_energy_cost' => [
                                    'type'     => 'input',
                                    'label'    => __('task energy cost'),
                                    'value'    => '',
                                    'required' => true
                                ],
                                'task_other_cost' => [
                                    'type'     => 'input',
                                    'label'    => __('task other cost'),
                                    'value'    => '',
                                    'required' => true
                                ],
                                'task_work_cost' => [
                                    'type'     => 'input',
                                    'label'    => __('task work cost'),
                                    'value'    => '',
                                    'required' => true
                                ],
                                'status' => [
                                    'type'     => 'toggle',
                                    'label'    => __('status'),
                                    'value'    => true,
                                    'required' => true
                                ],
                                'task_lower_target' => [
                                    'type'     => 'input',
                                    'label'    => __('task lower target'),
                                    'value'    => '',
                                    'required' => true
                                ],
                                'task_upper_target' => [
                                    'type'     => 'input',
                                    'label'    => __('task upper target'),
                                    'value'    => '',
                                    'required' => true
                                ],
                                'operative_reward_terms' => [
                                    'type'      => 'select',
                                    'options'   => ManufactureTaskOperativeRewardTermsEnum::values(),
                                    'label'     => __('operative reward terms'),
                                    'value'     => '',
                                    'required'  => true
                                ],
                                'operative_reward_allowance_type' => [
                                    'type'      => 'select',
                                    'options'   => ManufactureTaskOperativeRewardAllowanceTypeEnum::values(),
                                    'label'     => __('operative reward allowance type'),
                                    'value'     => '',
                                    'required'  => true
                                ],
                                'operative_reward_amount' => [
                                    'type'     => 'input',
                                    'label'    => __('operative reward amount'),
                                    'value'    => '',
                                    'required' => true
                                ],

                            ]
                        ]
                    ],
                    'route'      => [
                        'name'       => 'grp.models.production.manufacture_tasks.store',
                        'parameters' => [$this->parent->id]
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
            IndexManufactureTasks::make()->getBreadcrumbs(request()->route()->getName(), $routeParameters),
            [
                [
                    'type'         => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Creating task'),
                    ]
                ]
            ]
        );
    }
}
