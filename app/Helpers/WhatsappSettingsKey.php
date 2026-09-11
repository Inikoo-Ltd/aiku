<?php

/*
 * Author: Eka Yudinata <ekayudinata@gmail.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Helpers;

use App\Models\Catalogue\Shop;
use Illuminate\Support\Arr;

/**
 * A shop may connect a second WhatsApp number reserved for support, kept in its own
 * settings block. Both numbers share one channel, so the number a message arrived on is
 * what says which block to read, and a reply has to leave from the number the customer
 * wrote to. Sales stays the default everywhere.
 */
class WhatsappSettingsKey
{
    public const string SALES = 'whatsapp';

    public const string SUPPORT = 'whatsapp_support';

    /**
     * Anything that is not the shop's support number is sales, so a thread stored before
     * the support number existed, or one whose number id was never recorded, keeps
     * replying from the number it always did.
     */
    public static function forNumber(?Shop $shop, ?string $phoneNumberId): string
    {
        if (blank($phoneNumberId)) {
            return self::SALES;
        }

        $supportNumberId = (string) Arr::get($shop?->settings, self::SUPPORT.'.phone_number_id');

        return $supportNumberId !== '' && $supportNumberId === $phoneNumberId
            ? self::SUPPORT
            : self::SALES;
    }
}
