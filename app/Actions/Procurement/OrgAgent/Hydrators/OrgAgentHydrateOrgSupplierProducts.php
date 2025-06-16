<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 11 Aug 2024 14:29:19 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgAgent\Hydrators;

use App\Actions\Traits\Hydrators\WithHydrateOrgSupplierProducts;
use App\Models\Procurement\OrgAgent;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OrgAgentHydrateOrgSupplierProducts implements ShouldBeUnique
{
    use AsAction;
    use WithHydrateOrgSupplierProducts;

    public function getJobUniqueId(OrgAgent $orgAgent): string
    {
        return $orgAgent->id;
    }

    public function handle(OrgAgent $orgAgent): void
    {
        $stats = $this->getOrgSupplierProductsStats($orgAgent);
        $orgAgent->stats()->update($stats);
    }


}
