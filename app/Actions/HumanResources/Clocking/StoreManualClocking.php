<?php

namespace App\Actions\HumanResources\Clocking;

use App\Actions\HumanResources\Employee\Hydrators\EmployeeHydrateClockings;
use App\Actions\HumanResources\Timesheet\Hydrators\TimesheetHydrateTimeTrackers;
use App\Actions\HumanResources\TimeTracker\AddClockingToTimeTracker;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Enums\HumanResources\Clocking\ClockingTypeEnum;
use App\Models\HumanResources\Clocking;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\Timesheet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class StoreManualClocking extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    public function handle(Timesheet $timesheet, array $modelData): Clocking
    {
        /** @var Employee $employee */
        $employee = $timesheet->subject;
        $workplace = $employee->workplaces()->first() ?? $employee->organisation->workplaces()->first();

        return DB::transaction(function () use ($employee, $timesheet, $workplace, $modelData) {
            $clocking = Clocking::create([
                'group_id'        => $employee->group_id,
                'organisation_id' => $employee->organisation_id,
                'workplace_id'    => $workplace?->id,
                'timesheet_id'    => $timesheet->id,
                'subject_type'    => 'Employee',
                'subject_id'      => $employee->id,
                'type'            => ClockingTypeEnum::MANUAL,
                'clocked_at'      => Carbon::parse($modelData['clocked_at'])->utc(),
                'generator_type'  => 'User',
                'generator_id'    => Auth::id(),
                'notes'           => $modelData['notes'] ?? null,
            ]);

            AddClockingToTimeTracker::run($timesheet, $clocking);

            $startAt = $timesheet->timeTrackers()->min('starts_at');
            $endAt   = $timesheet->timeTrackers()->max('ends_at');

            $timesheet->update([
                'start_at' => $startAt,
                'end_at'   => $endAt,
            ]);

            TimesheetHydrateTimeTrackers::run($timesheet->id);
            EmployeeHydrateClockings::dispatch($employee);

            return $clocking->fresh();
        });
    }

    public function rules(): array
    {
        return [
            'clocked_at' => ['required', 'date'],
            'notes'      => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function asController(Timesheet $timesheet, ActionRequest $request): Clocking
    {
        $this->initialisation($timesheet->organisation, $request);

        return $this->handle($timesheet, $this->validatedData);
    }

    public function action(Timesheet $timesheet, array $modelData): Clocking
    {
        $this->asAction = true;
        $this->initialisation($timesheet->organisation, $modelData);

        return $this->handle($timesheet, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back()->with('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Clocking created.'),
        ]);
    }
}
