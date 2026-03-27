<?php

namespace App\Http\Resources\Catalogue;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $from
 * @property string $to
 * @property string|null $raw_from
 * @property string|null $raw_to
 * @property string $label
 * @property string|null $offer_code
 * @property string|null $campaign_code
 * @property string|null $campaign_type
 * @property string|null $duration_label
 * @property string|null $shop_code
 * @property string|null $shop_name
 * @property string|null $state
 * @property bool $status
 * @property array|null $details
 * @property array|null $route
 */
class OfferCalendarRangeResource extends JsonResource
{
    public function toArray($request): array
    {
        $data = (array) $this->resource;

        return [
            'from'          => $data['from'] ?? null,
            'to'            => $data['to'] ?? null,
            'raw_from'      => $data['raw_from'] ?? null,
            'raw_to'        => $data['raw_to'] ?? null,
            'label'         => $data['label'] ?? null,
            'offer_code'    => $data['offer_code'] ?? null,
            'campaign_code' => $data['campaign_code'] ?? null,
            'campaign_type' => $data['campaign_type'] ?? null,
            'duration_label' => $data['duration_label'] ?? null,
            'shop_code'     => strtoupper($data['shop_code'] ?? null),
            'shop_name'     => $data['shop_name'] ?? null,
            'state'         => $data['state'] ?? null,
            'status'        => $data['status'] ?? false,
            'details'       => $data['details'] ?? null,
            'route'         => $data['route'] ?? null,
        ];
    }
}
