<?php

namespace App\Actions\HumanResources\Leave;

use App\Actions\OrgAction;
use App\Enums\HumanResources\Leave\LeaveStatusEnum;
use App\Enums\HumanResources\Leave\LeaveTypeEnum;
use App\Http\Resources\HumanResources\LeaveResource;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\EmployeeLeaveBalance;
use App\Models\HumanResources\Leave;
use App\Models\HumanResources\Timesheet;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Contracts\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class StoreLeave extends OrgAction
{
    private ?Employee $employee = null;

    private function resolveEmployee(Request $request): ?Employee
    {
        $user = $request->user();

        if (!$user) {
            return null;
        }

        $organisationScope = $request->input('organisation') ?? $request->route('organisation');
        if (is_object($organisationScope)) {
            $organisationScope = $organisationScope->slug ?? $organisationScope->id ?? null;
        }

        if ($organisationScope) {
            $organisationScope = (string)$organisationScope;
            $isNumericOrganisationId = ctype_digit($organisationScope);

            $employee = $user->employees()
                ->whereHas('organisation', function ($query) use ($organisationScope, $isNumericOrganisationId) {
                    $query->where('slug', $organisationScope);

                    if ($isNumericOrganisationId) {
                        $query->orWhere('id', (int)$organisationScope);
                    }
                })
                ->first();

            if ($employee) {
                return $employee;
            }
        }

        return $user->employees()->first();
    }

    public function handle(Employee $employee, array $modelData): Leave
    {
        $startDate = Carbon::parse($modelData['start_date']);
        $endDate = Carbon::parse($modelData['end_date']);
        $durationDays = $this->calculateDurationDays($startDate, $endDate, $employee);

        $leave = Leave::create([
            'group_id'        => $employee->group_id,
            'organisation_id' => $employee->organisation_id,
            'employee_id'     => $employee->id,
            'employee_name'   => $employee->contact_name,
            'type'            => $modelData['type'],
            'start_date'      => $startDate,
            'end_date'        => $endDate,
            'duration_days'   => $durationDays,
            'reason'          => $modelData['reason'] ?? null,
            'status'          => LeaveStatusEnum::PENDING,
        ]);

        if (isset($modelData['attachments'])) {
            foreach ($modelData['attachments'] as $file) {
                $media = $leave->addMedia($file)->toMediaCollection('attachments');
                $media->ulid = Str::ulid();
                $media->save();
            }
        }

        return $leave;
    }

    private function calculateDurationDays(Carbon $startDate, Carbon $endDate, Employee $employee): int
    {
        $days = 0;
        $current = $startDate->copy();

        while ($current->lte($endDate)) {
            if ($current->isWeekday()) {
                $days++;
            }
            $current->addDay();
        }

        return $days;
    }

    public function rules(): array
    {
        $this->employee = $this->resolveEmployee(request());
        $leaveTypes = array_column(LeaveTypeEnum::cases(), 'value');

        return [
            'organisation' => ['nullable', 'string'],
            'type'         => ['required', Rule::in($leaveTypes)],
            'start_date'   => ['required', 'date', 'after_or_equal:today'],
            'end_date'     => ['required', 'date', 'after_or_equal:start_date'],
            'reason'       => ['required', 'string', 'max:1000'],
            'attachments'  => ['nullable', 'array', 'max:3'],
            'attachments.*' => ['nullable', File::types(['pdf', 'jpg', 'jpeg', 'png'])->max(5 * 1024)],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if (!$this->employee) {
            return;
        }

        $startDate = Carbon::parse(request()->input('start_date'));
        $endDate = Carbon::parse(request()->input('end_date'));

        $existingLeave = Leave::where('employee_id', $this->employee->id)
            ->where('status', '!=', LeaveStatusEnum::REJECTED->value)
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate])
                    ->orWhere(function ($q) use ($startDate, $endDate) {
                        $q->where('start_date', '<=', $startDate)
                            ->where('end_date', '>=', $endDate);
                    });
            })->exists();

        if ($existingLeave) {
            $validator->errors()->add('start_date', __('You already have a leave request overlapping with these dates.'));
        }

        $hasTimesheet = Timesheet::where('subject_type', 'Employee')
            ->where('subject_id', $this->employee->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->whereNotNull('start_at')
            ->exists();

        if ($hasTimesheet) {
            $validator->errors()->add('start_date', __('You have existing attendance records in this period. Please request an adjustment instead.'));
        }

        $type = request()->input('type');
        $durationDays = $this->calculateDurationDays($startDate, $endDate, $this->employee);
        $balance = EmployeeLeaveBalance::firstOrCreate(
            [
                'employee_id' => $this->employee->id,
                'year'        => now()->year,
            ],
            [
                'annual_days'  => 14,
                'medical_days' => 14,
            ]
        );

        $remainingField = match ($type) {
            LeaveTypeEnum::ANNUAL->value => 'annual_remaining',
            LeaveTypeEnum::MEDICAL->value => 'medical_remaining',
            default => null,
        };

        if ($remainingField && $balance->$remainingField < $durationDays) {
            $validator->errors()->add('duration_days', __('Insufficient leave balance.'));
        }
    }

    public function asController(ActionRequest $request): Leave
    {
        $this->employee = $this->resolveEmployee($request);

        if (!$this->employee) {
            abort(404, __('Employee record not found for current user.'));
        }

        $this->initialisation($this->employee->organisation, $request);

        return $this->handle($this->employee, $this->validatedData);
    }

    public function htmlResponse(Leave $leave, ActionRequest $request): RedirectResponse
    {
        return Redirect::route('grp.clocking_employees.index', ['tab' => 'leaves'])
            ->with('notification', [
                'status'     => 'success',
                'title'      => __('Success!'),
                'description' => __('Leave request submitted successfully.'),
            ]);
    }

    public function jsonResponse(Leave $leave): LeaveResource
    {
        return LeaveResource::make($leave);
    }
}
