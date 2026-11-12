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
use App\Actions\Procurement\PurchaseOrder\Hydrators\PurchaseOrderHydrateStage;
use App\Actions\SupplyChain\Supplier\WithSupplierJsonColumns;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateAgents;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrgAgents;
use App\Actions\SysAdmin\Organisation\UpdateOrganisation;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderJourneyStageEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\Procurement\PurchaseOrder;
use App\Http\Resources\SupplyChain\AgentsResource;
use App\Models\SupplyChain\Agent;
use App\Rules\IUnique;
use App\Rules\Phone;
use App\Rules\ValidAddress;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
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
        $modelData = $this->pullPoJourney($modelData);
        if (array_key_exists('buyers_manage_stock_deliveries', $modelData)) {
            $modelData['settings']['buyers_manage_stock_deliveries'] = (bool) Arr::pull($modelData, 'buyers_manage_stock_deliveries');
        }
        if (array_key_exists('receives_goods_in_own_warehouse', $modelData)) {
            $modelData['settings']['receives_goods_in_own_warehouse'] = (bool) Arr::pull($modelData, 'receives_goods_in_own_warehouse');
        }
        if (array_key_exists('goods_receipts_required_from', $modelData)) {
            $modelData['settings']['goods_receipts_required_from'] = Arr::pull($modelData, 'goods_receipts_required_from');
        }

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

        if ($agent->wasChanged('settings')) {
            PurchaseOrder::where('agent_id', $agent->id)
                ->whereIn('state', [PurchaseOrderStateEnum::SUBMITTED, PurchaseOrderStateEnum::CONFIRMED])
                ->get()
                ->each(fn (PurchaseOrder $purchaseOrder) => PurchaseOrderHydrateStage::dispatch($purchaseOrder)->delay($this->hydratorsDelay));
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

    /**
     * Moves the flat PO journey form fields into settings.po_journey, leaving the rest of the settings alone.
     *
     * @param array<string, mixed> $modelData
     *
     * @return array<string, mixed>
     */
    private function pullPoJourney(array $modelData): array
    {
        $journey = [];

        foreach (['cho_mode', 'ready_checks'] as $field) {
            if (array_key_exists('po_journey_'.$field, $modelData)) {
                $journey[$field] = Arr::pull($modelData, 'po_journey_'.$field);
            }
        }

        foreach (PurchaseOrderJourneyStageEnum::values() as $stage) {
            foreach (['label', 'description', 'days'] as $field) {
                $name = 'po_journey_'.$field.'_'.$stage;
                if (!array_key_exists($name, $modelData)) {
                    continue;
                }
                $value = Arr::pull($modelData, $name);
                $journey['stages'][$stage][$field] = $value === null || $value === '' ? null : ($field === 'days' ? (int) $value : trim((string) $value));
            }
        }

        if ($journey) {
            $modelData['settings']['po_journey'] = $journey;
        }

        return $modelData;
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
            'address'      => ['sometimes', 'required', new ValidAddress(requireFullAddress: !$this->asAction)],
            'currency_id'  => ['sometimes', 'required', 'exists:currencies,id'],
            'country_id'   => ['sometimes', 'required', 'exists:countries,id'],
            'timezone_id'  => ['sometimes', 'required', 'exists:timezones,id'],
            'language_id'  => ['sometimes', 'required', 'exists:languages,id'],
            'status'       => ['sometimes', 'required', 'boolean'],
            'buyers_manage_stock_deliveries' => ['sometimes', 'boolean'],
            'receives_goods_in_own_warehouse' => ['sometimes', 'boolean'],
            'goods_receipts_required_from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'image'        => ['sometimes', 'nullable', File::image()->max(12 * 1024)],
        ];

        $rules['po_journey_cho_mode']       = ['sometimes', 'required', Rule::in(Agent::CHO_MODES)];
        $rules['po_journey_ready_checks']   = ['sometimes', 'array', 'min:1'];
        $rules['po_journey_ready_checks.*'] = ['string', Rule::in(Agent::READY_CHECKS)];

        foreach (PurchaseOrderJourneyStageEnum::values() as $stage) {
            $rules['po_journey_label_'.$stage]       = ['sometimes', 'nullable', 'string', 'max:60'];
            $rules['po_journey_description_'.$stage] = ['sometimes', 'nullable', 'string', 'max:255'];
            $rules['po_journey_days_'.$stage]        = ['sometimes', 'nullable', 'integer', 'min:0', 'max:730'];
        }

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
