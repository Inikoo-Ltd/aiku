<?php

namespace App\Services\HumanResources;

use App\Models\HumanResources\Employee;
use App\Models\HumanResources\RestrictedException;
use App\Models\HumanResources\RestrictedPeriod;
use App\Models\HumanResources\LeaveType;
use Illuminate\Support\Carbon;

class RestrictedPeriodService
{
    public function checkRestrictedPeriod(
        Employee $employee,
        string $leaveTypeCode,
        Carbon $startDate,
        Carbon $endDate,
        ?LeaveType $leaveType = null
    ): ?array {
        $activePeriods = RestrictedPeriod::query()
            ->where('organisation_id', $employee->organisation_id)
            ->where('is_active', true)
            ->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate)
            ->with('targets')
            ->get();

        foreach ($activePeriods as $period) {
            $isTargeted = $this->isTargetedByPeriod($period, $employee, $leaveTypeCode);

            if (!$isTargeted) {
                continue;
            }

            $hasException = $this->hasExceptionForEmployee($period, $employee, $startDate, $endDate);

            if (!$hasException) {
                $strictness = $period->strictness;

                return [
                    'period_label' => $period->label,
                    'strictness'   => $strictness,
                    'message'      => $strictness === 'block'
                        ? __('Leave cannot be taken during restricted period: :period (:start - :end).', [
                            'period' => $period->label,
                            'start'  => $period->start_date->format('Y-m-d'),
                            'end'    => $period->end_date->format('Y-m-d'),
                        ])
                        : __('Warning: Leave during restricted period: :period (:start - :end).', [
                            'period' => $period->label,
                            'start'  => $period->start_date->format('Y-m-d'),
                            'end'    => $period->end_date->format('Y-m-d'),
                        ]),
                ];
            }
        }

        return null;
    }

    private function isTargetedByPeriod(
        RestrictedPeriod $period,
        Employee $employee,
        string $leaveTypeCode
    ): bool {
        if ($period->targets->isEmpty()) {
            return true;
        }

        foreach ($period->targets as $target) {
            $matches = false;

            switch ($target->target_type) {
                case 'Employee':
                    $matches = $target->target_id === $employee->id;
                    break;
                case 'Department':
                    $matches = $target->target_id === $employee->department_id;
                    break;
                case 'LeaveType':
                    $matches = $target->target_id === $this->getLeaveTypeId($leaveTypeCode, $employee->organisation_id);
                    break;
            }

            if ($matches) {
                return true;
            }
        }

        return false;
    }

    private function hasExceptionForEmployee(
        RestrictedPeriod $period,
        Employee $employee,
        Carbon $startDate,
        Carbon $endDate
    ): bool {
        return RestrictedException::query()
            ->where('organisation_id', $employee->organisation_id)
            ->where('employee_id', $employee->id)
            ->where(function ($query) use ($period) {
                $query->whereNull('restricted_period_id')
                    ->orWhere('restricted_period_id', $period->id);
            })
            ->where('from_date', '<=', $endDate)
            ->where('to_date', '>=', $startDate)
            ->exists();
    }

    private function getLeaveTypeId(string $leaveTypeCode, int $organisationId): ?int
    {
        $leaveType = LeaveType::where('code', $leaveTypeCode)
            ->where('organisation_id', $organisationId)
            ->first();

        return $leaveType?->id;
    }
}
