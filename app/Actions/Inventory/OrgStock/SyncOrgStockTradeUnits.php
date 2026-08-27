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
use App\Actions\Traits\ModelHydrateSingleTradeUnits;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Role;
use App\Notifications\OrgStockPackingChangedNotification;
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
     * A pivot change re-means every stored location count, so stocked locations lose their
     * audited status until someone counts the shelf again — except when the caller converts
     * the counts arithmetically ($stockStrategy 'convert'), which keeps them as trustworthy
     * as they were. Warehouse admins are notified either way, from here, so every writer
     * of the pivot (packing editor, group stock editor cascade, repairs) tells the warehouse.
     */
    public function handle(OrgStock $orgStock, array $tradeUnitsData, ?string $stockStrategy = null): OrgStock
    {
        $reMeansStockedLocations = self::pivotChanges($orgStock, $tradeUnitsData)
            && $orgStock->locationOrgStocks()->where('quantity', '!=', 0)->exists();

        $orgStock->tradeUnits()->sync($tradeUnitsData);

        if ($reMeansStockedLocations) {
            if ($stockStrategy !== 'convert') {
                $orgStock->locationOrgStocks()
                    ->where('quantity', '!=', 0)
                    ->update(['audited_at' => null, 'is_low_stock_checked' => false]);
            }
            $this->notifyWarehouses($orgStock, $stockStrategy);
        }

        foreach ($orgStock->tradeUnits as $tradeUnit) {
            SetTradeUnitStatus::dispatch($tradeUnit);
            TradeUnitsHydrateOrgStocks::dispatch($tradeUnit);
        }
        $orgStock = ModelHydrateSingleTradeUnits::run($orgStock);
        OrgStockHydratePackedIn::run($orgStock);

        return $orgStock;
    }

    private function notifyWarehouses(OrgStock $orgStock, ?string $stockStrategy): void
    {
        $body = $stockStrategy === 'convert'
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
