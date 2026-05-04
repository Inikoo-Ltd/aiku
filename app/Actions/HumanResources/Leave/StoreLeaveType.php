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

class StoreLeaveType extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    public function handle(Organisation $organisation, array $modelData): LeaveType
    {
        $modelData['group_id']        = $organisation->group_id;
        $modelData['organisation_id'] = $organisation->id;

        $settings = $modelData['settings'] ?? [];
        if (!is_array($settings)) {
            $settings = [];
        }

        $settingsValue = $settings['value'] ?? null;
        $settings['value'] = array_key_exists('value', $modelData)
            ? (float) $modelData['value']
            : (is_numeric($settingsValue) ? (float) $settingsValue : 1.0);
        $modelData['settings'] = $settings;

        unset($modelData['value']);

        return LeaveType::query()->create($modelData);
    }

    public function rules(): array
    {
        return [
            'code'                => [
                'required',
                new IUnique(
                    table: 'leave_types',
                    extraConditions: [
                        ['column' => 'organisation_id', 'value' => $this->organisation->id],
                    ],
                ),
                'max:32',
                'alpha_dash',
            ],
            'name'                => ['required', 'string', 'max:128'],
            'color'               => ['sometimes', 'nullable', 'string', 'max:128'],
            'description'         => ['sometimes', 'nullable', 'string'],
            'category'            => ['required', Rule::enum(LeaveCategoryEnum::class)],
            'requires_approval'              => ['sometimes', 'boolean'],
            'max_days_per_year'              => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'value'                          => ['sometimes', 'numeric', 'min:0.01'],
            'settings'                       => ['sometimes', 'nullable', 'array'],
            'settings.value'                 => ['sometimes', 'numeric', 'min:0.01'],
            'is_active'                      => ['sometimes', 'boolean'],
            'ignore_concurrency_leave_rules' => ['sometimes', 'boolean'],
        ];
    }

    public function asController(Organisation $organisation, ActionRequest $request): LeaveType
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation, $this->validatedData);
    }

    public function action(Organisation $organisation, array $modelData): LeaveType
    {
        $this->asAction = true;
        $this->initialisation($organisation, $modelData);

        return $this->handle($organisation, $this->validatedData);
    }

    public function htmlResponse(LeaveType $leaveType): RedirectResponse
    {
        request()->session()->flash('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Leave type successfully created.'),
        ]);

        return Redirect::back();
    }
}
