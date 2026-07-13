<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 12 Jul 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Retina\Dropshipping\Orders\Transaction\StoreRetinaEcomBasketTransaction;
use App\Actions\Web\Website\LaunchWebsite;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Web\Website\WebsiteStateEnum;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    loadDB();

    config(['session.driver' => 'file']);

    [$organisation, $user, $shop] = createShop();
    expect($shop->type)->toBe(ShopTypeEnum::B2B);
    $this->shop = $shop;

    $this->website = createWebsite($shop);
    $this->website->update([
        'domain' => '127.0.0.1',
        'status' => true,
    ]);
    if ($this->website->state !== WebsiteStateEnum::LIVE) {
        $this->website = LaunchWebsite::make()->action($this->website);
    }

    DB::table('webpages')->update(['canonical_url' => null]);

    [, $product] = createProduct($shop);
    $this->product = $product;

    $this->customer = createCustomer($shop);
    $this->webUser  = createWebUser($this->customer);
});

test('smoke: iris storefront home has no javascript errors', function () {
    visit('/')->assertNoJavaScriptErrors();
});

test('smoke: guest pages have no javascript errors', function () {
    $pages = visit([
        '/app/login',
        '/app/register',
        '/app/registration-form',
        '/app/reset-password-send',
    ]);

    $pages->assertNoJavaScriptErrors();
});

test('guest can see registration form', function () {
    visit('/app/registration-form')
        ->assertNoJavaScriptErrors()
        ->fill('contact_name', 'Test Person')
        ->fill('email', 'new-customer@example.com');
});

test('web user can log in', function () {
    visit('/app/login')
        ->assertSee('Sign in')
        ->fill('input[placeholder=username]', $this->webUser->username)
        ->fill('input[type=password]', 'test')
        ->click('Sign in')
        ->assertSee('Customer Information')
        ->assertPathBeginsWith('/app/dashboard')
        ->assertNoJavaScriptErrors();
});

test('web user can log out', function () {
    $this->actingAs($this->webUser, 'retina');

    $page = visit('/app/dashboard');
    $page->assertSee('Customer Information');

    // ponytail: bare test website has no header web block, so no logout button exists in
    // the UI; fire the same POST the topbar logout button makes. Click the real button
    // once the seed publishes a topbar.
    $page->script(<<<'JS'
        fetch('/app/logout', {
            method: 'POST',
            headers: {
                'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)[1]),
                'Accept': 'application/json',
            },
        })
    JS);

    $page->wait(1)
        ->navigate('/app/dashboard')
        ->assertPathBeginsWith('/app/login')
        ->assertSee('Sign in');
});

test('guest can request password reset link', function () {
    visit('/app/reset-password-send')
        ->assertSee('Reset Password')
        ->fill('input[type=email]', $this->webUser->email)
        ->click('Send Reset Link')
        ->assertSee('Reset link sent');
});

test('smoke: authenticated pages have no javascript errors', function () {
    $this->actingAs($this->webUser, 'retina');

    $pages = visit([
        '/app/dashboard',
        '/app/basket',
        '/app/orders',
    ]);

    $pages->assertNoJavaScriptErrors();
});

test('web user can order a product from the basket', function () {
    StoreRetinaEcomBasketTransaction::make()->handle(
        $this->customer,
        $this->product,
        ['quantity' => 3]
    );

    $this->actingAs($this->webUser, 'retina');

    visit('/app/basket')
        ->assertNoJavaScriptErrors()
        ->assertSee($this->product->name)
        ->click('Go to checkout')
        ->assertPathContains('/app/checkout')
        ->assertNoJavaScriptErrors()
        ->assertSee($this->product->name);
});
