<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-25 15:38
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\SupplierProduct\Json;

use App\Actions\OrgAction;
use App\Actions\SupplyChain\SupplierProduct\CopySupplierProductsToSupplier;
use App\Actions\SupplyChain\SupplierProduct\MoveSupplierProductsToSupplier;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\SupplierProduct;
use Lorisleiva\Actions\ActionRequest;

class GetSupplierProductMoveTargets extends OrgAction
{
    private Agent $agent;

    public function authorize(ActionRequest $request): bool
    {
        return CopySupplierProductsToSupplier::make()->getAccessibleAgents($request->user())->contains('id', $this->agent->id);
    }

    public function rules(): array
    {
        return [
            'supplier_products'   => ['required', 'array', 'min:1', 'max:200'],
            'supplier_products.*' => ['integer', 'distinct'],
        ];
    }

    public function asController(Agent $agent, ActionRequest $request): array
    {
        $this->agent = $agent;
        $this->initialisationFromGroup($agent->group, $request);

        $supplierProducts = SupplierProduct::where('agent_id', $agent->id)
            ->whereIn('id', $this->validatedData['supplier_products'])
            ->get(['id', 'supplier_id', 'code']);
        abort_unless($supplierProducts->count() === count($this->validatedData['supplier_products']), 404);

        return MoveSupplierProductsToSupplier::make()->getTargets($agent, $supplierProducts);
    }
}
