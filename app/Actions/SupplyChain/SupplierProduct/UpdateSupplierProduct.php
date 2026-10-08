<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 11 Aug 2024 14:46:44 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierProduct;

use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateCurrentSupplierSkuCost;
use App\Actions\OrgAction;
use App\Actions\Procurement\OrgSupplierProducts\UpdateOrgSupplierProduct;
use App\Actions\SupplyChain\Agent\Hydrators\AgentHydrateSupplierProducts;
use App\Actions\SupplyChain\HistoricSupplierProduct\StoreHistoricSupplierProduct;
use App\Actions\SupplyChain\Supplier\Hydrators\SupplierHydrateSupplierProducts;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateSupplierProducts;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\SupplyChain\SupplierProduct\SupplierProductStateEnum;
use App\Enums\SysAdmin\Authorisation\GroupPermissionsEnum;
use App\Http\Resources\SupplyChain\SupplierProductResource;
use App\Models\Inventory\OrgStock;
use App\Models\SupplyChain\SupplierProduct;
use App\Rules\AlphaDashDotSpaceSlashParenthesisPlus;
use App\Rules\IUnique;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateSupplierProduct extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithSupplierProductJsonColumns;

    private const UNAVAILABLE_STATES = [
        SupplierProductStateEnum::IN_PROCESS,
        SupplierProductStateEnum::DISCONTINUED,
    ];

    private const HISTORIC_FIELDS = [
        'code',
        'cbm',
        'units_per_pack',
        'units_per_carton',
    ];


    private const STATS_FIELDS = [
        'state',
        'is_available',
        'deleted_at',
    ];

    public bool $skipHistoric = false;
    private SupplierProduct $supplierProduct;

    public function handle(SupplierProduct $supplierProduct, array $modelData, bool $skipHistoric = false): SupplierProduct
    {
        if (Arr::exists($modelData, 'state') && in_array($this->parseState($modelData['state']), self::UNAVAILABLE_STATES, true)) {
            $modelData['is_available'] = false;
        }

        $modelData = $this->pullSupplierProductJsonColumns($modelData);

        $supplierProduct = $this->update($supplierProduct, $modelData, ['data', 'settings']);

        if (!$skipHistoric && $supplierProduct->wasChanged(self::HISTORIC_FIELDS)) {
            $historicProduct = StoreHistoricSupplierProduct::make()->action($supplierProduct, [
                'status' => true,
            ]);

            $supplierProduct->update([
                'current_historic_supplier_product_id' => $historicProduct->id,
            ]);
        }

        if ($supplierProduct->wasChanged('state')) {
            foreach ($supplierProduct->orgSupplierProducts as $orgSupplierProduct) {
                UpdateOrgSupplierProduct::run($orgSupplierProduct, ['state' => $supplierProduct->state]);
            }
        }

        if ($supplierProduct->wasChanged(['cost', 'extra_costs', 'currency_id'])) {
            $orgStocks = OrgStock::whereHas('orgSupplierProducts', fn ($query) => $query->where('supplier_product_id', $supplierProduct->id))->get();
            foreach ($orgStocks as $orgStock) {
                OrgStockHydrateCurrentSupplierSkuCost::dispatch($orgStock);
            }
        }

        if ($supplierProduct->wasChanged(self::STATS_FIELDS)) {
            GroupHydrateSupplierProducts::dispatch($supplierProduct->group)->delay($this->hydratorsDelay);
            SupplierHydrateSupplierProducts::dispatch($supplierProduct->supplier)->delay($this->hydratorsDelay);
            AgentHydrateSupplierProducts::dispatchIf((bool)$supplierProduct->agent_id, $supplierProduct->agent)->delay($this->hydratorsDelay);
        }

        return $supplierProduct;
    }

    public function rules(): array
    {
        $rules = [
            'code'             => [
                'sometimes',
                'required',
                $this->strict ? 'max:64' : 'max:255',
                $this->strict ? new AlphaDashDotSpaceSlashParenthesisPlus() : 'string',
                Rule::notIn(['export', 'create', 'upload']),
                new IUnique(
                    table: 'supplier_products',
                    extraConditions: [
                        ['column' => 'supplier_id', 'value' => $this->supplierProduct->supplier_id],
                        [
                            'column'   => 'id',
                            'operator' => '!=',
                            'value'    => $this->supplierProduct->id
                        ],
                    ]
                ),
            ],
            'name'             => ['sometimes', 'required', 'string', 'max:255'],
            'state'            => ['sometimes', 'required', Rule::enum(SupplierProductStateEnum::class)],
            'is_available'     => ['sometimes', 'required', 'boolean'],
            'cost'                     => ['sometimes', 'required', 'numeric', 'min:0'],
            'units_per_pack'           => ['sometimes', 'nullable', 'integer', 'min:1'],
            'units_per_carton'         => ['sometimes', 'nullable', 'integer', 'min:1'],
            'cbm'                      => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'extra_costs'              => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'estimated_lead_time_days' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:365'],
            'carton_weight'            => ['sometimes', 'nullable', 'integer', 'min:0'],
            'carton_net_weight'        => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];

        $rules = array_merge($rules, $this->supplierProductJsonFieldRules());

        if (!$this->strict) {
            $rules['data']             = ['sometimes', 'array'];
            $rules['settings']         = ['sometimes', 'array'];
            $rules['units_per_pack']   = ['sometimes', 'nullable'];
            $rules['units_per_carton'] = ['sometimes', 'nullable'];
            $rules                     = $this->noStrictUpdateRules($rules);
        }

        return $rules;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if (in_array($this->parseState($request->input('state')), [SupplierProductStateEnum::DISCONTINUING, SupplierProductStateEnum::DISCONTINUED], true)) {
            return $request->user()->authTo(GroupPermissionsEnum::SUPPLY_CHAIN->value);
        }

        return $request->user()->authTo(GroupPermissionsEnum::SUPPLY_CHAIN_EDIT->value);
    }

    public function asController(SupplierProduct $supplierProduct, ActionRequest $request): SupplierProduct
    {
        $this->supplierProduct = $supplierProduct;
        $this->initialisationFromGroup($supplierProduct->group, $request);

        return $this->handle($supplierProduct, $this->validatedData);
    }

    public function action(SupplierProduct $supplierProduct, array $modelData, bool $skipHistoric = false, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): SupplierProduct
    {
        if (!$audit) {
            SupplierProduct::disableAuditing();
        }

        $this->supplierProduct = $supplierProduct;
        $this->asAction        = true;
        $this->hydratorsDelay  = $hydratorsDelay;
        $this->skipHistoric    = $skipHistoric;
        $this->strict          = $strict;

        $this->initialisationFromGroup($supplierProduct->group, $modelData);

        return $this->handle($supplierProduct, $this->validatedData, $skipHistoric);
    }

    public function jsonResponse(SupplierProduct $supplierProduct): SupplierProductResource
    {
        return new SupplierProductResource($supplierProduct);
    }

    private function parseState(mixed $state): ?SupplierProductStateEnum
    {
        return $state instanceof SupplierProductStateEnum
            ? $state
            : SupplierProductStateEnum::tryFrom((string)$state);
    }
}
