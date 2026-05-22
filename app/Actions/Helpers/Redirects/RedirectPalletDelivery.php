<?php

/*
 * author Louis Perez
 * created on 21-05-2026-13h-02m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Helpers\Redirects;

use App\Actions\GrpAction;
use App\Models\Fulfilment\PalletDelivery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RedirectPalletDelivery extends GrpAction
{
    public function handle(PalletDelivery $palletDelivery): ?RedirectResponse
    {
        $url = route('grp.org.fulfilments.show.crm.customers.show.pallet_deliveries.show', [
            'organisation'          => $palletDelivery->organisation->slug,
            'fulfilment'            => $palletDelivery->fulfilment->slug,
            'fulfilmentCustomer'    => $palletDelivery->fulfilmentCustomer->slug,
            'palletDelivery'        => $palletDelivery->slug
        ]);

        return Redirect::to($url);
    }

    public function asController(PalletDelivery $palletDelivery, ActionRequest $request): RedirectResponse
    {
        $this->initialisation(group(), $request);

        return $this->handle($palletDelivery);
    }

}
