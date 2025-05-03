<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 18 Jan 2024 17:13:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Agent;

use App\Actions\GrpAction;
use App\Actions\SupplyChain\Agent\Search\AgentRecordSearch;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateAgents;
use App\Actions\SysAdmin\Organisation\StoreOrganisation;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\SupplyChain\Agent;
use App\Models\SysAdmin\Group;
use App\Rules\IUnique;
use App\Rules\Phone;
use App\Rules\ValidAddress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class StoreAgent extends GrpAction
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("procurement.".$this->group->id.".edit");
    }

    /**
     * @throws \Throwable
     */
    public function handle(Group $group, array $modelData): Agent
    {
        data_set($modelData, 'group_id', $group->id);
        data_set($modelData, 'type', OrganisationTypeEnum::AGENT);

        data_set($modelData, 'currency_id', $group->currency_id, overwrite: false);
        data_set($modelData, 'country_id', $group->country_id, overwrite: false);
        data_set($modelData, 'timezone_id', $group->timezone_id, overwrite: false);
        data_set($modelData, 'language_id', $group->language_id, overwrite: false);

        $agent = DB::transaction(function () use ($group, $modelData) {
            $organisation = StoreOrganisation::make()->action(
                $group,
                Arr::except($modelData, ['source_slug'])
            );

            data_forget($modelData, 'type');
            data_forget($modelData, 'currency_id');
            data_forget($modelData, 'country_id');
            data_forget($modelData, 'timezone_id');
            data_forget($modelData, 'language_id');
            data_forget($modelData, 'contact_name');
            data_forget($modelData, 'email');
            data_forget($modelData, 'phone');
            data_forget($modelData, 'address');


            /** @var Agent $agent */
            $agent = $organisation->agent()->create($modelData);
            $agent->stats()->create();
            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                $agent->timeSeries()->create(['frequency' => $frequency]);
            }
            $agent->refresh();

            return $agent;
        });

        GroupHydrateAgents::dispatch($group)->delay($this->hydratorsDelay);
        AgentRecordSearch::dispatch($agent)->delay($this->hydratorsDelay);
        return $agent;
    }


    public function rules(): array
    {
        $rules = [
            'code'         => [
                'required',
                'max:12',
                'alpha_dash',
                new IUnique(
                    table: 'organisations',
                    extraConditions: [
                        ['column' => 'group_id', 'value' => $this->group->id],
                    ]
                ),
            ],
            'name'         => ['nullable', 'string', 'max:255'],
            'contact_name' => ['sometimes', 'string', 'max:255'],
            'email'        => ['nullable', 'email'],
            'phone'        => ['nullable', new Phone()],
            'address'      => ['required', new ValidAddress()],

            'currency_id' => ['sometimes', 'exists:currencies,id'],
            'country_id'  => ['sometimes', 'exists:countries,id'],
            'timezone_id' => ['sometimes', 'exists:timezones,id'],
            'language_id' => ['sometimes', 'exists:languages,id'],

        ];

        if (!$this->strict) {
            $rules['source_id']   = ['sometimes', 'nullable', 'string', 'max:64'];
            $rules['source_slug'] = ['sometimes', 'nullable', 'string', 'max:64'];
            $rules['fetched_at']  = ['sometimes', 'date'];
            $rules['created_at']  = ['sometimes', 'date'];
            $rules['deleted_at']  = ['sometimes', 'date'];
        }

        return $rules;
    }

    /**
     * @throws \Throwable
     */
    public function action(Group $group, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): Agent
    {
        if (!$audit) {
            Agent::disableAuditing();
        }
        $this->hydratorsDelay = $hydratorsDelay;
        $this->strict         = $strict;
        $this->asAction       = true;
        $this->initialisation($group, $modelData);


        return $this->handle(
            group: $group,
            modelData: $this->validatedData
        );
    }


    /**
     * @throws \Throwable
     */
    public function asController(ActionRequest $request): Agent
    {
        $this->initialisation(group(), $request);


        return $this->handle(
            group: group(),
            modelData: $this->validatedData
        );
    }

    public function htmlResponse(Agent $agent): RedirectResponse
    {
        return Redirect::route('grp.supply-chain.agents.show', $agent->slug);
    }
}
