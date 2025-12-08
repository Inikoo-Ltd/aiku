<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Apr 2024 09:46:35 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\HumanResources\TimeTracker;

use App\Enums\EnumHelperTrait;

enum TimeTrackerStatusEnum: string
{
    use EnumHelperTrait;

    case OPEN = 'open';

    case CLOSED = 'closed';

    case ERROR = 'error';

    public function labels(): array
    {
        return [
            'open' => __('Open'),
            'closed' => __('Closed'),
            'error' => __('Error'),
        ];
    }

    public function statusIcon(): array
    {
        return [
            'open' => [
                'tooltip' => __('Open'),
                'icon' => 'fal fa-door-open',
                'class' => 'text-green-500',
            ],
            'closed' => [
                'tooltip' => __('Closed'),
                'icon' => 'fal fa-door-closed',
                'class' => 'text-red-500',
            ],
            'error' => [
                'tooltip' => __('Error'),
                'icon' => 'fal fa-times',
                'class' => 'text-red-500',
            ],
        ];
    }
}
