<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 11 Aug 2024 12:16:37 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgAgent\Hydrators;

use App\Actions\Traits\Hydrators\WithHydrateOrgSuppliers;
use App\Models\Procurement\OrgAgent;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OrgAgentHydrateOrgSuppliers implements ShouldBeUnique
{
    use AsAction;
    use WithHydrateOrgSuppliers;

    public function getJobUniqueId(OrgAgent $orgAgent): string
    {
        return $orgAgent->id;
    }

    public function handle(OrgAgent $orgAgent): void
    {

        $stats = $this->getOrgSuppliersStats($orgAgent);
        $orgAgent->stats()->update($stats);
    }


}
