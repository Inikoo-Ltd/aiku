<?php

namespace App\Actions\HumanResources\Concurrency;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Enums\HumanResources\Concurrency\LeaveConcurrencyTargetRoleEnum;
use App\Models\HumanResources\LeaveConcurrencyRule;
use App\Models\HumanResources\LeaveConcurrencyTarget;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class StoreLeaveConcurrencyTarget extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    private bool $alreadyExists = false;

    public function handle(LeaveConcurrencyRule $leaveConcurrencyRule, array $modelData): LeaveConcurrencyTarget
    {
        $modelData['leave_concurrency_rule_id'] = $leaveConcurrencyRule->id;

        $existingTarget = LeaveConcurrencyTarget::query()
            ->where('leave_concurrency_rule_id', $leaveConcurrencyRule->id)
            ->where('target_type', $modelData['target_type'])
            ->where('target_id', $modelData['target_id'])
            ->first();

        if ($existingTarget) {
            $this->alreadyExists = true;
            return $existingTarget;
        }

        return LeaveConcurrencyTarget::query()->create($modelData);
    }

    public function rules(): array
    {
        return [
            'target_type' => ['required', 'string', Rule::in(['Employee', 'JobPosition'])],
            'target_id' => ['required', 'numeric'],
            'role' => ['sometimes', 'nullable', 'string', Rule::enum(LeaveConcurrencyTargetRoleEnum::class)],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        $targetType = request()->input('target_type');
        $targetId = request()->input('target_id');

        $modelClass = match ($targetType) {
            'Employee' => \App\Models\HumanResources\Employee::class,
            'JobPosition' => \App\Models\HumanResources\JobPosition::class,
            default => null,
        };

        if (!$modelClass) {
            $validator->errors()->add('target_type', __('Invalid target type.'));
            return;
        }

        if (!$modelClass::where('id', $targetId)->exists()) {
            $validator->errors()->add('target_id', __('Target not found.'));
        }
    }

    public function asController(Organisation $organisation, LeaveConcurrencyRule $leaveConcurrencyRule, ActionRequest $request): LeaveConcurrencyTarget
    {
        $this->initialisation($organisation, $request);

        return $this->handle($leaveConcurrencyRule, $this->validatedData);
    }

    public function action(LeaveConcurrencyRule $leaveConcurrencyRule, array $modelData): LeaveConcurrencyTarget
    {
        $this->asAction = true;
        $this->initialisation($leaveConcurrencyRule->organisation, $modelData);

        return $this->handle($leaveConcurrencyRule, $this->validatedData);
    }

    public function htmlResponse(LeaveConcurrencyTarget $leaveConcurrencyTarget): RedirectResponse
    {
        if ($this->alreadyExists) {
            request()->session()->flash('notification', [
                'status' => 'warning',
                'title' => __('Already exists'),
                'description' => __('Target already exists in this leave concurrency rule.'),
            ]);

            return Redirect::back();
        }

        request()->session()->flash('notification', [
            'status' => 'success',
            'title' => __('Success!'),
            'description' => __('Target successfully added to leave concurrency rule.'),
        ]);

        return Redirect::back();
    }
}
