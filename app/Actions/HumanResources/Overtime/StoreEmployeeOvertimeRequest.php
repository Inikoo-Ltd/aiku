<?php

namespace App\Actions\HumanResources\Overtime;

use App\Actions\OrgAction;
use App\Enums\HumanResources\Overtime\OvertimeRequestSourceEnum;
use App\Enums\HumanResources\Overtime\OvertimeRequestStatusEnum;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\OvertimeRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class StoreEmployeeOvertimeRequest extends OrgAction
{
    protected ?Employee $employee = null;

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
            $organisationScope = (string) $organisationScope;
            $isNumericOrganisationId = ctype_digit($organisationScope);

            $employee = $user->employees()
                ->whereHas('organisation', function ($query) use ($organisationScope, $isNumericOrganisationId) {
                    $query->where('slug', $organisationScope);

                    if ($isNumericOrganisationId) {
                        $query->orWhere('id', (int) $organisationScope);
                    }
                })
                ->first();

            if ($employee) {
                return $employee;
            }
        }

        return $user->employees()->first();
    }

    public function handle(Employee $employee, array $modelData): OvertimeRequest
    {
        $durationMinutes = (int) data_get($modelData, 'requested_duration_minutes', 0);

        $modelData['group_id'] = $employee->group_id;
        $modelData['organisation_id'] = $employee->organisation_id;
        $modelData['employee_id'] = $employee->id;
        $modelData['requested_by_employee_id'] = $employee->id;
        $modelData['status'] = OvertimeRequestStatusEnum::PENDING;
        $modelData['source'] = OvertimeRequestSourceEnum::EMPLOYEE;
        $modelData['lieu_requested_minutes'] = $modelData['lieu_requested_minutes'] ?? 0;

        unset($modelData['organisation']);

        if ($durationMinutes <= 0) {
            $modelData['requested_duration_minutes'] = 0;
        }

        return OvertimeRequest::query()->create($modelData);
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        $date = $this->get('requested_date');

        if ($date) {
            $timezone = $this->organisation->timezone->name ?? null;
            $date = Carbon::parse($date, $timezone)->startOfDay();
        }

        $startHour = (int) $this->get('start_hour', 0);
        $startMinute = (int) $this->get('start_minute', 0);
        $durationHour = (int) $this->get('duration_hours', 0);
        $durationMinute = (int) $this->get('duration_minutes', 0);

        $durationMinutes = ($durationHour * 60) + $durationMinute;

        if ($date) {
            $startAt = $date->copy()->setTime($startHour, $startMinute);
            $this->set('requested_start_at', $startAt);

            if ($durationMinutes > 0) {
                $this->set('requested_end_at', $startAt->copy()->addMinutes($durationMinutes));
            }
        }

        $this->set('requested_duration_minutes', $durationMinutes);
    }

    public function rules(): array
    {
        return [
            'organisation'              => ['nullable', 'string'],
            'overtime_type_id'          => [
                'required',
                'integer',
                Rule::exists('overtime_types', 'id')->where('organisation_id', $this->organisation->id),
            ],
            'requested_date'            => ['required', 'date'],
            'requested_start_at'        => ['nullable', 'date'],
            'requested_end_at'          => ['nullable', 'date'],
            'requested_duration_minutes' => ['required', 'integer', 'min:1'],
            'reason'                    => ['sometimes', 'nullable', 'string'],
            'lieu_requested_minutes'    => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $durationMinutes = (int) $this->get('requested_duration_minutes', 0);

            if ($durationMinutes <= 0) {
                $validator->errors()->add('duration_hours', __('Duration must be greater than zero.'));
            }
        });
    }

    public function asController(ActionRequest $request): OvertimeRequest
    {
        $this->employee = $this->resolveEmployee($request);

        if (!$this->employee) {
            throw new NotFoundHttpException(__('Employee record not found for current user.'));
        }

        $this->initialisation($this->employee->organisation, $request);

        return $this->handle($this->employee, $this->validatedData);
    }

    public function htmlResponse(OvertimeRequest $overtimeRequest, ActionRequest $request): RedirectResponse
    {
        return Redirect::route('grp.clocking_employees.index', ['tab' => 'overtime'])
            ->with('notification', [
                'status'      => 'success',
                'title'       => __('Success!'),
                'description' => __('Overtime request submitted successfully.'),
            ]);
    }
}
