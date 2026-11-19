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
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\SysAdmin\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class BulkUpdateSupplierProductsState extends OrgAction
{
    /**
     * A blank reason keeps each product's own reason. All rows change or none do.
     *
     * @param  array{supplier_products: array<int, int>, state: string, discontinued_reason?: string|null}  $modelData
     */
    public function handle(Group $group, array $modelData): int
    {
        $state = SupplierProductStateEnum::from($modelData['state']);

        $supplierProducts = SupplierProduct::where('group_id', $group->id)
            ->whereIn('id', $modelData['supplier_products'])
            ->where(fn ($query) => $query
                ->where('state', '!=', $state->value)
                ->orWhereRaw("data->>'".UpdateSupplierProduct::FOLLOWS_DISCONTINUING_SKOS_KEY."' is not null"))
            ->get();

        $reason     = trim((string) ($modelData['discontinued_reason'] ?? ''));
        $updateData = ['state' => $state];
        if ($reason !== '') {
            $updateData['discontinued_reason'] = $reason;
        }

        DB::transaction(function () use ($supplierProducts, $updateData) {
            foreach ($supplierProducts as $supplierProduct) {
                UpdateSupplierProduct::make()->action($supplierProduct, $updateData);
            }
        });

        return $supplierProducts->count();
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        if (!$request->has('org_supplier_products')) {
            return;
        }

        $this->set(
            'supplier_products',
            OrgSupplierProduct::where('group_id', $this->group->id)
                ->whereIn('id', array_map('intval', (array) $request->input('org_supplier_products')))
                ->distinct()
                ->pluck('supplier_product_id')
                ->all()
        );
    }

    public function rules(): array
    {
        return [
            'supplier_products'   => ['required', 'array', 'min:1', 'max:200'],
            'supplier_products.*' => ['integer', Rule::exists('supplier_products', 'id')->where('group_id', $this->group->id)],
            'state'               => ['required', Rule::enum(SupplierProductStateEnum::class)],
            'discontinued_reason' => ['sometimes', 'nullable', 'string', 'max:500'],
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
