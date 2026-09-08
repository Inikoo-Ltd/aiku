<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 18 Jan 2024 17:13:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Agent;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithSupplyChainEditAuthorisation;
use App\Actions\Helpers\Media\SaveModelImage;
use App\Actions\Procurement\OrgAgent\UpdateOrgAgent;
use App\Actions\SupplyChain\Supplier\WithSupplierJsonColumns;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateAgents;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrgAgents;
use App\Actions\SysAdmin\Organisation\UpdateOrganisation;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Http\Resources\SupplyChain\AgentsResource;
use App\Models\SupplyChain\Agent;
use App\Rules\IUnique;
use App\Rules\Phone;
use App\Rules\ValidAddress;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rules\File;
use Lorisleiva\Actions\ActionRequest;

class UpdateAgent extends OrgAction
{
    use WithSupplyChainEditAuthorisation;
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithSupplierJsonColumns;


    private Agent $agent;
    private bool $action = false;

    public function handle(Agent $agent, array $modelData): Agent
    {
        $leavingContainer = Arr::exists($modelData, 'delivery_type')
            && Arr::get($modelData, 'delivery_type') !== 'container';

        if ($leavingContainer) {
            Arr::forget($modelData, self::CONTAINER_ONLY_FIELDS);
        }

        $modelData = $this->pullSupplierJsonColumns($modelData);

        if (Arr::has($modelData, 'image')) {
            $image = Arr::pull($modelData, 'image');
            if ($image) {
                $agent = SaveModelImage::run(
                    model: $agent,
                    imageData: [
                        'path'         => $image->getPathName(),
                        'originalName' => $image->getClientOriginalName(),
                        'extension'    => $image->getClientOriginalExtension(),
                    ],
                    scope: 'photo'
                );
            }
        }

        UpdateOrganisation::run($agent->organisation, Arr::except($modelData, [
            'source_id',
            'source_slug',
            'status',
            'last_fetched_at',
            'data',
            'settings',
        ]));

        $agent = $this->update($agent, Arr::only($modelData, [
            'status',
            'code',
            'name',
            'last_fetched_at',
            'data',
            'settings',
        ]), ['data', 'settings']);

        if ($leavingContainer) {
            $agent->update(['data' => Arr::except($agent->data, self::CONTAINER_ONLY_FIELDS)]);
        }

        if ($agent->wasChanged('status')) {
            foreach ($agent->orgAgents as $orgAgent) {
                if (!$agent->status) {
                    UpdateOrgAgent::make()->action($orgAgent, ['status' => false]);
                }
                OrganisationHydrateOrgAgents::dispatch($orgAgent->organisation)->delay($this->hydratorsDelay);
            }
            GroupHydrateAgents::dispatch($this->group)->delay($this->hydratorsDelay);
        }

        return $agent;
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
            'image'        => ['sometimes', 'nullable', File::image()->max(12 * 1024)],
        ];

        $rules = array_merge($rules, $this->supplierJsonFieldRules());

        if (!$this->strict) {
            $rules['last_fetched_at'] = ['sometimes', 'date'];
            $rules['data']            = ['sometimes', 'array'];
            $rules['settings']        = ['sometimes', 'array'];
            $rules                    = $this->noStrictUpdateRules($rules);
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
        $this->asAction       = true;
        $this->initialisationFromGroup($agent->group, $modelData);

        return $this->handle($agent, $this->validatedData);
    }

    public function asController(Agent $agent, ActionRequest $request): Agent
    {
        $this->agent = $agent;
        $this->initialisationFromGroup($agent->group, $request);

        return $this->handle($agent, $this->validatedData);
    }


    public function jsonResponse(Agent $agent): AgentsResource
    {
        return new AgentsResource($agent);
    }
}
