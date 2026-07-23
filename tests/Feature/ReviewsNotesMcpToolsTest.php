<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\CRM\CustomerNote\StoreCustomerNote;
use App\Actions\SysAdmin\Guest\StoreGuest;
use App\Mcp\Servers\AikuServer;
use App\Mcp\Tools\CustomerNotesTool;
use App\Mcp\Tools\ShopReviewsTool;
use App\Models\SysAdmin\Guest;

use function Pest\Laravel\actingAs;

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

test('user without crm permission is denied on reviews', function () {
    $guest = StoreGuest::make()->action(
        $this->group,
        array_merge(
            Guest::factory()->definition(),
            ['positions' => []]
        )
    );

    $response = AikuServer::actingAs($guest->getUser())->tool(ShopReviewsTool::class, [
        'shop' => $this->shop->slug,
    ]);

    $response->assertHasErrors(['Shop not found or permission denied.']);
});

test('admin gets shop reviews overview', function () {
    $response = AikuServer::actingAs($this->user)->tool(ShopReviewsTool::class, [
        'shop' => $this->shop->slug,
    ]);

    $response->assertOk()->assertSee('number_reviews');
});

test('admin can read customer notes', function () {
    actingAs($this->user);

    $noteText = 'Prefers delivery on Fridays '.uniqid();
    StoreCustomerNote::make()->action($this->customer, [
        'note' => $noteText,
    ]);

    $response = AikuServer::actingAs($this->user)->tool(CustomerNotesTool::class, [
        'shop'     => $this->shop->slug,
        'customer' => $this->customer->slug,
    ]);

    $response->assertOk()->assertSee('Prefers delivery on Fridays');
});

test('notes search filters results', function () {
    $response = AikuServer::actingAs($this->user)->tool(CustomerNotesTool::class, [
        'shop'     => $this->shop->slug,
        'customer' => $this->customer->slug,
        'search'   => 'zzz-no-match-'.uniqid(),
    ]);

    $response->assertOk()->assertSee('"notes":[]');
});

test('unknown customer returns error', function () {
    $response = AikuServer::actingAs($this->user)->tool(CustomerNotesTool::class, [
        'shop'     => $this->shop->slug,
        'customer' => 'no-such-customer',
    ]);

    $response->assertHasErrors(['Customer not found.']);
});
