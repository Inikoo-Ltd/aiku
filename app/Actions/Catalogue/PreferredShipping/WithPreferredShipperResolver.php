<?php

/*
 * Author: ekayudinata <dev@aw-advantage.com>
 * Created: Wed, 22 Jul 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\PreferredShipping;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\PreferredShipping;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\Shipper;
use Illuminate\Support\Collection;

trait WithPreferredShipperResolver
{
    /**
     * @return array{locked_shipper_id: int|null, preferred_shipper_id: int|null, locked_by: string|null, locked_scope: string|null, preferred_shipper: array|null}
     */
    public function getShipperDirective(DeliveryNote $deliveryNote): array
    {
        $order = $deliveryNote->orders->first();

        if ($order?->is_shipper_locked && $order->shipper_id) {
            return [
                'locked_shipper_id'    => $order->shipper_id,
                'preferred_shipper_id' => $order->shipper_id,
                'locked_by'            => 'customer',
                'locked_scope'         => null,
                'preferred_shipper'    => $this->describeShipper($order->shipper_id),
            ];
        }

        $zoneShippers = collect($order?->shippingZone?->shippers_price ?? []);
        $rule = $this->findPreferredShippingRule($deliveryNote);

        $preferredShipperId = $order?->shipper_id
            ?? ($zoneShippers->count() == 1 ? $zoneShippers->first()['shipper_id'] ?? null : null)
            ?? $rule?->shipper_id;

        $isLockedByRule = $rule?->important && $preferredShipperId == $rule->shipper_id;

        return [
            'locked_shipper_id'    => $isLockedByRule ? $preferredShipperId : null,
            'preferred_shipper_id' => $preferredShipperId,
            'locked_by'            => $isLockedByRule ? 'preferred_shipping' : null,
            'locked_scope'         => $isLockedByRule ? $this->describeRuleScope($rule) : null,
            'preferred_shipper'    => $this->describeShipper($preferredShipperId),
        ];
    }

    public function findPreferredShipperId(DeliveryNote $deliveryNote): ?int
    {
        return $this->findPreferredShippingRule($deliveryNote)?->shipper_id;
    }

    public function findPreferredShippingRule(DeliveryNote $deliveryNote): ?PreferredShipping
    {
        $countryId = $deliveryNote->deliveryAddress?->country_id;
        if (!$countryId) {
            return null;
        }

        $postalCode = $this->normalisePostcode($deliveryNote->deliveryAddress->postal_code);

        $rules = PreferredShipping::query()
            ->where('organisation_id', $deliveryNote->organisation_id)
            ->where('trade_scope', $this->tradeScopeForShopType($deliveryNote->shop?->type))
            ->where(function ($query) use ($deliveryNote) {
                $query->where('shop_id', $deliveryNote->shop_id)->orWhereNull('shop_id');
            })
            ->whereHas('shipper', fn ($query) => $query->where('status', true))
            ->get();

        return $this->pickPreferredShippingRule($rules, $countryId, $postalCode);
    }

    public function pickPreferredShipperId(Collection $rules, int $countryId, string $postalCode): ?int
    {
        return $this->pickPreferredShippingRule($rules, $countryId, $postalCode)?->shipper_id;
    }

    public function pickPreferredShippingRule(Collection $rules, int $countryId, string $postalCode): ?PreferredShipping
    {
        $postalCode = $this->normalisePostcode($postalCode);

        return $rules
            ->filter(
                fn (PreferredShipping $rule) => (!$rule->country_id || $rule->country_id == $countryId)
                    && $this->postcodeMatchesRule($postalCode, $rule->postcode)
            )
            ->sortByDesc(
                fn (PreferredShipping $rule) => ($rule->important ? 4 : 0)
                    + ($rule->postcode ? 2 : 0)
                    + ($rule->country_id ? 1 : 0)
            )
            ->first();
    }

    /**
     * @return array{id: int, name: string, trade_as: string|null, slug: string, api_shipper: string|null}|null
     */
    private function describeShipper(?int $shipperId): ?array
    {
        $shipper = $shipperId ? Shipper::find($shipperId) : null;

        if (!$shipper) {
            return null;
        }

        return [
            'id'          => $shipper->id,
            'name'        => $shipper->name,
            'trade_as'    => $shipper->trade_as,
            'slug'        => $shipper->slug,
            'api_shipper' => $shipper->api_shipper,
        ];
    }

    private function describeRuleScope(PreferredShipping $rule): string
    {
        return collect([$rule->country?->iso3, $rule->postcode])
            ->filter()
            ->implode(' ') ?: __('all destinations');
    }

    /**
     * Some carriers demand the wholesale/consumer distinction, so rules live in two
     * independent sets: b2c serves consumer shops (dropshipping and b2c ecommerce),
     * b2b serves everything else.
     */
    public function tradeScopeForShopType(?ShopTypeEnum $shopType): string
    {
        return in_array($shopType, [ShopTypeEnum::DROPSHIPPING, ShopTypeEnum::B2C], true) ? 'b2c' : 'b2b';
    }

    /**
     * A rule can hold several comma-separated prefixes ("91,93,67"); any of them matching is a match.
     */
    private function postcodeMatchesRule(string $postalCode, ?string $rulePostcode): bool
    {
        $prefixes = array_filter(explode(',', $this->normalisePostcode($rulePostcode)));

        if (empty($prefixes)) {
            return true;
        }

        foreach ($prefixes as $prefix) {
            if (str_starts_with($postalCode, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function normalisePostcode(?string $postcode): string
    {
        return strtoupper(preg_replace('/\s+/', '', $postcode ?? ''));
    }
}
