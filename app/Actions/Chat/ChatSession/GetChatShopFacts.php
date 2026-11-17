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
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Web\Website\WebsiteStateEnum;
use App\Models\Billables\Charge;
use App\Models\Catalogue\Shop;
use App\Models\Billables\ShippingZone;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\Bundle;
use App\Models\Helpers\Country;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

    public const array DROPSHIPPING_SHOP_BY_ORGANISATION = ['aw' => 'awd', 'sk' => 'dssk', 'es' => 'dse', 'aroma' => 'awd'];

    public const int HOME_SHOP_MIN_CUSTOMERS = 10;

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
            ($bundles = $this->bundleDiscount($shop)) ? ['title' => __('Bundle discount'), 'body' => $bundles] : null,
            $this->dropshippingShop($shop),
            ...$this->homeShops($shop),
        ]));
    }

    /**
     * Where a wholesale customer can dropship: the dropshipping shop of the shop's organisation (Raul, 17 Nov 2026).
     *
     * @return ?array{title: string, body: string}
     */
    private function dropshippingShop(Shop $shop): ?array
    {
        $target = $shop->type === ShopTypeEnum::B2B && ($slug = self::DROPSHIPPING_SHOP_BY_ORGANISATION[$shop->organisation->slug] ?? null)
            ? Shop::where('slug', $slug)->where('type', ShopTypeEnum::DROPSHIPPING)->where('state', ShopStateEnum::OPEN)->with('website')->first()
            : null;

        if (!$target?->website?->domain || $target->website->state !== WebsiteStateEnum::LIVE) {
            return null;
        }

        return [
            'title' => __('Dropshipping'),
            'body'  => __('We also do dropshipping, for customers who sell our products to their own buyers one at a time from an online store (Shopify, WooCommerce, eBay, Wix, TikTok Shop and others): we send each order straight to their buyer. Our dropshipping shop is :name, :url, where they register separately. Almost every product there is sold as a single unit, so it also suits a customer who wants one item rather than a whole outer.', ['name' => $target->name, 'url' => 'https://'.ltrim($target->website->domain, '/')]),
        ];
    }

    /**
     * For each country whose customers mostly buy from another shop of the same catalogue (the same master shop), that shop
     * and its link, so a customer in that country can be told where most customers there buy (e.g. EU customers of the UK
     * shop, Brexit), only across the UK/EU customs border (customsSide). Only countries this shop has customers from. "Mostly" means at least HOME_SHOP_MIN_CUSTOMERS customers there and twice the customers this shop has
     * from that country, counted by the customer's own address over the last 12 months: a near tie (two French shops)
     * says nothing. It is where customers buy, not a delivery price comparison, and the facts say only that.
     *
     * @return array<int, array{title: string, body: string}>
     */
    private function homeShops(Shop $shop): array
    {
        if (!$shop->master_shop_id) {
            return [];
        }

        return collect(self::homeShopIds($this->customersByShopAndCountry(), $shop->id, $shop->master_shop_id))
            ->map(function (int $targetId, string $countryCode) {
                $target  = Shop::with('website')->find($targetId);
                $country = Locale::getDisplayRegion('-'.$countryCode, 'en') ?: $countryCode;

                return $target?->website?->domain && $target->website->state === WebsiteStateEnum::LIVE ? [
                    'title' => __('Customers in :country', ['country' => $country]),
                    'body'  => __('Most of our customers in :country buy from our shop :name, :url. It is a separate shop: a customer from :country registers there and orders there.', ['country' => $country, 'name' => $target->name, 'url' => 'https://'.ltrim($target->website->domain, '/')]),
                ] : null;
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  iterable<object{shop_id: int, master_shop_id: int, country_code: string, shop_country_code: ?string, customers: int}>  $rows
     * @return array<string, int> country code => the shop most of its customers buy from
     */
    public static function homeShopIds(iterable $rows, int $shopId, int $masterShopId): array
    {
        return collect($rows)
            ->where('master_shop_id', $masterShopId)
            ->groupBy('country_code')
            ->map(function ($rows, string $countryCode) use ($shopId) {
                $top      = $rows->sortByDesc('customers')->first();
                $current  = $rows->first(fn (object $row) => (int) $row->shop_id === $shopId);
                $here     = (int) ($current?->customers ?? 0);
                $customer = self::customsSide($countryCode);

                return $here === 0 || (int) $top->shop_id === $shopId || $top->customers < self::HOME_SHOP_MIN_CUSTOMERS || $top->customers < 2 * $here
                    || !$customer || self::customsSide($current->shop_country_code) === $customer || self::customsSide($top->shop_country_code) !== $customer
                    ? null
                    : (int) $top->shop_id;
            })
            ->filter()
            ->all();
    }

    /**
     * UK or EU: the customs border a shop's orders cross (Brexit). Only a shop on the customer's side of it is worth
     * recommending, because that saves the customer import VAT and customs; within the EU, or for a customer outside
     * both, moving shop gains them nothing (Raul, 17 Nov 2026: rare, only when it truly helps).
     */
    private static function customsSide(?string $countryCode): ?string
    {
        return match (true) {
            in_array($countryCode, ['GB', 'GG', 'JE', 'IM'], true)      => 'uk',
            in_array($countryCode, Country::getCountryCodesInEU(), true) => 'eu',
            default                                                      => null,
        };
    }

    /**
     * @return array<int, object{shop_id: int, master_shop_id: int, country_code: string, shop_country_code: ?string, customers: int}>
     */
    private function customersByShopAndCountry(): array
    {
        return Cache::remember('chat-shop-facts:customers-by-shop-and-country:v2', now()->addDay(), fn () => DB::select(
            "select shops.id as shop_id, shops.master_shop_id, addresses.country_code, organisation_addresses.country_code as shop_country_code, count(distinct orders.customer_id) as customers
             from orders
             join shops on shops.id = orders.shop_id
             join organisations on organisations.id = shops.organisation_id
             left join addresses as organisation_addresses on organisation_addresses.id = organisations.address_id
             join customers on customers.id = orders.customer_id
             join addresses on addresses.id = customers.address_id
             where orders.submitted_at > now() - interval '12 months' and orders.state <> 'cancelled' and shops.state = 'open' and shops.master_shop_id is not null
             group by shops.id, shops.master_shop_id, addresses.country_code, organisation_addresses.country_code"
        ));
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

    private function bundleDiscount(Shop $shop): ?string
    {
        if ($shop->type !== ShopTypeEnum::DROPSHIPPING) {
            return null;
        }

        $percentage = Number::format(Bundle::discountPercentage($shop));

        return __('A bundle a customer creates for their sales channel costs :percentage% less than its products bought separately, and its suggested retail price is also :percentage% below theirs', ['percentage' => $percentage]);
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
