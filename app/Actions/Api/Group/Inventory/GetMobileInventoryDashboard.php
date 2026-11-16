<?php

/*
 * Author Louis Perez
 * Created on 16-11-2026-09h-42m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Inventory;

use App\Actions\Inventory\UI\ShowInventoryDashboard;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class GetMobileInventoryDashboard
{
    use AsController;
    use WithMobileInventoryAccess;

    /**
     * @return array<string, int|float|string|null>
     */
    public function handle(Warehouse $warehouse): array
    {
        $organisation   = $warehouse->organisation;
        $stats          = $warehouse->stats;
        $inventoryStats = $organisation->inventoryStats;
        $utilisation    = ShowInventoryDashboard::locationUtilisation($warehouse);

        return [
            'currency_code'               => $organisation->currency->code,
            'stock_value'                 => (float) $stats?->stock_value,
            'number_locations'            => (int) $stats?->number_locations,
            'number_empty_locations'      => (int) $stats?->number_empty_locations,
            'utilisation'                 => $utilisation === null ? null : (float) $utilisation,
            'number_current_org_stocks'   => (int) $inventoryStats?->number_current_org_stocks,
            'number_abnormalities'        => (int) $inventoryStats?->number_org_stocks_state_abnormality,
            'low_stock_audits'            => (int) $stats?->number_org_stocks_low_stock_audits,
            'replenishments_wholesale'    => (int) $stats?->number_org_stocks_replenishments_wholesale,
            'replenishments_dropshipping' => (int) $stats?->number_org_stocks_replenishments_dropshipping,
            'below_minimum_stock'         => (int) $stats?->number_org_stocks_below_minimum_stock,
            'number_with_minimum_stock'   => ShowInventoryDashboard::numberOrgStocksWithMinimumStock($organisation),
            'negative_stocks'             => ShowInventoryDashboard::numberNegativeStocks($warehouse),
            'wrong_weights'               => ShowInventoryDashboard::numberWrongWeights($organisation),
            'without_products'            => (int) $stats?->number_org_stocks_without_products,
        ];
    }

    public function asController(Warehouse $warehouse, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanViewInventory($user, $warehouse);

        return response()->json(['data' => $this->handle($warehouse)]);
    }
}
