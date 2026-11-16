<?php

/*
 * Author Louis Perez
 * Created on 13-11-2026-15h-05m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\Shipment;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;
use Symfony\Component\HttpFoundation\Response;

class DownloadMobileShipmentLabel
{
    use AsController;
    use WithMobilePickingGuards;

    public function asController(DeliveryNote $deliveryNote, Shipment $shipment, ActionRequest $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanView($deliveryNote, $user);
        $this->ensureShipmentOfNote($deliveryNote, $shipment);
        abort_unless(GetMobileDeliveryNote::hasPdfLabel($shipment), 404);

        $pdf = base64_decode($shipment->label, true);
        abort_if($pdf === false, 404);

        return response($pdf, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="label-'.$shipment->id.'.pdf"');
    }
}
