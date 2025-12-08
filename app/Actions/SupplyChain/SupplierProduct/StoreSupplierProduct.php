<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 11 Aug 2024 14:46:37 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierProduct;

use App\Actions\GrpAction;
use App\Actions\SupplyChain\Agent\Hydrators\AgentHydrateSupplierProducts;
use App\Actions\SupplyChain\HistoricSupplierProduct\StoreHistoricSupplierProduct;
use App\Actions\SupplyChain\Supplier\Hydrators\SupplierHydrateSupplierProducts;
use App\Actions\SupplyChain\SupplierProduct\Search\SupplierProductRecordSearch;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateProductSuppliers;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateSupplierProducts;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Enums\SupplyChain\SupplierProduct\SupplierProductStateEnum;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use App\Rules\AlphaDashDotSpaceSlashParenthesisPlus;
use App\Rules\IUnique;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreSupplierProduct extends GrpAction
{
    use WithNoStrictRules;

    public bool $skipHistoric = false;

    private int $supplier_id;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo('supply-chain.edit');
    }

    /**
     * @throws \Throwable
     */
    public function handle(Supplier $supplier, array $modelData): SupplierProduct
    {
        data_set($modelData, 'group_id', $supplier->group_id);

        if ($supplier->agent_id) {
            $modelData['agent_id'] = $supplier->agent_id;
        }
        data_set($modelData, 'currency_id', $supplier->currency_id);

        $supplierProduct = DB::transaction(function () use ($supplier, $modelData) {
            /** @var SupplierProduct $supplierProduct */
            $supplierProduct = $supplier->supplierProducts()->create($modelData);
            $supplierProduct->refresh();
            $supplierProduct->stats()->create();

            if (! $this->skipHistoric) {
                $historicProduct = StoreHistoricSupplierProduct::make()->action($supplierProduct, [
                    'status' => true,
                ]);
                $supplierProduct->update(
                    [
                        'current_historic_supplier_product_id' => $historicProduct->id,
                    ]
                );
            }

            return $supplierProduct;
        });

        GroupHydrateSupplierProducts::dispatch($supplier->group)->delay($this->hydratorsDelay);
        SupplierHydrateSupplierProducts::dispatch($supplier)->delay($this->hydratorsDelay);
        AgentHydrateSupplierProducts::dispatchIf((bool) $supplierProduct->agent_id, $supplierProduct->agent)->delay($this->hydratorsDelay);
        GroupHydrateProductSuppliers::dispatch($supplier->group)->delay($this->hydratorsDelay);

        SupplierProductRecordSearch::dispatch($supplierProduct);

        return $supplierProduct;
    }

    public function rules(): array
    {
        $rules = [
            'code' => [
                'required',
                $this->strict ? 'max:64' : 'max:255',
                $this->strict ? new AlphaDashDotSpaceSlashParenthesisPlus : 'string',
                Rule::notIn(['export', 'create', 'upload']),
                new IUnique(
                    table: 'supplier_products',
                    extraConditions: [
                        ['column' => 'supplier_id', 'value' => $this->supplier_id],
                    ]
                ),
            ],
            //            'stock_id'     => [
            //                'required',
            //                Rule::exists('stocks', 'id')->where(function (Builder $query) {
            //                    return $query->where('group_id', $this->group->id);
            //                }),
            //
            //            ],
            'name' => ['required', 'string', 'max:255'],
            'state' => ['sometimes', 'required', Rule::enum(SupplierProductStateEnum::class)],
            'is_available' => ['sometimes', 'required', 'boolean'],
            'cost' => ['required'],
            'units_per_pack' => ['sometimes', 'nullable'],
            'units_per_carton' => ['sometimes', 'nullable'],
            'cbm' => ['sometimes', 'nullable', 'numeric'],
        ];

        if (! $this->strict) {
            $rules = $this->noStrictStoreRules($rules);
            $rules['source_slug'] = ['sometimes', 'nullable', 'string'];
        }

        return $rules;
    }

    /**
     * @throws \Throwable
     */
    public function action(Supplier $supplier, array $modelData, bool $skipHistoric = false, int $hydratorsDelay = 0, bool $strict = true, $audit = true): SupplierProduct
    {
        if (! $audit) {
            SupplierProduct::disableAuditing();
        }
        $this->supplier_id = $supplier->id;
        $this->asAction = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->skipHistoric = $skipHistoric;
        $this->strict = $strict;

        $this->initialisation($supplier->group, $modelData);

        return $this->handle($supplier, $this->validatedData);
    }

    public function asController(Supplier $supplier, ActionRequest $request)
    {
        $this->supplier_id = $supplier->id;
        $this->initialisation($supplier->group, $request);

        return $this->handle($supplier, $this->validatedData);
    }
}
