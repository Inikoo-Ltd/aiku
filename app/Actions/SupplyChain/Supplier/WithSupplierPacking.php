<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 16 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Supplier;

use App\Enums\Goods\TradeUnit\TradeUnitPackagingMaterialEnum;
use App\Enums\SupplyChain\Supplier\SupplierPackingMethodEnum;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use Illuminate\Validation\Rule;

/**
 * Who packs a supplier's goods, in what material, and which supplier makes the packaging when it is bought in.
 * Set on the supplier for all its products; a product may set its own.
 */
trait WithSupplierPacking
{
    protected function packingRules(?Supplier $supplier): array
    {
        return [
            'packing_method'        => ['sometimes', 'nullable', Rule::enum(SupplierPackingMethodEnum::class)],
            'packing_material'      => ['sometimes', 'nullable', Rule::enum(TradeUnitPackagingMaterialEnum::class)],
            'packaging_supplier_id' => [
                'nullable',
                Rule::requiredIf(fn () => $this->get('packing_method') === SupplierPackingMethodEnum::THIRD_PARTY->value),
                'integer',
                $supplier ? Rule::exists('suppliers', 'id')->where('group_id', $supplier->group_id)->where('agent_id', $supplier->agent_id)->whereNull('deleted_at') : 'prohibited',
                Rule::notIn([$supplier?->id]),
            ],
        ];
    }

    protected function packingEditFields(Supplier|SupplierProduct $model, Supplier $supplier): array
    {
        $isOverride = $model instanceof SupplierProduct;

        return [
            'packing_method'        => [
                'type'        => 'select',
                'label'       => __('Packing method'),
                'information' => $isOverride
                    ? __('Leave empty to use the supplier\'s packing.')
                    : __('Who packs this supplier\'s goods for us. Its products follow it unless a product sets its own.'),
                'placeholder' => $isOverride ? __('As the supplier') : __('Select who packs'),
                'options'     => collect(SupplierPackingMethodEnum::labels())->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])->values()->all(),
                'value'       => $model->packing_method?->value,
                'mode'        => 'single',
            ],
            'packing_material'      => [
                'type'        => 'select',
                'label'       => __('Packing material'),
                'placeholder' => __('Select a material'),
                'options'     => collect(TradeUnitPackagingMaterialEnum::materials())->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label.' ('.TradeUnitPackagingMaterialEnum::labels()[$value].')'])->values()->all(),
                'value'       => $model->packing_material?->value,
                'searchable'  => true,
                'mode'        => 'single',
            ],
            'packaging_supplier_id' => [
                'type'        => 'select',
                'label'       => __('Packaging supplier'),
                'information' => __('The supplier that makes the packaging, when bought in.'),
                'placeholder' => __('Select a supplier'),
                'options'     => Supplier::where('group_id', $supplier->group_id)
                    ->where('status', true)
                    ->where('id', '!=', $supplier->id)
                    ->where('agent_id', $supplier->agent_id)
                    ->orderBy('code')
                    ->get(['id', 'code', 'name'])
                    ->map(fn (Supplier $packagingSupplier) => ['value' => $packagingSupplier->id, 'label' => $packagingSupplier->code.' · '.$packagingSupplier->name])
                    ->all(),
                'value'       => $model->packaging_supplier_id,
                'searchable'  => true,
                'mode'        => 'single',
            ],
        ];
    }
}
