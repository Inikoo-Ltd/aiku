<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 15-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Http\Resources\Dispatching;

use App\Http\Resources\HasSelfCall;
use App\Models\Dispatching\Shipment;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class ShipmentsResource extends JsonResource
{
    use HasSelfCall;

    public static $wrap = null;

    public function toArray($request): array
    {
        /** @var Shipment $shipment */
        $shipment    = $this->resource;
        $isPrintable = Arr::get($shipment->group->settings, 'printnode.print_by_printnode', false) && Arr::get($shipment->group->settings, 'printnode.apikey', false);

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
            'name'                    => $shipment->shipper->name,
            'reference'               => $shipment->reference,
            'tracking'                => $shipment->tracking,
            'trackings'               => $shipment->trackings,
            'tracking_urls'           => $shipment->tracking_urls,
            'formatted_tracking_urls' => $formattedTrackingURls,
            'tracking_url'            => $shipment->shipper->tracking_url,
            'combined_label_url'      => $shipment->combined_label_url,
            'label'                   => $shipment->label,
            'label_type'              => $shipment->label_type,
            'is_printable'            => $isPrintable,
        ];
    }
}
