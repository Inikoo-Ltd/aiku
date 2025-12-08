<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 10 Apr 2024 18:10:11 Central Indonesia Time, Sanur , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Accounting\PaymentServiceProvider;

use App\Enums\EnumHelperTrait;

enum PaymentServiceProviderStateEnum: string
{
    use EnumHelperTrait;
    case LEGACY = 'legacy';
    case ACTIVE = 'active';

    public static function labels(): array
    {
        return [
            'legacy' => __('Legacy'),
            'active' => __('Active'),
        ];
    }
}
