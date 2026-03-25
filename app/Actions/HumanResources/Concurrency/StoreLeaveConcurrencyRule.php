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

class StoreLeaveConcurrencyRule extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    public function handle(Organisation $organisation, array $modelData): LeaveConcurrencyRule
    {
        $modelData['organisation_id'] = $organisation->id;
        $modelData['created_by_id']   = $modelData['created_by_id'] ?? auth()->id();
        $modelData['updated_by_id']   = $modelData['updated_by_id'] ?? auth()->id();

        return LeaveConcurrencyRule::query()->create($modelData);
    }

    public function rules(): array
    {
        return [
            'name'             => ['required', 'string', 'max:255'],
            'rule_type'        => ['sometimes', 'string', Rule::enum(LeaveConcurrencyRuleTypeEnum::class)],
            'limit'            => ['sometimes', 'nullable', 'integer', 'min:1'],
            'max_overlap_days' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'is_active'        => ['sometimes', 'boolean'],
            'created_by_id'    => ['sometimes', 'nullable', 'exists:users,id'],
            'updated_by_id'    => ['sometimes', 'nullable', 'exists:users,id'],
        ];
    }

    public function afterValidator(): void
    {
        $ruleType = request()->input('rule_type') ?? LeaveConcurrencyRuleTypeEnum::QUOTA->value;

        if ($ruleType === LeaveConcurrencyRuleTypeEnum::QUOTA->value && !request()->input('limit')) {
            abort(422, __('Limit is required for quota rules.'));
        }
    }

    public function asController(Organisation $organisation, ActionRequest $request): LeaveConcurrencyRule
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation, $this->validatedData);
    }

    public function action(Organisation $organisation, array $modelData): LeaveConcurrencyRule
    {
        $this->asAction = true;
        $this->initialisation($organisation, $modelData);

        return $this->handle($organisation, $this->validatedData);
    }

    public function htmlResponse(LeaveConcurrencyRule $leaveConcurrencyRule): RedirectResponse
    {
        request()->session()->flash('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Leave concurrency rule successfully created.'),
        ]);

        return Redirect::back();
    }
}
