<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Nov 2026 13:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\LocationOrgStock;

use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementReasonEnum;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\Warehouse;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * HELP-4450: warehouses that switch it on lose the links of SKOs that ran out in a location,
 * through DeleteLocationOrgStock so each removal is a disassociate movement in the stock history.
 * Kept: active picking locations (trolley or basket), the SKO's current pick location, locations
 * marked keep_empty_org_stocks, SKOs with goods received but not yet placed, and anything that
 * moved in the last hour.
 */
class DisassociateEmptyLocationOrgStocks
{
    use AsAction;

    public const int MAX_PER_RUN = 200;

    public string $commandSignature = 'location_org_stocks:disassociate_empty {--dry : Count only}';

    public static function candidatesQuery(Warehouse $warehouse): Builder
    {
        return LocationOrgStock::query()
            ->where('location_org_stocks.warehouse_id', $warehouse->id)
            ->where('location_org_stocks.quantity', 0)
            ->where('location_org_stocks.default_wholesale_picking_location', false)
            ->where('location_org_stocks.default_dropshipping_picking_location', false)
            ->join('locations', 'locations.id', 'location_org_stocks.location_id')
            ->where('locations.keep_empty_org_stocks', false)
            ->join('org_stocks', 'org_stocks.id', 'location_org_stocks.org_stock_id')
            ->whereRaw('org_stocks.picking_location_id IS DISTINCT FROM location_org_stocks.location_id')
            ->whereRaw('org_stocks.picking_dropshipping_location_id IS DISTINCT FROM location_org_stocks.location_id')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('org_stock_movements')
                    ->whereColumn('org_stock_movements.org_stock_id', 'location_org_stocks.org_stock_id')
                    ->whereColumn('org_stock_movements.location_id', 'location_org_stocks.location_id')
                    ->where('org_stock_movements.date', '>', now()->subHour());
            })
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('stock_delivery_items')
                    ->whereColumn('stock_delivery_items.org_stock_id', 'location_org_stocks.org_stock_id')
                    ->whereIn('stock_delivery_items.state', [StockDeliveryItemStateEnum::RECEIVED, StockDeliveryItemStateEnum::CHECKED])
                    ->whereNull('stock_delivery_items.deleted_at');
            });
    }

    /**
     * @return array<string, int> removed links per warehouse slug
     */
    public function handle(bool $dry = false): array
    {
        $removed = [];

        foreach (Warehouse::whereRaw("(settings->>'auto_disassociate_empty_locations')::boolean")->get() as $warehouse) {
            $ids = self::candidatesQuery($warehouse)
                ->orderBy('location_org_stocks.id')
                ->limit(self::MAX_PER_RUN)
                ->pluck('location_org_stocks.id');

            $removed[$warehouse->slug] = $dry ? $ids->count() : $ids->sum(fn (int $id) => (int) $this->disassociate($id));
        }

        return $removed;
    }

    private function disassociate(int $locationOrgStockId): bool
    {
        return DB::transaction(function () use ($locationOrgStockId) {
            $locationOrgStock = LocationOrgStock::whereKey($locationOrgStockId)->lockForUpdate()->first();

            if (!$locationOrgStock || $locationOrgStock->quantity != 0) {
                return false;
            }

            DeleteLocationOrgStock::make()->action($locationOrgStock, null, OrgStockMovementReasonEnum::EMPTY_LOCATION);

            return true;
        });
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        foreach ($this->handle((bool) $command->option('dry')) as $warehouse => $count) {
            $command->line("$warehouse: $count");
        }

        return 0;
    }
}
