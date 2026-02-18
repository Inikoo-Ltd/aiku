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

class StoreOvertimeType extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    public function handle(Organisation $organisation, array $modelData): OvertimeType
    {
        $modelData['group_id']        = $organisation->group_id;
        $modelData['organisation_id'] = $organisation->id;

        return OvertimeType::query()->create($modelData);
    }

    public function rules(): array
    {
        return [
            'code'              => [
                'required',
                new IUnique(
                    table: 'overtime_types',
                    extraConditions: [
                        ['column' => 'organisation_id', 'value' => $this->organisation->id],
                    ],
                ),
                'max:32',
                'alpha_dash',
            ],
            'name'              => ['required', 'string', 'max:128'],
            'description'       => ['sometimes', 'nullable', 'string'],
            'category'          => ['required', Rule::enum(OvertimeCategoryEnum::class)],
            'compensation_type' => ['required', Rule::enum(OvertimeCompensationTypeEnum::class)],
            'multiplier'        => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'settings'          => ['sometimes', 'nullable', 'array'],
            'is_active'         => ['sometimes', 'boolean'],
        ];
    }

    public function asController(Organisation $organisation, ActionRequest $request): OvertimeType
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation, $this->validatedData);
    }

    public function action(Organisation $organisation, array $modelData): OvertimeType
    {
        $this->asAction = true;
        $this->initialisation($organisation, $modelData);

        return $this->handle($organisation, $this->validatedData);
    }

    public function htmlResponse(OvertimeType $overtimeType): RedirectResponse
    {
        request()->session()->flash('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Overtime type successfully created.'),
        ]);

        return Redirect::back();
    }
}
