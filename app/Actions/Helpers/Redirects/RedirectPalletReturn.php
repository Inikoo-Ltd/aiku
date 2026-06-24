<?php

/*
 * author Louis Perez
 * created on 21-05-2026-13h-02m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Helpers\Redirects;

use App\Actions\GrpAction;
use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\Models\Fulfilment\PalletReturn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RedirectPalletReturn extends GrpAction
{
    public function handle(PalletReturn $palletReturn): ?RedirectResponse
    {
        $url = route('grp.org.fulfilments.show.crm.customers.show.pallet_returns.with_stored_items.show', [
            'organisation'          => $palletReturn->organisation->slug,
            'fulfilment'            => $palletReturn->fulfilment->slug,
            'fulfilmentCustomer'    => $palletReturn->fulfilmentCustomer->slug,
            'palletReturn'          => $palletReturn->slug
        ]);

        if ($palletReturn->type == PalletReturnTypeEnum::PALLET) {
            $url = route('grp.org.fulfilments.show.crm.customers.show.pallet_returns.show', [
                'organisation'          => $palletReturn->organisation->slug,
                'fulfilment'            => $palletReturn->fulfilment->slug,
                'fulfilmentCustomer'    => $palletReturn->fulfilmentCustomer->slug,
                'palletReturn'          => $palletReturn->slug
            ]);
        }

        return Redirect::to($url);
    }

    public function asController(PalletReturn $palletReturn, ActionRequest $request): RedirectResponse
    {
        $this->initialisation(group(), $request);

        return $this->handle($palletReturn);
    }

}
