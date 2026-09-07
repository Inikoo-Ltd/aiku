<?php

namespace App\Actions\HumanResources\Timesheet;

use App\Actions\HumanResources\TimeTracker\CloseTimeTracker;
use App\Actions\HumanResources\TimeTracker\StoreTimeTracker;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Enums\HumanResources\Clocking\ClockingTypeEnum;
use App\Models\HumanResources\Clocking;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\Timesheet;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class StoreManualTimesheet extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    public function handle(Organisation $organisation, array $modelData): Timesheet
    {
        /** @var Employee $employee */
        $employee = Employee::findOrFail($modelData['employee_id']);
        $date     = Carbon::parse($modelData['date'])->toDateString();
        $timezone = $employee->organisation->timezone?->name ?? config('app.timezone');

        return DB::transaction(function () use ($employee, $date, $modelData, $timezone) {
            $timesheet = StoreTimesheet::make()->action($employee, ['date' => $date]);

            if (!empty($modelData['clock_in'])) {
                $workplace = $employee->workplaces()->first() ?? $employee->organisation->workplaces()->first();

                $clockIn = Clocking::create([
                    'group_id'        => $employee->group_id,
                    'organisation_id' => $employee->organisation_id,
                    'workplace_id'    => $workplace?->id,
                    'timesheet_id'    => $timesheet->id,
                    'subject_type'    => 'Employee',
                    'subject_id'      => $employee->id,
                    'type'            => ClockingTypeEnum::MANUAL,
                    'clocked_at'      => Carbon::parse($date.' '.$modelData['clock_in'], $timezone)->utc(),
                    'generator_type'  => 'User',
                    'generator_id'    => Auth::id(),
                    'notes'           => $modelData['notes'] ?? null,
                ]);

                $timeTracker = StoreTimeTracker::make()->action($timesheet, $clockIn, []);

                if (!empty($modelData['clock_out'])) {
                    $clockOutAt = Carbon::parse($date.' '.$modelData['clock_out'], $timezone)->utc();

                    if ($clockOutAt->lessThanOrEqualTo($clockIn->clocked_at)) {
                        $clockOutAt->addDay();
                    }

                    $clockOut = Clocking::create([
                        'group_id'        => $employee->group_id,
                        'organisation_id' => $employee->organisation_id,
                        'workplace_id'    => $workplace?->id,
                        'timesheet_id'    => $timesheet->id,
                        'subject_type'    => 'Employee',
                        'subject_id'      => $employee->id,
                        'type'            => ClockingTypeEnum::MANUAL,
                        'clocked_at'      => $clockOutAt,
                        'generator_type'  => 'User',
                        'generator_id'    => Auth::id(),
                    ]);

                    CloseTimeTracker::make()->action($timeTracker, $clockOut, []);
                }
            }

            return $timesheet->fresh();
        });
    }

    public function rules(): array
    {
        return [
            'employee_id' => [
                'required',
                Rule::exists('employees', 'id')->where('organisation_id', $this->organisation->id),
            ],
            'date' => [
                'required',
                'date',
                Rule::unique('timesheets', 'date')->where(function ($query) {
                    $query->where('subject_type', 'Employee')
                        ->where('subject_id', $this->get('employee_id'));
                }),
            ],
            'clock_in'  => ['nullable', 'date_format:H:i'],
            'clock_out' => ['nullable', 'date_format:H:i', 'after:clock_in'],
            'notes'     => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator, ActionRequest $request): void
    {
        $validator->after(function ($validator) {
            if ($this->get('clock_out') && !$this->get('clock_in')) {
                $validator->errors()->add('clock_out', __('Clock out needs a clock in time.'));
            }
        });
    }

    public function getValidationMessages(): array
    {
        return [
            'date.unique' => __('A timesheet already exists for this employee on this date.'),
        ];
    }

    public function action(Organisation $organisation, array $modelData): Timesheet
    {
        $this->asAction = true;
        $this->initialisation($organisation, $modelData);

        return $this->handle($organisation, $this->validatedData);
    }

    public function asController(Organisation $organisation, ActionRequest $request): Timesheet
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation, $this->validatedData);
    }

    public function htmlResponse(Timesheet $timesheet): RedirectResponse
    {
        return Redirect::back()->with('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Timesheet created.'),
        ]);
    }
}
