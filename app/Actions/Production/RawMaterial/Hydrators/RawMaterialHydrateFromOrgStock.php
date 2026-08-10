<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Aug 2026 17:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\RawMaterial\Hydrators;

use App\Enums\Inventory\OrgStock\OrgStockQuantityStatusEnum;
use App\Enums\Production\RawMaterial\RawMaterialStockStatusEnum;
use App\Models\Production\RawMaterial;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class RawMaterialHydrateFromOrgStock implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'hydrators-slave';

    public function getJobUniqueId(RawMaterial $rawMaterial): string
    {
        return (string)$rawMaterial->id;
    }

    public function handle(RawMaterial $rawMaterial): RawMaterial
    {
        $orgStock = $rawMaterial->orgStock;
        if (!$orgStock) {
            return $rawMaterial;
        }

        $stockStatus = match ($orgStock->quantity_status) {
            OrgStockQuantityStatusEnum::EXCESS => RawMaterialStockStatusEnum::SURPLUS,
            OrgStockQuantityStatusEnum::LOW => RawMaterialStockStatusEnum::LOW,
            OrgStockQuantityStatusEnum::CRITICAL => RawMaterialStockStatusEnum::CRITICAL,
            OrgStockQuantityStatusEnum::OUT_OF_STOCK => RawMaterialStockStatusEnum::OUT_OF_STOCK,
            OrgStockQuantityStatusEnum::ERROR => RawMaterialStockStatusEnum::ERROR,
            default => RawMaterialStockStatusEnum::OPTIMAL,
        };

        $rawMaterial->update([
            'quantity_on_location' => $orgStock->quantity_in_locations ?? 0,
            'stock_status'         => $stockStatus,
            'unit_cost'            => $orgStock->current_supplier_sku_cost ?? $rawMaterial->unit_cost,
        ]);

        return $rawMaterial;
    }
}
