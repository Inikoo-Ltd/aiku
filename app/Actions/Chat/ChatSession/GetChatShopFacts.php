<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 00:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Ordering\Order\CalculateOrderShipping;
use App\Actions\Ordering\Order\WithOrderForbiddenCountryCheck;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Enums\Accounting\PaymentAccountShop\PaymentAccountShopStateEnum;
use App\Enums\Catalogue\Charge\ChargeStateEnum;
use App\Enums\Catalogue\Charge\ChargeTriggerEnum;
use App\Enums\Catalogue\Charge\ChargeTypeEnum;
use App\Models\Billables\Charge;
use App\Models\Catalogue\Shop;
use App\Models\Billables\ShippingZone;
use App\Models\CRM\Customer;
use Illuminate\Support\Number;
use Locale;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * What the shop's own settings say about delivery, payment and offers, one entry each, for the
 * knowledge base: read from this shop's settings, never by a query a model writes.
 */
class GetChatShopFacts
{
    use AsAction;
    use WithOrderForbiddenCountryCheck;

    /**
     * What the shop's own settings say, one entry each, for the knowledge base.
     *
     * @return array<int, array{title: string, body: string}>
     */
    public function shopFacts(Shop $shop): array
    {
        $zones = $shop->currentShippingZoneSchema?->shippingZones()->where('status', true)->orderBy('position')->get() ?? collect();

        return array_values(array_filter([
            ...$zones->map(fn ($zone) => data_get($this->zonePrice($zone), 'type') === 'TBC'
                ? ['title' => __('Delivery').': '.$zone->name, 'body' => $zone->name.': '.__('delivery price to be confirmed by customer service')]
                : ['title' => __('Delivery').': '.$zone->name, 'body' => $zone->name.' ('.$this->zoneCountries($zone).'): '.$this->deliveryPrice($shop, $this->zonePrice($zone))])
                ->all(),
            ($banned = $this->bannedCountries($shop)) ? ['title' => __('Blocked delivery countries'), 'body' => $banned] : null,
            ($free = $this->freeShipping($shop)) ? ['title' => __('Free delivery offer'), 'body' => $free] : null,
            ($bonus = $this->firstOrderBonus($shop, null)) ? ['title' => __('First order bonus'), 'body' => $bonus] : null,
            ($methods = $this->paymentMethods($shop)) ? ['title' => __('Payment methods'), 'body' => $methods] : null,
            ($charges = $this->charges($shop)) ? ['title' => __('Charges'), 'body' => $charges] : null,
        ]));
    }

    private function zoneCountries($zone): string
    {
        return collect($zone->territories ?? [])
            ->map(fn ($territory) => (Locale::getDisplayRegion('-'.data_get($territory, 'country_code'), 'en') ?: data_get($territory, 'country_code'))
                .(data_get($territory, 'included_postal_codes') ? ' ('.__('only postcodes matching').' '.data_get($territory, 'included_postal_codes').')' : '')
                .(data_get($territory, 'excluded_postal_codes') ? ' ('.__('except postcodes matching').' '.data_get($territory, 'excluded_postal_codes').')' : ''))
            ->unique()
            ->join(', ') ?: __('rest of the world');
    }

    /**
     * The price the checkout charges with no shipper picked yet: a shipper price replaces the zone's own.
     *
     * @return array<string, mixed>
     */
    private function zonePrice(ShippingZone $zone): array
    {
        return CalculateOrderShipping::make()->getZonePriceDataForShipper($zone, null);
    }

    /**
     * The zone's price steps as the checkout reads them (CalculateOrderShipping): bounds are the
     * goods amount, or the order's estimated weight in kg for a weight priced zone.
     *
     * @param  array<string, mixed>|null  $price
     */
    public function deliveryPrice(Shop $shop, ?array $price): string
    {
        $byWeight = data_get($price, 'type') === 'Step Order Estimated Weight';
        $bound    = fn ($value) => $byWeight ? Number::format((float) $value, maxPrecision: 2).' kg' : $this->money($shop, (float) $value);

        return collect(data_get($price, 'steps', []))
            ->map(fn ($step) => match (true) {
                $step['price'] === 'TBC'           => __('from').' '.$bound($step['from']).': '.__('delivery price to be confirmed by customer service'),
                (float) $step['price'] === 0.0     => __('free from').' '.$bound($step['from']),
                default                            => $this->money($shop, (float) $step['price']).($step['to'] !== 'INF' ? ' '.__('up to').' '.$bound($step['to']) : ''),
            })
            ->join(', ').' ('.($byWeight ? __('estimated order weight') : __('goods before VAT')).')';
    }

