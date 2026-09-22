<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Traits\WithIrisAuthCookie;
use Illuminate\Support\Facades\Cookie;

$storefrontSession = fn () => new class () {
    use WithIrisAuthCookie;

    public function markLoggedIn(): void
    {
        $this->queueIrisAuthCookie();
    }

    public function markLoggedOut(): void
    {
        $this->forgetIrisAuthCookie();
    }
};

$queuedAuthCookie = fn () => collect(Cookie::getQueuedCookies())
    ->first(fn ($cookie) => $cookie->getName() === 'iris_vua');

test('the storefront session flag is readable by javascript', function () use ($storefrontSession, $queuedAuthCookie) {
    $storefrontSession()->markLoggedIn();

    $cookie = $queuedAuthCookie();

    expect($cookie)->not->toBeNull()
        ->and($cookie->isHttpOnly())->toBeFalse()
        ->and($cookie->getValue())->not->toBeEmpty()
        ->and($cookie->getExpiresTime())->toBeGreaterThan(now()->timestamp);
});

test('logging out expires the storefront session flag', function () use ($storefrontSession, $queuedAuthCookie) {
    $storefrontSession()->markLoggedIn();
    $storefrontSession()->markLoggedOut();

    $cookie = $queuedAuthCookie();

    expect($cookie)->not->toBeNull()
        ->and($cookie->getValue())->toBeEmpty()
        ->and($cookie->getExpiresTime())->toBeLessThan(now()->timestamp);
});
