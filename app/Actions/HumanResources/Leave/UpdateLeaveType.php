<?php

namespace App\Actions\HumanResources\Leave;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Enums\HumanResources\Leave\LeaveCategoryEnum;
use App\Models\HumanResources\LeaveType;
use App\Models\SysAdmin\Organisation;
use App\Rules\IUnique;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateLeaveType extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    private LeaveType $leaveType;

    public function handle(LeaveType $leaveType, array $modelData): LeaveType
    {
        if (array_key_exists('code', $modelData) && $modelData['code'] === $leaveType->code) {
            unset($modelData['code']);
        }

        $settings = $leaveType->settings ?? [];
        if (!is_array($settings)) {
            $settings = [];
        }

        if (array_key_exists('settings', $modelData) && is_array($modelData['settings'])) {
            $settings = array_merge($settings, $modelData['settings']);
        }

        if (array_key_exists('value', $modelData)) {
            $settings['value'] = (float) $modelData['value'];
        } elseif (!array_key_exists('value', $settings) || !is_numeric($settings['value'])) {
            $settings['value'] = 1.0;
        }

        $modelData['settings'] = $settings;
        unset($modelData['value']);

        $leaveType->update($modelData);

        return $leaveType->refresh();
    }

    public function rules(): array
    {
        return [
            'code'                => [
                'sometimes',
                'required',
                new IUnique(
                    table: 'leave_types',
                    extraConditions: [
                        [
                            'column' => 'organisation_id',
                            'value'  => $this->organisation->id,
                        ],
                        [
                            'column'   => 'id',
                            'operator' => '!=',
                            'value'    => $this->leaveType->id,
                        ],
                    ]
                ),
                'max:32',
                'alpha_dash',
            ],
            'name'                => ['sometimes', 'required', 'string', 'max:128'],
            'color'               => ['sometimes', 'nullable', 'string', 'max:128'],
            'description'         => ['sometimes', 'nullable', 'string'],
            'category'            => ['sometimes', 'required', Rule::enum(LeaveCategoryEnum::class)],
            'requires_approval'              => ['sometimes', 'boolean'],
            'max_days_per_year'              => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'value'                          => ['sometimes', 'numeric', 'min:0.01'],
            'settings'                       => ['sometimes', 'nullable', 'array'],
            'settings.value'                 => ['sometimes', 'numeric', 'min:0.01'],
            'is_active'                      => ['sometimes', 'boolean'],
            'ignore_concurrency_leave_rules' => ['sometimes', 'boolean'],
        ];
    }

    public function asController(Organisation $organisation, LeaveType $leaveType, ActionRequest $request): LeaveType
    {
        $this->leaveType = $leaveType;
        $this->initialisation($organisation, $request);

        return $this->handle($leaveType, $this->validatedData);
    }

    public function action(LeaveType $leaveType, array $modelData): LeaveType
    {
        $this->asAction = true;
        $this->leaveType = $leaveType;
        $this->initialisation($leaveType->organisation, $modelData);

        return $this->handle($leaveType, $this->validatedData);
    }

    public function htmlResponse(LeaveType $leaveType): RedirectResponse
    {
        request()->session()->flash('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Leave type successfully updated.'),
        ]);

        return Redirect::back();
    }
}
