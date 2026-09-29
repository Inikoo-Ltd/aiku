<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 00:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Ordering\Order\WithOrderForbiddenCountryCheck;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Enums\Accounting\PaymentAccountShop\PaymentAccountShopStateEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Enums\Web\Webpage\WebpageSubTypeEnum;
use App\Models\Accounting\Invoice;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChatSession;
use App\Models\CRM\Customer;
use App\Models\Web\Webpage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use Locale;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The answer to a general question, looked up by a fixed query for each kind of question Jev
 * can pick (ClassifyChatTurn::ASKS), never by a query a model writes: only this shop's settings
 * and this customer's own records. One line each, for the agent to read before answering.
 */
class GetChatShopFacts
{
    use AsAction;
    use WithOrderForbiddenCountryCheck;

    /**
     * @return array<int, string>
     */
    public function handle(ChatSession|MetaChatSession $chatSession, string $ask, string $customerWrote): array
    {
        $shop     = $chatSession->shop;
        $customer = DraftChatReply::knownCustomer($chatSession);

        if (!$shop) {
            return [];
        }

        return array_values(array_filter(match ($ask) {
            'ship_to_country'  => [$this->bannedCountries($shop), $this->shippingCost($shop, $customer), $this->page($shop, WebpageSubTypeEnum::SHIPPING, __('Delivery'))],
            'shipping_cost'    => [$this->shippingCost($shop, $customer), $this->freeShipping($shop), $this->page($shop, WebpageSubTypeEnum::SHIPPING, __('Delivery'))],
            'delivery_time'    => [$this->page($shop, WebpageSubTypeEnum::SHIPPING, __('Delivery'))],
            'returns_policy'   => [$this->page($shop, WebpageSubTypeEnum::RETURNS, __('Returns'))],
            'how_to_order'     => [$customer ? null : $this->page($shop, WebpageSubTypeEnum::REGISTER, __('Register'))],
            'invoice_copy'     => [$customer ? $this->latestInvoice($shop, $customer) : null],
            'refund_status'    => $customer ? $this->refunds($customer) : [],
            'balance'          => [$customer ? __('Balance').': '.$this->money($shop, (float) $customer->balance) : null],
            'payment_methods'  => [$this->paymentMethods($shop)],
            'vat'              => [__('Prices on the website are without VAT; VAT is added at checkout by the delivery country and tax number')],
            'discount_missing' => [$this->firstOrderBonus($shop, $customer), ...$this->customerOffers($customer)],
            'product_price'    => $this->prices($shop, $customerWrote),
            default            => [],
        }));
    }

    /**
     * What the shop's own settings say, one entry each, for the knowledge base.
     *
     * @return array<int, array{title: string, body: string}>
     */
    public function shopFacts(Shop $shop): array
    {
        $zones = $shop->currentShippingZoneSchema?->shippingZones()->where('status', true)->orderBy('position')->get() ?? collect();

        return array_values(array_filter([
            ...$zones->map(fn ($zone) => data_get($zone->price, 'type') === 'TBC'
                ? ['title' => __('Delivery').': '.$zone->name, 'body' => $zone->name.': '.__('delivery price to be confirmed by customer service')]
                : ['title' => __('Delivery').': '.$zone->name, 'body' => $zone->name.' ('.$this->zoneCountries($zone).'): '.$this->steps($shop, $zone->price).' ('.__('goods before VAT').')'])
                ->all(),
            ($banned = $this->bannedCountries($shop)) ? ['title' => __('Blocked delivery countries'), 'body' => $banned] : null,
            ($free = $this->freeShipping($shop)) ? ['title' => __('Free delivery offer'), 'body' => $free] : null,
            ($bonus = $this->firstOrderBonus($shop, null)) ? ['title' => __('First order bonus'), 'body' => $bonus] : null,
            ($methods = $this->paymentMethods($shop)) ? ['title' => __('Payment methods'), 'body' => $methods] : null,
        ]));
    }

    private function zoneCountries($zone): string
    {
        return collect($zone->territories ?? [])
            ->map(fn ($territory) => (Locale::getDisplayRegion('-'.data_get($territory, 'country_code'), 'en') ?: data_get($territory, 'country_code'))
                .(data_get($territory, 'included_postcodes') ? ' ('.__('some postcodes').')' : ''))
            ->unique()
            ->join(', ') ?: __('rest of the world');
    }

    /**
     * @param  array<string, mixed>|null  $price
     */
    private function steps(Shop $shop, ?array $price): string
    {
        return collect(data_get($price, 'steps', []))
            ->map(fn ($step) => (float) $step['price'] === 0.0
                ? __('free from').' '.$this->money($shop, (float) $step['from'])
                : $this->money($shop, (float) $step['price']).($step['to'] !== 'INF' ? ' '.__('up to').' '.$this->money($shop, (float) $step['to']) : ''))
            ->join(', ');
    }

