<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Feb 2023 16:47:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatch\Shipment\ApiCalls;

use App\Actions\Dispatch\Shipment\Hydrators\ShipmentHydrateUniversalSearch;
use App\Models\Dispatch\DeliveryNote;
use App\Models\Dispatch\Shipment;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreShipment
{
    use AsAction;
    use WithAttributes;

    public function handle(DeliveryNote $deliveryNote,Shipper $shipper, array $modelData): Shipment
    {
        $type = 'apiCall';
        $modelData['delivery_note_id'] = $deliveryNote->id;
        $shipment=match($shipper->api_shipper) {
            'apc-gb'=> CallApcgbShipperApi::run($deliveryNote,$shipper,$type),
            'dpd-gb'=> CallDpdgbShipperApi::run($deliveryNote,$shipper,$type),
            default => $shipper->shipments()->create($modelData),
        };
        ShipmentHydrateUniversalSearch::dispatch($shipment);

        return $shipment;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'unique:tenant.shipments', 'between:2,9', 'alpha']
        ];
    }

    public function action(DeliveryNote $deliveryNote, array $objectData): Shipment
    {
        $this->setRawAttributes($objectData);
        $validatedData = $this->validateAttributes();

        return $this->handle($deliveryNote, $validatedData);
    }
}
