<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:22:44 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\CRM\Appointment;

use App\Enums\EnumHelperTrait;

enum AppointmentTypeEnum: string
{
    use EnumHelperTrait;

    case LEAD = 'lead';

    public static function labels(): array
    {
        return [
            'lead' => 'Leads',
        ];
    }
}
