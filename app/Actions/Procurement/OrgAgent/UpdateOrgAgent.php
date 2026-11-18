<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 03 Apr 2024 20:54:46 Central Indonesia Time, Bali Office , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgAgent;

use App\Actions\Traits\Authorisations\WithProcurementEditAuthorisation;
use App\Actions\OrgAction;
use App\Actions\Procurement\WithProcurementSerialReferences;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrgAgents;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Procurement\OrgAgent\OrgAgentOrderContainerEnum;
use App\Models\Procurement\OrgAgent;
use App\Models\SysAdmin\Permission;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class UpdateOrgAgent extends OrgAction
{
    use WithProcurementEditAuthorisation;
    use WithActionUpdate;
    use WithProcurementSerialReferences;


    private OrgAgent $orgAgent;

    /**
     * Who may be emailed to approve and pay the agent's money requests: an active user of the client who opens the
     * agent page the email links to (procurement view in the client) and is not staff of the agent itself, who would
     * otherwise approve and pay what it asks for.
     *
     * @return Builder<User>
     */
    public static function moneyRequestPayerCandidates(OrgAgent $orgAgent): Builder
    {
        $permissions = Permission::whereIn('name', ["procurement.$orgAgent->organisation_id", "procurement.$orgAgent->organisation_id.view"])->pluck('name')->all();
        if ($permissions === []) {
            return User::whereRaw('false');
        }

        return User::permission($permissions)
            ->where('users.group_id', $orgAgent->group_id)
            ->where('users.status', true)
            ->whereDoesntHave('employees', fn ($query) => $query->where('employees.organisation_id', $orgAgent->agent->organisation_id));
    }

    public function handle(OrgAgent $orgAgent, $modelData = []): OrgAgent
    {

        $modelData = $this->updateProcurementSerialReferences($orgAgent, $modelData);

        foreach (['money_request_payer_user_ids' => 'payer_user_ids', 'money_request_cc_user_ids' => 'cc_user_ids'] as $field => $setting) {
            if (Arr::has($modelData, $field)) {
                data_set($modelData, "settings.money_requests.$setting", array_values(array_unique(array_map('intval', Arr::pull($modelData, $field) ?? []))));
            }
        }

        $orgAgent = $this->update($orgAgent, $modelData, ['settings']);

        OrganisationHydrateOrgAgents::dispatch($orgAgent->organisation);

        return $orgAgent;
    }

    public function rules(ActionRequest $request): array
    {
        return array_merge([
            'source_id'   => 'sometimes|nullable|string|max:64',
            'status'      => ['sometimes', 'required', 'boolean'],
            'order_container' => ['sometimes', 'required', Rule::enum(OrgAgentOrderContainerEnum::class)],
            'cho_penalty_percentage' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'money_request_payer_user_ids'   => ['sometimes', 'nullable', 'array'],
            'money_request_payer_user_ids.*' => ['integer', Rule::exists('users', 'id')->where('group_id', $this->organisation->group_id)],
            'money_request_cc_user_ids'      => ['sometimes', 'nullable', 'array'],
            'money_request_cc_user_ids.*'    => ['integer', Rule::exists('users', 'id')->where('group_id', $this->organisation->group_id)],
        ], $this->procurementSerialReferenceRules());
    }

    public function afterValidator(Validator $validator): void
    {
        $payerIds = array_values(array_unique(array_map('intval', array_filter((array) $this->get('money_request_payer_user_ids'), 'is_numeric'))));
        if ($payerIds === []) {
            return;
        }

        $allowed = self::moneyRequestPayerCandidates($this->orgAgent)->whereIn('users.id', $payerIds)->count();
        if ($allowed !== count($payerIds)) {
            $validator->errors()->add('money_request_payer_user_ids', __('Only active users of this organisation who can see its procurement, and are not staff of the agent, can approve and pay its money requests.'));
        }
    }

    public function asController(OrgAgent $orgAgent, ActionRequest $request): OrgAgent
    {
        $this->orgAgent = $orgAgent;
        $this->initialisation($orgAgent->organisation, $request);

        return $this->handle($orgAgent, $this->validatedData);
    }

    public function action(OrgAgent $orgAgent, $modelData, $hydratorsDelay = 0): OrgAgent
    {
        $this->asAction       = true;
        $this->orgAgent       = $orgAgent;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisation($orgAgent->organisation, $modelData);

        return $this->handle($orgAgent, $this->validatedData);
    }


}
