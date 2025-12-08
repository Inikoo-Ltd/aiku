<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 11 Aug 2024 14:46:44 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierProduct;

use App\Actions\GrpAction;
use App\Actions\Procurement\OrgSupplierProducts\UpdateOrgSupplierProduct;
use App\Actions\SupplyChain\Agent\Hydrators\AgentHydrateSupplierProducts;
use App\Actions\SupplyChain\HistoricSupplierProduct\StoreHistoricSupplierProduct;
use App\Actions\SupplyChain\Supplier\Hydrators\SupplierHydrateSupplierProducts;
use App\Actions\SupplyChain\SupplierProduct\Search\SupplierProductRecordSearch;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateSupplierProducts;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\SupplyChain\SupplierProduct\SupplierProductStateEnum;
use App\Http\Resources\SupplyChain\SupplierProductResource;
use App\Models\SupplyChain\SupplierProduct;
use App\Rules\AlphaDashDotSpaceSlashParenthesisPlus;
use App\Rules\IUnique;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class UpdateSupplierProduct extends GrpAction
{
    use WithActionUpdate;
    use WithNoStrictRules;

    public bool $skipHistoric = false;

    private SupplierProduct $supplierProduct;

    public function handle(SupplierProduct $supplierProduct, array $modelData, bool $skipHistoric = false): SupplierProduct
    {
        if (Arr::exists($modelData, 'state')) {
            if ($modelData['state'] == SupplierProductStateEnum::DISCONTINUED
                || SupplierProductStateEnum::IN_PROCESS
            ) {
                $modelData['is_available'] = false;
            }
        }

        $supplierProduct = $this->update($supplierProduct, $modelData, ['data', 'settings']);

        if (! $skipHistoric and $supplierProduct->wasChanged(
            ['code', 'cbm', 'units_per_pack', 'units_per_pack']
        )) {

            $historicProduct = StoreHistoricSupplierProduct::make()->action($supplierProduct, [
                'status' => true,
            ]);
            $supplierProduct->update(
                [
                    'current_historic_supplier_product_id' => $historicProduct->id,
                ]
            );
        }

        if (! $skipHistoric and $supplierProduct->wasChanged(
            ['state', 'is_available']
        )) {
            foreach ($supplierProduct->orgSupplierProducts as $orgSupplierProduct) {
                UpdateOrgSupplierProduct::run(
                    $orgSupplierProduct,
                    [
                        'state' => $supplierProduct->state,
                        'is_available' => $supplierProduct->is_available,
                    ]
                );
                GroupHydrateSupplierProducts::dispatch($supplierProduct->group)->delay($this->hydratorsDelay);
                SupplierHydrateSupplierProducts::dispatch($supplierProduct->supplier)->delay($this->hydratorsDelay);
                AgentHydrateSupplierProducts::dispatchIf((bool) $supplierProduct->agent_id, $supplierProduct->agent)->delay($this->hydratorsDelay);
            }
        }

        if ($supplierProduct->wasChanged()) {
            SupplierProductRecordSearch::dispatch($supplierProduct);
        }

        return $supplierProduct;
    }

    public function rules(): array
    {
        $rules = [
            'code' => [
                'sometimes',
                'required',
                $this->strict ? 'max:64' : 'max:255',
                $this->strict ? new AlphaDashDotSpaceSlashParenthesisPlus : 'string',
                Rule::notIn(['export', 'create', 'upload']),
                new IUnique(
                    table: 'supplier_products',
                    extraConditions: [
                        ['column' => 'supplier_id', 'value' => $this->supplierProduct->supplier_id],
                        [
                            'column' => 'id',
                            'operator' => '!=',
                            'value' => $this->supplierProduct->id,
                        ],
                    ]
                ),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'cost' => ['sometimes', 'required'],
            'state' => ['sometimes', 'required', Rule::enum(SupplierProductStateEnum::class)],
            'is_available' => ['sometimes', 'required', 'boolean'],

        ];

        if (! $this->strict) {
            $rules = $this->noStrictUpdateRules($rules);
        }

        return $rules;
    }

    public function action(SupplierProduct $supplierProduct, array $modelData, bool $skipHistoric = false, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): SupplierProduct
    {
        if (! $audit) {
            SupplierProduct::disableAuditing();
        }
        $this->strict = $strict;
        $this->asAction = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->skipHistoric = $skipHistoric;
        $this->supplierProduct = $supplierProduct;
        $this->initialisation($supplierProduct->group, $modelData);

        return $this->handle($supplierProduct, $this->validatedData);
    }

    public function jsonResponse(SupplierProduct $supplierProduct): SupplierProductResource
    {
        return new SupplierProductResource($supplierProduct);
    }
}
