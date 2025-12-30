<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Dec 2025 13:36:57 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Enums\HumanResources\Holiday;

use App\Enums\EnumHelperTrait;

enum HolidayTypeEnum: string
{
    use EnumHelperTrait;

    case PUBLIC = 'public';
    case COMPANY = 'company';


    public static function labels(): array
    {
        return [
            'public' => __('Public'),
            'company' => __('Company'),
        ];
    }
}
