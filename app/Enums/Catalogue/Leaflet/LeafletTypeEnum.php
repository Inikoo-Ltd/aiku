<?php

/*
 * Author: Andi Ferdiawan
 * Created: Wed, 08 Jul 2026 15:10:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Enums\Catalogue\Leaflet;

use App\Enums\EnumHelperTrait;

enum LeafletTypeEnum: string
{
    use EnumHelperTrait;

    case THANK_YOU_CARD = 'thank_you_card';
    case PROMOTIONAL_LEAFLET = 'promotional_leaflet';
    case CARE_INSTRUCTIONS = 'care_instructions';
    case CUSTOM_LEAFLET = 'custom_leaflet';
    case MARKETING_FLYER = 'marketing_flyer';
    case VOUCHER = 'voucher';
    case BRAND_STORY = 'brand_story';

    public static function labels(): array
    {
        return [
            'thank_you_card'       => __('Thank You Card'),
            'promotional_leaflet'  => __('Promotional Leaflet'),
            'care_instructions'    => __('Care Instructions'),
            'custom_leaflet'       => __('Custom Leaflet'),
            'marketing_flyer'      => __('Marketing Flyer'),
            'voucher'              => __('Promotional Voucher'),
            'brand_story'          => __('Brand Story Leaflet'),
        ];
    }
}
