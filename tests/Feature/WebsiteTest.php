<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 28 Jan 2024 15:08:30 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Web\Website\StoreWebsite;
use App\Actions\Web\Website\UpdateWebsite;
use App\Enums\Web\Website\WebsiteTypeEnum;
use App\Models\Web\Webpage;
use App\Models\Web\Website;

use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeAll(function () {
    loadDB('test_base_database.dump');
});
beforeEach(function () {
    list(
        $this->organisation,
        $this->user,
        $this->shop
    )                 = createShop();
    $this->warehouse  = createWarehouse();
    $this->fulfilment = createFulfilment($this->organisation);

    Config::set(
        'inertia.testing.page_paths',
        [resource_path('js/Pages/Grp')]
    );
    actingAs($this->user);
});

test('create b2b website', function () {
    $website = StoreWebsite::make()->action(
        $this->shop,
        Website::factory()->definition()
    );

    expect($website)->toBeInstanceOf(Website::class)
        ->and($website->storefront)->toBeInstanceOf(Webpage::class)
        ->and($website->webStats->number_webpages)->toBe(4);


    return $website;
});


test('update website', function (Website $website) {
    $updateData = [
        'name' => 'Test Website Updated',
    ];

    $shop = UpdateWebsite::make()->action($website, $updateData);
    $shop->refresh();

    expect($shop->name)->toBe('Test Website Updated');
})->depends('create b2b website');


test('can show empty list of websites in fulfilment', function () {
    expect($this->fulfilment->shop->website)->toBe(null);

    $response = get(
        route(
            'grp.org.fulfilments.show.web.websites.index',
            [
                $this->organisation->slug,
                $this->fulfilment->slug
            ]
        )
    );

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Web/Websites')
            ->has('title')
            ->has('data.data', 0)
            ->where('pageHead.actions.0.route.name', 'grp.org.fulfilments.show.web.websites.create');
    });
});

test('create fulfilment website', function () {
    $website = StoreWebsite::make()->action(
        $this->fulfilment->shop,
        Website::factory()->definition()
    );


    expect($website)->toBeInstanceOf(Website::class)
        ->and($website->type)->toBe(WebsiteTypeEnum::FULFILMENT)
        ->and($website->storefront)->toBeInstanceOf(Webpage::class)
        ->and($website->webStats->number_webpages)->toBe(4);

    return $website;
});

test('can show list of websites in fulfilment', function () {
    $response = get(
        route(
            'grp.org.fulfilments.show.web.websites.index',
            [
                $this->organisation->slug,
                $this->fulfilment->slug
            ]
        )
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Web/Websites')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->where('pageHead.actions.0', null)
            ->has('data.data', 1);
    });
})->depends('create fulfilment website');

test('can show fulfilment website', function (Website $website) {
    $response = get(
        route(
            'grp.org.fulfilments.show.web.websites.show',
            [
                $this->organisation->slug,
                $this->fulfilment->slug,
                $website->slug
            ]
        )
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Web/Website')
            ->has('title')
            ->has('breadcrumbs', 3);
    });
})->depends('create fulfilment website');
