<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 11 Aug 2024 12:07:55 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Hydrators;

use App\Models\SupplyChain\Agent;
use App\Models\SysAdmin\Group;

trait WithHydrateSuppliers
{
    public function getSuppliersStats(Group|Agent $model): array
    {
        $stats = [
            'number_suppliers' => $model->suppliers()->count(),
            'number_active_suppliers' => $model->suppliers()->where('status', 'true')->count(),
        ];
        $stats['number_archived_suppliers'] = $stats['number_suppliers'] - $stats['number_active_suppliers'];

        if ($model instanceof Group) {
            $stats['number_suppliers_in_agents'] = $model->suppliers()->whereNotNull('agent_id')->count();
            $stats['number_active_suppliers_in_agents'] = $model->suppliers()->whereNotNull('agent_id')->where('status', 'true')->count();
            $stats['number_archived_suppliers_in_agents'] = $stats['number_suppliers_in_agents'] - $stats['number_active_suppliers_in_agents'];

            $stats['number_independent_suppliers'] = $stats['number_suppliers'] - $stats['number_suppliers_in_agents'];
            $stats['number_active_independent_suppliers'] = $stats['number_active_suppliers'] - $stats['number_active_suppliers_in_agents'];
            $stats['number_archived_independent_suppliers'] = $stats['number_archived_suppliers'] - $stats['number_archived_suppliers_in_agents'];
        }

        return $stats;
    }
}
