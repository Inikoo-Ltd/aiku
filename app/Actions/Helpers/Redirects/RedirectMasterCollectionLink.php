<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 29 Oct 2025 20:49:37 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Redirects;

use App\Actions\OrgAction;
use App\Models\Masters\MasterCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RedirectMasterCollectionLink extends OrgAction
{
    public function handle(MasterCollection $masterCollection): ?RedirectResponse
    {
        return Redirect::route(
            'grp.masters.master_shops.show.master_collections.show',
            [
                $masterCollection->masterShop->slug,
                $masterCollection->slug,
            ]
        );
    }

    public function asController(MasterCollection $masterCollection, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromGroup($masterCollection->group, $request);

        return $this->handle($masterCollection);
    }
}
