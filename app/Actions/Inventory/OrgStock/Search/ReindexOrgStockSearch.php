<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 10 Apr 2026 15:51:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\Search;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Inventory\OrgStock;

class ReindexOrgStockSearch
{
    use WithHydrateCommand;

    public string $commandSignature = 'search:org_stocks {organisations?*} {--s|slugs=}';

    public function __construct()
    {
        $this->model = OrgStock::class;
    }


    public function handle(OrgStock $orgStock): void
    {
    }


}
