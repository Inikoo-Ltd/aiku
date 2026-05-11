<?php

/*
 * author Louis Perez
 * created on 11-05-2026-15h-51m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Helpers\Redirects;

use App\Actions\OrgAction;
use App\Models\GoodsIn\ReturnDeliveryNote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RedirectReturnDeliveryNotesLink extends OrgAction
{
    public function handle(ReturnDeliveryNote $returnDeliveryNote): ?RedirectResponse
    {
        $url = route('grp.org.warehouses.show.incoming.return-delivery-notes.show', [
            $returnDeliveryNote->organisation->slug,
            $returnDeliveryNote->warehouse->slug,
            $returnDeliveryNote->slug
        ]);
        return Redirect::to($url);
    }



    public function asController(ReturnDeliveryNote $returnDeliveryNote, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromShop($returnDeliveryNote->shop, $request);

        return $this->handle($returnDeliveryNote);
    }

}
