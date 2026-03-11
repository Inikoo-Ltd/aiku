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

class UpdateRestrictedPeriod extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    private RestrictedPeriod $restrictedPeriod;

    public function handle(RestrictedPeriod $restrictedPeriod, array $modelData): RestrictedPeriod
    {
        $modelData['updated_by_id'] = $modelData['updated_by_id'] ?? auth()->id();

        $restrictedPeriod->update($modelData);

        return $restrictedPeriod->refresh();
    }

    public function rules(): array
    {
        return [
            'label'                   => ['sometimes', 'required', 'string', 'max:255'],
            'start_date'              => ['sometimes', 'required', 'date'],
            'end_date'                => ['sometimes', 'required', 'date', 'after_or_equal:start_date'],
            'strictness'              => ['sometimes', 'string', Rule::in(['block', 'warn'])],
            'is_active'               => ['sometimes', 'boolean'],
            'allow_superuser_override' => ['sometimes', 'boolean'],
            'holiday_year_id'         => ['sometimes', 'nullable', 'exists:holiday_years,id'],
            'updated_by_id'           => ['sometimes', 'nullable', 'exists:users,id'],
        ];
    }

    public function afterValidator(): void
    {
        if (isset($this->validatedData['start_date']) && isset($this->validatedData['end_date'])) {
            if ($this->validatedData['start_date'] > $this->validatedData['end_date']) {
                abort(422, __('End date must be after or equal to start date.'));
            }
        }
    }

    public function asController(Organisation $organisation, RestrictedPeriod $restrictedPeriod, ActionRequest $request): RestrictedPeriod
    {
        $this->restrictedPeriod = $restrictedPeriod;
        $this->initialisation($organisation, $request);

        return $this->handle($restrictedPeriod, $this->validatedData);
    }

    public function action(RestrictedPeriod $restrictedPeriod, array $modelData): RestrictedPeriod
    {
        $this->asAction = true;
        $this->restrictedPeriod = $restrictedPeriod;
        $this->initialisation($restrictedPeriod->organisation, $modelData);

        return $this->handle($restrictedPeriod, $this->validatedData);
    }

    public function htmlResponse(RestrictedPeriod $restrictedPeriod): RedirectResponse
    {
        request()->session()->flash('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Restricted period successfully updated.'),
        ]);

        return Redirect::back();
    }
}
