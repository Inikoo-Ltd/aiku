<?php

namespace App\Services\HumanResources;

use App\Enums\HumanResources\Concurrency\LeaveConcurrencyRuleTypeEnum;
use App\Enums\HumanResources\Concurrency\LeaveConcurrencyTargetRoleEnum;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\Leave;
use App\Models\HumanResources\LeaveConcurrencyRule;
use App\Models\HumanResources\LeaveType;
use Illuminate\Support\Carbon;

class LeaveConcurrencyService
{
    public function checkOverlap(
        Employee   $employee,
        string     $leaveTypeCode,
        Carbon     $startDate,
        Carbon     $endDate,
        ?LeaveType $leaveType = null
    ): array
    {
        $exemptLeaveTypes = ['sick-leave', 'meeting', 'late-for-work'];

        if ($leaveType && $leaveType->ignore_concurrency_leave_rules) {
            return [];
        }

        if (in_array($leaveTypeCode, $exemptLeaveTypes, true)) {
            return [];
        }

        $conflicts = [];

        $activeRules = LeaveConcurrencyRule::query()
            ->where('organisation_id', $employee->organisation_id)
            ->where('is_active', true)
            ->with('targets')
            ->get();

        foreach ($activeRules as $rule) {
            $isTargeted = $this->isTargetedByRule($rule, $employee, $leaveTypeCode);

            if (!$isTargeted) {
                continue;
            }

            $conflictResult = $this->checkRuleConflict(
                $rule,
                $employee,
                $leaveTypeCode,
                $startDate,
                $endDate,
                $leaveType
            );

            if ($conflictResult) {
                $conflicts[] = $conflictResult;
            }
        }

        return $conflicts;
    }

