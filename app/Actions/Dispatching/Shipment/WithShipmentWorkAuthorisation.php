<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 Oct 2026 19:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Shipment;

use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\Shipment;
use Lorisleiva\Actions\ActionRequest;

trait WithShipmentWorkAuthorisation
{
    public function authorize(ActionRequest $request): bool
    {
        $shipment = $request->route('shipment');
        if (!$shipment instanceof Shipment) {
            return false;
        }

        $deliveryNotes = $shipment->deliveryNotes()->get();
        if ($deliveryNotes->isNotEmpty()) {
            return $deliveryNotes->every(fn (DeliveryNote $deliveryNote) => $deliveryNote->canBeShippedBy($request->user()));
        }

        $palletReturn = $shipment->palletReturns()->firstOrFail();

        return $request->user()->authTo([
            "fulfilment.$palletReturn->warehouse_id.edit",
            "org-admin.$palletReturn->organisation_id",
        ]);
    }
}
