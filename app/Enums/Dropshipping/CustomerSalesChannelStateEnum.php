<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jan 2024 15:25:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Dropshipping;

use App\Enums\EnumHelperTrait;

enum CustomerSalesChannelStateEnum: string
{
    use EnumHelperTrait;

    case CREATED = 'created';
    case AUTHENTICATED = 'authenticated';
    case CARD_SAVED = 'card_saved';
    case PORTFOLIO_ADDED = 'portfolio_added';
    case READY = 'ready';
    case NOT_READY = 'not_ready';

    public static function labels(): array
    {
        return [
            self::CREATED->value => __('Created'),
            self::AUTHENTICATED->value => __('Authenticated'),
            self::CARD_SAVED->value => __('Card Saved'),
            self::PORTFOLIO_ADDED->value => __('Portfolio Added'),
            self::READY->value => __('Ready'),
            self::NOT_READY->value => __('Not Ready'),
        ];
    }
}
