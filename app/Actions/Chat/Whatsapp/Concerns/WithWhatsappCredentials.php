<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Whatsapp\Concerns;

use App\Helpers\WhatsappSettingsKey;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;

trait WithWhatsappCredentials
{
    /**
     * The number belongs to the shop and the token to its organisation. There is no
     * config fallback: every organisation connects its own WhatsApp account, so a shared
     * default would quietly send through the wrong one.
     *
     * @return array{phone_number_id: string, access_token: string}
     */
    protected function whatsappCredentials(?Shop $shop, string $settingsKey = WhatsappSettingsKey::SALES): array
    {
        return [
            'phone_number_id' => (string) Arr::get($shop?->settings, $settingsKey.'.phone_number_id'),
            'access_token'    => (string) Arr::get($shop?->organisation?->settings, 'meta.access_key'),
        ];
    }

    /**
     * Both numbers reach the same webhook through the same Meta app, so the number the
     * payload names is the only thing saying which shop it belongs to and whether it
     * arrived on sales or support. Sales is tried first: it is the number every
     * configured shop has, and no shop may reuse one id for both.
     *
     * @return array{shop: Shop, settings_key: string}|null
     */
    protected function resolveWhatsappNumber(string $phoneNumberId): ?array
    {
        if ($phoneNumberId === '') {
            return null;
        }

        foreach ([WhatsappSettingsKey::SALES, WhatsappSettingsKey::SUPPORT] as $settingsKey) {
            $shop = Shop::whereJsonContains('settings->'.$settingsKey.'->phone_number_id', $phoneNumberId)->first();

            if ($shop) {
                return [
                    'shop'         => $shop,
                    'settings_key' => $settingsKey,
                ];
            }
        }

        return null;
    }

    /**
     * Each organisation registers its own Meta app, so these have no meaningful default.
     *
     * @return array{app_id: string, app_secret: string}
     */
    protected function metaAppCredentials(?Organisation $organisation): array
    {
        return [
            'app_id'     => (string) Arr::get($organisation?->settings, 'meta.app_id'),
            'app_secret' => (string) Arr::get($organisation?->settings, 'meta.app_secret'),
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
