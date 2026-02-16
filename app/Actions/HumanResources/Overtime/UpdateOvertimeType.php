<?php

namespace App\Actions\HumanResources\Overtime;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Enums\HumanResources\Overtime\OvertimeCategoryEnum;
use App\Enums\HumanResources\Overtime\OvertimeCompensationTypeEnum;
use App\Models\HumanResources\OvertimeType;
use App\Models\SysAdmin\Organisation;
use App\Rules\IUnique;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateOvertimeType extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    private OvertimeType $overtimeType;

    public function handle(OvertimeType $overtimeType, array $modelData): OvertimeType
    {
        if (array_key_exists('code', $modelData) && $modelData['code'] === $overtimeType->code) {
            unset($modelData['code']);
        }

        $overtimeType->update($modelData);

        return $overtimeType->refresh();
    }

    public function rules(): array
    {
        return [
            'code'              => [
                'sometimes',
                'required',
                new IUnique(
                    table: 'overtime_types',
                    extraConditions: [
                        [
                            'column' => 'organisation_id',
                            'value'  => $this->organisation->id,
                        ],
                        [
                            'column'   => 'id',
                            'operator' => '!=',
                            'value'    => $this->overtimeType->id,
                        ],
                    ]
                ),
                'max:32',
                'alpha_dash',
            ],
            'name'              => ['sometimes', 'required', 'string', 'max:128'],
            'description'       => ['sometimes', 'nullable', 'string'],
            'category'          => ['sometimes', 'required', Rule::enum(OvertimeCategoryEnum::class)],
            'compensation_type' => ['sometimes', 'required', Rule::enum(OvertimeCompensationTypeEnum::class)],
            'multiplier'        => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'settings'          => ['sometimes', 'nullable', 'array'],
            'is_active'         => ['sometimes', 'boolean'],
        ];
    }

    public function asController(Organisation $organisation, OvertimeType $overtimeType, ActionRequest $request): OvertimeType
    {
        $this->overtimeType = $overtimeType;
        $this->initialisation($organisation, $request);

        return $this->handle($overtimeType, $this->validatedData);
    }

    public function action(OvertimeType $overtimeType, array $modelData): OvertimeType
    {
        $this->asAction = true;
        $this->overtimeType = $overtimeType;
        $this->initialisation($overtimeType->organisation, $modelData);

        return $this->handle($overtimeType, $this->validatedData);
    }

    public function htmlResponse(OvertimeType $overtimeType): RedirectResponse
    {
        return Redirect::back()->with('success', __('Overtime type updated successfully.'));
    }
}
