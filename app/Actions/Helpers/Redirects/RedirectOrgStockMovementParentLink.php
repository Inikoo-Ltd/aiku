<?php

/*
 * Author Louis Perez
 * Created on 27-07-2026-14h-22m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Helpers\Redirects;

use App\Actions\OrgAction;
use App\Models\GoodsIn\ReturnDeliveryNote;
use App\Models\Inventory\OrgStockMovement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RedirectOrgStockMovementParentLink extends OrgAction
{
    public function handle(OrgStockMovement $orgStockMovement): ?RedirectResponse
    {
        $parent = $orgStockMovement->parent;

        $url = 'grp.org.warehouses.show.dispatching.delivery_notes.show';
        if ($parent instanceof ReturnDeliveryNote) {
            $url =  'grp.org.warehouses.show.incoming.return_delivery_notes.show';
        }

        return Redirect::to(route($url, [
            $parent->organisation->slug,
            $parent->warehouse->slug,
            $parent->slug
        ]));
    }



    public function asController(OrgStockMovement $orgStockMovement, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromWarehouse($orgStockMovement->warehouse, $request);

        return $this->handle($orgStockMovement);
    }

}
