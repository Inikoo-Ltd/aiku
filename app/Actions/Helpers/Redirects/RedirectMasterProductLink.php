<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 Sept 2025 02:58:02 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Redirects;

use App\Actions\OrgAction;
use App\Models\Masters\MasterAsset;
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
