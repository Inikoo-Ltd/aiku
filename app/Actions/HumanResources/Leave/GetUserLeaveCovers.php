<?php

namespace App\Actions\HumanResources\Leave;

use App\Enums\HumanResources\Leave\LeaveStatusEnum;
use App\Models\HumanResources\JobPosition;
use App\Models\HumanResources\Leave;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Lorisleiva\Actions\Concerns\AsObject;

class GetUserLeaveCovers
{
    use AsObject;

    /**
     * The absences the user covers that have not ended yet, one per covered leave, with the jobs
     * the absent colleague leaves to them and how much open work of each kind is waiting.
     *
     * @return array<int, array{id: int, employee_name: string, type_label: string, start_date: string, end_date: string, is_ongoing: bool, has_permissions: bool, job_positions: array<int, string>, open_work: array<int, array{key: string, label: string, count: int}>}>
     */
    public function handle(User $user): array
    {
        return $this->coveredLeaves($user)
            ->map(fn (Leave $leave) => [
                ...$this->summary($leave),
                'open_work' => GetCoveredWork::make()->counts($this->absentUserIds($leave)),
            ])
            ->all();
    }

    /**
     * @return SupportCollection<int, int>
     */
    public function absentUserIds(Leave $leave): SupportCollection
    {
        return $leave->employee?->users->pluck('id') ?? collect();
    }

    /**
     * @return Collection<int, Leave>
     */
    public function coveredLeaves(User $user): Collection
    {
        return Leave::query()
            ->where('status', LeaveStatusEnum::APPROVED)
            ->whereIn('cover_employee_id', $user->employees()->wherePivot('status', true)->pluck('employees.id'))
            ->whereDate('end_date', '>=', today())
            ->with(['leaveType:id,name', 'employee.jobPositions', 'employee.users:id'])
            ->orderBy('start_date')
            ->get();
    }

    /**
     * @return array{id: int, employee_name: string, type_label: string, start_date: string, end_date: string, is_ongoing: bool, has_permissions: bool, job_positions: array<int, string>}
     */
    public function summary(Leave $leave): array
    {
        return [
            'id'              => $leave->id,
            'employee_name'   => $leave->employee_name,
            'type_label'      => $leave->leaveType?->name ?? $leave->type,
            'start_date'      => $leave->start_date->format('Y-m-d'),
            'end_date'        => $leave->end_date->format('Y-m-d'),
            'is_ongoing'      => $leave->start_date->lte(today()),
            'has_permissions' => $leave->cover_has_permissions,
            'job_positions'   => $leave->employee?->jobPositions->map(fn (JobPosition $jobPosition) => $jobPosition->name)->unique()->values()->all() ?? [],
        ];
    }
}
