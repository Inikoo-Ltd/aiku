<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Feb 2023 16:47:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Shipment;

use App\Actions\Dispatching\Shipment\ApiCalls\ApcGbCallShipperApi;
use App\Actions\Dispatching\Shipment\ApiCalls\CallApiItdShipping;
use App\Actions\Dispatching\Shipment\ApiCalls\DpdGbCallShipperApi;
use App\Actions\Dispatching\Shipment\ApiCalls\DpdSkCallShipperApi;
use App\Actions\Dispatching\Shipment\ApiCalls\PostmenCallShipperApi;
use App\Actions\Dispatching\Shipment\ApiCalls\WhistlGbCallShipperApi;
use App\Actions\Dispatching\Shipment\Hydrators\ShipmentHydrateUniversalSearch;
use App\Actions\OrgAction;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\Shipment;
use App\Models\Dispatching\Shipper;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreShipment extends OrgAction
{
    use AsAction;
    use WithAttributes;

    public function handle(DeliveryNote $deliveryNote, Shipper $shipper, array $modelData): Shipment
    {
        data_set($modelData, 'group_id', $deliveryNote->group_id);
        data_set($modelData, 'organisation_id', $deliveryNote->organisation_id);
        data_set($modelData, 'shop_id', $deliveryNote->shop_id);


        $modelData = array_merge(
            $modelData,
            [
                'group_id' => $deliveryNote->group_id,
                'organisation_id' => $deliveryNote->organisation_id,
                'shop_id' => $deliveryNote->shop_id,
                'customer_id' => $deliveryNote->customer_id,
            ]
        );

        /** @var Shipment $shipment */
        $shipment = match ($shipper->api_shipper) {
            'apc-gb' => ApcGbCallShipperApi::run($deliveryNote, $shipper),
            'dpd-gb' => DpdGbCallShipperApi::run($deliveryNote, $shipper),
            'dpd-sk' => DpdSkCallShipperApi::run($deliveryNote, $shipper),
            'pst-mn' => PostmenCallShipperApi::run($deliveryNote, $shipper),
            'whl-gb' => WhistlGbCallShipperApi::run($deliveryNote, $shipper),
            'itd' => CallApiItdShipping::run($deliveryNote, $shipper),
            default => $shipper->shipments()->create($modelData),
        };

        if ($parent instanceof DeliveryNote) {
            $shipment->deliveryNotes()->attach($parent->id);
        } else {

        }


        $shipment->refresh();



        ShipmentHydrateUniversalSearch::dispatch($shipment);

        return $shipment;
    }

    public function rules(): array
    {
        return [
            'tracking' => ['sometimes', 'max:64', 'string']
        ];
    }

    public function action(DeliveryNote $deliveryNote, Shipper $shipper, array $modelData): Shipment
    {
        $this->initialisation($deliveryNote->organisation, $modelData);

        return $this->handle($deliveryNote, $shipper, $this->validatedData);
    }
}
