<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Tue, 14 Jul 2026 10:00:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce\Traits;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

trait WithWooCommerceAuthorizationToken
{
    public function storeWooAuthorizationToken(array $payload): string
    {
        $token = Str::random(40);
        Cache::put($this->getWooAuthorizationTokenCacheKey($token), $payload, 3600);

        return $token;
    }

    public function getWooAuthorizationTokenPayload(?string $token): ?array
    {
        if (blank($token)) {
            return null;
        }

        return Cache::get($this->getWooAuthorizationTokenCacheKey($token));
    }

    public function getWooAuthorizationTokenCacheKey(string $token): string
    {
        return 'woo-auth-callback:'.$token;
    }
}
