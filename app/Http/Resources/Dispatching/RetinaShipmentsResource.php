<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 14 Jul 2025 15:28:08 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dispatching;

use App\Http\Resources\HasSelfCall;
use App\Models\Dispatching\Shipment;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/**
 * @property Shipment $resource
 * @property int $id
 * @property string $name
 * @property string|null $reference
 * @property string $tracking
 * @property array|null $trackings
 * @property array|null $tracking_urls
 * @property string|null $label
 * @property string|null $label_type
 */
class RetinaShipmentsResource extends JsonResource
{
    use HasSelfCall;

    public static $wrap = null;

    public function toArray($request): array
    {
        $shipment = $this->resource;


        $formattedTrackingURls = [];
        foreach ($shipment->trackings as $key => $tracking) {
            $url = Arr::get($shipment->tracking_urls, $key);
            if ($url) {
                $formattedTrackingURls[] = [
                    'url'      => Arr::get($shipment->tracking_urls, $key),
                    'tracking' => $tracking
                ];
            }
        }

        return [
            'id'                      => $shipment->id,
            'name'                    => $shipment->shipper->trade_as ?? $shipment->shipper->name,
            'shipper_url'             => $shipment->shipper->tracking_url,
            'reference'               => $shipment->reference,
            'tracking'                => $shipment->tracking,
            'trackings'               => $shipment->trackings,
            'formatted_tracking_urls' => $formattedTrackingURls,
        ];
    }
}
