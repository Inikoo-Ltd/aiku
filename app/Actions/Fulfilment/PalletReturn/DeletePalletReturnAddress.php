<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 21-01-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Fulfilment\PalletReturn;

use App\Actions\OrgAction;
use App\Models\Fulfilment\PalletReturn;
use Lorisleiva\Actions\ActionRequest;

class DeletePalletReturnAddress extends OrgAction
{
    public function handle(PalletReturn $palletreturn): PalletReturn
    {
        $address = $palletreturn->deliveryAddress;
        if ($address) {
            $palletreturn->delivery_address_id = null;
            $palletreturn->is_collection = true;
            $palletreturn->save();
        }

        return $palletreturn;
    }

    public function asController(PalletReturn $palletReturn, ActionRequest $request): void
    {
        $this->initialisationFromFulfilment($palletReturn->fulfilment, $request);
        $this->handle($palletReturn);
    }

    public function action(PalletReturn $palletreturn): PalletReturn
    {
        $this->initialisationFromShop($palletreturn->shop, []);
        return $this->handle($palletreturn);
    }
}
