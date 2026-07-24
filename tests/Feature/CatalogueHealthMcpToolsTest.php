<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Mcp\Servers\AikuServer;
use App\Mcp\Tools\OffersOverviewTool;
use App\Mcp\Tools\FamilySalesTool;
use App\Mcp\Tools\ProductsWithoutImagesTool;
use App\Models\SysAdmin\Guest;
use App\Actions\SysAdmin\Guest\StoreGuest;

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
    $this->customer = createCustomer($this->shop);

    app()->instance('group', $this->group);
    setPermissionsTeamId($this->group->id);
});

test('user without products permission is denied on ProductsWithoutImagesTool', function () {
    $guest = StoreGuest::make()->action(
        $this->group,
        array_merge(
            Guest::factory()->definition(),
            ['positions' => []]
        )
    );

    $response = AikuServer::actingAs($guest->getUser())->tool(ProductsWithoutImagesTool::class, [
        'shop' => $this->shop->slug,
    ]);

    $response->assertHasErrors(['Shop not found or permission denied.']);
});

test('admin gets products without images', function () {
    [$orgStocks, $product] = createProduct($this->shop);

    $product->update(['image_id' => null]);

    $response = AikuServer::actingAs($this->user)->tool(ProductsWithoutImagesTool::class, [
        'shop' => $this->shop->slug,
    ]);

    $response->assertOk()
        ->assertSee('"total_without_images"');
});

test('FamilySalesTool returns families over date range', function () {
    createProduct($this->shop);

    $response = AikuServer::actingAs($this->user)->tool(FamilySalesTool::class, [
        'shop' => $this->shop->slug,
        'from' => '2026-01-01',
        'to'   => '2026-12-31',
    ]);

    $response->assertOk()
        ->assertSee('"families"');
});

test('FamilySalesTool with invalid date range fails validation', function () {
    $response = AikuServer::actingAs($this->user)->tool(FamilySalesTool::class, [
        'shop' => $this->shop->slug,
        'from' => '2026-12-31',
        'to'   => '2026-01-01',
    ]);

    $response->assertHasErrors();
});

test('admin gets offers overview with status all', function () {
    createProduct($this->shop);

    $response = AikuServer::actingAs($this->user)->tool(OffersOverviewTool::class, [
        'shop' => $this->shop->slug,
        'status' => 'all',
    ]);

    $response->assertOk()
        ->assertSee('"offers"');
});
