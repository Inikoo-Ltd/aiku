<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 21 Jul 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\UI\HumanResources;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum ClockingMachinesTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case CONNECTED    = 'connected';
    case DISCONNECTED = 'disconnected';

    public function blueprint(): array
    {
        return match ($this) {
            ClockingMachinesTabsEnum::CONNECTED => [
                'title' => __('Connected'),
                'icon'  => 'fal fa-plug',
            ],
            ClockingMachinesTabsEnum::DISCONNECTED => [
                'title' => __('Disconnected'),
                'icon'  => 'fal fa-unlink',
                'type'  => 'icon',
                'align' => 'right',
            ],
        };
    }
}
