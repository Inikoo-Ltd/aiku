<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Feb 2023 16:47:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Shipment;

use App\Actions\Dispatching\Shipment\ApiCalls\CallApiApcGbShipping;
use App\Actions\Dispatching\Shipment\ApiCalls\CallApiDpdGbShipping;
use App\Actions\Dispatching\Shipment\ApiCalls\CallApiGlsSKShipping;
use App\Actions\Dispatching\Shipment\ApiCalls\CallApiItdShipping;
use App\Actions\Dispatching\Shipment\ApiCalls\CallApiPacketaShipping;
use App\Actions\Dispatching\Shipment\ApiCalls\PostmenCallShipperApi;
use App\Actions\Dispatching\Shipment\ApiCalls\WhistlGbCallShipperApi;
use App\Actions\Dispatching\Shipment\Hydrators\ShipmentHydrateUniversalSearch;
use App\Actions\OrgAction;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\Shipment;
use App\Models\Dispatching\Shipper;
use App\Models\Fulfilment\PalletReturn;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Illuminate\Validation\ValidationException;

class StoreShipment extends OrgAction
{
    use AsAction;
    use WithAttributes;

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(DeliveryNote|PalletReturn $parent, Shipper $shipper, array $modelData): Shipment
    {
        data_set($modelData, 'group_id', $parent->group_id);
        data_set($modelData, 'organisation_id', $parent->organisation_id);
        data_set($modelData, 'shop_id', $parent->shop_id);

        $modelData = array_merge(
            $modelData,
            [
                'group_id'        => $parent->group_id,
                'organisation_id' => $parent->organisation_id,
                'shop_id'         => $parent->shop_id,
                'customer_id'     => $parent->customer_id,
            ]
        );

        if ($shipper->api_shipper) {
            $shipmentData = match ($shipper->api_shipper) {
                'apc-gb' => CallApiApcGbShipping::run($parent, $shipper),
                'gls-sk' => CallApiGlsSKShipping::run($parent, $shipper),
                // 'dpd-gb' => DpdGbCallShipperApi::run($parent, $shipper),
                'packeta-sk' => CallApiPacketaShipping::run($parent, $shipper),
                'dpd-gb' => CallApiDpdGbShipping::run($parent, $shipper),
                'pst-mn' => PostmenCallShipperApi::run($parent, $shipper),
                'whl-gb' => WhistlGbCallShipperApi::run($parent, $shipper),
                'itd' => CallApiItdShipping::run($parent, $shipper),
                default => [
                    'status' => 'error',
                    'errorData' => [
                        'message' => 'Unsupported API Shipper ' . $shipper->name,
                    ],
                ]
            };

            if ($shipmentData['status'] == 'success') {
                $modelData = array_merge($modelData, $shipmentData['modelData']);
            } else {
                throw ValidationException::withMessages(
                    $shipmentData['errorData']
                );
            }
        }
        /** @var Shipment $shipment */
        $shipment = $shipper->shipments()->create($modelData);
        $shipment->refresh();
        $parent->shipments()->attach($shipment->id);

        ShipmentHydrateUniversalSearch::dispatch($shipment);

        return $shipment;
    }

    public function rules(): array
    {
        return [
            'reference'      => ['sometimes', 'max:1000', 'string'],
            'tracking'       => ['sometimes', 'max:1000', 'string'],
        ];
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function action(DeliveryNote|PalletReturn $parent, Shipper $shipper, array $modelData): Shipment
    {
        $this->initialisation($parent->organisation, $modelData);

        return $this->handle($parent, $shipper, $this->validatedData);
    }
}
