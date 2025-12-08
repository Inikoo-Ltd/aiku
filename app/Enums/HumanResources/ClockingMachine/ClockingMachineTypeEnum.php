<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 03 Jan 2024 16:42:33 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\HumanResources\ClockingMachine;

use App\Enums\EnumHelperTrait;

enum ClockingMachineTypeEnum: string
{
    use EnumHelperTrait;

    case BIOMETRIC = 'biometric';
    case STATIC_NFC = 'static-nfc';
    case MOBILE_APP = 'mobile-app';
    case LEGACY = 'legacy';

    public static function labels(): array
    {
        return [
            'biometric' => __('Biometric'),
            'static-nfc' => __('Static NFC'),
            'mobile-app' => __('Mobile App').' (Han)',
            'legacy' => __('Legacy'),
        ];
    }
}
