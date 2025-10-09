<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 26 Nov 2024 21:36:24 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Billables\ShippingZone\HydrateShippingZones;
use App\Actions\Billables\ShippingZone\StoreShippingZone;
use App\Actions\Billables\ShippingZone\UpdateShippingZone;
use App\Actions\Billables\ShippingZoneSchema\HydrateShippingZoneSchemas;
use App\Actions\Billables\ShippingZoneSchema\StoreShippingZoneSchema;
use App\Actions\Billables\ShippingZoneSchema\UpdateShippingZoneSchema;
use App\Models\Billables\ShippingZone;
use App\Models\Billables\ShippingZoneSchema;

use function Pest\Laravel\actingAs;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    list(
        $this->organisation,
        $this->user,
        $this->shop
    ) = createShop();

    $this->group = $this->organisation->group;

    list(
        $this->tradeUnit,
        $this->product
    ) = createProduct($this->shop);

    $this->customer = createCustomer($this->shop);

    createWarehouse();

    Config::set(
        'inertia.testing.page_paths',
        [resource_path('js/Pages/Grp')]
    );
    actingAs($this->user);
});


test('create shipping zone schema', function () {
    $shippingZoneSchema = StoreShippingZoneSchema::make()->action($this->shop, ShippingZoneSchema::factory()->definition());
    expect($shippingZoneSchema)->toBeInstanceOf(ShippingZoneSchema::class);

    return $shippingZoneSchema;
});

test('update shipping zone schema', function ($shippingZoneSchema) {
    $shippingZoneSchema = UpdateShippingZoneSchema::make()->action($shippingZoneSchema, ShippingZoneSchema::factory()->definition());
    $this->assertModelExists($shippingZoneSchema);
})->depends('create shipping zone schema');

test('create shipping zone', function ($shippingZoneSchema) {
    $shippingZone = StoreShippingZone::make()->action($shippingZoneSchema, ShippingZone::factory()->definition());
    $this->assertModelExists($shippingZoneSchema);

    return $shippingZone;
})->depends('create shipping zone schema');

test('update shipping zone', function ($shippingZone) {
    $shippingZone = UpdateShippingZone::make()->action($shippingZone, ShippingZone::factory()->definition());
    $this->assertModelExists($shippingZone);
})->depends('create shipping zone');


test('shipping zone schemas hydrators', function () {
    $shippingZoneSchema = ShippingZoneSchema::first();
    HydrateShippingZoneSchemas::run($shippingZoneSchema);
    $this->artisan('hydrate:shipping_zone_schemas')->assertExitCode(0);
});

test('shipping zone hydrators', function () {
    $shippingZone = ShippingZone::first();
    HydrateShippingZones::run($shippingZone);
    $this->artisan('hydrate:shipping_zones')->assertExitCode(0);
});
