<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\CRM\ShoppingAssistant;

use App\Enums\EnumHelperTrait;

enum ShoppingAssistantAnswerKindEnum: string
{
    use EnumHelperTrait;

    case PRODUCTS = 'products';
    case SHOP_ANSWER = 'shop_answer';
    case FAMILY_DESCRIPTION = 'family_description';
    case TOOL = 'tool';
    case QUESTION = 'question';
    case OFFER_PERSON = 'offer_person';
    case OFF_TOPIC = 'off_topic';
    case OTHER = 'other';

    /**
     * What the model may declare in present_products. The other kinds are decided by code from what
     * the turn really used.
     *
     * @return array<int, string>
     */
    public static function declarable(): array
    {
        return [self::PRODUCTS->value, self::SHOP_ANSWER->value, self::QUESTION->value, self::OFFER_PERSON->value, self::OFF_TOPIC->value];
    }

    /**
     * Answers to a general question about the shop rather than a request for products.
     *
     * @return array<int, string>
     */
    public static function general(): array
    {
        return [self::SHOP_ANSWER->value, self::FAMILY_DESCRIPTION->value, self::TOOL->value, self::OFFER_PERSON->value];
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::PRODUCTS->value           => __('Products'),
            self::SHOP_ANSWER->value        => __('Shop knowledge'),
            self::FAMILY_DESCRIPTION->value => __('Family description'),
            self::TOOL->value               => __('Our data (dispatch, shipping, prices, restock)'),
            self::QUESTION->value           => __('Asked the customer a question'),
            self::OFFER_PERSON->value       => __('Offered a person'),
            self::OFF_TOPIC->value          => __('Declined'),
            self::OTHER->value              => __('Other'),
        ];
    }
}
