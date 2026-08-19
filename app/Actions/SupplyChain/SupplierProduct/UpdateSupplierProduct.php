<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 11 Aug 2024 14:46:44 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierProduct;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithSupplyChainEditAuthorisation;
use App\Actions\Procurement\OrgSupplierProducts\UpdateOrgSupplierProduct;
use App\Actions\SupplyChain\Agent\Hydrators\AgentHydrateSupplierProducts;
use App\Actions\SupplyChain\HistoricSupplierProduct\StoreHistoricSupplierProduct;
use App\Actions\SupplyChain\Supplier\Hydrators\SupplierHydrateSupplierProducts;
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
use Lorisleiva\Actions\ActionRequest;

class UpdateSupplierProduct extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithSupplierProductJsonColumns;
    use WithSupplyChainEditAuthorisation;

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

    private const ORG_PROPAGATED_FIELDS = [
        'state',
        'is_available',
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

        if ($supplierProduct->wasChanged(self::ORG_PROPAGATED_FIELDS)) {
            foreach ($supplierProduct->orgSupplierProducts as $orgSupplierProduct) {
                UpdateOrgSupplierProduct::run(
                    $orgSupplierProduct,
                    [
                        'state'        => $supplierProduct->state,
                        'is_available' => $supplierProduct->is_available
                    ]
                );
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
            'cost'             => ['sometimes', 'required'],
            'units_per_pack'   => ['sometimes', 'nullable'],
            'units_per_carton' => ['sometimes', 'nullable'],
            'cbm'              => ['sometimes', 'nullable', 'numeric'],
            'extra_costs'      => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ];

        $rules = array_merge($rules, $this->supplierProductJsonFieldRules());

        if (!$this->strict) {
            $rules['data']     = ['sometimes', 'array'];
            $rules['settings'] = ['sometimes', 'array'];
            $rules             = $this->noStrictUpdateRules($rules);
        }

        return $rules;
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
