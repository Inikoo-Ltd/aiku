<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Enums\Comms\Mailshot;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\EnumHelperTrait;
use App\Models\Catalogue\Shop;

enum MailshotReadyMadeLinkEnum: string
{
    use EnumHelperTrait;

    case WHATSAPP_OPT_IN = 'whatsapp_opt_in';

    public function label(): string
    {
        return match ($this) {
            self::WHATSAPP_OPT_IN => __('WhatsApp opt-in card'),
        };
    }

    public function path(): string
    {
        return match ($this) {
            self::WHATSAPP_OPT_IN => '/app/dashboard',
        };
    }

    public function anchor(): string
    {
        return match ($this) {
            self::WHATSAPP_OPT_IN => 'whatsapp-updates',
        };
    }

    public function campaign(): string
    {
        return match ($this) {
            self::WHATSAPP_OPT_IN => 'whatsapp_optin',
        };
    }

    public function isAvailableIn(Shop $shop): bool
    {
        return match ($this) {
            self::WHATSAPP_OPT_IN => $shop->type === ShopTypeEnum::B2B,
        };
    }
}
