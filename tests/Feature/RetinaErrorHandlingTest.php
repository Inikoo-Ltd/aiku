<?php

/*
 * Created: Tue, 22 Sep 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Web\Website\LaunchWebsite;
use App\Actions\Web\Website\UI\DetectWebsiteFromDomain;
use App\Enums\CRM\Customer\CustomerStatusEnum;
use App\Enums\Web\Website\WebsiteStateEnum;
use Illuminate\Support\Facades\Config;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;

uses()->group('ui');

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    $this->organisation = createOrganisation();
    $this->shop         = createShop()[2];
    $this->website      = createWebsite($this->shop);
    if ($this->website->state != WebsiteStateEnum::LIVE) {
        LaunchWebsite::make()->action($this->website);
    }

    $this->customer = createCustomer($this->shop);
    $this->customer->update(['status' => CustomerStatusEnum::APPROVED]);
    $this->webUser = createWebUser($this->customer);

    Config::set(
        'inertia.testing.page_paths',
        [resource_path('js/Pages/Retina')]
    );

    DetectWebsiteFromDomain::mock()
        ->shouldReceive('handle')
        ->with('localhost')
        ->andReturn($this->website);
});

test('an unknown order keeps a signed in customer inside retina', function () {
    actingAs($this->webUser, 'retina');
    app()->detectEnvironment(fn () => 'production');

    try {
        $response = $this->get('/app/orders/faq');
    } finally {
        app()->detectEnvironment(fn () => 'testing');
    }

    expect($response->headers->get('location'))->toBeNull();

    $response->assertStatus(404)
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Errors/Error'));
});
