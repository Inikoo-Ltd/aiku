<?php

/*
 * Author: Vika Aqordi <aqordivika@yahoo.co.id>
 * Created: Mon, 10 Aug 2026 10:00:00 Bali, Indonesia
 * Copyright (c) 2026, Vika Aqordi
*/

namespace App\Actions\Dispatching\DeliveryNoteItem;

use App\Actions\Dispatching\Picking\StorePicking;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\DB;

/**
 * What a scan has to work out before it can pick: how much of the line is still missing, which shelf
 * to send the picker to, and how much of it that shelf can actually hand over. Shared by the delivery
 * note and the picking session scanners so both send the picker to the same place.
 */
trait WithScannedDeliveryNoteItemPicking
{
    /**
     * Mirrors the quantity_to_pick the picking tables show: anything already picked, written off as
     * not picked or parked as waiting is not pickable.
     */
    public static function quantityLeftToPick(DeliveryNoteItem $deliveryNoteItem): float
    {
        return max(0, round(
            (float)$deliveryNoteItem->quantity_required
            - (float)$deliveryNoteItem->quantity_picked
            - (float)$deliveryNoteItem->quantity_not_picked
            - (float)$deliveryNoteItem->quantity_waiting_warehouse
            - (float)$deliveryNoteItem->quantity_waiting_crm,
            3
        ));
    }

    /**
     * The same subtraction without the tolerance rounding, so a pick can never put more in the
     * trolley than the line is missing: 5/12 of an outer is 0.416667, and rounding that to 0.417
     * would write a hair more than was asked for on every 'pick the rest'.
     */
    protected static function exactQuantityLeftToPick(DeliveryNoteItem $deliveryNoteItem): float
    {
        return max(0, (float)$deliveryNoteItem->quantity_required
            - (float)$deliveryNoteItem->quantity_picked
            - (float)$deliveryNoteItem->quantity_not_picked
            - (float)$deliveryNoteItem->quantity_waiting_warehouse
            - (float)$deliveryNoteItem->quantity_waiting_crm);
    }

    /**
     * The location the picker is sent to is the one the picking table would preselect: the shop's
     * default picking location first, then picking priority. Locations that hold nothing are skipped,
     * because a scan is only useful if it names a shelf the item can actually be taken from.
     */
    protected function pickingLocation(DeliveryNoteItem $deliveryNoteItem, ?int $requestedLocationOrgStockId): ?object
    {
        $deliveryNote = $deliveryNoteItem->deliveryNote;

        $defaultLocationColumn = $deliveryNoteItem->shop->type == ShopTypeEnum::B2B
            ? 'location_org_stocks.default_wholesale_picking_location'
            : 'location_org_stocks.default_dropshipping_picking_location';

        $locations = DB::table('location_org_stocks')
            ->leftJoin('locations', 'locations.id', '=', 'location_org_stocks.location_id')
            ->where('location_org_stocks.org_stock_id', $deliveryNoteItem->org_stock_id)
            ->where('location_org_stocks.warehouse_id', $deliveryNote->warehouse_id)
            ->where('location_org_stocks.quantity', '>', 0)
            ->orderByRaw("$defaultLocationColumn desc")
            ->orderBy('location_org_stocks.picking_priority')
            ->select([
                'location_org_stocks.id',
                'location_org_stocks.quantity',
                'locations.code as location_code',
            ])
            ->get();

        if ($requestedLocationOrgStockId) {
            return $locations->firstWhere('id', (int)$requestedLocationOrgStockId) ?? $locations->first();
        }

        return $locations->first();
    }

    /**
     * 'Pick all' passes no quantity, meaning whatever is still missing on the line. Either way the
     * location cannot hand over more than it holds, which is the same clamp PickAllItem applies.
     *
     * @return float the quantity that went into the trolley, zero when the shelf had nothing to give
     */
    protected function storeScannedPicking(DeliveryNoteItem $deliveryNoteItem, User $user, object $location, ?float $requestedQuantity): float
    {
        $quantityToPick = min(
            $requestedQuantity ?? static::exactQuantityLeftToPick($deliveryNoteItem),
            static::exactQuantityLeftToPick($deliveryNoteItem),
            (float)$location->quantity
        );

        if ($quantityToPick <= 0) {
            return 0;
        }

        StorePicking::make()->action($deliveryNoteItem, $user, [
            'location_org_stock_id' => $location->id,
            'quantity'              => $quantityToPick,
            'picker_user_id'        => $user->id,
        ]);

        return $quantityToPick;
    }
}
