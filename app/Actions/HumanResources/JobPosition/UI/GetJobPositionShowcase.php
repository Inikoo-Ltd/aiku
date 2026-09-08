<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Thu, 25 May 2023 15:03:06 Central European Summer Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\HumanResources\JobPosition\UI;

use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Http\Resources\HumanResources\JobPositionResource;
use App\Models\HumanResources\JobPosition;
use Lorisleiva\Actions\Concerns\AsObject;

class GetJobPositionShowcase
{
    use AsObject;

    public function handle(JobPosition $jobPosition): array
    {
        $stats         = $jobPosition->stats;
        $numberGuests  = IndexJobPositionGuests::numberGuests($jobPosition);
        $numberRoles   = $jobPosition->roles()->count();
        $numberWorking = $stats?->number_employees_currently_working ?? 0;

        return [
            'jobPosition'     => JobPositionResource::make($jobPosition),
            'stats'           => [
                [
                    'label'       => __('Employees currently working'),
                    'value'       => $numberWorking,
                    'information' => __('Employees in the working or leaving state holding this responsibility.'),
                ],
                [
                    'label'       => __('Employees ever assigned'),
                    'value'       => $stats?->number_employees ?? 0,
                    'information' => __('Includes employees who already left.'),
                ],
                [
                    'label'       => __('Guests'),
                    'value'       => $numberGuests,
                    'information' => __('External users holding this responsibility.'),
                ],
                [
                    'label'       => __('System roles'),
                    'value'       => $numberRoles,
                    'information' => __('Permission roles granted by this responsibility.'),
                ],
                [
                    'label'       => __('Employees work time'),
                    'value'       => round((float) ($stats?->number_employees_work_time ?? 0), 2),
                    'information' => __('Sum of the work time shares of its employees, 1 being one full time employee.'),
                ],
                [
                    'label'       => __('Guests work time'),
                    'value'       => round((float) ($stats?->number_guests_work_time ?? 0), 2),
                    'information' => __('Sum of the work time shares of its guests.'),
                ],
            ],
            'employeeStates'  => $this->employeeStates($jobPosition),
        ];
    }

    /**
     * @return array<int, array{state: string, label: string, icon: array, value: int}>
     */
    private function employeeStates(JobPosition $jobPosition): array
    {
        $stats  = $jobPosition->stats;
        $labels = EmployeeStateEnum::labels();
        $icons  = EmployeeStateEnum::stateIcon();

        $states = [];
        foreach (EmployeeStateEnum::cases() as $case) {
            $states[] = [
                'state' => $case->value,
                'label' => $labels[$case->value],
                'icon'  => $icons[$case->value],
                'value' => $stats?->{'number_employees_state_'.$case->value} ?? 0,
            ];
        }

        return $states;
    }
}
