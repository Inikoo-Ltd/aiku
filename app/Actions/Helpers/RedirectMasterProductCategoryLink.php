<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 17 Jun 2025 21:41:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers;

use App\Actions\OrgAction;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RedirectMasterProductCategoryLink extends OrgAction
{
    public function handle(MasterProductCategory $masterProductCategory): ?RedirectResponse
    {
        if ($masterProductCategory->type === MasterProductCategoryTypeEnum::SUB_DEPARTMENT) {
            return Redirect::route(
                'grp.masters.master_shops.show.master_sub_departments.show',
                [
                    $masterProductCategory->masterShop->slug,
                    $masterProductCategory->slug,
                ]
            );
        } elseif ($masterProductCategory->type === MasterProductCategoryTypeEnum::DEPARTMENT) {
            return Redirect::route(
                'grp.masters.master_shops.show.master_departments.show',
                [
                    $masterProductCategory->masterShop->slug,
                    $masterProductCategory->slug,
                ]
            );
        } else {
            return Redirect::route(
                'grp.masters.master_shops.show.master_families.show',
                [
                    $masterProductCategory->masterShop->slug,
                    $masterProductCategory->slug,
                ]
            );
        }
    }

    public function asController(MasterProductCategory $masterProductCategory, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromGroup($masterProductCategory->group, $request);

        return $this->handle($masterProductCategory);
    }
}
