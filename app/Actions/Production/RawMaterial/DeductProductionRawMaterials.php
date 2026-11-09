<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Nov 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\RawMaterial;

use App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock;
use App\Actions\Inventory\OrgStockMovement\StoreOrgStockMovement;
use App\Enums\Inventory\LocationStock\LocationStockTypeEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\OrgStock;
use App\Models\Production\Production;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsObject;

class DeductProductionRawMaterials
{
    use AsObject;

    /**
     * Takes raw materials out of stock, from the factory location first; a negative quantity puts them back.
     *
     * @param  array<int, array{orgStock: OrgStock, quantity: float}>  $consumptionByOrgStock  units of each raw material, by org stock id
     */
    public function handle(Production $production, array $consumptionByOrgStock, ?int $userId): void
    {
        foreach ($consumptionByOrgStock as $consumption) {
            $orgStock = $consumption['orgStock'];
            if (abs($consumption['quantity']) < 1e-9) {
                continue;
            }

            $deductionLocationOrgStock = LocationOrgStock::where('org_stock_id', $orgStock->id)
                ->when($production->factory_location_id, fn ($query, $factoryLocationId) => $query->orderByRaw('(location_id = ? and quantity > 0) desc', [$factoryLocationId]))
                ->orderByRaw("type = '".LocationStockTypeEnum::PICKING->value."' desc")
                ->orderByDesc('quantity')
                ->first();

            $factoryLocation = $production->factoryLocation;
            if (!$deductionLocationOrgStock && $factoryLocation) {
                $deductionLocationOrgStock = StoreLocationOrgStock::make()->action($orgStock, $factoryLocation, []);
            }

            if (!$deductionLocationOrgStock) {
                throw ValidationException::withMessages([
                    'location_id' => __('Raw material :code has no stock location to deduct from', ['code' => $orgStock->code]),
                ]);
            }

            StoreOrgStockMovement::make()->action($orgStock, $deductionLocationOrgStock->location, [
                'quantity' => -$consumption['quantity'] / $orgStock->packedIn(),
                'type'     => OrgStockMovementTypeEnum::PRODUCTION,
                'user_id'  => $userId,
            ]);
        }
    }
}
