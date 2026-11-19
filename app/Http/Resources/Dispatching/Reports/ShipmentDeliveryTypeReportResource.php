<?php

namespace App\Http\Resources\Dispatching\Reports;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $slug
 * @property string $reference
 * @property string $name
 * @property string $shop_slug
 * @property string $shop_code
 * @property int $shipments
 * @property int $parcels
 * @property int $home
 * @property int $pickup_point
 * @property int $not_classified
 */
class ShipmentDeliveryTypeReportResource extends JsonResource
{
    /**
     * @param  Request  $request
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'slug'           => $this->slug,
            'reference'      => $this->reference,
            'name'           => $this->name,
            'shop_slug'      => $this->shop_slug,
            'shop_code'      => $this->shop_code,
            'shipments'      => (int) $this->shipments,
            'parcels'        => (int) $this->parcels,
            'home'           => (int) $this->home,
            'pickup_point'   => (int) $this->pickup_point,
            'not_classified' => (int) $this->not_classified,
        ];
    }
}
