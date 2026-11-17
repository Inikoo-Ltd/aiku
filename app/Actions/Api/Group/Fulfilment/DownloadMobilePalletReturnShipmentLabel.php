<?php

/*
 * Author Louis Perez
 * Created on 17-11-2026-10h-05m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Fulfilment;

use App\Actions\Api\Group\GoodsOut\GetMobileDeliveryNote;
use App\Models\Dispatching\Shipment;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;
use Symfony\Component\HttpFoundation\Response;

class DownloadMobilePalletReturnShipmentLabel
{
    use AsController;
    use WithMobilePalletReturns;

    public function asController(Warehouse $warehouse, PalletReturn $palletReturn, Shipment $shipment, ActionRequest $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanViewPalletReturn($user, $warehouse, $palletReturn);
        $this->ensureShipmentOfReturn($palletReturn, $shipment);
        abort_unless(GetMobileDeliveryNote::hasPdfLabel($shipment), 404);

        $pdf = base64_decode($shipment->label, true);
        abort_if($pdf === false, 404);

        return response($pdf, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="label-'.$shipment->id.'.pdf"');
    }
}
