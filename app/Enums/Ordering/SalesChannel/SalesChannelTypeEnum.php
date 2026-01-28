<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 18:31:21 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Ordering\SalesChannel;

use App\Enums\EnumHelperTrait;

enum SalesChannelTypeEnum: string
{
    use EnumHelperTrait;

    case WEBSITE = 'website';
    case PHONE = 'phone';
    case SHOWROOM = 'showroom';
    case EMAIL = 'email';
    case MARKETPLACE = 'marketplace';
    case SOCIAL_MEDIA = 'social_media';
    case PLATFORM = 'platform'; // e.g. Shopify, Magento, WooCommerce
    case OTHER = 'other';
    case NA = 'na';

    public static function labels(): array
    {
        return [
            'website'      => __('Website'),
            'phone'        => __('Phone'),
            'showroom'     => __('Showroom'),
            'email'        => __('Email'),
            'other'        => __('Other'),
            'marketplace'  => __('Marketplace'),
            'social_media' => __('Social media'),
            'platform'     => __('Platform'),
            'na'           => __('N/A'),

        ];
    }

    public function canSeed(): bool
    {
        return match ($this) {
            SalesChannelTypeEnum::MARKETPLACE,
            SalesChannelTypeEnum::SOCIAL_MEDIA,
            SalesChannelTypeEnum::PLATFORM,
            => false,
            default => true,
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::WEBSITE      => 'fal fa-globe',
            self::PHONE        => 'fal fa-phone',
            self::SHOWROOM     => 'fal fa-store-alt',
            self::EMAIL        => 'fal fa-envelope',
            self::OTHER        => 'fal fa-question-circle',
            self::MARKETPLACE  => 'fal fa-shopping-cart',
            self::SOCIAL_MEDIA => 'fal fa-social-media',
            self::PLATFORM     => 'fal fa-cogs',
            self::NA           => 'fal fa-question-circle',
        };
    }

}
