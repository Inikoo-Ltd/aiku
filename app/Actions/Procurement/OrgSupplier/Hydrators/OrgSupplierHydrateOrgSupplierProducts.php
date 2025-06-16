<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 11 Aug 2024 14:29:31 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgSupplier\Hydrators;

use App\Actions\Traits\Hydrators\WithHydrateOrgSupplierProducts;
use App\Models\Procurement\OrgSupplier;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OrgSupplierHydrateOrgSupplierProducts implements ShouldBeUnique
{
    use AsAction;
    use WithHydrateOrgSupplierProducts;

    public function getJobUniqueId(OrgSupplier $orgSupplier): string
    {
        return $orgSupplier->id;
    }

    public function handle(OrgSupplier $orgSupplier): void
    {
        $stats = $this->getOrgSupplierProductsStats($orgSupplier);
        $orgSupplier->stats()->update($stats);
    }

}
