<?php

namespace App\Actions\HumanResources\Leave;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Enums\HumanResources\Leave\LeaveStatusEnum;
use App\Http\Resources\HumanResources\LeaveResource;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\Leave;
use App\Models\SysAdmin\Organisation;
use App\Services\HumanResources\LeaveTypeResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreEmployeeLeave extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    public function handle(Employee $employee, array $modelData): Leave
    {
        $startDate = Carbon::parse($modelData['start_date']);
        $endDate = Carbon::parse($modelData['end_date']);
        $leaveType = LeaveTypeResolver::findForOrganisationByCode($employee->organisation_id, $modelData['type'], true);

        $leave = Leave::create([
            'group_id' => $employee->group_id,
            'organisation_id' => $employee->organisation_id,
            'employee_id' => $employee->id,
            'employee_name' => $employee->contact_name,
            'type' => $modelData['type'],
            'leave_type_id' => $leaveType?->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'duration_days' => StoreLeave::make()->calculateDurationDays($startDate, $endDate, $employee),
            'is_half_day' => false,
            'session' => 'Full',
            'reason' => $modelData['reason'] ?? null,
            'status' => LeaveStatusEnum::APPROVED,
            'approved_by' => request()->user()?->id,
            'approved_at' => now(),
        ]);

        ApproveLeave::make()->applyBalanceDeduction($leave);

        return $leave;
    }

    public function rules(): array
    {
        return [
            'type' => [
                'required',
                'string',
                Rule::exists('leave_types', 'code')
                    ->where('organisation_id', $this->organisation->id)
                    ->where('is_active', true),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function asController(Organisation $organisation, Employee $employee, ActionRequest $request): Leave
    {
        $this->initialisation($organisation, $request);

        abort_unless($employee->organisation_id === $organisation->id, 404);

        return $this->handle($employee, $this->validatedData);
    }

    public function htmlResponse(Leave $leave): RedirectResponse
    {
        return Redirect::back()->with('notification', [
            'status' => 'success',
            'title' => __('Success!'),
            'description' => __('Leave recorded for :name.', ['name' => $leave->employee_name]),
        ]);
    }

    public function jsonResponse(Leave $leave): LeaveResource
    {
        return LeaveResource::make($leave);
    }
}
