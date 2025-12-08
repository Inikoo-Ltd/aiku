<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 10 Jan 2024 13:08:02 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Helpers\Snapshot;

use App\Enums\EnumHelperTrait;

enum SnapshotStateEnum: string
{
    use EnumHelperTrait;

    case UNPUBLISHED = 'unpublished';
    case LIVE = 'live';
    case HISTORIC = 'historic';

    public static function labels(): array
    {
        return [
            'unpublished' => __('Unpublished'),
            'live' => __('Live'),
            'historic' => __('Historic'),
        ];
    }
}
