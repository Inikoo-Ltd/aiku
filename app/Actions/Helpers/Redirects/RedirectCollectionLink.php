<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 05 May 2026 01:03:23 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Redirects;

use App\Actions\OrgAction;
use App\Models\Catalogue\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RedirectCollectionLink extends OrgAction
{
    public function handle(Collection $collection): ?RedirectResponse
    {
        $organisation = $collection->organisation;
        $shop         = $collection->shop;


        $route = [
            'name'       => 'grp.org.shops.show.catalogue.collections.show',
            'parameters' => [
                'organisation' => $organisation->slug,
                'shop'         => $shop->slug,
                'collection'   => $collection->slug
            ]
        ];

        return Redirect::route($route['name'], $route['parameters']);
    }


    public function asController(Collection $collection, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromShop($collection->shop, $request);

        return $this->handle($collection);
    }

}
