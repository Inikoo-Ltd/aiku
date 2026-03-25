<?php

namespace App\Actions\HumanResources\WorkSchedule;

use App\Actions\OrgAction;
use App\Models\HumanResources\WorkSchedule;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;
use Inertia\Inertia;
use Inertia\Response;

class EditWorkSchedule extends OrgAction
{
    public function handle(Organisation $organisation, WorkSchedule $workSchedule): WorkSchedule
    {
        return $workSchedule->load('days');
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo([
            'org-admin.' . $this->organisation->id
        ]);
    }

    public function asController(Organisation $organisation, WorkSchedule $workSchedule, ActionRequest $request)
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation, $workSchedule);
    }

    public function htmlResponse(WorkSchedule $workSchedule, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/HumanResources/EditWorkSchedule',
            [
                'breadcrumbs' => [
                    [
                        'type' => 'simple',
                        'simple' => [
                            'route' => ['grp.org.hr.show', $this->organisation->slug],
                            'label' => __('HR'),
                            'icon' => 'fal fa-bars',
                        ],
                    ],
                    [
                        'type' => 'simple',
                        'simple' => [
                            'route' => ['grp.org.hr.shift_schedules.index', $this->organisation->slug],
                            'label' => __('Shift Schedules'),
                            'icon' => 'fal fa-clock',
                        ],
                    ],
                    [
                        'type' => 'simple',
                        'simple' => [
                            'route' => [
                                'name' => 'grp.org.hr.shift_schedules.edit',
                                'parameters' => [$this->organisation->slug, $workSchedule->id],
                            ],
                            'label' => $workSchedule->name,
                        ],
                    ],
                ],
                'title' => __('Edit Shift Schedule'),
                'pageHead' => [
                    'title' => __('Edit Shift Schedule'),
                ],
                'schedule' => $workSchedule,
            ]
        );
    }
}
