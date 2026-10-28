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
use App\Actions\SupplyChain\SupplierProduct\CopySupplierProductsToSupplier;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateSuppliers;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithModelAddressActions;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Rules\IUnique;
use App\Rules\Phone;
use App\Rules\ValidAddress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class StoreSupplier extends OrgAction
{
    use WithModelAddressActions;
    use WithNoStrictRules;
    use WithSupplierJsonColumns;

    private ?Agent $agent = null;

    private ?Organisation $scopeOrganisation = null;

    private int $numberCopiedSupplierProducts = 0;

    /** @var array<int, int> */
    private array $copySourceAgentIds = [];

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if ($this->scopeOrganisation) {
            return $request->user()->authTo("procurement.{$this->scopeOrganisation->id}.edit");
        }

        if ($this->agent && $request->user()->authTo("procurement.{$this->agent->organisation_id}.edit")) {
            return true;
        }

        return $request->user()->authTo('supply-chain.edit');
    }

    /**
     * @throws \Throwable
     */
    public function handle(Group|Agent $parent, array $modelData): Supplier
    {
        $addressData = Arr::get($modelData, 'address');
        Arr::forget($modelData, 'address');

        $copiedOrgSupplierProductIds = Arr::pull($modelData, 'org_supplier_products', []);

        if (Arr::get($modelData, 'delivery_type') !== 'container') {
            Arr::forget($modelData, self::CONTAINER_ONLY_FIELDS);
        }

        $modelData = $this->pullSupplierJsonColumns($modelData);

        if ($parent instanceof Agent) {
            data_set($modelData, 'group_id', $parent->group_id);
        }

        $supplier = DB::transaction(function () use ($parent, $modelData, $addressData, $copiedOrgSupplierProductIds) {
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

            if ($parent instanceof Agent && $copiedOrgSupplierProductIds && $this->copySourceAgentIds) {
                $this->copySupplierProducts($supplier, $copiedOrgSupplierProductIds);
            }

            return $supplier;
        });

        SetIconAsSupplierImage::dispatch($supplier)->delay($this->hydratorsDelay);
        GroupHydrateSuppliers::dispatch($this->getGroup($parent))->delay($this->hydratorsDelay);

        if ($supplier->agent_id) {
            AgentHydrateSuppliers::dispatch($supplier->agent)->delay($this->hydratorsDelay);
        }

        return $supplier;
    }

    /**
     * The new supplier form has no field for the products it takes over, so a refused copy is reported on the code.
     *
     * @param  array<int, int>  $orgSupplierProductIds
     *
     * @throws \Throwable
     */
    private function copySupplierProducts(Supplier $supplier, array $orgSupplierProductIds): void
    {
        $copySupplierProducts = CopySupplierProductsToSupplier::make();

        try {
            $this->numberCopiedSupplierProducts = $copySupplierProducts->action(
                $supplier,
                $copySupplierProducts->getAgentsSupplierProducts($this->copySourceAgentIds, $orgSupplierProductIds),
                $this->hydratorsDelay
            );
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages([
                'code' => collect($exception->errors())->flatten()->first(),
            ]);
        }
    }

    public function rules(): array
    {
        $rules = [
            'code'            => [
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
            'contact_name'    => ['nullable', 'string', 'max:255'],
            'contact_website' => ['nullable', 'string', 'max:255'],
            'company_name'    => ['nullable', 'string', 'max:255'],
            'email'           => ['nullable', 'email'],
            'phone'           => ['nullable', new Phone()],
            'wechat'          => ['sometimes', 'nullable', 'string', 'max:255'],
            'whatsapp'        => ['sometimes', 'nullable', new Phone()],
            'address'         => ['required', new ValidAddress(requireFullAddress: !$this->asAction)],
            'currency_id'     => ['required', 'exists:currencies,id'],
            'status'          => ['sometimes', 'required', 'boolean'],
            'scope_type'      => ['string', Rule::in(['Group', 'Organisation'])],
            'scope_id'        => ['integer'],
            'org_supplier_products'   => ['sometimes', 'array'],
            'org_supplier_products.*' => ['integer'],
        ];

        $rules = array_merge($rules, $this->supplierJsonFieldRules());

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
        if ($this->scopeOrganisation) {
            $this->set('scope_type', 'Organisation');
            $this->set('scope_id', $this->scopeOrganisation->id);
        } elseif (!$this->get('scope_type')) {
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
        $this->agent = $agent;
        $this->initialisationFromGroup($agent->group, $request);

        $this->copySourceAgentIds = CopySupplierProductsToSupplier::make()->getAccessibleAgents($request->user())->pluck('id')->all();

        return $this->handle($agent, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function inOrganisation(Organisation $organisation, ActionRequest $request): Supplier
    {
        $this->scopeOrganisation = $organisation;
        $this->initialisationFromGroup($organisation->group, $request);

        return $this->handle($organisation->group, $this->validatedData);
    }

    public function htmlResponse(Supplier $supplier): RedirectResponse
    {
        if ($this->scopeOrganisation) {
            return Redirect::route('grp.org.procurement.org_suppliers.show', [
                $this->scopeOrganisation->slug,
                $supplier->orgSuppliers()->where('organisation_id', $this->scopeOrganisation->id)->value('slug'),
            ]);
        }

        if ($this->numberCopiedSupplierProducts && $supplier->agent_id) {
            return Redirect::route('grp.org.procurement.org_suppliers.show.supplier_products.index', [
                $supplier->agent->organisation->slug,
                $supplier->orgSuppliers()->orderBy('id')->value('slug'),
            ]);
        }

        if ($supplier->agent_id) {
            return Redirect::route('grp.supply-chain.agents.show.suppliers.show', [$supplier->agent->slug, $supplier->slug]);
        }

        return Redirect::route('grp.supply-chain.suppliers.show', $supplier->slug);
    }

    protected function getGroup(Group|Agent $parent): Group
    {
        return $parent instanceof Agent ? $parent->group : $parent;
    }
}
