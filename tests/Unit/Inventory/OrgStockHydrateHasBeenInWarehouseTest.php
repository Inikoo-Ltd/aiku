<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 04 Dec 2025 15:43:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

/*
 * Author: Junie (AI Assistant)
 * Created: Thu, 04 Dec 2025 12:45:00 Local Time
 */

use App\Actions\Inventory\Location\StoreLocation;
use App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock;
use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateHasBeenInWarehouse;
use App\Actions\Inventory\OrgStockMovement\StoreOrgStockMovement;
use Illuminate\Foundation\Testing\TestCase;

uses(TestCase::class);

function makeOrgStockWithWarehouse(): array
{
    $warehouse = createWarehouse();
    $organisation = $warehouse->organisation;

    // Ensure there is at least one stock and matching org stock
    [$stock] = createStocks($organisation->group);
    [$orgStock] = createOrgStocks($organisation, [$stock]);

    return [$organisation, $warehouse, $orgStock];
}

it('defaults has_been_in_warehouse to false when no movements and zero qty', function () {
    [$_org, $_wh, $orgStock] = makeOrgStockWithWarehouse();

    // Safety: set quantities to zero explicitly
    $orgStock->updateQuietly([
        'quantity_in_locations' => 0,
    ]);

    OrgStockHydrateHasBeenInWarehouse::run($orgStock);

    $orgStock->refresh();
    expect($orgStock->has_been_in_warehouse)->toBeFalse();
});

it('sets has_been_in_warehouse to true when there is at least one movement', function () {
    [$_org, $warehouse, $orgStock] = makeOrgStockWithWarehouse();

    // Create a location in that warehouse
    $location = StoreLocation::run($warehouse, [
        'code' => 'TST-LOC-'.Str::random(6),
        'name' => 'Test Location',
    ]);

    // Create a small IN movement
    StoreOrgStockMovement::run($orgStock, $location, [
        'quantity' => 1,
        'type' => \App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum::AUDIT,
        'is_received' => true,
        'is_delivered' => true,
    ]);

    OrgStockHydrateHasBeenInWarehouse::run($orgStock);

    $orgStock->refresh();
    expect($orgStock->has_been_in_warehouse)->toBeTrue();
});

it('sets has_been_in_warehouse to true when quantity_in_locations > 0', function () {
    [$_org, $warehouse, $orgStock] = makeOrgStockWithWarehouse();

    // Create a location and attach org stock with quantity
    $location = StoreLocation::run($warehouse, [
        'code' => 'TST-LOC-'.Str::random(6),
        'name' => 'Test Location 2',
    ]);

    StoreLocationOrgStock::run($orgStock, $location, [
        'quantity' => 5,
    ]);

    // Hydrate quantities which will dispatch our has_been_in_warehouse update too
    \App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateQuantityInLocations::run($orgStock);

    $orgStock->refresh();
    // Ensure the quantity path worked
    expect((float) $orgStock->quantity_in_locations)->toBeGreaterThan(0.0);

    // And the flag is true
    expect($orgStock->has_been_in_warehouse)->toBeTrue();
});
