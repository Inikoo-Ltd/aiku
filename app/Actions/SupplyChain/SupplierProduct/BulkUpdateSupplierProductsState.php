<?php

/*
 * Author: Vika Aqordi
 * Created on 17-11-2026
 * Github: https://github.com/aqordeon
 * Copyright: 2026
 */

namespace App\Actions\SupplyChain\SupplierProduct;

use App\Actions\OrgAction;
use App\Enums\SupplyChain\SupplierProduct\SupplierProductStateEnum;
use App\Enums\SysAdmin\Authorisation\GroupPermissionsEnum;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\SysAdmin\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class BulkUpdateSupplierProductsState extends OrgAction
{
    /**
     * @param  array{supplier_products: array<int, int>, state: string}  $modelData
     */
    public function handle(Group $group, array $modelData): int
    {
        $state = SupplierProductStateEnum::from($modelData['state']);

        $supplierProducts = SupplierProduct::where('group_id', $group->id)
            ->whereIn('id', $modelData['supplier_products'])
            ->where('state', '!=', $state->value)
            ->get();

        foreach ($supplierProducts as $supplierProduct) {
            UpdateSupplierProduct::make()->action($supplierProduct, ['state' => $state]);
        }

        return $supplierProducts->count();
    }

    public function rules(): array
    {
        return [
            'supplier_products'   => ['required', 'array', 'min:1', 'max:500'],
            'supplier_products.*' => ['integer', Rule::exists('supplier_products', 'id')->where('group_id', $this->group->id)],
            'state'               => ['required', Rule::enum(SupplierProductStateEnum::class)],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        if (in_array(SupplierProductStateEnum::tryFrom((string) $request->input('state')), [SupplierProductStateEnum::DISCONTINUING, SupplierProductStateEnum::DISCONTINUED], true)) {
            return $request->user()->authTo(GroupPermissionsEnum::SUPPLY_CHAIN->value);
        }

        return $request->user()->authTo(GroupPermissionsEnum::SUPPLY_CHAIN_EDIT->value);
    }

    public function asController(ActionRequest $request): int
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($this->group, $this->validatedData);
    }

    public function jsonResponse(int $updatedCount): JsonResponse
    {
        return response()->json(['updated' => $updatedCount]);
    }
}
