<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Fri, 17 Jul 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $slug
 * @property mixed $code
 * @property mixed $picking_location_id
 * @property mixed $ordered
 * @property \Illuminate\Database\Eloquent\Collection $locationOrgStocks
 */
class OrgStockReplenishmentsResource extends JsonResource
{
    public function toArray($request): array
    {
        $pickingLocationStock = $this->locationOrgStocks->firstWhere('location_id', $this->picking_location_id);

        $stock    = $pickingLocationStock?->quantity ?? 0;
        $ordered  = $this->ordered ?? 0;
        $settings = $pickingLocationStock?->settings ?? [];

        $otherLocations = $this->locationOrgStocks
            ->filter(fn ($locationOrgStock) => $locationOrgStock->location_id !== $this->picking_location_id)
            ->map(fn ($locationOrgStock) => [
                'code'     => $locationOrgStock->location?->code,
                'quantity' => trimDecimalZeros($locationOrgStock->quantity),
            ])
            ->values();

        return [
            'id'              => $this->id,
            'slug'            => $this->slug,
            'code'            => $this->code,
            'stock'           => trimDecimalZeros($stock),
            'ordered'         => trimDecimalZeros($ordered),
            'eventual_stock'  => trimDecimalZeros($stock + $ordered),
            'location'        => $pickingLocationStock?->location ? [
                'code'   => $pickingLocationStock->location->code,
                'slug'   => $pickingLocationStock->location->slug,
                'status' => $pickingLocationStock->location->status,
            ] : null,
            'other_locations' => $otherLocations,
            'recommended'     => [
                'min' => $settings['min_stock'] ?? null,
                'max' => $settings['max_stock'] ?? null,
            ],
        ];
    }
}
