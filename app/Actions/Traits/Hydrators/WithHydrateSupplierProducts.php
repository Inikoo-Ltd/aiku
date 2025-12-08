<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 11 Aug 2024 16:51:42 Central Indonesia Time, (Pizarro) Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\SupplyChain\SupplierProduct\SupplierProductStateEnum;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\SysAdmin\Group;

trait WithHydrateSupplierProducts
{
    use WithEnumStats;

    public function getSupplierProductsStats(Group|Agent|Supplier $model): array
    {
        $stats = [
            'number_supplier_products' => $model->supplierProducts()->count(),
            'number_available_supplier_products' => $model->supplierProducts()->whereIn('state', [
                SupplierProductStateEnum::ACTIVE,
                SupplierProductStateEnum::DISCONTINUING,
            ])->where('is_available', true)->count(),
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'supplier_products',
                field: 'state',
                enum: SupplierProductStateEnum::class,
                models: SupplierProduct::class,
                where: function ($q) use ($model) {
                    $q->where(
                        match (class_basename($model)) {
                            'Group' => 'group_id',
                            'Agent' => 'agent_id',
                            'Supplier' => 'supplier_id',
                        },
                        $model->id
                    );
                }
            )
        );

        $stats['number_current_supplier_products'] = $stats['number_supplier_products_state_active'] + $stats['number_supplier_products_state_discontinuing'];
        $stats['number_no_available_supplier_products'] = $stats['number_current_supplier_products'] - $stats['number_available_supplier_products'];

        return $stats;
    }
}
