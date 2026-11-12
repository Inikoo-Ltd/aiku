<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\Whatsapp\Templates;

use App\Enums\CRM\Livechat\WhatsappTemplateTagEnum;
use App\Models\CRM\Customer;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\MetaChatSession;
use App\Models\Catalogue\Product;
use App\Models\Dispatching\Shipment;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Fills the merge tags a template was written with, in the order they were compiled into
 * `{{1}}`, `{{2}}`. WhatsApp rejects blank parameters, so anything that cannot be resolved
 * is reported back rather than sent as an empty string.
 */
class ResolveWhatsappTemplateTags
{
    use AsObject;

    /**
     * @param  array<int, string>  $tags
     * @param  array{product_ids?: array<int, int>, order_ids?: array<int, int>}  $context
     * @return array{values: array<int, string>, missing: array<int, string>}
     */
    public function handle(MetaChatSession $metaChatSession, array $tags, ?ChatAgent $agent = null, ?Order $order = null, array $context = []): array
    {
        $values  = [];
        $missing = [];

        foreach ($tags as $tag) {
            $case = WhatsappTemplateTagEnum::tryFrom($tag);
            $value = $case ? $this->resolve($case, $metaChatSession, $agent, $order, $context) : null;

            if (blank($value)) {
                $missing[] = $tag;
                $values[]  = null;
            } else {
                $values[] = (string) $value;
            }
        }

        return ['values' => $values, 'missing' => $missing];
    }

    /**
     * A notification is about the order that triggered it, so a given order wins over the
     * customer's latest one, which may already be a newer basket.
     */
    protected function resolve(WhatsappTemplateTagEnum $tag, MetaChatSession $metaChatSession, ?ChatAgent $agent, ?Order $order = null, array $context = []): ?string
    {
        $customer = $this->customer($metaChatSession);
        $shop     = $metaChatSession->shop;
        $order    ??= $this->lastOrder($customer);

        return match ($tag) {
            WhatsappTemplateTagEnum::CUSTOMER_NAME          => $this->customerName($metaChatSession, $customer),
            WhatsappTemplateTagEnum::CUSTOMER_FIRST_NAME    => $this->firstWord($this->customerName($metaChatSession, $customer)),
            WhatsappTemplateTagEnum::CUSTOMER_COMPANY       => $customer?->company_name,
            WhatsappTemplateTagEnum::CUSTOMER_EMAIL         => $customer?->email,
            WhatsappTemplateTagEnum::CUSTOMER_PHONE         => $customer?->phone ?? $metaChatSession->phone_number,
            WhatsappTemplateTagEnum::CUSTOMER_REFERENCE     => $customer?->reference,
            WhatsappTemplateTagEnum::CUSTOMER_REGISTER_DATE => $customer?->created_at?->format('j M Y'),
            WhatsappTemplateTagEnum::CUSTOMER_BALANCE       => $this->money($customer?->balance, $shop),

            WhatsappTemplateTagEnum::SHOP_NAME  => $shop?->name,
            WhatsappTemplateTagEnum::SHOP_URL   => $this->shopUrl($metaChatSession),
            WhatsappTemplateTagEnum::SHOP_EMAIL => $shop?->email,
            WhatsappTemplateTagEnum::SHOP_PHONE => $shop?->phone,

            WhatsappTemplateTagEnum::ORDER_NUMBER      => $this->orderReferences($context) ?? $order?->reference,
            WhatsappTemplateTagEnum::ORDER_TOTAL       => $this->money($order?->total_amount, $shop),
            WhatsappTemplateTagEnum::ORDER_DATE        => $order?->date?->format('j M Y'),
            WhatsappTemplateTagEnum::ORDER_STATE       => Str::headline((string) $order?->state?->value),
            WhatsappTemplateTagEnum::ORDER_ITEMS_COUNT => $order?->number_item_transactions,

            WhatsappTemplateTagEnum::INVOICE_NUMBER => $this->lastInvoice($customer)?->reference,
            WhatsappTemplateTagEnum::INVOICE_TOTAL  => $this->money($this->lastInvoice($customer)?->total_amount, $shop),
            WhatsappTemplateTagEnum::INVOICE_DATE   => $this->lastInvoice($customer)?->date?->format('j M Y'),

            WhatsappTemplateTagEnum::DELIVERY_ADDRESS  => $this->deliveryAddress($customer),
            WhatsappTemplateTagEnum::DELIVERY_TOWN     => $customer?->deliveryAddress?->locality,
            WhatsappTemplateTagEnum::DELIVERY_POSTCODE => $customer?->deliveryAddress?->postal_code,
            WhatsappTemplateTagEnum::DELIVERY_COUNTRY  => $customer?->deliveryAddress?->country?->name,

            WhatsappTemplateTagEnum::PRODUCT_NAMES => $this->productNames($context),
            WhatsappTemplateTagEnum::PRODUCT_COUNT => $this->products($context)->count() ?: null,
            WhatsappTemplateTagEnum::PRODUCT_URL   => $this->products($context)->first()?->webpage?->getCanonicalUrl(),

            WhatsappTemplateTagEnum::TRACKING_CARRIER => $this->carrierNames($order),
            WhatsappTemplateTagEnum::TRACKING_NUMBER  => $this->trackingNumbers($order),
            WhatsappTemplateTagEnum::TRACKING_URL     => $this->trackingUrls($order),

            WhatsappTemplateTagEnum::AGENT_NAME       => $agent?->user?->contact_name,
            WhatsappTemplateTagEnum::AGENT_FIRST_NAME => $this->firstWord($agent?->user?->contact_name),
        };
    }

