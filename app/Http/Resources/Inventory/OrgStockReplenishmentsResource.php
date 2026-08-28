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
 * @property mixed $active_location_id
 * @property mixed $stock
 * @property mixed $pending_picking
 * @property \Illuminate\Database\Eloquent\Collection $locationOrgStocks
 */
class OrgStockReplenishmentsResource extends JsonResource
{
    public function toArray($request): array
    {
        $activeLocationStock = $this->locationOrgStocks->firstWhere('location_id', $this->active_location_id);

        $settings = $activeLocationStock?->settings ?? [];

        $otherLocations = $this->locationOrgStocks
            ->filter(fn ($locationOrgStock) => $locationOrgStock->location_id !== $this->active_location_id)
            ->filter(fn ($locationOrgStock) => $locationOrgStock->quantity > 0)
            ->map(fn ($locationOrgStock) => [
                'code'     => $locationOrgStock->location?->code,
                'slug'     => $locationOrgStock->location?->slug,
                'quantity' => trimDecimalZeros($locationOrgStock->quantity),
            ])
            ->values();

        return [
            'id'              => $this->id,
            'slug'            => $this->slug,
            'code'            => $this->code,
            'stock'           => trimDecimalZeros($this->stock),
            'pending_picking' => trimDecimalZeros($this->pending_picking),
            'location'        => $activeLocationStock?->location ? [
                'code'   => $activeLocationStock->location->code,
                'slug'   => $activeLocationStock->location->slug,
                'status' => $activeLocationStock->location->status,
            ] : null,
            'other_locations' => $otherLocations,
            'recommended'     => [
                'min' => $settings['min_stock'] ?? null,
                'max' => $settings['max_stock'] ?? null,
            ],
        ];
    }
}
