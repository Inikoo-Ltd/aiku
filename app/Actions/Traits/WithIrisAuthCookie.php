<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use Illuminate\Support\Facades\Cookie;

/**
 * Marks the browser as holding a storefront session.
 *
 * Varnish reads it to pick the logged in cache variant, and the Vue stores read it to seed
 * `is_logged_in` on the first paint, which is why it is left readable by JavaScript: without it the
 * storefront paints its logged out chrome until the first-hit fetch answers, seconds after login.
 * It carries no identity, only the fact that a session exists.
 */
trait WithIrisAuthCookie
{
    protected function queueIrisAuthCookie(): void
    {
        Cookie::queue('iris_vua', true, config('session.lifetime') * 60, null, null, null, false);
    }

    protected function forgetIrisAuthCookie(): void
    {
        Cookie::queue(Cookie::forget('iris_vua'));
    }
}
