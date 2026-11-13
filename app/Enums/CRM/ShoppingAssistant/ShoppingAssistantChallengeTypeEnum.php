<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\CRM\ShoppingAssistant;

use App\Enums\EnumHelperTrait;

enum ShoppingAssistantChallengeTypeEnum: string
{
    use EnumHelperTrait;

    case UNMET_NEED = 'unmet_need';
    case OUT_OF_STOCK = 'out_of_stock';
    case PRICE = 'price';
    case MOQ_CARTON = 'moq_carton';
    case SHIPPING = 'shipping';
    case WEBSITE = 'website';
    case OTHER = 'other';

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::UNMET_NEED->value   => __('Not in our range'),
            self::OUT_OF_STOCK->value => __('Out of stock'),
            self::PRICE->value        => __('Price'),
            self::MOQ_CARTON->value   => __('Minimum order or carton size'),
            self::SHIPPING->value     => __('Shipping or delivery'),
            self::WEBSITE->value      => __('Website or ordering problem'),
            self::OTHER->value        => __('Other'),
        ];
    }

    /**
     * What the tagging model is told each type means.
     *
     * @return array<string, string>
     */
    public static function definitions(): array
    {
        return [
            self::UNMET_NEED->value   => 'asked for a product, material or kind of item we do not sell',
            self::OUT_OF_STOCK->value => 'wanted something we sell that was out of stock or unavailable',
            self::PRICE->value        => 'found a price too high, asked for a discount or compared prices',
            self::MOQ_CARTON->value   => 'minimum order, carton or pack size too big or wrong for them',
            self::SHIPPING->value     => 'delivery cost, delivery time, countries delivered to, or a delivery problem',
            self::WEBSITE->value      => 'could not find, log in, order, pay or use the website',
            self::OTHER->value        => 'any other obstacle to buying that the customer described',
        ];
    }
}
