<?php

/*
 * author Louis Perez
 * created on 05-06-2026-15h-32m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Masters\MasterProductCategory\Hydrators;

use App\Actions\Masters\MasterProductCategory\CascadeMasterProductCategoryFaqToChildren;
use App\Actions\OrgAction;
use App\Models\Masters\MasterProductCategory;

class MasterProductCategoryHydrateFAQ extends OrgAction
{
    public function handle(MasterProductCategory $masterProductCategory): void
    {
        CascadeMasterProductCategoryFaqToChildren::run($masterProductCategory);
    }

    public function action(MasterProductCategory $masterProductCategory): void
    {
        $this->initialisationFromGroup($masterProductCategory->group, []);

        $this->handle($masterProductCategory);
    }
}
