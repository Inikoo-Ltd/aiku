<?php

namespace App\Actions\HumanResources\Overtime;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Enums\HumanResources\Overtime\OvertimeRequestSourceEnum;
use App\Enums\HumanResources\Overtime\OvertimeRequestStatusEnum;
use App\Models\HumanResources\OvertimeRequest;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreOvertimeRequest extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    protected bool $asAction = false;

    public function handle(Organisation $organisation, array $modelData): OvertimeRequest
    {
        data_set($modelData, 'group_id', $organisation->group_id);
        data_set($modelData, 'organisation_id', $organisation->id);
        data_set($modelData, 'source', OvertimeRequestSourceEnum::ADMIN_RECORD);
        data_set($modelData, 'lieu_requested_minutes', $modelData['lieu_requested_minutes'] ?? 0);

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
            'employee_id'                => [
                'required',
                'integer',
                Rule::exists('employees', 'id')->where('organisation_id', $this->organisation->id),
            ],
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
            'status'                     => ['required', Rule::enum(OvertimeRequestStatusEnum::class)],
            'lieu_requested_minutes'     => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function asController(Organisation $organisation, ActionRequest $request): OvertimeRequest
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation, $this->validatedData);
    }

    public function htmlResponse(OvertimeRequest $overtimeRequest): RedirectResponse
    {
        return Redirect::back()->with('success', __('Overtime request created successfully.'));
    }

    public function action(Organisation $organisation, array $modelData): OvertimeRequest
    {
        $this->asAction = true;
        $this->initialisation($organisation, $modelData);

        return $this->handle($organisation, $this->validatedData);
    }
}
