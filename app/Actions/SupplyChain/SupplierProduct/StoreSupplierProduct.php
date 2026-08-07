<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 11 Aug 2024 14:46:37 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierProduct;

use App\Actions\OrgAction;
use App\Actions\Procurement\OrgSupplierProducts\SyncOrgSupplierProducts;
use App\Actions\Traits\Authorisations\WithSupplyChainEditAuthorisation;
use App\Actions\SupplyChain\Agent\Hydrators\AgentHydrateSupplierProducts;
use App\Actions\SupplyChain\HistoricSupplierProduct\StoreHistoricSupplierProduct;
use App\Actions\SupplyChain\Supplier\Hydrators\SupplierHydrateSupplierProducts;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateProductSuppliers;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateSupplierProducts;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithPullIntoJsonColumn;
use App\Enums\SupplyChain\SupplierProduct\SupplierProductStateEnum;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use App\Rules\AlphaDashDotSpaceSlashParenthesisPlus;
use App\Rules\IUnique;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreSupplierProduct extends OrgAction
{
    use WithNoStrictRules;
    use WithPullIntoJsonColumn;
    use WithSupplyChainEditAuthorisation;

    private const DATA_FIELDS = [
        'minimum_carton_order',
        'delivery_time',
    ];

    private const NULLABLE_NUMERIC_FIELDS = [
        'cbm',
        'extra_costs',
        'minimum_carton_order',
        'delivery_time',
    ];

    public bool $skipHistoric = false;
    private int $supplier_id;

    /**
     * @throws \Throwable
     */
    public function handle(Supplier $supplier, array $modelData): SupplierProduct
    {
        $tradeUnits = Arr::pull($modelData, 'trade_units', []);
        $modelData  = $this->pullIntoJsonColumn($modelData, 'data', self::DATA_FIELDS);

        data_set($modelData, 'group_id', $supplier->group_id);
        data_set($modelData, 'state', SupplierProductStateEnum::ACTIVE, overwrite: false);

        if ($supplier->agent_id) {
            $modelData['agent_id'] = $supplier->agent_id;
        }

        data_set($modelData, 'currency_id', $supplier->currency_id);

        $supplierProduct = DB::transaction(function () use ($supplier, $modelData, $tradeUnits) {
            /** @var SupplierProduct $supplierProduct */
            $supplierProduct = $supplier->supplierProducts()->create($modelData);
            $supplierProduct->refresh();
            $supplierProduct->stats()->create();

            if ($tradeUnits) {
                $quantityPerTradeUnit = Arr::get($modelData, 'units_per_pack') ?: 1;

                SyncSupplierProductTradeUnits::run(
                    $supplierProduct,
                    collect($tradeUnits)
                        ->mapWithKeys(fn ($tradeUnitId) => [
                            $tradeUnitId => ['quantity' => $quantityPerTradeUnit]
                        ])
                        ->all()
                );
            }

            if (!$this->skipHistoric) {
                $historicProduct = StoreHistoricSupplierProduct::make()->action($supplierProduct, [
                    'status' => true,
                ]);
                $supplierProduct->update(
                    [
                        'current_historic_supplier_product_id' => $historicProduct->id
                    ]
                );
            }

            return $supplierProduct;
        });

        SyncOrgSupplierProducts::make()->fromSupplierProduct($supplierProduct, $this->hydratorsDelay);

        GroupHydrateSupplierProducts::dispatch($supplier->group)->delay($this->hydratorsDelay);
        SupplierHydrateSupplierProducts::dispatch($supplier)->delay($this->hydratorsDelay);
        AgentHydrateSupplierProducts::dispatchIf((bool)$supplierProduct->agent_id, $supplierProduct->agent)->delay($this->hydratorsDelay);
        GroupHydrateProductSuppliers::dispatch($supplier->group)->delay($this->hydratorsDelay);

        return $supplierProduct;
    }

    public function rules(): array
    {
        $rules = [
            'code'                 => [
                'required',
                $this->strict ? 'max:64' : 'max:255',
                $this->strict ? new AlphaDashDotSpaceSlashParenthesisPlus() : 'string',
                Rule::notIn(['export', 'create', 'upload']),
                new IUnique(
                    table: 'supplier_products',
                    extraConditions: [
                        ['column' => 'supplier_id', 'value' => $this->supplier_id],
                    ]
                ),
            ],
            'name'                 => ['required', 'string', 'max:255'],
            'state'                => ['sometimes', 'required', Rule::enum(SupplierProductStateEnum::class)],
            'is_available'         => ['sometimes', 'required', 'boolean'],
            'cost'                 => ['required'],
            'units_per_pack'       => ['sometimes', 'nullable'],
            'units_per_carton'     => ['sometimes', 'nullable'],
            'cbm'                  => ['sometimes', 'nullable', 'numeric'],
            'extra_costs'          => ['sometimes', 'nullable', 'numeric', 'min:0'],

            'minimum_carton_order' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'delivery_time'        => ['sometimes', 'nullable', 'integer', 'min:0'],

            'trade_units'          => ['sometimes', 'nullable', 'array'],
            'trade_units.*'        => ['integer', 'exists:trade_units,id'],
        ];

        if (!$this->strict) {
            $rules                = $this->noStrictStoreRules($rules);
            $rules['source_slug'] = ['sometimes', 'nullable', 'string'];
        }

        return $rules;
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        foreach (self::NULLABLE_NUMERIC_FIELDS as $field) {
            if ($this->has($field) && $this->get($field) === '') {
                $this->set($field, null);
            }
        }
    }

    /**
     * @throws \Throwable
     */
    public function action(Supplier $supplier, array $modelData, bool $skipHistoric = false, int $hydratorsDelay = 0, bool $strict = true, $audit = true): SupplierProduct
    {
        if (!$audit) {
            SupplierProduct::disableAuditing();
        }

        $this->supplier_id    = $supplier->id;
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->skipHistoric   = $skipHistoric;
        $this->strict         = $strict;

        $this->initialisationFromGroup($supplier->group, $modelData);

        return $this->handle($supplier, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Supplier $supplier, ActionRequest $request): SupplierProduct
    {
        $this->supplier_id = $supplier->id;
        $this->initialisationFromGroup($supplier->group, $request);

        return $this->handle($supplier, $this->validatedData);
    }

    public function htmlResponse(SupplierProduct $supplierProduct): RedirectResponse
    {
        $supplier = $supplierProduct->supplier;

        if ($supplier->agent_id) {
            return Redirect::route(
                'grp.supply-chain.agents.show.suppliers.supplier_products.show',
                [$supplier->agent->slug, $supplier->slug, $supplierProduct->slug]
            );
        }

        return Redirect::route(
            'grp.supply-chain.suppliers.supplier_products.show',
            [$supplier->slug, $supplierProduct->slug]
        );
    }
}
