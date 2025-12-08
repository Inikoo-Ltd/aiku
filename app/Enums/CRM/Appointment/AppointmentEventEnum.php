<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:22:44 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\CRM\Appointment;

use App\Enums\EnumHelperTrait;

enum AppointmentEventEnum: string
{
    use EnumHelperTrait;

    case TELEPHONE = 'telephone';
    case IN_PERSON = 'in_person';
    case CALLBACK = 'callback';

    public static function labels(): array
    {
        return [
            'callback' => 'Callback',
            'in_person' => 'In Person',
            'telephone' => 'Telephone',
        ];
    }
}
