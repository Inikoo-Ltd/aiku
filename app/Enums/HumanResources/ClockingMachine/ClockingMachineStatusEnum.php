<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 03 Jan 2024 16:42:33 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\HumanResources\ClockingMachine;

use App\Enums\EnumHelperTrait;

enum ClockingMachineStatusEnum: string
{
    use EnumHelperTrait;

    case DISCONNECTED = 'disconnected';
    case CONNECTED = 'connected';
    case DECOMMISSIONED = 'decommissioned';

    public static function labels(): array
    {
        return [
            'disconnected' => __('Disconnected'),
            'connected' => __('Connected'),
            'decommissioned' => __('Decommissioned'),
        ];
    }
}