    private function bannedCountries(Shop $shop): ?string
    {
        $countries = collect($this->getBannedCountriesTarget($shop)->bannedDeliveryCountries())
            ->map(fn (array $ban, string $code) => (Locale::getDisplayRegion('-'.$code, 'en') ?: $code).(data_get($ban, 'postcode') ? ' ('.__('some postcodes').')' : ''))
            ->sort()
            ->join(', ');

        return $countries ? __('Blocked delivery countries').': '.$countries : null;
    }

    /**
     * The delivery price steps of the zone for the customer's delivery country, or the shop's own
     * country when we do not know the customer, from the shipping zones the checkout uses.
     */
    private function shippingCost(Shop $shop, ?Customer $customer): ?string
    {
        $country = $customer?->deliveryAddress?->country_code ?? $shop->country?->code;
        $zone    = $country ? $shop->currentShippingZoneSchema?->shippingZones()
            ->where('status', true)
            ->get()
            ->first(fn ($zone) => collect($zone->territories ?? [])->contains(fn ($territory) => data_get($territory, 'country_code') === $country && !data_get($territory, 'included_postcodes'))) : null;

        if (!$zone || data_get($zone->price, 'type') === 'TBC') {
            return null;
        }

        $steps = $this->steps($shop, $zone->price);

        return __('Delivery to').' '.(Locale::getDisplayRegion('-'.$country, 'en') ?: $country).': '.$steps.' ('.__('goods before VAT').')';
    }

    private function freeShipping(Shop $shop): ?string
    {
        $offer = data_get($shop->offers_data, 'discounted_shipping');

        return data_get($offer, 'active') && data_get($offer, 'min_amount')
            ? __('Free delivery from').' '.$this->money($shop, (float) data_get($offer, 'min_amount')).' '.__('of goods before VAT')
            : null;
    }

    private function page(Shop $shop, WebpageSubTypeEnum $subType, string $label): ?string
    {
        $webpage = $shop->website ? Webpage::where('website_id', $shop->website->id)
            ->where('sub_type', $subType)
            ->where('state', WebpageStateEnum::LIVE)
            ->first() : null;

        return $webpage ? $label.': '.($webpage->canonical_url ?: 'https://'.$shop->website->domain.'/'.$webpage->url) : null;
    }

    private function latestInvoice(Shop $shop, Customer $customer): ?string
    {
        $invoice = Invoice::where('customer_id', $customer->id)
            ->where('type', InvoiceTypeEnum::INVOICE)
            ->latest('date')
            ->first();

        return $invoice && $shop->website?->domain
            ? __('Latest invoice').' '.$invoice->reference.' ('.$invoice->date?->toDateString().'): https://'.$shop->website->domain.'/invoice/'.$invoice->ulid
            : null;
    }

    /**
     * @return array<int, string>
     */
    private function refunds(Customer $customer): array
    {
        return Invoice::where('customer_id', $customer->id)
            ->where('type', InvoiceTypeEnum::REFUND)
            ->where('date', '>', now()->subDays(90))
            ->latest('date')
            ->limit(3)
            ->get()
            ->map(fn (Invoice $refund) => __('Refund').' '.$refund->reference.' '.$refund->date?->toDateString().': '
                .$this->money($customer->shop, abs((float) $refund->total_amount)).', '
                .($refund->in_process ? __('still being prepared') : ($refund->pay_status?->value === 'paid' ? __('paid') : __('not paid yet'))))
            ->whenEmpty(fn ($none) => $none->push(__('No refunds in the last 90 days')))
            ->all();
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

    /**
     * @return array<int, string>
     */
    private function customerOffers(?Customer $customer): array
    {
        return $customer ? DB::table('offers')
            ->where('trigger_type', 'Customer')
            ->where('trigger_id', $customer->id)
            ->where('state', 'active')
            ->get(['name', 'end_at'])
            ->map(fn ($offer) => __('Offer for this customer').': '.$offer->name.($offer->end_at ? ' ('.__('until').' '.substr((string) $offer->end_at, 0, 10).')' : ''))
            ->all() : [];
    }

    /**
     * @return array<int, string>
     */
    private function prices(Shop $shop, string $customerWrote): array
    {
        $codes = array_column(GetChatProductFacts::run($shop, $customerWrote), 'code');

        return Product::where('shop_id', $shop->id)->whereIn('code', $codes)->get()
            ->map(fn (Product $product) => $product->code.' '.$product->name.': '.$this->money($shop, (float) $product->price)
                .((float) $product->units > 1 ? ' '.__('for').' '.(float) $product->units.' '.($product->unit ?: __('units')) : '').' '.__('before VAT'))
            ->all();
    }

    private function money(Shop $shop, float $amount): string
    {
        return Number::currency($amount, $shop->currency?->code ?? 'GBP');
    }
}
