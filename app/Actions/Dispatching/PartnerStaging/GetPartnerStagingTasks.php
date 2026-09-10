<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 08 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\PartnerStaging;

use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Inventory\Warehouse;
use App\Models\Procurement\OrgPartner;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetPartnerStagingTasks
{
    use AsObject;

    /**
     * What still has to be walked from a picking location to a partner's goods out location.
     *
     * Derived, never stored: pre-picked quantity minus what already sits in the partner's
     * location, so a row disappears the moment the stock is actually moved.
     *
     * Pre-picking is a reservation, not a sale: the line carries pre_picked_at and no order.
     *
     * @return array<int, array<string, mixed>>
     */
    public function handle(Warehouse $warehouse): array
    {
        $partners = OrgPartner::where('organisation_id', $warehouse->organisation_id)
            ->whereNotNull('goods_out_location_id')
            ->with(['partner', 'goodsOutLocation'])
            ->get()
            ->keyBy('partner_id');

        if ($partners->isEmpty()) {
            return [];
        }

        $prePicked = DB::table('partner_shopping_list_items as items')
            ->join('org_stocks', 'org_stocks.id', 'items.org_stock_id')
            ->join('stocks', 'stocks.id', 'items.stock_id')
            ->whereNotNull('items.pre_picked_at')
            ->where('items.state', ShoppingListItemStateEnum::OPEN)
            ->where('items.partner_organisation_id', $warehouse->organisation_id)
            ->whereNull('items.deleted_at')
            ->whereIn('items.organisation_id', $partners->keys())
            ->groupBy('items.organisation_id', 'items.stock_id', 'stocks.code', 'stocks.name')
            ->select([
                'items.organisation_id as buyer_id',
                'items.stock_id',
                'stocks.code as stock_code',
                'stocks.name as stock_name',
                DB::raw('sum(items.quantity) as quantity_pre_picked'),
            ])
            ->get();

        $tasks = [];
        foreach ($prePicked as $row) {
            $partner = $partners->get($row->buyer_id);
            if (!$partner) {
                continue;
            }

            $sellerOrgStockId = DB::table('org_stocks')
                ->where('organisation_id', $warehouse->organisation_id)
                ->where('stock_id', $row->stock_id)
                ->value('id');

            $staged = (float) DB::table('location_org_stocks')
                ->where('location_id', $partner->goods_out_location_id)
                ->where('org_stock_id', $sellerOrgStockId)
                ->sum('quantity');

            $toMove = round((float) $row->quantity_pre_picked - $staged, 3);
            if ($toMove <= 0) {
                continue;
            }

            $tasks[] = [
                'org_partner_id'  => $partner->id,
                'org_stock_id'    => $sellerOrgStockId,
                'stock_code'      => $row->stock_code,
                'stock_name'      => $row->stock_name,
                'partner_code'    => $partner->partner->code,
                'to_location'     => $partner->goodsOutLocation->code,
                'quantity_staged' => $staged,
                'quantity_to_move' => $toMove,
                'from_locations'  => $this->sourceLocations($sellerOrgStockId, $partner->goods_out_location_id),
            ];
        }

        return $tasks;
    }

    /** @return array<int, array{location_org_stock_id: int, code: string, quantity: float}> */
    private function sourceLocations(?int $orgStockId, int $goodsOutLocationId): array
    {
        if (!$orgStockId) {
            return [];
        }

        return DB::table('location_org_stocks')
            ->join('locations', 'locations.id', 'location_org_stocks.location_id')
            ->where('location_org_stocks.org_stock_id', $orgStockId)
            ->where('location_org_stocks.location_id', '!=', $goodsOutLocationId)
            ->where('locations.is_goods_out', false)
            ->where('location_org_stocks.quantity', '>', 0)
            ->orderByDesc('location_org_stocks.quantity')
            ->limit(5)
            ->get([
                'location_org_stocks.id as location_org_stock_id',
                'locations.code',
                'location_org_stocks.quantity',
            ])
            ->map(fn ($row) => [
                'location_org_stock_id' => (int) $row->location_org_stock_id,
                'code'                  => $row->code,
                'quantity'              => (float) $row->quantity,
            ])
            ->all();
    }
}
