<?php

namespace App\Actions\HumanResources\RestrictedPeriods;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Models\HumanResources\RestrictedPeriod;
use App\Models\HumanResources\RestrictedPeriodTarget;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreRestrictedPeriodTarget extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    public function handle(RestrictedPeriod $restrictedPeriod, array $modelData): RestrictedPeriodTarget
    {
        $modelData['restricted_period_id'] = $restrictedPeriod->id;

        $targetType = $modelData['target_type'];
        $targetId = $modelData['target_id'];

        $modelClass = match ($targetType) {
            'Employee'  => \App\Models\HumanResources\Employee::class,
            'LeaveType' => \App\Models\HumanResources\LeaveType::class,
            default     => null,
        };

        if (!$modelClass || !$modelClass::where('id', $targetId)->exists()) {
            abort(422, __('Target not found.'));
        }

        return RestrictedPeriodTarget::query()->create($modelData);
    }

    public function rules(): array
    {
        return [
            'target_type' => ['required', 'string', Rule::in(['Employee', 'LeaveType'])],
            'target_id'   => ['required', 'numeric'],
        ];
    }

    public function asController(Organisation $organisation, RestrictedPeriod $restrictedPeriod, ActionRequest $request): RestrictedPeriodTarget
    {
        $this->initialisation($organisation, $request);

        return $this->handle($restrictedPeriod, $this->validatedData);
    }

    public function action(RestrictedPeriod $restrictedPeriod, array $modelData): RestrictedPeriodTarget
    {
        $this->asAction = true;
        $this->initialisation($restrictedPeriod->organisation, $modelData);

        return $this->handle($restrictedPeriod, $this->validatedData);
    }

    public function htmlResponse(RestrictedPeriodTarget $restrictedPeriodTarget): RedirectResponse
    {
        request()->session()->flash('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Target successfully added to restricted period.'),
        ]);

        return Redirect::back();
    }
}