    private function bannedCountries(Shop $shop): ?string
    {
        $countries = collect($this->getBannedCountriesTarget($shop)->bannedDeliveryCountries())
            ->map(fn (array $ban, string $code) => (Locale::getDisplayRegion('-'.$code, 'en') ?: $code).(data_get($ban, 'postcode') ? ' ('.__('some postcodes').')' : ''))
            ->sort()
            ->join(', ');

        return $countries ? __('Blocked delivery countries').': '.$countries : null;
    }

    private function freeShipping(Shop $shop): ?string
    {
        $offer = data_get($shop->offers_data, 'discounted_shipping');

        return data_get($offer, 'active') && data_get($offer, 'min_amount')
            ? __('Free delivery from').' '.$this->money($shop, (float) data_get($offer, 'min_amount')).' '.__('of goods before VAT')
            : null;
    }

    private function paymentMethods(Shop $shop): ?string
    {
        $methods = $shop->paymentAccountShops()
            ->where('state', PaymentAccountShopStateEnum::ACTIVE)
            ->where('show_in_checkout', true)
            ->orderBy('checkout_display_position')
            ->get()
            ->map(fn ($paymentAccountShop) => $paymentAccountShop->type ? (PaymentAccountTypeEnum::labels()[$paymentAccountShop->type->value] ?? $paymentAccountShop->type->value) : null)
            ->filter()
            ->unique()
            ->join(', ');

        return $methods ? __('Payment methods at checkout').': '.$methods : null;
    }

    private function charges(Shop $shop): ?string
    {
        $charges = $shop->charges()
            ->where('state', ChargeStateEnum::ACTIVE)
            ->where('status', true)
            ->orderBy('id')
            ->get()
            ->map(fn (Charge $charge) => $this->charge($shop, $charge))
            ->filter()
            ->join('; ');

        return $charges ? __('Charges an order can carry').': '.$charges : null;
    }

    /**
     * When the checkout adds the charge, as CalculateOrderHangingCharges and the other charge actions decide it.
     */
    private function charge(Shop $shop, Charge $charge): ?string
    {
        $amount = data_get($charge->settings, 'amount');
        $price  = filled($amount) ? $this->money($shop, (float) $amount) : __('amount worked out at checkout');

        if ($charge->type === ChargeTypeEnum::HANGING->value) {
            $rules = (string) data_get($charge->settings, 'rules');
            if (!str_contains($rules, ';')) {
                return null;
            }
            [$operator, $limit] = explode(';', $rules);

            return $charge->name.' '.$price.' '.($operator === '>' ? __('when goods before VAT are over') : __('when goods before VAT are under')).' '.$this->money($shop, (float) $limit);
        }

        $when = match (true) {
            $charge->type === ChargeTypeEnum::COLLECTION->value         => __('on orders for collection'),
            $charge->trigger === ChargeTriggerEnum::SELECTED_BY_CUSTOMER => __('when the customer picks it at checkout'),
            $charge->trigger === ChargeTriggerEnum::PAYMENT_ACCOUNT      => __('when the customer pays with that method'),
            $charge->trigger === ChargeTriggerEnum::SELECTED_BY_USER     => __('only when staff add it by hand'),
            $charge->trigger === ChargeTriggerEnum::PRODUCT              => __('on some products'),
            default                                                     => __('on every order'),
        };

        return $charge->name.' '.$price.' '.$when;
    }

    private function firstOrderBonus(Shop $shop, ?Customer $customer): ?string
    {
        $bonus = data_get($shop->offers_data, 'fob');

        if (!data_get($bonus, 'active')) {
            return null;
        }

        $ordered = $customer && $customer->orders()->whereNotIn('state', ['creating', 'cancelled'])->exists();

        return __('First order bonus').': '.rtrim((string) data_get($bonus, 'percentage_off'), '%').'% '.__('off from').' '.$this->money($shop, (float) data_get($bonus, 'min_amount'))
            .($customer ? ' · '.($ordered ? __('this customer has ordered before, so it no longer applies') : __('this customer has not ordered yet')) : '');
    }

    private function money(Shop $shop, float $amount): string
    {
        return Number::currency($amount, $shop->currency?->code ?? 'GBP');
    }
}
