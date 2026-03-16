<?php

namespace App\Actions\HumanResources\Overtime;

use App\Actions\OrgAction;
use App\Enums\HumanResources\Overtime\OvertimeRequestStatusEnum;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\OvertimeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class UpdateEmployeeOvertimeRequest extends OrgAction
{
    protected ?Employee $employee = null;

    public function prepareForValidation(ActionRequest $request): void
    {
        $date = $this->get('requested_date');

        if ($date) {
            $timezone = $this->organisation->timezone->name ?? null;
            $date = Carbon::parse($date, $timezone)->startOfDay();
        }

        $startHour = (int)$this->get('start_hour', 0);
        $startMinute = (int)$this->get('start_minute', 0);
        $durationHour = (int)$this->get('duration_hours', 0);
        $durationMinute = (int)$this->get('duration_minutes', 0);

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
            'organisation'               => ['nullable', 'string'],
            'overtime_type_id'           => [
                'required',
                'integer',
                Rule::exists('overtime_types', 'id')->where('organisation_id', $this->organisation->id),
            ],
            'requested_date'             => ['required', 'date'],
            'requested_start_at'         => ['nullable', 'date'],
            'requested_end_at'           => ['nullable', 'date'],
            'requested_duration_minutes' => ['required', 'integer', 'min:1'],
            'reason'                     => ['sometimes', 'nullable', 'string'],
            'lieu_requested_minutes'     => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $durationMinutes = (int)$this->get('requested_duration_minutes', 0);

            if ($durationMinutes <= 0) {
                $validator->errors()->add('duration_hours', __('Duration must be greater than zero.'));
            }
        });
    }

    public function handle(Employee $employee, OvertimeRequest $overtimeRequest, array $modelData): OvertimeRequest
    {
        if ($overtimeRequest->employee_id !== $employee->id) {
            throw new NotFoundHttpException();
        }

        if ($overtimeRequest->status !== OvertimeRequestStatusEnum::PENDING) {
            abort(403, __('Only pending overtime requests can be edited.'));
        }

        $overtimeRequest->update([
            'overtime_type_id'           => $modelData['overtime_type_id'],
            'requested_date'             => $modelData['requested_date'],
            'requested_start_at'         => $modelData['requested_start_at'] ?? null,
            'requested_end_at'           => $modelData['requested_end_at'] ?? null,
            'requested_duration_minutes' => $modelData['requested_duration_minutes'],
            'reason'                     => $modelData['reason'] ?? null,
            'lieu_requested_minutes'     => $modelData['lieu_requested_minutes'] ?? 0,
        ]);

        return $overtimeRequest->refresh();
    }

    public function asController(ActionRequest $request, OvertimeRequest $overtimeRequest): OvertimeRequest
    {
        $this->employee = $this->resolveEmployee($request);

        if (!$this->employee) {
            throw new NotFoundHttpException(__('Employee record not found for current user.'));
        }

        $this->initialisation($this->employee->organisation, $request);

        return $this->handle($this->employee, $overtimeRequest, $this->validatedData);
    }

    public function htmlResponse(OvertimeRequest $overtimeRequest, ActionRequest $request): RedirectResponse
    {
        return Redirect::route('grp.clocking_employees.index', ['tab' => 'overtime'])
            ->with('notification', [
                'status'      => 'success',
                'title'       => __('Success!'),
                'description' => __('Overtime request updated successfully.'),
            ]);
    }
}
