<?php

namespace App\Actions\HumanResources\AttendanceAdjustment;

use App\Actions\OrgAction;
use App\Enums\HumanResources\Attendance\AttendanceAdjustmentStatusEnum;
use App\Enums\HumanResources\Leave\LeaveStatusEnum;
use App\Http\Resources\HumanResources\AttendanceAdjustmentResource;
use App\Models\HumanResources\AttendanceAdjustment;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\Leave;
use App\Models\HumanResources\Timesheet;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File;
use Illuminate\Contracts\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class StoreAttendanceAdjustment extends OrgAction
{
    private ?Employee $employee = null;
    private ?Timesheet $timesheet = null;

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

    public function handle(Employee $employee, array $modelData): AttendanceAdjustment
    {
        $date = Carbon::parse($modelData['date']);
        $timezone = $employee->organisation->timezone->name ?? 'UTC';

        $originalStartAt = null;
        $originalEndAt = null;

        if (isset($modelData['timesheet_id'])) {
            $this->timesheet = Timesheet::find($modelData['timesheet_id']);
            if ($this->timesheet) {
                $originalStartAt = $this->timesheet->start_at;
                $originalEndAt = $this->timesheet->end_at;
            }
        }

        if (!$originalStartAt || !$originalEndAt) {
            $originalStartAt = $date->copy()->setTime(8, 0, 0);
            $originalEndAt = $date->copy()->setTime(17, 0, 0);
        }

        $adjustment = AttendanceAdjustment::create([
            'group_id'            => $employee->group_id,
            'organisation_id'     => $employee->organisation_id,
            'employee_id'         => $employee->id,
            'employee_name'       => $employee->contact_name,
            'timesheet_id'        => $modelData['timesheet_id'] ?? null,
            'date'                => $date,
            'original_start_at'   => $originalStartAt,
            'original_end_at'     => $originalEndAt,
            'requested_start_at'  => $modelData['requested_start_at'] ?? null,
            'requested_end_at'    => $modelData['requested_end_at'] ?? null,
            'reason'              => $modelData['reason'],
            'status'              => AttendanceAdjustmentStatusEnum::PENDING,
        ]);

        if (isset($modelData['attachments'])) {
            foreach ($modelData['attachments'] as $file) {
                $media = $adjustment->addMedia($file)->toMediaCollection('attachments');
                $media->ulid = Str::ulid();
                $media->save();
            }
        }

        return $adjustment;
    }

    public function rules(): array
    {
        $this->employee = $this->resolveEmployee(request());

        return [
            'organisation'        => ['nullable', 'string'],
            'timesheet_id'        => ['nullable', 'exists:timesheets,id'],
            'date'                => ['required', 'date'],
            'requested_start_at'  => ['nullable', 'date_format:H:i'],
            'requested_end_at'    => ['nullable', 'date_format:H:i', 'after:requested_start_at'],
            'reason'              => ['required', 'string', 'max:1000'],
            'attachments'         => ['nullable', 'array', 'max:3'],
            'attachments.*'       => ['nullable', File::types(['pdf', 'jpg', 'jpeg', 'png'])->max(5 * 1024)],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if (!$this->employee) {
            return;
        }

        $date = Carbon::parse(request()->input('date'));

        $existingLeave = Leave::where('employee_id', $this->employee->id)
            ->where('status', LeaveStatusEnum::APPROVED->value)
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->exists();

        if ($existingLeave) {
            $validator->errors()->add('date', __('You have approved leave on this date. Adjustments are not allowed.'));
        }

        $existingAdjustment = AttendanceAdjustment::where('employee_id', $this->employee->id)
            ->where('date', $date)
            ->where('status', AttendanceAdjustmentStatusEnum::PENDING->value)
            ->exists();

        if ($existingAdjustment) {
            $validator->errors()->add('date', __('You already have a pending adjustment request for this date.'));
        }
    }

    public function asController(ActionRequest $request): AttendanceAdjustment
    {
        $this->employee = $this->resolveEmployee($request);

        if (!$this->employee) {
            abort(404, __('Employee record not found for current user.'));
        }

        $this->initialisation($this->employee->organisation, $request);

        $validated = $this->validatedData;

        if (isset($validated['requested_start_at'])) {
            $date = Carbon::parse($validated['date']);
            $validated['requested_start_at'] = $date->copy()
                ->setTimeFromTimeString($validated['requested_start_at'])
                ->setTimezone('UTC');
        }

        if (isset($validated['requested_end_at'])) {
            $date = Carbon::parse($validated['date']);
            $validated['requested_end_at'] = $date->copy()
                ->setTimeFromTimeString($validated['requested_end_at'])
                ->setTimezone('UTC');
        }

        return $this->handle($this->employee, $validated);
    }

    public function htmlResponse(AttendanceAdjustment $adjustment, ActionRequest $request): RedirectResponse
    {
        return Redirect::route('grp.clocking_employees.index', ['tab' => 'adjustments'])
            ->with('notification', [
                'status'      => 'success',
                'title'       => __('Success!'),
                'description' => __('Adjustment request submitted successfully.'),
            ]);
    }

    public function jsonResponse(AttendanceAdjustment $adjustment): AttendanceAdjustmentResource
    {
        return AttendanceAdjustmentResource::make($adjustment);
    }
}