    private function isTargetedByRule(
        LeaveConcurrencyRule $rule,
        Employee             $employee,
        string               $leaveTypeCode
    ): bool
    {
        foreach ($rule->targets as $target) {
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

    private function checkBidirectionalConflict(
        LeaveConcurrencyRule $rule,
        Employee             $employee,
        string               $leaveTypeCode,
        Carbon               $startDate,
        Carbon               $endDate
    ): ?array
    {
        $allTargets = $rule->targets;
        foreach ($allTargets as $target) {
            $partnerEmployees = $this->getEmployeesByTarget($target, $employee->organisation_id);
            foreach ($partnerEmployees as $partnerEmployee) {
                if ($partnerEmployee->id === $employee->id) {
                    continue;
                }

                $hasApprovedLeave = Leave::query()
                    ->where('employee_id', $partnerEmployee->id)
                    ->where('status', 'approved')
                    ->where(function ($query) use ($startDate, $endDate) {
                        $query->where('start_date', '<=', $endDate)
                            ->where('end_date', '>=', $startDate);
                    })
                    ->exists();
                if ($hasApprovedLeave) {
                    $partnerName = $partnerEmployee->contact_name ?? __('Employee #' . $partnerEmployee->id);
                    return [
                        'rule_name' => $rule->name,
                        'type' => 'bidirectional_block',
                        'message' => __('Cannot take leave while :name is on approved leave', ['name' => $partnerName]),
                    ];
                }
            }
        }
        return null;
    }

    private function checkRuleConflict(
        LeaveConcurrencyRule $rule,
        Employee             $employee,
        string               $leaveTypeCode,
        Carbon               $startDate,
        Carbon               $endDate,
        ?LeaveType           $leaveType = null
    ): ?array
    {
        $ruleType = $rule->rule_type->value;

        if ($ruleType === LeaveConcurrencyRuleTypeEnum::QUOTA->value) {
            return $this->checkQuotaConflict($rule, $employee, $leaveTypeCode, $startDate, $endDate);
        }

        if ($ruleType === LeaveConcurrencyRuleTypeEnum::DEPENDENCY->value) {
            return $this->checkDependencyConflict($rule, $employee, $leaveTypeCode, $startDate, $endDate);
        }

        if ($ruleType === LeaveConcurrencyRuleTypeEnum::BIDIRECTIONAL->value) {
            return $this->checkBidirectionalConflict($rule, $employee, $leaveTypeCode, $startDate, $endDate);
        }

        return null;
    }

    private function checkQuotaConflict(
        LeaveConcurrencyRule $rule,
        Employee             $employee,
        string               $leaveTypeCode,
        Carbon               $startDate,
        Carbon               $endDate
    ): ?array
    {
        $limit = $rule->limit ?? 1;
        $maxOverlapDays = $rule->max_overlap_days ?? 0;

        $scopedEmployeeIds = $this->getScopedEmployeeIds($rule, $employee->organisation_id);

        if ($scopedEmployeeIds->isEmpty()) {
            return null;
        }

        $leaveTypeIds = $this->getTargetLeaveTypeIds($rule, $employee->organisation_id);

        $overlappingLeaves = Leave::query()
            ->whereIn('employee_id', $scopedEmployeeIds)
            ->where('status', '!=', 'rejected')
            ->where(function ($query) use ($startDate, $endDate) {
                $query->where('start_date', '<=', $endDate)
                    ->where('end_date', '>=', $startDate);
            });

        if ($leaveTypeIds->isNotEmpty()) {
            $overlappingLeaves->whereIn('leave_type_id', $leaveTypeIds);
        }

        $overlappingLeaves = $overlappingLeaves->get();

        $count = $overlappingLeaves
            ->pluck('employee_id')
            ->unique()
            ->count();

        if ($count >= $limit) {
            return [
                'rule_name' => $rule->name,
                'type' => 'quota_exceeded',
                'message' => __('The quota for concurrent leave has been reached. :count employees already have leave on these dates. Maximum allowed: :max.', [
                    'count' => $count,
                    'max' => $limit,
                ]),
            ];
        }

        if ($maxOverlapDays > 0) {
            $otherEmployeeLeaves = $overlappingLeaves
                ->where('employee_id', '!=', $employee->id);

            foreach ($otherEmployeeLeaves as $existingLeave) {
                $overlapStart = max($startDate, $existingLeave->start_date);
                $overlapEnd = min($endDate, $existingLeave->end_date);
                $overlapDays = $overlapStart->diffInDays($overlapEnd) + 1;

                if ($overlapDays > $maxOverlapDays) {
                    $otherEmployeeName = $existingLeave->employee->contact_name ?? __('Employee #' . $existingLeave->employee_id);

                    return [
                        'rule_name' => $rule->name,
                        'type' => 'max_overlap_exceeded',
                        'message' => __('Leave overlaps with :name for :count days. Maximum allowed: :max days.', [
                            'name' => $otherEmployeeName,
                            'count' => $overlapDays,
                            'max' => $maxOverlapDays,
                        ]),
                    ];
                }
            }
        }

        return null;
    }

    private function getScopedEmployeeIds(LeaveConcurrencyRule $rule, int $organisationId): \Illuminate\Support\Collection
    {
        $employeeIds = collect();

        foreach ($rule->targets as $target) {
            if ($target->target_type === 'Employee') {
                $employeeIds->push($target->target_id);
            } elseif ($target->target_type === 'Department') {
                $departmentEmployeeIds = Employee::where('department_id', $target->target_id)
                    ->where('organisation_id', $organisationId)
                    ->pluck('id');
                $employeeIds = $employeeIds->merge($departmentEmployeeIds);
            }
        }

        return $employeeIds->unique();
    }

    private function getTargetLeaveTypeIds(LeaveConcurrencyRule $rule, int $organisationId): \Illuminate\Support\Collection
    {
        $leaveTypeCodes = collect();

        foreach ($rule->targets as $target) {
            if ($target->target_type === 'LeaveType') {
                $leaveType = LeaveType::where('id', $target->target_id)
                    ->where('organisation_id', $organisationId)
                    ->first();
                if ($leaveType) {
                    $leaveTypeCodes->push($leaveType->id);
                }
            }
        }

        return $leaveTypeCodes;
    }

    private function checkDependencyConflict(
        LeaveConcurrencyRule $rule,
        Employee             $employee,
        string               $leaveTypeCode,
        Carbon               $startDate,
        Carbon               $endDate
    ): ?array
    {
        $subjectTargets = $rule->targets->where('role', LeaveConcurrencyTargetRoleEnum::SUBJECT->value);

        foreach ($subjectTargets as $target) {
            $subjectEmployees = $this->getEmployeesByTarget($target, $employee->organisation_id);

            foreach ($subjectEmployees as $subjectEmployee) {
                $hasSubjectLeave = Leave::query()
                    ->where('employee_id', $subjectEmployee->id)
                    ->where('status', '!=', 'rejected')
                    ->where(function ($query) use ($startDate, $endDate) {
                        $query->where('start_date', '<=', $endDate)
                            ->where('end_date', '>=', $startDate);
                    })
                    ->exists();

                if ($hasSubjectLeave) {
                    $subjectName = $subjectEmployee->contact_name ?? __('Employee #' . $subjectEmployee->id);
                    return [
                        'rule_name' => $rule->name,
                        'type' => 'dependency_block',
                        'message' => __('Cannot take leave while :name is on leave.', [
                            'name' => $subjectName,
                        ]),
                    ];
                }
            }
        }

        return null;
    }

    private function getEmployeesByTarget($target, int $organisationId): \Illuminate\Database\Eloquent\Collection
    {
        if ($target->target_type === 'Employee') {
            return Employee::where('id', $target->target_id)
                ->where('organisation_id', $organisationId)
                ->get();
        }

        if ($target->target_type === 'Department') {
            return Employee::where('department_id', $target->target_id)
                ->where('organisation_id', $organisationId)
                ->get();
        }

        return collect();
    }

    private function getLeaveTypeId(string $leaveTypeCode, int $organisationId): ?int
    {
        $leaveType = LeaveType::where('code', $leaveTypeCode)
            ->where('organisation_id', $organisationId)
            ->first();

        return $leaveType?->id;
    }
}
