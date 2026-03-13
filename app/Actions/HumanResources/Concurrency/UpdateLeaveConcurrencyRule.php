<?php

namespace App\Actions\HumanResources\Concurrency;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Enums\HumanResources\Concurrency\LeaveConcurrencyRuleTypeEnum;
use App\Models\HumanResources\LeaveConcurrencyRule;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateLeaveConcurrencyRule extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    private LeaveConcurrencyRule $leaveConcurrencyRule;

    public function handle(LeaveConcurrencyRule $leaveConcurrencyRule, array $modelData): LeaveConcurrencyRule
    {
        $modelData['updated_by_id'] = $modelData['updated_by_id'] ?? auth()->id();

        $leaveConcurrencyRule->update($modelData);

        return $leaveConcurrencyRule->refresh();
    }

    public function rules(): array
    {
        return [
            'name'             => ['sometimes', 'required', 'string', 'max:255'],
            'rule_type'        => ['sometimes', 'string', Rule::enum(LeaveConcurrencyRuleTypeEnum::class)],
            'limit'            => ['sometimes', 'nullable', 'integer', 'min:1'],
            'max_overlap_days' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'is_active'        => ['sometimes', 'boolean'],
            'updated_by_id'    => ['sometimes', 'nullable', 'exists:users,id'],
        ];
    }

    public function afterValidator(): void
    {
        $ruleType = $this->validatedData['rule_type'] ?? $this->leaveConcurrencyRule->rule_type->value;

        if ($ruleType === LeaveConcurrencyRuleTypeEnum::QUOTA->value && !isset($this->validatedData['limit']) && $this->leaveConcurrencyRule->limit === null) {
            abort(422, __('Limit is required for quota rules.'));
        }
    }

    public function asController(Organisation $organisation, LeaveConcurrencyRule $leaveConcurrencyRule, ActionRequest $request): LeaveConcurrencyRule
    {
        $this->leaveConcurrencyRule = $leaveConcurrencyRule;
        $this->initialisation($organisation, $request);

        return $this->handle($leaveConcurrencyRule, $this->validatedData);
    }

    public function action(LeaveConcurrencyRule $leaveConcurrencyRule, array $modelData): LeaveConcurrencyRule
    {
        $this->asAction = true;
        $this->leaveConcurrencyRule = $leaveConcurrencyRule;
        $this->initialisation($leaveConcurrencyRule->organisation, $modelData);

        return $this->handle($leaveConcurrencyRule, $this->validatedData);
    }

    public function htmlResponse(LeaveConcurrencyRule $leaveConcurrencyRule): RedirectResponse
    {
        request()->session()->flash('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Leave concurrency rule successfully updated.'),
        ]);

        return Redirect::back();
    }
}
