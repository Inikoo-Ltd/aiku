<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 11 Aug 2024 16:23:30 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Procurement\OrgSupplierProduct;

use App\Enums\EnumHelperTrait;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgSupplier;
use App\Models\SysAdmin\Organisation;

enum OrgSupplierProductStateEnum: string
{
    use EnumHelperTrait;

    case ACTIVE        = 'active';
    case DISCONTINUING = 'discontinuing';
    case DISCONTINUED  = 'discontinued';

    public static function labels(): array
    {
        return [
            'active'        => __('Active'),
            'discontinuing' => __('Discontinuing'),
            'discontinued'  => __('Discontinued'),
        ];
    }

    public static function count(Organisation|OrgAgent|OrgSupplier $parent): array
    {
        if ($parent instanceof Organisation) {
            $stats = $parent->procurementStats;
        } else {
            $stats = $parent->stats;
        }

        return [
            'active'        => $stats->number_org_supplier_products_state_active,
            'discontinuing' => $stats->number_org_supplier_products_state_discontinuing,
            'discontinued'  => $stats->number_org_supplier_products_state_discontinued,
        ];
    }
}
