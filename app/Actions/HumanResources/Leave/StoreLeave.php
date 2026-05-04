<?php

namespace App\Actions\HumanResources\Leave;

use App\Actions\OrgAction;
use App\Enums\HumanResources\Leave\LeaveStatusEnum;
use App\Http\Resources\HumanResources\LeaveResource;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\EmployeeLeaveBalance;
use App\Models\HumanResources\Holiday;
use App\Models\HumanResources\Leave;
use App\Models\HumanResources\LeaveApprovalRecord;
use App\Models\HumanResources\LeaveApprover;
use App\Models\HumanResources\LeaveType;
use App\Models\HumanResources\Timesheet;
use App\Services\HumanResources\LeaveConcurrencyService;
use App\Services\HumanResources\LeaveTypeResolver;
use App\Services\HumanResources\RestrictedPeriodService;
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
    private ?LeaveType $selectedLeaveType = null;

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
        $isHalfDay = (bool)($modelData['is_half_day'] ?? false);
        $session = $modelData['session'] ?? 'Full';
        $durationDays = $isHalfDay ? 1 : $this->calculateDurationDays($startDate, $endDate, $employee);

        $leave = Leave::create([
            'group_id' => $employee->group_id,
            'organisation_id' => $employee->organisation_id,
            'employee_id' => $employee->id,
            'employee_name' => $employee->contact_name,
            'type' => $modelData['type'],
            'leave_type_id' => $this->selectedLeaveType?->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'duration_days' => $durationDays,
            'is_half_day' => $isHalfDay,
            'session' => $session,
            'reason' => $modelData['reason'] ?? null,
            'status' => LeaveStatusEnum::PENDING,
        ]);

        if (isset($modelData['attachments'])) {
            foreach ($modelData['attachments'] as $file) {
                $leave->addMedia($file)
                    ->withProperties([
                        'group_id' => $leave->group_id,
                        'type' => 'attachment',
                        'ulid' => (string)Str::ulid(),
                    ])
                    ->toMediaCollection('attachments');
            }
        }

        $level1Approvers = LeaveApprover::byOrganisation($leave->organisation)
            ->bySequence(1)
            ->active()
            ->get();

        foreach ($level1Approvers as $approver) {
            LeaveApprovalRecord::create([
                'leave_id' => $leave->id,
                'approver_id' => $approver->user_id,
                'sequence_number' => 1,
                'status' => 'pending',
            ]);
        }

        return $leave;
    }

    private function calculateDurationDays(Carbon $startDate, Carbon $endDate, Employee $employee): int
    {
        $days = 0;
        $current = $startDate->copy();

        $holidays = Holiday::query()
            ->where('organisation_id', $employee->organisation_id)
            ->whereDate('from', '<=', $endDate->toDateString())
            ->whereDate('to', '>=', $startDate->toDateString())
            ->get()
            ->pluck('from', 'to')
            ->flatMap(fn ($date, $to) => [
                $date->toDateString() => true,
                $to->toDateString() => true,
            ]);

        while ($current->lte($endDate)) {
            if ($current->isWeekday() && !isset($holidays[$current->toDateString()])) {
                $days++;
            }
            $current->addDay();
        }

        return $days;
    }

    private function leaveTypeValue(?LeaveType $leaveType): float
    {
        if (!$leaveType) {
            return 1.0;
        }

        return $leaveType->deductionValue();
    }

    private function leaveDeduction(Leave $leave): float
    {
        $value = $this->leaveTypeValue($leave->leaveType);
        $deduction = (float) $leave->duration_days * $value;

        if ($leave->is_half_day && $value === 1.0) {
            return 0.5;
        }

        return $deduction;
    }

    public function rules(): array
    {
        $this->employee = $this->resolveEmployee(request());

        $typeRules = ['required', 'string'];
        if ($this->employee) {
            $typeRules[] = Rule::exists('leave_types', 'code')
                ->where(function ($query) {
                    $query
                        ->where('organisation_id', $this->employee->organisation_id)
                        ->where('is_active', true);
                });
        }

        return [
            'organisation' => ['nullable', 'string'],
            'type' => $typeRules,
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'is_half_day' => ['sometimes', 'boolean'],
            'session' => ['sometimes', Rule::in(['Morning', 'Afternoon', 'Full'])],
            'reason' => ['required', 'string', 'max:1000'],
            'attachments' => ['nullable', 'array', 'max:3'],
            'attachments.*' => ['nullable', File::types(['pdf', 'jpg', 'jpeg', 'png'])->max(5 * 1024)],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if (!$this->employee) {
            return;
        }

        if ($validator->errors()->has('start_date') || $validator->errors()->has('end_date')) {
            return;
        }

        $startDate = Carbon::parse(request()->input('start_date'));
        $endDate = Carbon::parse(request()->input('end_date'));
        $type = request()->input('type');

        $this->selectedLeaveType = null;
        if ($type) {
            $this->selectedLeaveType = LeaveTypeResolver::findForOrganisationByCode(
                organisationId: $this->employee->organisation_id,
                code: (string)$type,
                onlyActive: true
            );
        }

        if (!$this->selectedLeaveType) {
            $validator->errors()->add('type', __('The selected leave type is invalid.'));
            return;
        }

        if ($endDate->lt($startDate)) {
            return;
        }

        $isHalfDay = (bool)request()->boolean('is_half_day');
        $session = request()->input('session', 'Full');
        if ($isHalfDay && !$startDate->isSameDay($endDate)) {
            $validator->errors()->add('end_date', __('Half day leave must be a single date.'));
            return;
        }
        if ($isHalfDay && !in_array($session, ['Morning', 'Afternoon'], true)) {
            $validator->errors()->add('session', __('Please select Morning or Afternoon for half day leave.'));
            return;
        }

        if ($isHalfDay) {
            $conflictingLeave = Leave::where('employee_id', $this->employee->id)
                ->where('status', '!=', LeaveStatusEnum::REJECTED->value)
                ->whereDate('start_date', $startDate)
                ->where(function ($query) use ($session) {
                    $query->where('is_half_day', false)
                        ->orWhere(function ($q) use ($session) {
                            $q->where('is_half_day', true)
                                ->where('session', $session);
                        });
                })
                ->exists();

            if ($conflictingLeave) {
                $message = $session === 'Full'
                    ? __('You already have a leave request on this date.')
                    : __('You already have a :session half-day leave on this date.', ['session' => $session]);
                $validator->errors()->add('session', $message);
                return;
            }
        }

        $concurrencyService = new LeaveConcurrencyService();
        $concurrencyConflicts = $concurrencyService->checkOverlap(
            $this->employee,
            $type,
            $startDate,
            $endDate,
            $this->selectedLeaveType
        );

        foreach ($concurrencyConflicts as $conflict) {
            $validator->errors()->add('start_date', $conflict['message']);
            break;
        }

        $hasTimesheet = Timesheet::where('subject_type', 'Employee')
            ->where('subject_id', $this->employee->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->whereNotNull('start_at')
            ->exists();

        if ($hasTimesheet) {
            $validator->errors()->add('start_date', __('You have existing attendance records in this period. Please request an adjustment instead.'));
        }

        $holidayOverlap = Holiday::query()
            ->where('organisation_id', $this->employee->organisation_id)
            ->whereDate('from', '<=', $endDate->toDateString())
            ->whereDate('to', '>=', $startDate->toDateString())
            ->orderBy('from')
            ->first();

        if ($holidayOverlap) {
            $label = $holidayOverlap->label ?: __('Holiday');
            $validator->errors()->add(
                'start_date',
                __('Leave cannot be submitted for holiday dates. Overlaps with :label (:from - :to).', [
                    'label' => $label,
                    'from' => $holidayOverlap->from->format('Y-m-d'),
                    'to' => $holidayOverlap->to->format('Y-m-d'),
                ])
            );
        }

        $restrictedPeriodService = new RestrictedPeriodService();
        $restrictedPeriodViolation = $restrictedPeriodService->checkRestrictedPeriod(
            $this->employee,
            $type,
            $startDate,
            $endDate,
            $this->selectedLeaveType
        );

        if ($restrictedPeriodViolation) {
            if ($restrictedPeriodViolation['strictness'] === 'block') {
                $validator->errors()->add('start_date', $restrictedPeriodViolation['message']);
            }
        }

        $durationDays = $this->calculateDurationDays($startDate, $endDate, $this->employee);
        $requestedDays = (float) $durationDays * $this->leaveTypeValue($this->selectedLeaveType);
        if ($isHalfDay && $requestedDays === (float) $durationDays) {
            $requestedDays = 0.5;
        }
        $balanceYear = $startDate->year;
        $bucket = LeaveTypeResolver::bucketFromLeaveType($this->selectedLeaveType, (string) $type);

        $balance = EmployeeLeaveBalance::firstOrCreate(
            [
                'employee_id' => $this->employee->id,
                'year'        => $balanceYear,
            ],
            [
                'annual_days'   => $this->employee->organisation->getDefaultAnnualLeaveDays(),
                'annual_used'   => 0,
                'medical_days'  => 0,
                'medical_used'  => 0,
                'unpaid_days'   => 0,
                'unpaid_used'   => 0,
            ]
        );

        $submittedDaysByBucket = Leave::query()
            ->where('employee_id', $this->employee->id)
            ->whereYear('start_date', $balanceYear)
            ->with('leaveType')
            ->get()
            ->filter(function (Leave $leave) {
                return $leave->status?->value !== LeaveStatusEnum::REJECTED->value;
            })
            ->sum(function (Leave $leave) use ($bucket) {
                if (LeaveTypeResolver::bucketFromLeaveType($leave->leaveType, $leave->type) !== $bucket) {
                    return 0;
                }

                return $this->leaveDeduction($leave);
            });

        $bucketAllowance = match ($bucket) {
            'annual' => (float) $balance->annual_days,
            'medical' => (float) $balance->medical_days,
            default => null,
        };

        if ($bucketAllowance !== null) {
            $bucketRemaining = max(0, $bucketAllowance - (float) $submittedDaysByBucket);
            if ($bucketRemaining < $requestedDays) {
                $validator->errors()->add(
                    'duration_days',
                    __('Insufficient leave balance. Remaining balance: :remaining days.', [
                        'remaining' => $bucketRemaining,
                    ])
                );
            }
        }

        if ($bucket === 'annual' && !$validator->errors()->has('duration_days')) {
            $leaveTypeMaxDays = (float) ($this->selectedLeaveType->max_days_per_year ?? PHP_INT_MAX);
            $annualAllowance = min((float) $balance->annual_days, $leaveTypeMaxDays);
            $annualRemaining = max(0, $annualAllowance - (float) $submittedDaysByBucket);

            if ($annualRemaining < $requestedDays) {
                $maxDays = $annualAllowance % 1 === 0 ? (int) $annualAllowance : $annualAllowance;
                $validator->errors()->add(
                    'duration_days',
                    __('Maximum for the selected leave type is :max days.', [
                        'max' => $maxDays,
                    ])
                );
            }
        }

        if ($this->selectedLeaveType->max_days_per_year !== null && $bucket !== 'annual' && !$validator->errors()->has('duration_days')) {
            $leaveTypeSubmittedDays = Leave::query()
                ->where('employee_id', $this->employee->id)
                ->where('leave_type_id', $this->selectedLeaveType->id)
                ->whereYear('start_date', $balanceYear)
                ->where('status', '!=', LeaveStatusEnum::REJECTED->value)
                ->get()
                ->sum(function (Leave $leave) {
                    return $this->leaveDeduction($leave);
                });

            $leaveTypeAvailable = max(0, (float)$this->selectedLeaveType->max_days_per_year - (float)$leaveTypeSubmittedDays);

            if ($leaveTypeAvailable < $requestedDays) {
                $maxDays = $this->selectedLeaveType->max_days_per_year % 1 === 0
                    ? (int)$this->selectedLeaveType->max_days_per_year
                    : $this->selectedLeaveType->max_days_per_year;
                $validator->errors()->add(
                    'duration_days',
                    __('Maximum for the selected leave type is :max days.', [
                        'max' => $maxDays,
                    ])
                );
            }
        }
    }

    public function asController(ActionRequest $request): Leave
    {
        $this->employee = $this->resolveEmployee($request);

        if (!$this->employee) {
            abort(404, __('Employee record not found for current user.'));
        }

        $this->initialisation($this->employee->organisation, $request);

        if (!$this->selectedLeaveType) {
            $type = (string)($this->validatedData['type'] ?? '');
            if ($type !== '') {
                $this->selectedLeaveType = LeaveTypeResolver::findForOrganisationByCode(
                    organisationId: $this->employee->organisation_id,
                    code: $type,
                    onlyActive: true
                );
            }
        }

        return $this->handle($this->employee, $this->validatedData);
    }

    public function htmlResponse(Leave $leave, ActionRequest $request): RedirectResponse
    {
        return Redirect::route('grp.clocking_employees.index', ['tab' => 'leaves'])
            ->with('notification', [
                'status' => 'success',
                'title' => __('Success!'),
                'description' => __('Leave request submitted successfully.'),
            ]);
    }

    public function jsonResponse(Leave $leave): LeaveResource
    {
        return LeaveResource::make($leave);
    }
}
