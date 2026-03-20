<?php

namespace App\Actions\HumanResources\RestrictedPeriods;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Models\HumanResources\RestrictedPeriod;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreRestrictedPeriod extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    public function handle(Organisation $organisation, array $modelData): RestrictedPeriod
    {
        $modelData['organisation_id'] = $organisation->id;
        $modelData['created_by_id']   = $modelData['created_by_id'] ?? auth()->id();
        $modelData['updated_by_id']   = $modelData['updated_by_id'] ?? auth()->id();

        return RestrictedPeriod::query()->create($modelData);
    }

    public function rules(): array
    {
        return [
            'label'                   => ['required', 'string', 'max:255'],
            'start_date'              => ['required', 'date'],
            'end_date'                => ['required', 'date', 'after_or_equal:start_date'],
            'strictness'              => ['sometimes', 'string', Rule::in(['block', 'warn'])],
            'is_active'               => ['sometimes', 'boolean'],
            'allow_superuser_override' => ['sometimes', 'boolean'],
            'holiday_year_id'         => ['sometimes', 'nullable', 'exists:holiday_years,id'],
            'created_by_id'           => ['sometimes', 'nullable', 'exists:users,id'],
            'updated_by_id'           => ['sometimes', 'nullable', 'exists:users,id'],
        ];
    }

    public function asController(Organisation $organisation, ActionRequest $request): RestrictedPeriod
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation, $this->validatedData);
    }

    public function action(Organisation $organisation, array $modelData): RestrictedPeriod
    {
        $this->asAction = true;
        $this->initialisation($organisation, $modelData);

        return $this->handle($organisation, $this->validatedData);
    }

    public function htmlResponse(RestrictedPeriod $restrictedPeriod): RedirectResponse
    {
        request()->session()->flash('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Restricted period successfully created.'),
        ]);

        return Redirect::back();
    }
}
