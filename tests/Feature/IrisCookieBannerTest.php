<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Mon, 23 Nov 2026 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

use App\Actions\Web\Website\UI\DetectWebsiteFromDomain;
use App\Actions\Web\Website\UpdateWebsite;

beforeEach(function () {
    loadDB();
    $this->organisation = createOrganisation();
    $this->shop         = createShop($this->organisation)[2];
    $this->website      = createWebsite($this->shop);
    $this->website->update(['status' => true]);

    DetectWebsiteFromDomain::mock()
        ->shouldReceive('parseDomain')
        ->andReturn($this->website->domain);
});

test('own cookie banner and its Consent Mode defaults only show on websites that turned it on', function () {
    $homepage = fn () => $this->withoutVite()->followingRedirects()->get('http://'.$this->website->domain.'/')->assertOk();

    $homepage()
        ->assertDontSee('window.gtag("consent", "default"', false)
        ->assertSee('"own_cookie_banner":false', false);

    UpdateWebsite::make()->action($this->website, ['own_cookie_banner' => true]);
    expect($this->website->refresh()->settings['own_cookie_banner'])->toBeTrue();

    $homepage()
        ->assertSee('window.gtag("consent", "default"', false)
        ->assertSee('"own_cookie_banner":true', false);

    expect(fn () => UpdateWebsite::make()->action($this->website, ['own_cookie_banner' => 'maybe']))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('the customer area (/app login and register) gets the same Consent Mode defaults when the setting is on', function () {
    $login = fn () => $this->withoutVite()->followingRedirects()->get('http://'.$this->website->domain.'/app/login')->assertOk();

    $login()->assertDontSee('window.gtag("consent", "default"', false);

    UpdateWebsite::make()->action($this->website, ['own_cookie_banner' => true]);

    $login()
        ->assertSee('window.gtag("consent", "default"', false)
        ->assertSee('own_cookie_banner', false);
});
