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
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateOrgAgent extends OrgAction
{
    use WithProcurementEditAuthorisation;
    use WithActionUpdate;
    use WithProcurementSerialReferences;


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

    public function asController(OrgAgent $orgAgent, ActionRequest $request): OrgAgent
    {
        $this->initialisation($orgAgent->organisation, $request);

        return $this->handle($orgAgent, $this->validatedData);
    }

    public function action(OrgAgent $orgAgent, $modelData, $hydratorsDelay = 0): OrgAgent
    {
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisation($orgAgent->organisation, $modelData);

        return $this->handle($orgAgent, $this->validatedData);
    }


}