    public const PRODUCT_NAMES_MAX_LENGTH = 400;

    /**
     * @param  array{product_ids?: array<int, int>}  $context
     * @return Collection<int, Product>
     */
    protected function products(array $context): Collection
    {
        $productIds = array_values(array_unique(array_map('intval', Arr::get($context, 'product_ids', []))));

        if (!$productIds) {
            return collect();
        }

        return Product::whereIn('id', $productIds)->with('webpage')->get()
            ->sortBy(fn (Product $product) => array_search($product->id, $productIds))
            ->values();
    }

    /**
     * A long list is cut on a whole name and closed with how many were left out, so the
     * parameter stays well inside what WhatsApp accepts and never ends mid word.
     *
     * @param  array{product_ids?: array<int, int>}  $context
     */
    protected function productNames(array $context): ?string
    {
        $names = $this->products($context)->pluck('name')->filter()->values();

        if ($names->isEmpty()) {
            return null;
        }

        $shown = [];
        foreach ($names as $name) {
            if ($shown && mb_strlen(implode(', ', [...$shown, $name])) > self::PRODUCT_NAMES_MAX_LENGTH) {
                break;
            }
            $shown[] = $name;
        }

        $left = $names->count() - count($shown);

        return implode(', ', $shown).($left ? ' '.__('and :count more', ['count' => $left]) : '');
    }

    /**
     * @param  array{order_ids?: array<int, int>}  $context
     */
    protected function orderReferences(array $context): ?string
    {
        $orderIds = array_values(array_unique(array_map('intval', Arr::get($context, 'order_ids', []))));

        if (!$orderIds) {
            return null;
        }

        return Order::whereIn('id', $orderIds)->orderBy('id')->pluck('reference')->filter()->implode(', ') ?: null;
    }

    protected function firstWord(?string $value): ?string
    {
        return Str::of((string) $value)->trim()->explode(' ')->first() ?: null;
    }

    /**
     * WhatsApp refuses parameters containing newlines or tabs, and an address is stored
     * across several lines, so it is flattened into one.
     */
    protected function deliveryAddress(?Customer $customer): ?string
    {
        $address = $customer?->deliveryAddress;

        if (!$address) {
            return null;
        }

        return collect([
            $address->address_line_1,
            $address->address_line_2,
            $address->locality,
            $address->postal_code,
            $address->country?->name,
        ])->filter()->implode(', ');
    }

    protected function money($amount, $shop): ?string
    {
        if ($amount === null) {
            return null;
        }

        return ($shop?->currency?->symbol ?? '').number_format((float) $amount, 2);
    }

    protected function lastInvoice(?Customer $customer)
    {
        return $customer?->invoices()->latest('id')->first();
    }

    /**
     * A WhatsApp thread is identified by a phone number, so it may well belong to nobody
     * we know. The profile name WhatsApp sends is kept as the last resort.
     */
    protected function customerName(MetaChatSession $metaChatSession, ?Customer $customer): ?string
    {
        return $customer?->contact_name
            ?? $customer?->name
            ?? $metaChatSession->guest_identifier;
    }

    protected function customer(MetaChatSession $metaChatSession): ?Customer
    {
        return $metaChatSession->customer;
    }

    protected function shopUrl(MetaChatSession $metaChatSession): ?string
    {
        $domain = $metaChatSession->shop?->website?->domain;

        return $domain ? 'https://'.ltrim($domain, '/') : null;
    }

    /**
     * @return Collection<int, Shipment>
     */
    protected function shipments(?Order $order): Collection
    {
        $deliveryNote = $order?->currentDeliveryNote();

        return $deliveryNote ? $deliveryNote->shipments()->with('shipper')->get() : collect();
    }

    protected function carrierNames(?Order $order): ?string
    {
        return $this->shipments($order)
            ->map(fn (Shipment $shipment) => $shipment->shipper?->trade_as ?: $shipment->shipper?->name)
            ->filter()
            ->unique()
            ->implode(', ') ?: null;
    }

    protected function trackingNumbers(?Order $order): ?string
    {
        return $this->shipments($order)
            ->flatMap(fn (Shipment $shipment) => $shipment->trackings ?: [$shipment->tracking])
            ->filter()
            ->unique()
            ->implode(', ') ?: null;
    }

    /**
     * WhatsApp refuses newlines in a parameter, so an order shipped in several parcels
     * carries every link on one line.
     */
    protected function trackingUrls(?Order $order): ?string
    {
        return $this->shipments($order)
            ->flatMap(fn (Shipment $shipment) => Arr::wrap($shipment->tracking_urls))
            ->filter()
            ->unique()
            ->implode(' ') ?: null;
    }

    protected function lastOrder(?Customer $customer)
    {
        return $customer?->orders()->latest('id')->first();
    }

}
