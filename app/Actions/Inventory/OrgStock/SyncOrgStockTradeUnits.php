<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Dec 2024 20:55:09 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock;

use App\Actions\Goods\TradeUnit\Hydrators\TradeUnitsHydrateOrgStocks;
use App\Actions\Goods\TradeUnit\SetTradeUnitStatus;
use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydratePackedIn;
use App\Actions\Inventory\OrgStockMovement\StoreOrgStockMovement;
use App\Actions\Traits\ModelHydrateSingleTradeUnits;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementReasonEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Actions\Tasks\StoreStaffTask;
use App\Models\Inventory\OrgStock;
use App\Models\SysAdmin\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncOrgStockTradeUnits
{
    use AsAction;

    /**
     * The pivot quantity is the meaning of every stored stock count: comparing before
     * writing lets callers guard locations whose numbers are about to be re-meant.
     */
    public static function pivotChanges(OrgStock $orgStock, array $tradeUnitsData): bool
    {
        $currentPivot = $orgStock->tradeUnits()
            ->pluck('model_has_trade_units.quantity', 'trade_units.id')
            ->map(fn ($quantity) => (float) $quantity)
            ->sortKeys();

        $newPivot = collect($tradeUnitsData)
            ->map(fn ($pivotData) => (float) $pivotData['quantity'])
            ->sortKeys();

        return $currentPivot->toArray() != $newPivot->toArray();
    }

    /**
     * Physical pieces on the shelf don't move when the packing is redeclared, so the count
     * converts by old/new pack size. Only defined where both sides are a real packing under
     * the same rule the packed_in hydrators use: a single trade unit with a clean integer
     * quantity between 1 and 50000 — anything else is "packing unknown" and must be counted.
     */
    public static function conversionRatio(OrgStock $orgStock, array $tradeUnitsData): ?float
    {
        $currentPivot = $orgStock->tradeUnits()->pluck('model_has_trade_units.quantity', 'trade_units.id');
        $newPivot     = collect($tradeUnitsData)->map(fn ($pivotData) => (float) $pivotData['quantity']);

        if ($currentPivot->count() !== 1 || $newPivot->count() !== 1
            || (int) $currentPivot->keys()->first() !== (int) $newPivot->keys()->first()
        ) {
            return null;
        }

        $oldPackSize = (float) $currentPivot->first();
        $newPackSize = (float) $newPivot->first();

        foreach ([$oldPackSize, $newPackSize] as $packSize) {
            if (floor($packSize) != $packSize || $packSize <= 0 || $packSize > 50000) {
                return null;
            }
        }

        return $oldPackSize !== $newPackSize ? $oldPackSize / $newPackSize : null;
    }

    /**
     * A pivot change re-means every stored location count. With $stockStrategy 'convert' and a
     * packing that converts arithmetically, the counts are rescaled as zero-valued UOM audit
     * movements (the shelf and its value do not move, only the counting unit), keeping them as
     * trustworthy as they were. Otherwise the numbers stay and stocked locations lose their
     * audited status until someone counts the shelf again.
     */
    public function handle(OrgStock $orgStock, array $tradeUnitsData, ?string $stockStrategy = null, ?int $userId = null): OrgStock
    {
        $stockedLocations = $orgStock->locationOrgStocks()->where('quantity', '!=', 0)->with('location')->get();

        $reMeansStockedLocations = $stockedLocations->isNotEmpty() && self::pivotChanges($orgStock, $tradeUnitsData);

        $conversionRatio = $reMeansStockedLocations && $stockStrategy === 'convert'
            ? self::conversionRatio($orgStock, $tradeUnitsData)
            : null;

        DB::transaction(function () use ($orgStock, $tradeUnitsData, $reMeansStockedLocations, $stockedLocations, $conversionRatio, $userId) {
            $orgStock->tradeUnits()->sync($tradeUnitsData);

            if (!$reMeansStockedLocations) {
                return;
            }

            if (!$conversionRatio) {
                $orgStock->locationOrgStocks()
                    ->where('quantity', '!=', 0)
                    ->update(['audited_at' => null, 'is_low_stock_checked' => false]);

                return;
            }

            foreach ($stockedLocations as $locationOrgStock) {
                $convertedQuantity = round($locationOrgStock->quantity * $conversionRatio, 3);
                StoreOrgStockMovement::make()->action($orgStock, $locationOrgStock->location, [
                    'quantity'         => $convertedQuantity - $locationOrgStock->quantity,
                    'audited_quantity' => $convertedQuantity,
                    'type'             => OrgStockMovementTypeEnum::AUDIT,
                    'reason'           => OrgStockMovementReasonEnum::UOM,
                    'note'             => __('Count converted after packing change'),
                    'org_amount'       => 0,
                    'user_id'          => $userId,
                    'date'             => now()->format('Y-m-d H:i:s.u'),
                ]);
            }
        });

        $requester = $userId ? User::find($userId) : null;
        if ($reMeansStockedLocations && !$conversionRatio && $requester) {
            $this->askWarehousesToRecount($orgStock, $stockedLocations, $requester);
        }

        $orgStock->unsetRelation('tradeUnits');
        foreach ($orgStock->tradeUnits as $tradeUnit) {
            SetTradeUnitStatus::dispatch($tradeUnit);
            TradeUnitsHydrateOrgStocks::dispatch($tradeUnit);
        }
        $orgStock = ModelHydrateSingleTradeUnits::run($orgStock);
        OrgStockHydratePackedIn::run($orgStock);

        return $orgStock;
    }

    /**
     * Kept counts stay in the old packing until someone counts the shelf, so each warehouse
     * holding the SKO gets a task in its queue, raised by whoever changed the packing.
     * Converted counts stay trusted and need nothing; changes with no person behind them
     * (fetches, repairs) only flag the locations, one task each would flood the queue.
     */
    private function askWarehousesToRecount(OrgStock $orgStock, Collection $stockedLocations, User $requester): void
    {
        foreach ($stockedLocations->groupBy('warehouse_id') as $warehouseLocations) {
            StoreStaffTask::make()->action($requester, [
                'subject'     => __('Recount :code in :warehouse, its packing changed', [
                    'code'      => $orgStock->code,
                    'warehouse' => $warehouseLocations->first()->location->warehouse->name,
                ]),
                'description' => __('The packing of :code changed, so the stock of these locations is still counted in the old packing. Please count them again:', ['code' => $orgStock->code])
                    ."\n".$warehouseLocations->map(fn ($locationOrgStock) => $locationOrgStock->location->code.': '.(float) $locationOrgStock->quantity)->implode("\n"),
                'department'  => 'warehouse',
                'model_type'  => 'OrgStock',
                'model_id'    => $orgStock->id,
            ]);
        }
    }
}
