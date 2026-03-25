<?php

namespace App\Actions\HumanResources\RestrictedPeriods;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\RestrictedException;
use App\Models\HumanResources\RestrictedPeriod;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class StoreRestrictedException extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    public function handle(Organisation $organisation, array $modelData): RestrictedException
    {
        $modelData['group_id']        = $organisation->group_id;
        $modelData['organisation_id'] = $organisation->id;
        $modelData['approved_by_id']   = $modelData['approved_by_id'] ?? auth()->id();

        return RestrictedException::query()->create($modelData);
    }

    public function rules(): array
    {
        return [
            'restricted_period_id' => ['sometimes', 'nullable', 'exists:restricted_periods,id'],
            'employee_id'          => ['required', 'exists:employees,id'],
            'from_date'            => ['required', 'date'],
            'to_date'              => ['required', 'date', 'after_or_equal:from_date'],
            'note'                 => ['sometimes', 'nullable', 'string', 'max:1000'],
            'approved_by_id'       => ['sometimes', 'nullable', 'exists:users,id'],
        ];
    }

    public function afterValidator(): void
    {
        $employee = Employee::find($this->validatedData['employee_id']);

        if (!$employee || $employee->organisation_id !== $this->organisation->id) {
            abort(422, __('Employee not found in this organisation.'));
        }

        if ($this->validatedData['from_date'] > $this->validatedData['to_date']) {
            abort(422, __('End date must be after or equal to start date.'));
        }

        if (isset($this->validatedData['restricted_period_id'])) {
            $restrictedPeriod = RestrictedPeriod::find($this->validatedData['restricted_period_id']);
            if (!$restrictedPeriod || $restrictedPeriod->organisation_id !== $this->organisation->id) {
                abort(422, __('Restricted period not found in this organisation.'));
            }
        }
    }

    public function asController(Organisation $organisation, ActionRequest $request): RestrictedException
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation, $this->validatedData);
    }

    public function action(Organisation $organisation, array $modelData): RestrictedException
    {
        $this->asAction = true;
        $this->initialisation($organisation, $modelData);

        return $this->handle($organisation, $this->validatedData);
    }

    public function htmlResponse(RestrictedException $restrictedException): RedirectResponse
    {
        request()->session()->flash('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Restricted exception successfully created.'),
        ]);

        return Redirect::back();
    }
}
