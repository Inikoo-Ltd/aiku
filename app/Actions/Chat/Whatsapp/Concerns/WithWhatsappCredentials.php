<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Whatsapp\Concerns;

use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;

trait WithWhatsappCredentials
{
    public const WHATSAPP_COEXISTENCE = 'coexistence';

    /**
     * The number belongs to the shop and the token to its organisation. There is no
     * config fallback: every organisation connects its own WhatsApp account, so a shared
     * default would quietly send through the wrong one. A shop connected through Embedded
     * Signup holds the business token Meta issued for its own account, which is the one that
     * can reach a WABA the organisation's system user was never given.
     *
     * @return array{phone_number_id: string, waba_id: string, access_token: string}
     */
    protected function whatsappCredentials(?Shop $shop): array
    {
        return [
            'phone_number_id' => (string) Arr::get($shop?->settings, 'whatsapp.phone_number_id'),
            'waba_id'         => (string) Arr::get($shop?->settings, 'whatsapp.waba_id'),
            'access_token'    => (string) (Arr::get($shop?->settings, 'whatsapp.access_token') ?: Arr::get($shop?->organisation?->settings, 'meta.access_key')),
        ];
    }

    /**
     * A Coexistence number stays on the WhatsApp Business app on the shop phone while the Cloud
     * API reads and writes it too. Meta gives such numbers no API calls and echoes what staff
     * type on the phone, so those paths look at this; a shop without the key is Cloud API.
     */
    protected function isWhatsappCoexistence(?Shop $shop): bool
    {
        return Arr::get($shop?->settings, 'whatsapp.mode') === self::WHATSAPP_COEXISTENCE;
    }

    /**
     * Procurement talks to suppliers from a number of its own, kept apart from the shops' so a
     * supplier never lands in a customer queue. The token is the organisation's, as for shops.
     *
     * @return array{phone_number_id: string, waba_id: string, access_token: string}
     */
    protected function procurementWhatsappCredentials(?Organisation $organisation): array
    {
        return [
            'phone_number_id' => (string) Arr::get($organisation?->settings, 'procurement.whatsapp.phone_number_id'),
            'waba_id'         => (string) Arr::get($organisation?->settings, 'procurement.whatsapp.waba_id'),
            'access_token'    => (string) Arr::get($organisation?->settings, 'meta.access_key'),
        ];
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
