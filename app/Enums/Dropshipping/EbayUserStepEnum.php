<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jan 2024 15:25:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Dropshipping;

use App\Enums\EnumHelperTrait;

enum EbayUserStepEnum: string
{
    use EnumHelperTrait;

    case NAME = 'name';
    case MARKETPLACE = 'marketplace';
    case AUTH = 'auth';
    case COMPLETED = 'completed';

    public static function labels(): array
    {
        return [
            self::NAME->value => __('Name'),
            self::MARKETPLACE->value => __('Marketplace'),
            self::AUTH->value => __('Auth'),
            self::COMPLETED->value => __('Completed'),
        ];
    }
}
