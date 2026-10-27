<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 11 Aug 2024 14:46:44 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierProduct;

use App\Actions\SupplyChain\Supplier\WithSupplierPacking;
use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateCurrentSupplierSkuCost;
use App\Actions\OrgAction;
use App\Actions\Procurement\OrgSupplierProducts\UpdateOrgSupplierProduct;
use App\Actions\SupplyChain\Agent\Hydrators\AgentHydrateSupplierProducts;
use App\Actions\SupplyChain\HistoricSupplierProduct\StoreHistoricSupplierProduct;
use App\Actions\SupplyChain\Supplier\Hydrators\SupplierHydrateSupplierProducts;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateSupplierProducts;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\SupplyChain\SupplierProduct\SupplierProductAvailabilityStatusEnum;
use App\Enums\SupplyChain\SupplierProduct\SupplierProductStateEnum;
use App\Enums\SupplyChain\SupplierProduct\SupplierProductUnavailableReasonEnum;
use App\Enums\SysAdmin\Authorisation\GroupPermissionsEnum;
use App\Http\Resources\SupplyChain\SupplierProductResource;
use App\Models\Inventory\OrgStock;
use App\Models\SupplyChain\SupplierProduct;
use App\Rules\AlphaDashDotSpaceSlashParenthesisPlus;
use App\Rules\IUnique;
use App\Enums\SupplyChain\SupplierProduct\SupplierUnitEnum;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateSupplierProduct extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithSupplierProductJsonColumns;
    use WithPackagingTermsRules;
    use WithSupplierPacking;

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
        'availability_status',
        'deleted_at',
    ];

    public bool $skipHistoric = false;
    private SupplierProduct $supplierProduct;

    public function handle(SupplierProduct $supplierProduct, array $modelData, bool $skipHistoric = false): SupplierProduct
    {
        if (Arr::exists($modelData, 'state')) {
            if (in_array($this->parseState($modelData['state']), self::UNAVAILABLE_STATES, true)) {
                $modelData['is_available'] = false;
            } elseif (!Arr::exists($modelData, 'is_available') && in_array($supplierProduct->state, self::UNAVAILABLE_STATES, true)) {
                $modelData['is_available'] = true;
            }
        }

        if (Arr::exists($modelData, 'units_per_pack') && !$skipHistoric && ($skoPackedIn = $supplierProduct->skoPackedIn()) !== null) {
            $modelData['units_per_pack'] = $skoPackedIn;
        }

        $modelData = $this->syncAvailability($supplierProduct, $modelData);
        $modelData = $this->fillCbmFromCartonDimensions($modelData);
        $modelData = $this->stampLeadTimeConfirmation($supplierProduct, $modelData);

        $modelData = $this->pullSupplierProductJsonColumns($modelData);
        $modelData = $this->fillUnitsPerSupplierUnitFromWeight($supplierProduct, $modelData);
        $modelData['currency_id'] = $supplierProduct->supplier?->currency_id ?? $supplierProduct->currency_id;

        $supplierProduct = $this->update($supplierProduct, $modelData, ['data', 'settings', 'packaging_terms']);
        $changedFields = array_keys($supplierProduct->getChanges());
        $wasChanged    = fn (array $fields): bool => array_intersect($fields, $changedFields) !== [];

        if (!$skipHistoric && $wasChanged(self::HISTORIC_FIELDS)) {
            $historicProduct = StoreHistoricSupplierProduct::make()->action($supplierProduct, [
                'status' => true,
            ]);

            $supplierProduct->update([
                'current_historic_supplier_product_id' => $historicProduct->id,
            ]);
        }

        if ($wasChanged(['state'])) {
            foreach ($supplierProduct->orgSupplierProducts as $orgSupplierProduct) {
                UpdateOrgSupplierProduct::run($orgSupplierProduct, ['state' => $supplierProduct->state]);
            }
        }

        if ($wasChanged(['code'])) {
            OrgStock::whereHas('orgSupplierProducts', fn ($query) => $query->where('supplier_product_id', $supplierProduct->id))->searchable();
        }

        if ($wasChanged(['cost', 'extra_costs', 'currency_id'])) {
            $orgStocks = OrgStock::whereHas('orgSupplierProducts', fn ($query) => $query->where('supplier_product_id', $supplierProduct->id))->get();
            foreach ($orgStocks as $orgStock) {
                OrgStockHydrateCurrentSupplierSkuCost::dispatch($orgStock);
            }
        }

        if ($wasChanged(self::STATS_FIELDS)) {
            GroupHydrateSupplierProducts::dispatch($supplierProduct->group)->delay($this->hydratorsDelay);
            SupplierHydrateSupplierProducts::dispatch($supplierProduct->supplier)->delay($this->hydratorsDelay);
            AgentHydrateSupplierProducts::dispatchIf((bool)$supplierProduct->agent_id, $supplierProduct->agent)->delay($this->hydratorsDelay);
        }

        return $supplierProduct;
    }

    /**
     * The availability status is the detailed answer, is_available the yes/no every buyer screen reads; whichever is given sets the other.
     */
    private function syncAvailability(SupplierProduct $supplierProduct, array $modelData): array
    {
        if (Arr::exists($modelData, 'availability_status')) {
            $status                    = SupplierProductAvailabilityStatusEnum::from($modelData['availability_status'] instanceof SupplierProductAvailabilityStatusEnum ? $modelData['availability_status']->value : $modelData['availability_status']);
            $modelData['is_available'] = $status !== SupplierProductAvailabilityStatusEnum::UNAVAILABLE;

            if ($status === SupplierProductAvailabilityStatusEnum::AVAILABLE) {
                $modelData['unavailable_reason']    = null;
                $modelData['available_from']        = null;
                $modelData['availability_new_cost'] = null;
            }

            return $modelData;
        }

        if (Arr::exists($modelData, 'is_available')) {
            if (!$modelData['is_available']) {
                $modelData['availability_status'] = SupplierProductAvailabilityStatusEnum::UNAVAILABLE;
            } elseif ($supplierProduct->availability_status === SupplierProductAvailabilityStatusEnum::UNAVAILABLE) {
                $modelData['availability_status'] = SupplierProductAvailabilityStatusEnum::AVAILABLE;
            }
        }

        return $modelData;
    }

    private function fillCbmFromCartonDimensions(array $modelData): array
    {
        $dimensions = Arr::get($modelData, 'carton_dimensions');
        if ($dimensions && !Arr::exists($modelData, 'cbm')) {
            $modelData['cbm'] = round($dimensions['l'] * $dimensions['w'] * $dimensions['h'] / 1_000_000, 6);
        }

        return $modelData;
    }

    /**
     * A new estimate is unconfirmed until someone confirms it with the supplier; confirm_lead_time false withdraws the confirmation.
     */
    private function stampLeadTimeConfirmation(SupplierProduct $supplierProduct, array $modelData): array
    {
        $confirmLeadTime = Arr::pull($modelData, 'confirm_lead_time');

        if ($confirmLeadTime) {
            $modelData['lead_time_confirmed_at']         = now();
            $modelData['lead_time_confirmed_by_user_id'] = auth()->id();
        } elseif ($confirmLeadTime !== null || (Arr::exists($modelData, 'estimated_lead_time_days') && (int) $modelData['estimated_lead_time_days'] !== (int) $supplierProduct->estimated_lead_time_days)) {
            $modelData['lead_time_confirmed_at']         = null;
            $modelData['lead_time_confirmed_by_user_id'] = null;
        }

        return $modelData;
    }

    /**
     * A supplier unit set by weight without saying how many of our units it holds takes the trade unit weight's answer.
     */
    private function fillUnitsPerSupplierUnitFromWeight(SupplierProduct $supplierProduct, array $modelData): array
    {
        if (empty($modelData['supplier_unit'])) {
            if (Arr::exists($modelData, 'supplier_unit')) {
                $modelData['units_per_supplier_unit'] = null;
            }

            return $modelData;
        }

        if (Arr::get($modelData, 'units_per_supplier_unit') === null && $supplierProduct->units_per_supplier_unit === null) {
            $modelData['units_per_supplier_unit'] = $supplierProduct->unitsPerSupplierUnitByWeight(SupplierUnitEnum::from($modelData['supplier_unit']));
        }

        return $modelData;
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
            'supplier_unit'            => ['sometimes', 'nullable', Rule::enum(SupplierUnitEnum::class)],
            'units_per_supplier_unit'  => ['sometimes', 'nullable', 'numeric', 'gt:0'],
            'carton_dimensions'        => ['sometimes', 'nullable', 'array:l,w,h'],
            'carton_dimensions.l'      => ['required_with:carton_dimensions', 'numeric', 'gt:0'],
            'carton_dimensions.w'      => ['required_with:carton_dimensions', 'numeric', 'gt:0'],
            'carton_dimensions.h'      => ['required_with:carton_dimensions', 'numeric', 'gt:0'],
            'confirm_lead_time'        => ['sometimes', 'boolean'],
            'availability_status'      => ['sometimes', 'required', Rule::enum(SupplierProductAvailabilityStatusEnum::class)],
            'unavailable_reason'       => ['nullable', Rule::requiredIf(fn () => $this->strict && in_array($this->get('availability_status'), ['limited', 'unavailable'], true)), Rule::enum(SupplierProductUnavailableReasonEnum::class)],
            'available_from'           => ['sometimes', 'nullable', 'date'],
            'availability_new_cost'    => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'availability_note'        => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];

        $rules = array_merge($rules, $this->supplierProductJsonFieldRules(), $this->packagingTermsRules(), $this->packingRules($this->supplierProduct->supplier));

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
