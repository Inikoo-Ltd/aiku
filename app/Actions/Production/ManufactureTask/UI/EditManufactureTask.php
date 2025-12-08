<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 May 2023 21:14:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ManufactureTask\UI;

use App\Actions\OrgAction;
use App\Enums\Fulfilment\Pallet\PalletTypeEnum;
use App\Enums\Production\ManufactureTask\ManufactureTaskOperativeRewardAllowanceTypeEnum;
use App\Enums\Production\ManufactureTask\ManufactureTaskOperativeRewardTermsEnum;
use App\Http\Resources\Fulfilment\PalletResource;
use App\Models\Production\ManufactureTask;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditManufactureTask extends OrgAction
{
    protected Production|Organisation $parent;

    public function handle(ManufactureTask $manufactureTask): ManufactureTask
    {
        return $manufactureTask;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->parent instanceof Organisation) {
            $this->canEdit = $request->user()->authTo('org-supervisor.'.$this->organisation->id);

            return $request->user()->authTo(
                [
                    'productions-view.'.$this->organisation->id,
                    'org-supervisor.'.$this->organisation->id,
                ]
            );
        }

        $this->canEdit = $request->user()->authTo("productions_rd.{$this->production->id}.edit");

        return $request->user()->authTo("productions_rd.{$this->production->id}.view");
    }

    public function jsonResponse(LengthAwarePaginator $storedItems): AnonymousResourceCollection
    {
        return PalletResource::collection($storedItems);
    }

    public function htmlResponse(ManufactureTask $manufactureTask, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->originalParameters()
                ),
                'title' => __('Edit manufacture task'),
                'pageHead' => [
                    'title' => __('Edit manufacture task'),
                    // 'actions'   => [
                    //     [
                    //         'type'  => 'button',
                    //         'style' => 'exitEdit',
                    //         'route' => [
                    //             'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
                    //             'parameters' => array_values($request->route()->originalParameters())
                    //         ]
                    //     ]
                    // ]
                ],
                'formData' => [
                    'blueprint' => [
                        [
                            'title' => __('Edit Manufacture Task'),
                            'label' => 'edit',
                            'icon' => ['fal', 'fa-narwhal'],
                            'fields' => [
                                'code' => [
                                    'type' => 'input',
                                    'label' => __('code'),
                                    'value' => $manufactureTask->code,
                                    'required' => true,
                                ],
                                'name' => [
                                    'type' => 'input',
                                    'label' => __('name'),
                                    'value' => $manufactureTask->name,
                                    'required' => true,
                                ],
                                'task_materials_cost' => [
                                    'type' => 'input',
                                    'label' => __('task materials cost'),
                                    'value' => $manufactureTask->task_materials_cost,
                                    'required' => true,
                                ],
                                'task_energy_cost' => [
                                    'type' => 'input',
                                    'label' => __('task energy cost'),
                                    'value' => $manufactureTask->task_energy_cost,
                                    'required' => true,
                                ],
                                'task_other_cost' => [
                                    'type' => 'input',
                                    'label' => __('task other cost'),
                                    'value' => $manufactureTask->task_other_cost,
                                    'required' => true,
                                ],
                                'task_work_cost' => [
                                    'type' => 'input',
                                    'label' => __('task work cost'),
                                    'value' => $manufactureTask->task_work_cost,
                                    'required' => true,
                                ],
                                'status' => [
                                    'type' => 'toggle',
                                    'label' => __('status'),
                                    'value' => $manufactureTask->status,
                                    'required' => true,
                                ],
                                'task_lower_target' => [
                                    'type' => 'input',
                                    'label' => __('task lower target'),
                                    'value' => $manufactureTask->task_lower_target,
                                    'required' => true,
                                ],
                                'task_upper_target' => [
                                    'type' => 'input',
                                    'label' => __('task upper target'),
                                    'value' => $manufactureTask->task_upper_target,
                                    'required' => true,
                                ],
                                'operative_reward_terms' => [
                                    'type' => 'select',
                                    'options' => ManufactureTaskOperativeRewardTermsEnum::values(),
                                    'label' => __('operative reward terms'),
                                    'value' => $manufactureTask->operative_reward_terms,
                                    'required' => true,
                                ],
                                'operative_reward_allowance_type' => [
                                    'type' => 'select',
                                    'options' => ManufactureTaskOperativeRewardAllowanceTypeEnum::values(),
                                    'label' => __('operative reward allowance type'),
                                    'value' => $manufactureTask->operative_reward_allowance_type,
                                    'required' => true,
                                ],
                                'operative_reward_amount' => [
                                    'type' => 'input',
                                    'label' => __('operative reward amount'),
                                    'value' => $manufactureTask->operative_reward_amount,
                                    'required' => true,
                                ],
                                // 'type' => [
                                //     'type'    => 'select',
                                //     'label'   => __('type'),
                                //     'value'   => $storedItem->type,
                                //     'required'=> true,
                                //     'options' => PalletTypeEnum::values()
                                // ],
                                // 'location' => [
                                //     'type'     => 'combobox',
                                //     'label'    => __('location'),
                                //     'value'    => '',
                                //     'required' => true,
                                //     'apiUrl'   => route('grp.json.locations') . '?filter[slug]=',
                                // ]
                            ],
                        ],
                    ],
                    'args' => [
                        'updateRoute' => [
                            'name' => 'grp.models.production.manufacture_tasks.update',
                            'parameters' => [$this->parent->id, $manufactureTask->id],
                        ],
                    ],
                ],
            ]
        );
    }

    public function inOrganisation(Organisation $organisation, ManufactureTask $manufactureTask, ActionRequest $request): ManufactureTask
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request);

        return $this->handle($manufactureTask);
    }

    public function asController(Organisation $organisation, Production $production, ManufactureTask $manufactureTask, ActionRequest $request): ManufactureTask
    {
        $this->parent = $production;
        $this->initialisationFromProduction($production, $request);

        return $this->handle($manufactureTask);
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowManufactureTask::make()->getBreadcrumbs(
                routeParameters: $routeParameters,
                suffix: '('.__('Editing').')'
            ),
            [
                [
                    'type' => 'editingModel',
                    'editingModel' => [
                        'label' => __('Editing raw material'),
                    ],
                ],
            ]
        );
    }
}
