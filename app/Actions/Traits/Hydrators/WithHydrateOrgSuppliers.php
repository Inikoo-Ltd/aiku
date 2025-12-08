<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 11 Aug 2024 12:12:15 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Hydrators;

use App\Models\Procurement\OrgAgent;
use App\Models\SysAdmin\Organisation;

trait WithHydrateOrgSuppliers
{
    public function getOrgSuppliersStats(Organisation|OrgAgent $model): array
    {

        $stats = [
            'number_org_suppliers' => $model->orgSuppliers()->count(),
            'number_active_org_suppliers' => $model->orgSuppliers()->where('status', 'true')->count(),
        ];
        $stats['number_archived_org_suppliers'] = $stats['number_org_suppliers'] - $stats['number_active_org_suppliers'];

        if ($model instanceof Organisation) {
            $stats['number_org_suppliers_in_agents'] = $model->orgSuppliers()->whereNotNull('org_agent_id')->count();
            $stats['number_active_org_suppliers_in_agents'] = $model->orgSuppliers()->whereNotNull('org_agent_id')->where('status', 'true')->count();
            $stats['number_archived_org_suppliers_in_agents'] = $stats['number_org_suppliers_in_agents'] - $stats['number_active_org_suppliers_in_agents'];

            $stats['number_independent_org_suppliers'] = $stats['number_org_suppliers'] - $stats['number_org_suppliers_in_agents'];
            $stats['number_active_independent_org_suppliers'] = $stats['number_active_org_suppliers'] - $stats['number_active_org_suppliers_in_agents'];
            $stats['number_archived_independent_org_suppliers'] = $stats['number_archived_org_suppliers'] - $stats['number_archived_org_suppliers_in_agents'];
        }

        return $stats;

    }
}
