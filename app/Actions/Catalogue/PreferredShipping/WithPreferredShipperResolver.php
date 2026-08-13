<?php

/*
 * Author: ekayudinata <dev@aw-advantage.com>
 * Created: Wed, 22 Jul 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\PreferredShipping;

use App\Models\Catalogue\PreferredShipping;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Support\Collection;

trait WithPreferredShipperResolver
{
    /**
     * @return array{locked_shipper_id: int|null, preferred_shipper_id: int|null}
     */
    public function getShipperDirective(DeliveryNote $deliveryNote): array
    {
        $order = $deliveryNote->orders->first();

        if ($order?->is_shipper_locked && $order->shipper_id) {
            return ['locked_shipper_id' => $order->shipper_id, 'preferred_shipper_id' => $order->shipper_id];
        }

        $zoneShippers = collect($order?->shippingZone?->shippers_price ?? []);
        $preferredShipperId = $order?->shipper_id
            ?? ($zoneShippers->count() == 1 ? $zoneShippers->first()['shipper_id'] ?? null : null)
            ?? $this->findPreferredShipperId($deliveryNote);

        return ['locked_shipper_id' => null, 'preferred_shipper_id' => $preferredShipperId];
    }

    public function findPreferredShipperId(DeliveryNote $deliveryNote): ?int
    {
        $countryId = $deliveryNote->deliveryAddress?->country_id;
        if (!$countryId) {
            return null;
        }

        $postalCode = $this->normalisePostcode($deliveryNote->deliveryAddress->postal_code);

        $rules = PreferredShipping::query()
            ->where('organisation_id', $deliveryNote->organisation_id)
            ->where(function ($query) use ($deliveryNote) {
                $query->where('shop_id', $deliveryNote->shop_id)->orWhereNull('shop_id');
            })
            ->whereHas('shipper', fn ($query) => $query->where('status', true))
            ->get();

        return $this->pickPreferredShipperId($rules, $countryId, $postalCode);
    }

    public function pickPreferredShipperId(Collection $rules, int $countryId, string $postalCode): ?int
    {
        $postalCode = $this->normalisePostcode($postalCode);

        return $rules
            ->filter(
                fn (PreferredShipping $rule) => (!$rule->country_id || $rule->country_id == $countryId)
                    && (!$rule->postcode || str_starts_with($postalCode, $this->normalisePostcode($rule->postcode)))
            )
            ->sortByDesc(
                fn (PreferredShipping $rule) => ($rule->important ? 4 : 0)
                    + ($rule->postcode ? 2 : 0)
                    + ($rule->country_id ? 1 : 0)
            )
            ->first()?->shipper_id;
    }

    private function normalisePostcode(?string $postcode): string
    {
        return strtoupper(preg_replace('/\s+/', '', $postcode ?? ''));
    }
}
