<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 15 Aug 2023 12:15:47 Malaysia Time, Pantai Lembeng, Bali
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\SysAdmin\Guest;

use App\Enums\EnumHelperTrait;

enum GuestTypeEnum: string
{
    use EnumHelperTrait;

    case CONTRACTOR = 'contractor';
    case EXTERNAL_EMPLOYEE = 'external_employee';
    case EXTERNAL_ADMINISTRATOR = 'external_administrator';

    public static function labels(): array
    {
        return [
            'contractor' => 'Contractor',
            'external_employee' => 'External Employee',
            'external_administrator' => 'External Administrator',
        ];
    }
}
