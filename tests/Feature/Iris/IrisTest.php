<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 28 Jan 2024 15:08:30 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Web\Website\UI\DetectWebsiteFromDomain;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses()->group('ui');

beforeAll(function () {
    loadDB();
});
beforeEach(function () {
    list(
        $this->organisation,
        $this->user,
        $this->shop
    )                        = createShop();
    $this->warehouse         = createWarehouse();
    $this->fulfilment        = createFulfilment($this->organisation);
    $this->fulfilmentWebsite = createWebsite($this->fulfilment->shop);


    Config::set(
        'inertia.testing.page_paths',
        [resource_path('js/Pages/Grp')]
    );
    actingAs($this->user);
});



test('test iris fulfilment website not launched', function () {

    $website = $this->fulfilmentWebsite;

    Config::set('inertia.testing.page_paths', [resource_path('js/Pages/Iris')]);
    DetectWebsiteFromDomain::shouldRun()->with('localhost')->andReturn($website);


    $response = get(
        route(
            'iris.home'
        )
    );
    $response->assertStatus(307);
    $response->assertRedirect('disclosure/under-construction');

    $redirect = $this->followRedirects($response);
    $redirect->assertStatus(200);


    $redirect->assertInertia(function (AssertableInertia $page) {
        $page->component('Disclosure/UnderConstruction');
    });
})->todo('missing route');
