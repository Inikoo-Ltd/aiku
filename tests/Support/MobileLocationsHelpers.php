<?php

/*
 * Author Louis Perez
 * Created on 16-11-2026-09h-42m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace Tests\Feature;

use App\Actions\Goods\Stock\StoreStock;
use App\Actions\Inventory\Location\StoreLocation;
use App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock;
use App\Actions\Inventory\OrgStock\StoreOrgStock;
use App\Actions\Inventory\WarehouseArea\StoreWarehouseArea;
use App\Actions\SysAdmin\User\SetUserAuthorisedModels;
use App\Enums\Inventory\LocationStock\LocationStockTypeEnum;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Models\Goods\Stock;
use App\Models\Inventory\Location;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockMovement;
use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseArea;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

require_once __DIR__.'/MobileApiHelpers.php';

/**
 * @return array{0: User, 1: Warehouse}
 */
function actingAsMobileLocationsViewer(Group $group): array
{
    config(['app.mobile_app_keys' => ['mobile-test-key']]);
    Auth::shouldUse('web');
    $warehouse = createWarehouse();
    $user      = createMobileAppUser($group);
    $user->assignRole(RolesEnum::getRoleName(RolesEnum::DISPATCH_CLERK->value, $warehouse));
    SetUserAuthorisedModels::run($user);
    Sanctum::actingAs($user, ['mobile']);

    return [$user, $warehouse];
}

/**
 * @return array{0: WarehouseArea, 1: Location, 2: Location, 3: string}
 */
function createMobileLocationsFixture(Warehouse $warehouse): array
{
    $prefix = 'MB'.Str::upper(Str::random(5));
    $area   = StoreWarehouseArea::make()->action($warehouse, array_merge(WarehouseArea::factory()->definition(), ['code' => $prefix.'A']));

    $stocked = StoreLocation::make()->action($area, ['code' => $prefix.'-01', 'max_weight' => 120, 'max_volume' => 1.2]);
    $empty   = StoreLocation::make()->action($area, ['code' => $prefix.'-02']);

    $stock    = StoreStock::make()->action($warehouse->group, Stock::factory()->definition());
    $orgStock = StoreOrgStock::make()->action($warehouse->organisation, $stock);
    StoreLocationOrgStock::make()->action(
        orgStock: $orgStock,
        location: $stocked,
        modelData: ['quantity' => 30, 'type' => LocationStockTypeEnum::PICKING, 'settings' => ['min_stock' => 12, 'max_stock' => 48], 'fetched_at' => now()],
        strict: false
    );

    return [$area, $stocked->refresh(), $empty->refresh(), $prefix];
}

/**
 * @return array{0: Location, 1: OrgStock}
 */
function createMobileEmptyLocationLink(Warehouse $warehouse, Location $location): array
{
    $stock    = StoreStock::make()->action($warehouse->group, Stock::factory()->definition());
    $orgStock = StoreOrgStock::make()->action($warehouse->organisation, $stock);
    StoreLocationOrgStock::make()->action(
        orgStock: $orgStock,
        location: $location,
        modelData: ['quantity' => 0, 'type' => LocationStockTypeEnum::STORING, 'fetched_at' => now()],
        strict: false
    );
    OrgStockMovement::where('org_stock_id', $orgStock->id)->update(['date' => now()->subDays(2)]);
    OrgStock::whereKey($orgStock->id)->update(['picking_location_id' => null, 'picking_dropshipping_location_id' => null]);

    return [$location, $orgStock];
}

/**
 * @return array{0: \App\Models\SysAdmin\User, 1: Warehouse}
 */
function actingAsMobileWarehouseUser(Group $group, RolesEnum $role): array
{
    [$user, $warehouse] = actingAsMobileLocationsViewer($group);
    grantMobileWarehouseRole($user, $warehouse, $role);

    return [$user, $warehouse];
}

function grantMobileWarehouseRole(\App\Models\SysAdmin\User $user, Warehouse $warehouse, RolesEnum $role): void
{
    $user->assignRole(RolesEnum::getRoleName($role->value, $warehouse));
    SetUserAuthorisedModels::run($user);
    Sanctum::actingAs($user->refresh(), ['mobile']);
}
