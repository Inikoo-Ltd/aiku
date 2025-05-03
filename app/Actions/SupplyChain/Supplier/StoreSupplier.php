<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 03 Apr 2024 20:48:26 Central Indonesia Time, Bali Office, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Supplier;

use App\Actions\Helpers\Currency\SetCurrencyHistoricFields;
use App\Actions\OrgAction;
use App\Actions\Procurement\OrgSupplier\StoreOrgSupplierFromSupplierInAgent;
use App\Actions\SupplyChain\Agent\Hydrators\AgentHydrateSuppliers;
use App\Actions\SupplyChain\Supplier\Search\SupplierRecordSearch;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateSuppliers;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithModelAddressActions;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use App\Models\SysAdmin\Group;
use App\Rules\IUnique;
use App\Rules\Phone;
use App\Rules\ValidAddress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreSupplier extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithModelAddressActions;
    use WithNoStrictRules;


    /**
     * @throws \Throwable
     */
    public function handle(Group|Agent $parent, array $modelData): Supplier
    {
        $addressData = Arr::get($modelData, 'address');
        Arr::forget($modelData, 'address');

        if (class_basename($parent) == 'Agent') {
            data_set($modelData, 'group_id', $parent->group_id);
            $group = $parent->group;
        } else {
            $group = $parent;
        }

        $supplier = DB::transaction(function () use ($parent, $modelData, $addressData) {
            /** @var Supplier $supplier */
            $supplier = $parent->suppliers()->create($modelData);
            $supplier->stats()->create();
            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                $supplier->timeSeries()->create(['frequency' => $frequency]);
            }
            SetCurrencyHistoricFields::run($supplier->currency, $supplier->created_at);

            $supplier = $this->addAddressToModelFromArray($supplier, $addressData, 'contact');



            $supplier->refresh();
            if ($supplier->agent_id) {
                StoreOrgSupplierFromSupplierInAgent::make()->action(
                    $supplier,
                    [
                        'source_id' => $supplier->source_id
                    ],
                    $this->hydratorsDelay,
                    $this->strict
                );
            }

            return $supplier;
        });

        GroupHydrateSuppliers::dispatch($group)->delay($this->hydratorsDelay);


        if ($supplier->agent_id) {
            AgentHydrateSuppliers::dispatch($supplier->agent)->delay($this->hydratorsDelay);
        }

        SupplierRecordSearch::dispatch($supplier);

        return $supplier;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("procurement.".$this->group->id.".edit");
    }

    public function rules(): array
    {



        $rules = [
            'code'         => [
                'required',
                'max:32',
                'alpha_dash',
                new IUnique(
                    table: 'suppliers',
                    extraConditions: [
                        ['column' => 'group_id', 'value' => $this->group->id],
                    ]
                ),
            ],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_website' => ['nullable', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'email'        => ['nullable', 'email'],
            'phone'        => ['nullable', new Phone()],
            'address'      => ['required', new ValidAddress()],
            'currency_id'  => ['required', 'exists:currencies,id'],
            'status'       => ['sometimes', 'required', 'boolean'],
            'scope_type'   => ['string', Rule::in(['Group', 'Organisation'])],
            'scope_id'     => ['integer']

        ];

        if (!$this->strict) {
            $rules['phone']       = ['sometimes', 'nullable', 'max:255'];
            $rules['source_slug'] = ['sometimes', 'nullable', 'string'];
            $rules['archived_at'] = ['sometimes', 'nullable', 'date'];
            $rules                = $this->noStrictStoreRules($rules);
        }

        return $rules;
    }

    public function afterValidator(Validator $validator): void
    {
        if (!$this->get('contact_name') && !$this->get('company_name')) {
            $validator->errors()->add('contact_name', 'contact name or company name is required');
        }
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        if (!$this->get('scope_type')) {
            $this->set('scope_type', 'Group');
            $this->set('scope_id', $this->group->id);
        }
    }

    /**
     * @throws \Throwable
     */
    public function action(Group|Agent $parent, array $modelData, int $hydratorsDelay = 0, bool $strict = true, $audit = true): Supplier
    {
        if (!$audit) {
            Supplier::disableAuditing();
        }
        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;

        if (class_basename($parent) == 'Agent') {
            $group = $parent->group;
        } else {
            $group = $parent;
        }

        $this->initialisationFromGroup($group, $modelData);

        return $this->handle(
            parent: $parent,
            modelData: $this->validatedData
        );
    }

    /**
     * @throws \Throwable
     */
    public function asController(ActionRequest $request): Supplier
    {

        $group = group();

        $this->initialisationFromGroup($group, $request);

        return $this->handle(
            parent: $group,
            modelData: $this->validatedData
        );
    }

    /**
     * @throws \Throwable
     */
    public function inAgent(Agent $agent, ActionRequest $request): Supplier
    {
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle(
            parent: $agent,
            modelData: $this->validatedData
        );
    }

    public function htmlResponse(Supplier $supplier): RedirectResponse
    {
        if ($supplier->agent_id) {
            /** @var Agent $agent */
            $agent = $supplier->agent;

            return Redirect::route('grp.supply-chain.agents.show.suppliers.index', $agent->slug);
        }

        return Redirect::route('grp.supply-chain.suppliers.show', $supplier->slug);
    }
}
