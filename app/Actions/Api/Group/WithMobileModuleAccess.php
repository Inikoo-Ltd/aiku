<?php

/*
 * Author Louis Perez
 * Created on 18-11-2026-08h-51m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group;

use App\Actions\Dispatching\DeliveryNote\UI\IndexDeliveryNotes;
use App\Actions\UI\Incoming\ShowIncomingHub;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;

trait WithMobileModuleAccess
{
    public static function canViewGoodsOut(User $user, Warehouse $warehouse): bool
    {
        return $warehouse->group_id === $user->group_id && IndexDeliveryNotes::canViewDispatching($user, $warehouse);
    }

    public static function canViewGoodsIn(User $user, Warehouse $warehouse): bool
    {
        return $warehouse->group_id === $user->group_id && ShowIncomingHub::canViewGoodsIn($user, $warehouse);
    }

    public static function canViewReturns(User $user, Warehouse $warehouse): bool
    {
        return $warehouse->group_id === $user->group_id && $user->authTo([
            "incoming.$warehouse->id.view",
            "returns.$warehouse->id",
        ]);
    }

    public static function canViewInventory(User $user, Warehouse $warehouse): bool
    {
        if ($warehouse->group_id !== $user->group_id) {
            return false;
        }

        $organisationId = $warehouse->organisation_id;
        $permissions    = ["inventory.$organisationId.view", "accounting.$organisationId.view"];

        foreach ($warehouse->organisation->warehouses()->pluck('id') as $warehouseId) {
            $permissions[] = "supervisor-stocks.$warehouseId.view";
            $permissions[] = "stocks.$warehouseId.view";
            $permissions[] = "fulfilment.$warehouseId.view";
        }

        return $user->authTo($permissions);
    }

    public static function canViewLocations(User $user, Warehouse $warehouse): bool
    {
        return $warehouse->group_id === $user->group_id && $user->authTo([
            "inventory.$warehouse->organisation_id.view",
            "supervisor-incoming.$warehouse->id",
            "supervisor-locations.$warehouse->id",
            "locations.$warehouse->id.view",
            "warehouses-view.$warehouse->organisation_id",
        ]);
    }

    public static function canViewFulfilment(User $user, Warehouse $warehouse): bool
    {
        return $warehouse->group_id === $user->group_id && $user->authTo([
            "fulfilment.$warehouse->id.view",
            "supervisor-incoming.$warehouse->id",
            "supervisor-fulfilment.$warehouse->id",
            "warehouses-view.$warehouse->organisation_id",
        ]);
    }
}
