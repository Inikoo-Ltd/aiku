<?php

/*
 * author Louis Perez
 * created on 28-04-2026-10h-09m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Dispatching\DeliveryNote\Return;

use App\Actions\OrgAction;
use App\Models\Dispatching\DeliveryNote;
use Inertia\Inertia;
use Lorisleiva\Actions\ActionRequest;

class ShowReturnDeliveryNote extends OrgAction
{
    public function htmlResponse(DeliveryNote $deliveryNote)
    {
        return Inertia::render('', [
            
        ]);
    }

    public function asController(DeliveryNote $deliveryNote, ActionRequest $request)
    {
        $this->initialisationFromShop($deliveryNote->shop, $request);

        return $deliveryNote;
    }
}
