<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\CRM\ShoppingAssistant;

use App\Enums\EnumHelperTrait;

enum ShoppingAssistantHandOverReasonEnum: string
{
    use EnumHelperTrait;

    case NOT_SURE = 'not_sure';
    case ORDER_QUESTION = 'order_question';
    case ASKED_FOR_PERSON = 'asked_for_person';

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::NOT_SURE->value         => __('Not sure of the answer'),
            self::ORDER_QUESTION->value   => __('Question about their own order'),
            self::ASKED_FOR_PERSON->value => __('Customer asked for a person'),
        ];
    }
}
