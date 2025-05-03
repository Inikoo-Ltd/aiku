<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 18 Jan 2024 17:13:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Agent;

use App\Actions\GrpAction;
use App\Actions\Procurement\OrgAgent\UpdateOrgAgent;
use App\Actions\SupplyChain\Agent\Search\AgentRecordSearch;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateAgents;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrgAgents;
use App\Actions\SysAdmin\Organisation\UpdateOrganisation;
use App\Actions\Traits\WithActionUpdate;
use App\Http\Resources\SupplyChain\AgentsResource;
use App\Models\SupplyChain\Agent;
use App\Rules\IUnique;
use App\Rules\Phone;
use App\Rules\ValidAddress;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UpdateAgent extends GrpAction
{
    use WithActionUpdate;


    private Agent $agent;
    private bool $action = false;

    public function handle(Agent $agent, array $modelData): Agent
    {
        UpdateOrganisation::run($agent->organisation, Arr::except($modelData, [
            'source_id',
            'source_slug',
            'status',
            'last_fetched_at'
        ]));

        $agent = $this->update($agent, Arr::only($modelData, [
            'status',
            'code',
            'name',
            'last_fetched_at'
        ]));
        if ($agent->wasChanged('status')) {
            foreach ($agent->orgAgents as $orgAgent) {
                if (!$agent->status) {
                    UpdateOrgAgent::make()->action($orgAgent, ['status' => false]);
                }
                OrganisationHydrateOrgAgents::dispatch($orgAgent->organisation)->delay($this->hydratorsDelay);
            }
            GroupHydrateAgents::dispatch($this->group)->delay($this->hydratorsDelay);
        }

        AgentRecordSearch::dispatch($agent);


        return $agent;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->action = true) {
            return true;
        }

        return $request->user()->authTo("supply-chain.".$this->group->id.".edit");
    }

    public function rules(): array
    {
        $rules = [
            'code'         => [
                'sometimes',
                'required',
                'max:12',
                'alpha_dash',
                new IUnique(
                    table: 'organisations',
                    extraConditions: [
                        ['column' => 'group_id', 'value' => $this->group->id],
                        [
                            'column'   => 'id',
                            'operator' => '!=',
                            'value'    => $this->agent->organisation->id
                        ],
                    ]
                ),
            ],
            'name'         => ['sometimes', 'required', 'string', 'max:255'],
            'contact_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email'        => ['sometimes', 'nullable', 'email'],
            'phone'        => ['sometimes', 'nullable', new Phone()],
            'address'      => ['sometimes', 'required', new ValidAddress()],
            'currency_id'  => ['sometimes', 'required', 'exists:currencies,id'],
            'country_id'   => ['sometimes', 'required', 'exists:countries,id'],
            'timezone_id'  => ['sometimes', 'required', 'exists:timezones,id'],
            'language_id'  => ['sometimes', 'required', 'exists:languages,id'],
            'status'       => ['sometimes', 'required', 'boolean'],
        ];

        if (!$this->strict) {
            $rules['last_fetched_at'] = ['sometimes', 'date'];
        }

        return $rules;
    }

    public function action(Agent $agent, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): Agent
    {
        if (!$audit) {
            Agent::disableAuditing();
        }
        $this->hydratorsDelay = $hydratorsDelay;
        $this->strict         = $strict;
        $this->agent          = $agent;
        $this->action         = true;
        $this->initialisation($agent->group, $modelData);

        return $this->handle($agent, $this->validatedData);
    }

    public function asController(Agent $agent, ActionRequest $request): Agent
    {
        $this->agent = $agent;
        $this->initialisation($agent->group, $request);

        return $this->handle($agent, $this->validatedData);
    }


    public function jsonResponse(Agent $agent): AgentsResource
    {
        return new AgentsResource($agent);
    }
}
