<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Whatsapp\Concerns;

use App\Models\Catalogue\Shop;
use Illuminate\Support\Arr;

trait WithWhatsappCredentials
{
    /**
     * The number belongs to the shop and the token to its organisation; the config
     * values only stand in for a single-tenant setup.
     *
     * @return array{phone_number_id: string, access_token: string}
     */
    protected function whatsappCredentials(?Shop $shop): array
    {
        return [
            'phone_number_id' => (string) (Arr::get($shop?->settings, 'whatsapp.phone_number_id') ?: config('meta.whatsapp.phone_number_id')),
            'access_token'    => (string) (Arr::get($shop?->organisation?->settings, 'meta.access_key') ?: config('meta.whatsapp.access_token')),
        ];
    }

    /**
     * `META_BASE_ENDPOINT` is commonly configured with a trailing slash, which would
     * otherwise build graph URLs containing a double slash.
     */
    protected function whatsappEndpoint(string $path): string
    {
        return sprintf(
            '%s/%s/%s',
            rtrim((string) config('meta.base_endpoint'), '/'),
            config('meta.whatsapp.api_version'),
            ltrim($path, '/')
        );
    }
}
