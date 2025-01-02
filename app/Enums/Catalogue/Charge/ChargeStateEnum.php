<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 16 Apr 2024 12:45:19 Malaysia Time, Kuala Lumpur , Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Catalogue\Charge;

use App\Enums\Catalogue\IsBillableState;
use App\Enums\EnumHelperTrait;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;

enum ChargeStateEnum: string
{
    use EnumHelperTrait;
    use IsBillableState;

    case IN_PROCESS   = 'in_process';
    case ACTIVE       = 'active';
    case DISCONTINUED = 'discontinued';


    public static function count(Shop|Organisation|Group $parent): array
    {
        $stats = match (class_basename($parent)) {
            'Shop' => $parent->stats,
            'Organisation', 'Group' => $parent->catalogueStats,
        };


        return [
            'in_process'   => $stats->number_charges_state_in_process,
            'active'       => $stats->number_charges_state_active,
            'discontinued' => $stats->number_charges_state_discontinued
        ];
    }

}
