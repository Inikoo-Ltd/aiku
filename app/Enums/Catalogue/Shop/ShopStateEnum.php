<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 13 Mar 2023 00:47:01 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Catalogue\Shop;

use App\Enums\EnumHelperTrait;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;

enum ShopStateEnum: string
{
    use EnumHelperTrait;

    case IN_PROCESS   = 'in_process';
    case OPEN         = 'open';
    case CLOSING_DOWN = 'closing_down';
    case CLOSED       = 'closed';

    public static function labels(): array
    {
        return [
            'in_process'      => __('In Process'),
            'open'            => __('Open'),
            'closing_down'    => __('Closing Down'),
            'closed'          => __('Closed')
        ];
    }

    public static function count(Organisation|Group $parent): array
    {
        $stats = $parent->catalogueStats;

        return [
            'in_process'      => $stats->number_shops_state_in_process,
            'open'            => $stats->number_shops_state_open,
            'closing_down'    => $stats->number_shops_state_closing_down,
            'closed'          => $stats->number_shops_state_closed
        ];
    }
}
