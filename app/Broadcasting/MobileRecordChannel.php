<?php

/*
 * Author Louis Perez
 * Created on 26-11-2026-10h-46m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Broadcasting;

use App\Actions\Api\Group\WithMobileModuleAccess;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Fulfilment\PalletReturn;
use App\Models\GoodsIn\ReturnDeliveryNote;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Inventory\PickingSession;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;

/**
 * The record pings the staff app listens to while a record is open, for whoever may open that record in the
 * app: the same module check as its screen.
 */
class MobileRecordChannel
{
    use WithMobileModuleAccess;

    public function join($user, string $type, string $id): bool
    {
        if (!$user instanceof User || !ctype_digit($id)) {
            return false;
        }

        return match ($type) {
            'delivery_note'   => $this->canView($user, DeliveryNote::find($id)?->warehouse, 'canViewGoodsOut'),
            'picking_session' => $this->canView($user, PickingSession::find($id)?->warehouse, 'canViewGoodsOut'),
            'return'          => $this->canView($user, ReturnDeliveryNote::find($id)?->warehouse, 'canViewReturns'),
            'pallet_return'   => $this->canView($user, PalletReturn::find($id)?->warehouse, 'canViewFulfilment'),
            'stock_delivery'  => $this->canViewStockDelivery($user, StockDelivery::find($id)),
            default           => false,
        };
    }

    private function canView(User $user, ?Warehouse $warehouse, string $check): bool
    {
        return $warehouse !== null && self::$check($user, $warehouse);
    }

    private function canViewStockDelivery(User $user, ?StockDelivery $stockDelivery): bool
    {
        return $stockDelivery !== null && Warehouse::where('organisation_id', $stockDelivery->organisation_id)
            ->get()
            ->contains(fn (Warehouse $warehouse) => self::canViewGoodsIn($user, $warehouse));
    }
}
