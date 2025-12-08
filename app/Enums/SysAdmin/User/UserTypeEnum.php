<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 12 Jul 2023 22:41:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\SysAdmin\User;

use App\Enums\EnumHelperTrait;
use App\Models\SysAdmin\Group;

enum UserTypeEnum: string
{
    use EnumHelperTrait;

    case EMPLOYEE = 'employee';
    case GUEST = 'guest';
    case SUPPLIER = 'supplier';
    case AGENT = 'agent';

    public static function labels(): array
    {
        return [
            'employee' => __('Employee'),
            'guest' => __('Guest'),
            'supplier' => __('Supplier'),
            'agent' => __('Agent'),
        ];
    }

    public static function count(Group $group): array
    {
        $stats = $group->sysadminStats;

        return [
            'employee' => $stats->number_users_type_employee,
            'guest' => $stats->number_users_type_guest,
            'supplier' => $stats->number_users_type_supplier,
            'agent' => $stats->number_users_type_agent,

        ];
    }
}
