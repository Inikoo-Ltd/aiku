<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 17 Jun 2025 21:41:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers;

use App\Actions\OrgAction;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RedirectMasterProductLink extends OrgAction
{
    public function handle(MasterAsset $masterAsset): ?RedirectResponse
    {
        return Redirect::route(
            'grp.masters.master_shops.show.master_products.show',
            [
                $masterAsset->masterShop->slug,
                $masterAsset->slug,
            ]
        );
    }

    public function asController(MasterAsset $masterAsset, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromGroup($masterAsset->group, $request);

        return $this->handle($masterAsset);
    }
}
