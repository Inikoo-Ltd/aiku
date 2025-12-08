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
            'website' => __('Website'),
            'phone' => __('Phone'),
            'showroom' => __('Showroom'),
            'email' => __('Email'),
            'other' => __('Other'),
            'marketplace' => __('Marketplace'),
            'social_media' => __('Social media'),
            'platform' => __('Platform'),
            'na' => __('N/A'),

        ];
    }

    public function canSeed(): bool
    {
        return match ($this) {
            SalesChannelTypeEnum::MARKETPLACE,
            SalesChannelTypeEnum::SOCIAL_MEDIA,
            SalesChannelTypeEnum::PLATFORM, => false,
            default => true,
        };
    }
}
