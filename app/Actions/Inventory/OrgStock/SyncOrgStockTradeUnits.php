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
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Role;
use App\Notifications\OrgStockPackingChangedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
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
     * audited status until someone counts the shelf again. Warehouse admins are notified either
     * way, from here, so every writer of the pivot (packing editor, group stock editor cascade,
     * repairs) tells the warehouse.
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

        if ($reMeansStockedLocations) {
            $this->notifyWarehouses($orgStock, (bool) $conversionRatio);
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

    private function notifyWarehouses(OrgStock $orgStock, bool $countsConverted): void
    {
        $body = $countsConverted
            ? __('The packing of :code changed and its location counts were converted to the new pack size. Please verify on the shelf when convenient.', ['code' => $orgStock->code])
            : __('The packing of :code changed but its location counts were kept: every location holding it needs a physical recount.', ['code' => $orgStock->code]);

        $previousTeamId = getPermissionsTeamId();
        setPermissionsTeamId($orgStock->group_id);

        try {
            $warehouses = Warehouse::whereIn(
                'id',
                $orgStock->locationOrgStocks()->where('quantity', '!=', 0)->pluck('warehouse_id')->unique()
            )->get();

            foreach ($warehouses as $warehouse) {
                $users = Role::where('name', RolesEnum::getRoleName(RolesEnum::WAREHOUSE_ADMIN->value, $warehouse))
                    ->first()?->users;
                if ($users && $users->isNotEmpty()) {
                    Notification::send($users, new OrgStockPackingChangedNotification($orgStock, $warehouse, $body));
                } else {
                    Log::warning('Packing change on stocked org stock could not notify warehouse admins', [
                        'org_stock' => $orgStock->slug,
                        'warehouse' => $warehouse->slug,
                    ]);
                }
            }
        } finally {
            setPermissionsTeamId($previousTeamId);
        }
    }
}
