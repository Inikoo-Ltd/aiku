<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 03 Apr 2024 20:48:26 Central Indonesia Time, Bali Office, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Supplier;

use App\Actions\Helpers\Currency\SetCurrencyHistoricFields;
use App\Actions\OrgAction;
use App\Actions\Procurement\OrgSupplier\StoreOrgSupplierFromFreeSupplier;
use App\Actions\Procurement\OrgSupplier\StoreOrgSupplierFromSupplierInAgent;
use App\Actions\SupplyChain\Agent\Hydrators\AgentHydrateSuppliers;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateSuppliers;
use App\Actions\Traits\Authorisations\WithSupplyChainEditAuthorisation;
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
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class StoreSupplier extends OrgAction
{
    use WithModelAddressActions;
    use WithNoStrictRules;
    use WithSupplyChainEditAuthorisation;

    private const DATA_FIELDS = [
        'delivery_type',
        'incoterm',
        'port_of_export',
        'port_of_import',
        'production_waiting_time',
        'delivery_time',
    ];

    private const CONTAINER_ONLY_FIELDS = [
        'incoterm',
        'port_of_export',
        'port_of_import',
    ];

    private const SETTINGS_FIELDS = [
        'default_product_allow_on_demand',
        'default_product_country_origin',
        'payment_terms',
        'order_number_prefix',
        'minimum_order',
        'cooling_period',
    ];

    /**
     * @throws \Throwable
     */
    public function handle(Group|Agent $parent, array $modelData): Supplier
    {
        $addressData = Arr::get($modelData, 'address');
        Arr::forget($modelData, 'address');

        if (Arr::get($modelData, 'order_number_prefix')) {
            data_set($modelData, 'order_number_prefix', Str::upper($modelData['order_number_prefix']));
        }

        if (Arr::get($modelData, 'delivery_type') !== 'container') {
            Arr::forget($modelData, self::CONTAINER_ONLY_FIELDS);
        }

        $modelData = $this->pullIntoJsonColumn($modelData, 'data', self::DATA_FIELDS);
        $modelData = $this->pullIntoJsonColumn($modelData, 'settings', self::SETTINGS_FIELDS);

        if ($parent instanceof Agent) {
            data_set($modelData, 'group_id', $parent->group_id);
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
                    ['source_id' => $supplier->source_id],
                    $this->hydratorsDelay,
                    $this->strict
                );
            } else {
                StoreOrgSupplierFromFreeSupplier::make()->action(
                    $supplier,
                    ['source_id' => $supplier->source_id],
                    $this->hydratorsDelay,
                    $this->strict
                );
            }

            return $supplier;
        });

        GroupHydrateSuppliers::dispatch($this->getGroup($parent))->delay($this->hydratorsDelay);

        if ($supplier->agent_id) {
            AgentHydrateSuppliers::dispatch($supplier->agent)->delay($this->hydratorsDelay);
        }

        return $supplier;
    }

    public function rules(): array
    {
        $rules = [
            'code'                            => [
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
            'contact_name'                    => ['nullable', 'string', 'max:255'],
            'contact_website'                 => ['nullable', 'string', 'max:255'],
            'company_name'                    => ['nullable', 'string', 'max:255'],
            'email'                           => ['nullable', 'email'],
            'phone'                           => ['nullable', new Phone()],
            'address'                         => ['required', new ValidAddress()],
            'currency_id'                     => ['required', 'exists:currencies,id'],
            'status'                          => ['sometimes', 'required', 'boolean'],
            'scope_type'                      => ['string', Rule::in(['Group', 'Organisation'])],
            'scope_id'                        => ['integer'],

            'delivery_type'                   => ['sometimes', 'nullable', 'string', 'in:parcel,container'],
            'incoterm'                        => ['sometimes', 'nullable', 'string', 'max:8'],
            'port_of_export'                  => ['sometimes', 'nullable', 'string', 'max:255'],
            'port_of_import'                  => ['sometimes', 'nullable', 'string', 'max:255'],
            'production_waiting_time'         => ['sometimes', 'nullable', 'integer', 'min:0'],
            'delivery_time'                   => ['sometimes', 'nullable', 'integer', 'min:0'],

            'default_product_allow_on_demand' => ['sometimes', 'boolean'],
            'default_product_country_origin'  => ['sometimes', 'nullable', 'exists:countries,id'],
            'payment_terms'                   => ['sometimes', 'nullable', 'string', 'max:255'],
            'order_number_prefix'             => ['sometimes', 'nullable', 'string', 'max:16'],
            'minimum_order'                   => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'cooling_period'                  => ['sometimes', 'nullable', 'integer', 'min:0'],
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
    public function action(Group|Agent $parent, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): Supplier
    {
        if (!$audit) {
            Supplier::disableAuditing();
        }

        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;

        $this->initialisationFromGroup($this->getGroup($parent), $modelData);

        return $this->handle($parent, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function asController(ActionRequest $request): Supplier
    {
        $group = group();
        $this->initialisationFromGroup($group, $request);

        return $this->handle($group, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function inAgent(Agent $agent, ActionRequest $request): Supplier
    {
        $this->initialisationFromGroup($agent->group, $request);

        return $this->handle($agent, $this->validatedData);
    }

    public function htmlResponse(Supplier $supplier): RedirectResponse
    {
        if ($supplier->agent_id) {
            return Redirect::route('grp.supply-chain.agents.show.suppliers.show', [$supplier->agent->slug, $supplier->slug]);
        }

        return Redirect::route('grp.supply-chain.suppliers.show', $supplier->slug);
    }

    protected function pullIntoJsonColumn(array $modelData, string $column, array $fields): array
    {
        foreach ($fields as $field) {
            if (array_key_exists($field, $modelData)) {
                $modelData[$column][$field] = Arr::pull($modelData, $field);
            }
        }

        return $modelData;
    }

    protected function getGroup(Group|Agent $parent): Group
    {
        return $parent instanceof Agent ? $parent->group : $parent;
    }
}
