<?php

/*
 * Author: ekayudinata <dev@aw-advantage.com>
 * Created: Wed, 22 Jul 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\PreferredShipping;

use App\Actions\Ordering\Order\UpdateOrderIsShippingTBC;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Models\Catalogue\PreferredShipping;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\Shipper;
use App\Models\Ordering\Order;
use Illuminate\Support\Collection;

trait WithPreferredShipperResolver
{
    /**
     * @return array{locked_shipper_id: int|null, preferred_shipper_id: int|null, locked_by: string|null, locked_scope: string|null, preferred_shipper: array|null, unconfirmed_shipping_order: string|null}
     */
    public function getShipperDirective(DeliveryNote $deliveryNote): array
    {
        return [
            ...$this->getShipperChoiceDirective($deliveryNote),
            'unconfirmed_shipping_order' => $this->findOrderWithUnconfirmedShipping($deliveryNote)?->reference,
        ];
    }

    /**
     * HELP-4309. A zone priced "TBC" means customer services agree the shipping cost with the
     * customer and enter it on the order. Until they do, the order is invoiced with free
     * shipping, so a label bought from the warehouse is paid by us. The stored is_shipping_tbc
     * goes stale when the engine is switched to manual, hence the recalculation.
     * HELP-4358. A replacement is resent at our cost whatever the zone says, and its order was
     * already invoiced, so there is no shipping left for customer services to agree.
     */
    public function findOrderWithUnconfirmedShipping(DeliveryNote $deliveryNote): ?Order
    {
        if ($deliveryNote->type == DeliveryNoteTypeEnum::REPLACEMENT) {
            return null;
        }

        return $deliveryNote->orders->first(
            fn (Order $order) => UpdateOrderIsShippingTBC::make()->isAwaitingShippingAmount($order)
        );
    }

    private function getShipperChoiceDirective(DeliveryNote $deliveryNote): array
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
            ->when(!$deliveryNote->is_premium_dispatch, fn ($query) => $query->where('priority_only', false))
            ->whereHas('shipper', fn ($query) => $query->where('status', true))
            ->get();

        return $this->pickPreferredShippingRule($rules, $countryId, $postalCode, $deliveryNote->weight ?: $deliveryNote->estimated_weight);
    }

    public function pickPreferredShipperId(Collection $rules, int $countryId, string $postalCode, ?int $weight = null): ?int
    {
        return $this->pickPreferredShippingRule($rules, $countryId, $postalCode, $weight)?->shipper_id;
    }

    /**
     * HELP-4514. A weight band (grams) runs from min_weight up to, not including, max_weight, the
     * convention of the Packeta tiers in CallApiPacketaShipping, so a band never hands Packeta a
     * weight it has no address for. It ranks under a postcode, which is about coverage, not price.
     */
    public function pickPreferredShippingRule(Collection $rules, int $countryId, string $postalCode, ?int $weight = null): ?PreferredShipping
    {
        $postalCode = $this->normalisePostcode($postalCode);
        $weight     = (int) $weight;

        return $rules
            ->filter(
                fn (PreferredShipping $rule) => (!$rule->country_id || $rule->country_id == $countryId)
                    && $this->postcodeMatchesRule($postalCode, $rule->postcode)
                    && (!$rule->min_weight || $weight >= $rule->min_weight)
                    && (!$rule->max_weight || $weight < $rule->max_weight)
            )
            ->sortByDesc(
                fn (PreferredShipping $rule) => ($rule->important ? 32 : 0)
                    + ($rule->priority_only ? 16 : 0)
                    + ($rule->shop_id ? 8 : 0)
                    + ($rule->postcode ? 4 : 0)
                    + ($rule->min_weight || $rule->max_weight ? 2 : 0)
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
        return collect([$rule->shop?->code, $rule->priority_only ? __('Priority') : null, $rule->country?->iso3, $rule->postcode, $this->describeWeightBand($rule)])
            ->filter()
            ->implode(' ') ?: __('all destinations');
    }

    private function describeWeightBand(PreferredShipping $rule): ?string
    {
        if (!$rule->min_weight && !$rule->max_weight) {
            return null;
        }

        return ($rule->min_weight / 1000).'-'.($rule->max_weight ? ($rule->max_weight / 1000).' kg' : '∞ kg');
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
