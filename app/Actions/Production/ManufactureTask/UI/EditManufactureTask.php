<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 May 2023 21:14:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ManufactureTask\UI;

use App\Actions\OrgAction;
use App\Enums\Fulfilment\Pallet\PalletTypeEnum;
use App\Http\Resources\Fulfilment\PalletResource;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Models\HumanResources\Employee;
use App\Models\Production\ManufactureTask;
use App\Models\Production\WorkArea;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
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
                    'org-supervisor.'.$this->organisation->id
                ]
            );
        }

        $this->canEdit = $request->user()->authTo(["org-supervisor.{$this->organisation->id}", "productions_rd.{$this->production->id}.edit"]);

        return $request->user()->authTo(["org-supervisor.{$this->organisation->id}", "productions_rd.{$this->production->id}.view"]);
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
                'title'       => __('Edit manufacture task'),
                'pageHead'    => [
                    'title'     => __('Edit manufacture task'),
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
                            'title'  => __('Edit Manufacture Task'),
                            'label'  => 'edit',
                            'icon'   => ['fal', 'fa-narwhal'],
                            'fields' => [
                                'code' => [
                                    'type'     => 'input',
                                    'label'    => __('Code'),
                                    'value'    => $manufactureTask->code,
                                    'required' => true
                                ],
                                'name' => [
                                    'type'     => 'input',
                                    'label'    => __('Name'),
                                    'value'    => $manufactureTask->name,
                                    'required' => true
                                ],
                                'description' => [
                                    'type'     => 'textarea',
                                    'label'    => __('Description'),
                                    'value'    => $manufactureTask->description,
                                    'required' => false
                                ],
                                'is_piece_rate' => [
                                    'type'        => 'toggle',
                                    'label'       => __('Paid by piece rate'),
                                    'information' => __('Turn off for hourly work such as mixing. Sessions are paid at the base hourly rate, with no target, no bonus band and no piece reward, and the time still counts in the labour cost of the product.'),
                                    'value'       => $manufactureTask->is_piece_rate,
                                    'required'    => true
                                ],
                                'is_recorded_in_kg' => [
                                    'type'        => 'toggle',
                                    'label'       => __('Recorded in kg'),
                                    'information' => __('Turn on for steps such as mixing: the artisan enters the kg made, and the quantity on each recipe step is the kg of mix one unit takes.'),
                                    'value'       => $manufactureTask->is_recorded_in_kg,
                                    'required'    => true
                                ],
                                'is_quantity_only' => [
                                    'type'        => 'toggle',
                                    'label'       => __('Quantity only, no timer'),
                                    'information' => __('Turn on for hourly work such as filling to order: the artisan enters the quantity made and presses Done, with no Start/Stop timer. The session keeps who made it and when, with no hours.'),
                                    'value'       => $manufactureTask->is_quantity_only,
                                    'required'    => true
                                ],
                                'auto_assign_to' => [
                                    'type'        => 'select',
                                    'label'       => __('Assign to order jobs automatically'),
                                    'information' => __('When an order puts a product with this step on To Produce, the job goes straight to this artisan, or to the least busy artisan of this work area, one job per order. Leave empty to assign them by hand.'),
                                    'value'       => ($autoAssign = Arr::get($manufactureTask->data, 'auto_assign')) ? $autoAssign['type'].':'.$autoAssign['id'] : null,
                                    'options'     => $this->autoAssignOptions($manufactureTask),
                                    'searchable'  => true,
                                    'required'    => false,
                                ],
                                'status' => [
                                    'type'     => 'toggle',
                                    'label'    => __('Active'),
                                    'value'    => $manufactureTask->status,
                                    'required' => true
                                ],
                                // 'type' => [
                                //     'type'    => 'select',
                                //     'label'   => __('Type'),
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
                            ]
                        ]
                    ],
                    'args' => [
                        'updateRoute' => [
                            'name'       => 'grp.models.production.manufacture_tasks.update',
                            'parameters' => [$this->parent->id, $manufactureTask->id]
                        ],
                    ]
                ],
            ]
        );
    }

    /** @return array<int, array{value: string, label: string}> */
    private function autoAssignOptions(ManufactureTask $manufactureTask): array
    {
        $workAreas = WorkArea::where('production_id', $manufactureTask->production_id)->orderBy('name')->get();

        return $workAreas
            ->map(fn (WorkArea $workArea) => ['value' => 'work_area:'.$workArea->id, 'label' => __('Work area').': '.$workArea->name])
            ->concat(
                Employee::whereIn('id', DB::table('artisan_assignments')->where('artisanable_type', 'WorkArea')->whereIn('artisanable_id', $workAreas->pluck('id'))->select('employee_id'))
                    ->where('state', EmployeeStateEnum::WORKING)
                    ->orderBy('contact_name')
                    ->get()
                    ->map(fn (Employee $employee) => ['value' => 'employee:'.$employee->id, 'label' => $employee->contact_name])
            )
            ->values()
            ->all();
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
                    'type'         => 'editingModel',
                    'editingModel' => [
                        'label' => __('Editing raw material'),
                    ]
                ]
            ]
        );
    }
}
