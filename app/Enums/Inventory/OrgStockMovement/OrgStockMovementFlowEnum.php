<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Mar 2023 04:01:51 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Inventory\OrgStockMovement;

use App\Enums\EnumHelperTrait;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;

enum OrgStockMovementFlowEnum: string
{
    use EnumHelperTrait;


    case IN = 'in';
    case OUT = 'out';
    case AUDIT = 'audit';

    public static function labels(): array
    {
        return [
            'in'    => __('In'),
            'out'   => __('Out'),
            'audit' => __('Audit')
        ];
    }

    public static function count(Group|Organisation $parent): array
    {
        $stats = $parent->inventoryStats;

        return [
            'in'    => $stats->number_org_stock_movements_flow_in,
            'out'   => $stats->number_org_stock_movements_flow_out,
            'audit' => $stats->number_org_stock_movements_flow_audit
        ];
    }

}
