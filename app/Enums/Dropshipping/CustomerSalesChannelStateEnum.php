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
    case IN_PROCESS = 'in_process';
    case AUTHENTICATED = 'authenticated';
    case WITH_PORTFOLIO = 'with_portfolio';
    case CLOSED = 'closed';

    public static function labels(): array
    {
        return [
            self::CREATED->value => __('Created'),
            self::IN_PROCESS->value => __('In Process'),
            self::AUTHENTICATED->value => __('Authenticated'),
            self::WITH_PORTFOLIO->value => __('Portfolio Added'),
            self::CLOSED->value => __('Closed'),
        ];
    }
}
