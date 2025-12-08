<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 11 Aug 2024 17:06:46 Central Indonesia Time, (Pizarro) Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Procurement\OrgSupplierProduct\OrgSupplierProductStateEnum;
use App\Enums\SupplyChain\SupplierProduct\SupplierProductStateEnum;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\SysAdmin\Organisation;

trait WithHydrateOrgSupplierProducts
{
    use WithEnumStats;

    public function getOrgSupplierProductsStats(Organisation|OrgAgent|OrgSupplier $model): array
    {
        $stats = [
            'number_org_supplier_products' => $model->orgSupplierProducts()->count(),
            'number_available_org_supplier_products' => $model->orgSupplierProducts()->whereIn('state', [
                SupplierProductStateEnum::ACTIVE,
                SupplierProductStateEnum::DISCONTINUING,
            ])->where('is_available', true)->count(),
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'org_supplier_products',
                field: 'state',
                enum: OrgSupplierProductStateEnum::class,
                models: OrgSupplierProduct::class,
                where: function ($q) use ($model) {
                    $q->where(
                        match (class_basename($model)) {
                            'Organisation' => 'organisation_id',
                            'OrgAgent' => 'org_agent_id',
                            'OrgSupplier' => 'org_supplier_id',
                        },
                        $model->id
                    );
                }
            )
        );

        $stats['number_current_org_supplier_products'] = $stats['number_org_supplier_products_state_active'] + $stats['number_org_supplier_products_state_discontinuing'];
        $stats['number_no_available_org_supplier_products'] = $stats['number_current_org_supplier_products'] - $stats['number_available_org_supplier_products'];

        return $stats;
    }
}
