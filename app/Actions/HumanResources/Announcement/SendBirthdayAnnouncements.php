<?php

namespace App\Actions\HumanResources\Announcement;

use App\Models\HumanResources\Employee;
use App\Models\HumanResources\HRAnnouncement;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\Concerns\AsAction;

class SendBirthdayAnnouncements
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
        $employees = Employee::where('organisation_id', $organisation->id)
            ->whereNotNull('date_of_birth')
            ->whereRaw("TO_CHAR(date_of_birth, 'MM-DD') = TO_CHAR(CURRENT_DATE, 'MM-DD')")
            ->get();

        foreach ($employees as $employee) {
            $this->createAnnouncement($employee);
        }
    }

    protected function createAnnouncement(Employee $employee): void
    {
        $existingAnnouncement = HRAnnouncement::where('employee_id', $employee->id)
            ->where('type', 'birthday')
            ->whereDate('created_at', now()->toDateString())
            ->first();

        if ($existingAnnouncement) {
            return;
        }

        HRAnnouncement::create([
            'organisation_id' => $employee->organisation_id,
            'employee_id' => $employee->id,
            'type' => 'birthday',
            'title' => 'Happy Birthday!',
            'message' => "Happy Birthday, {$employee->contact_name}! Wishing you a wonderful day filled with joy and happiness.",
            'metadata' => [
                'employee_name' => $employee->contact_name,
                'date_of_birth' => $employee->date_of_birth?->format('Y-m-d'),
            ],
        ]);

        HRAnnouncement::create([
            'organisation_id' => $employee->organisation_id,
            'employee_id' => null,
            'type' => 'birthday_admin',
            'title' => 'Employee Birthday Today',
            'message' => "Today is {$employee->contact_name}'s birthday. Don't forget to wish them well!",
            'metadata' => [
                'employee_id' => $employee->id,
                'employee_name' => $employee->contact_name,
            ],
        ]);
    }
}
