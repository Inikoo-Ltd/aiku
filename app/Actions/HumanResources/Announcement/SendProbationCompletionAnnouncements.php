<?php

namespace App\Actions\HumanResources\Announcement;

use App\Models\HumanResources\Employee;
use App\Models\HumanResources\HRAnnouncement;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\Concerns\AsAction;

class SendProbationCompletionAnnouncements
{
    use AsAction;

    public string $jobQueue = 'low-priority';

    public function handle(): void
    {
        $organisations = Organisation::all();

        foreach ($organisations as $organisation) {
            $this->processOrganisation($organisation);
        }
    }

    protected function processOrganisation(Organisation $organisation): void
    {
        $probationDays = $organisation->settings['hr']['probation_period_days'] ?? 90;

        $employees = Employee::where('organisation_id', $organisation->id)
            ->whereNotNull('employment_start_at')
            ->where('state', 'working')
            ->get()
            ->filter(function ($employee) use ($probationDays) {
                if (!$employee->employment_start_at) {
                    return false;
                }
                $probationEnd = $employee->employment_start_at->addDays($employee->probation_period_days ?? $probationDays);
                return $probationEnd->isToday();
            });

        foreach ($employees as $employee) {
            $this->createAnnouncement($employee);
        }
    }

    protected function createAnnouncement(Employee $employee): void
    {
        $existingAnnouncement = HRAnnouncement::where('employee_id', $employee->id)
            ->where('type', 'probation_completion')
            ->whereDate('created_at', now()->toDateString())
            ->first();

        if ($existingAnnouncement) {
            return;
        }

        HRAnnouncement::create([
            'organisation_id' => $employee->organisation_id,
            'employee_id' => $employee->id,
            'type' => 'probation_completion',
            'title' => 'Probation Period Completed',
            'message' => "Congratulations! Your probation period has been completed. Please update your contract status.",
            'metadata' => [
                'employee_id' => $employee->id,
                'employee_name' => $employee->contact_name,
                'employment_start_at' => $employee->employment_start_at?->format('Y-m-d'),
            ],
        ]);

        HRAnnouncement::create([
            'organisation_id' => $employee->organisation_id,
            'employee_id' => null,
            'type' => 'probation_completion_admin',
            'title' => 'Employee Probation Completed',
            'message' => "{$employee->contact_name}'s probation period has been completed. Please update their contract status.",
            'metadata' => [
                'employee_id' => $employee->id,
                'employee_name' => $employee->contact_name,
                'employment_start_at' => $employee->employment_start_at?->format('Y-m-d'),
            ],
        ]);
    }
}
