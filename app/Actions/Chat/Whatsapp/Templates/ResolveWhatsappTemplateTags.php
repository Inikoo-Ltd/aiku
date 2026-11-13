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
use App\Actions\Comms\Email\SendNewOrderEmailToCustomer;
use App\Actions\Comms\Email\SendPreOrderUpdateEmail;
use App\Models\Accounting\CreditTransaction;
use App\Models\Catalogue\Product;
use App\Models\Ordering\PreOrder;
use App\Models\Dispatching\Shipment;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsObject;
use Throwable;

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
     * @param  array{product_ids?: array<int, int>, order_ids?: array<int, int>, pre_order_id?: int, pre_order_update?: string, credit_transaction_id?: int, chat_url?: string|null}  $context
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
            WhatsappTemplateTagEnum::SHOP_BANK_DETAILS => $this->bankDetails($shop),

            WhatsappTemplateTagEnum::ORDER_NUMBER      => $this->orderReferences($context) ?? $order?->reference,
            WhatsappTemplateTagEnum::ORDER_TOTAL       => $this->money($order?->total_amount, $shop),
            WhatsappTemplateTagEnum::ORDER_DATE        => $order?->date?->format('j M Y'),
            WhatsappTemplateTagEnum::ORDER_STATE       => Str::headline((string) $order?->state?->value),
            WhatsappTemplateTagEnum::ORDER_ITEMS_COUNT => $order?->number_item_transactions,
            WhatsappTemplateTagEnum::ORDER_TIME        => $this->localTime($order?->submitted_at ?? $order?->created_at, $shop),
            WhatsappTemplateTagEnum::ORDER_URL         => $this->orderUrl($order),
            WhatsappTemplateTagEnum::ORDER_AMOUNT_DUE  => $order ? $this->money(max(0, round((float) $order->total_amount - (float) $order->payment_amount, 2)), $shop) : null,
            WhatsappTemplateTagEnum::ORDER_PAYMENT_DEADLINE => $this->paymentDeadline($order),
            WhatsappTemplateTagEnum::ORDER_DISPATCH_DATE    => $order?->dispatched_at?->format('j M Y'),

            WhatsappTemplateTagEnum::PRE_ORDER_UPDATE           => $this->preOrderUpdate($context, $shop),
            WhatsappTemplateTagEnum::PRE_ORDER_BALANCE_DUE_DATE => $this->preOrder($context)?->balance_due_at?->format('j M Y'),
            WhatsappTemplateTagEnum::PRE_ORDER_DISPATCH_DATES   => $this->preOrderDispatchDates($this->preOrder($context)),

            WhatsappTemplateTagEnum::CREDIT_AMOUNT => $this->creditTransaction($context) ? $this->money(abs((float) $this->creditTransaction($context)->amount), $shop) : null,
            WhatsappTemplateTagEnum::CREDIT_REASON => $this->creditTransaction($context)?->reason?->label() ?? $this->creditTransaction($context)?->notes,

            WhatsappTemplateTagEnum::CHAT_URL => Arr::get($context, 'chat_url'),

            WhatsappTemplateTagEnum::INVOICE_NUMBER => $this->lastInvoice($customer)?->reference,
            WhatsappTemplateTagEnum::INVOICE_TOTAL  => $this->money($this->lastInvoice($customer)?->total_amount, $shop),
            WhatsappTemplateTagEnum::INVOICE_DATE   => $this->lastInvoice($customer)?->date?->format('j M Y'),

            WhatsappTemplateTagEnum::DELIVERY_ADDRESS  => $this->deliveryAddress($customer),
            WhatsappTemplateTagEnum::DELIVERY_TOWN     => $customer?->deliveryAddress?->locality,
            WhatsappTemplateTagEnum::DELIVERY_POSTCODE => $customer?->deliveryAddress?->postal_code,
            WhatsappTemplateTagEnum::DELIVERY_COUNTRY  => $customer?->deliveryAddress?->country?->name,

            WhatsappTemplateTagEnum::PRODUCT_NAMES => $this->productNames($context, $order),
            WhatsappTemplateTagEnum::PRODUCT_COUNT => $this->products($context, $order)->count() ?: null,
            WhatsappTemplateTagEnum::PRODUCT_URL   => $this->products($context, $order)->first()?->webpage?->getCanonicalUrl(),

            WhatsappTemplateTagEnum::TRACKING_CARRIER => $this->carrierNames($order),
            WhatsappTemplateTagEnum::TRACKING_NUMBER  => $this->trackingNumbers($order),
            WhatsappTemplateTagEnum::TRACKING_URL     => $this->trackingUrls($order),

            WhatsappTemplateTagEnum::AGENT_NAME       => $agent?->user?->contact_name,
            WhatsappTemplateTagEnum::AGENT_FIRST_NAME => $this->firstWord($agent?->user?->contact_name),
        };
    }

    public const PRODUCT_NAMES_MAX_LENGTH = 400;

    /**
     * The products the notification is about: the ones it was given, or for a pre-order the
     * products on its order.
     *
     * @param  array{product_ids?: array<int, int>, pre_order_id?: int}  $context
     * @return Collection<int, Product>
     */
    protected function products(array $context, ?Order $order = null): Collection
    {
        $productIds = array_values(array_unique(array_map('intval', Arr::get($context, 'product_ids', []))));

        if (!$productIds && $order && Arr::has($context, 'pre_order_id')) {
            $productIds = $order->transactions()
                ->where('model_type', class_basename(Product::class))
                ->orderBy('id')
                ->pluck('model_id')
                ->unique()
                ->values()
                ->all();
        }

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
     * @param  array{product_ids?: array<int, int>, pre_order_id?: int}  $context
     */
    protected function productNames(array $context, ?Order $order = null): ?string
    {
        $names = $this->products($context, $order)->pluck('name')->filter()->values();

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
     * The shop's bank transfer instructions, the same text the order confirmation email shows,
     * flattened to one line because WhatsApp refuses newlines and long runs of spaces.
     */
    protected function bankDetails($shop): ?string
    {
        $instructions = (string) Arr::get($shop?->settings ?? [], 'bank_transfer_instructions_for_email', '');

        $text = html_entity_decode(strip_tags(preg_replace('/<(br|\/p|\/div|\/li)\b[^>]*>/i', ', ', $instructions)), ENT_QUOTES | ENT_HTML5);
        $text = preg_replace('/\s*,\s*(,\s*)+/', ', ', preg_replace('/\s+/u', ' ', $text));

        return trim((string) $text, " ,") ?: null;
    }

    protected function localTime($moment, $shop): ?string
    {
        return $moment?->copy()->setTimezone($shop?->timezoneName() ?? config('app.timezone'))->format('H:i');
    }

    /**
     * The day before an unpaid bank transfer order is cancelled, the date the final payment
     * reminder email gives.
     */
    protected function paymentDeadline(?Order $order): ?string
    {
        if (!$order?->submitted_at || !$order->shop) {
            return null;
        }

        return $order->submitted_at->copy()
            ->addDays((int) $order->shop->unpaidBankOrderSetting('cancel_after_days') - 1)
            ->format('j M Y');
    }

    /**
     * @param  array{pre_order_id?: int}  $context
     */
    protected function preOrder(array $context): ?PreOrder
    {
        $preOrderId = Arr::get($context, 'pre_order_id');

        return $preOrderId ? PreOrder::find($preOrderId) : null;
    }

    protected function preOrderDispatchDates(?PreOrder $preOrder): ?string
    {
        $from = $preOrder?->estimated_dispatch_from?->format('j M Y');
        $to   = $preOrder?->estimated_dispatch_to?->format('j M Y');

        return match (true) {
            $from && $to && $from !== $to => __(':from to :to', ['from' => $from, 'to' => $to]),
            default                       => $from ?? $to,
        };
    }

    /**
     * One pre-order outbox carries four kinds of update, so what changed is a short factual
     * phrase the template places in its sentence.
     *
     * @param  array{pre_order_id?: int, pre_order_update?: string}  $context
     */
    protected function preOrderUpdate(array $context, $shop): ?string
    {
        $preOrder = $this->preOrder($context);

        if (!$preOrder) {
            return null;
        }

        $order     = $preOrder->order;
        $amountDue = $this->money(max(0, round((float) $order?->total_amount - (float) $order?->payment_amount, 2)), $shop);
        $dueDate   = $preOrder->balance_due_at?->format('j M Y');

        return match (Arr::get($context, 'pre_order_update')) {
            SendPreOrderUpdateEmail::BALANCE_REQUEST       => $dueDate
                ? __('the balance of :amount is due by :date', ['amount' => $amountDue, 'date' => $dueDate])
                : __('the balance of :amount is now due', ['amount' => $amountDue]),
            SendPreOrderUpdateEmail::BALANCE_REMINDER      => $dueDate
                ? __('the balance of :amount is still due, by :date', ['amount' => $amountDue, 'date' => $dueDate])
                : __('the balance of :amount is still due', ['amount' => $amountDue]),
            SendPreOrderUpdateEmail::DISPATCH_DATE_CHANGED => $this->preOrderDispatchDates($preOrder)
                ? __('the estimated dispatch date is now :dates', ['dates' => $this->preOrderDispatchDates($preOrder)])
                : null,
            SendPreOrderUpdateEmail::CANCELLED             => __('the pre-order has been cancelled'),
            default                                        => null,
        };
    }

    /**
     * @param  array{credit_transaction_id?: int}  $context
     */
    protected function creditTransaction(array $context): ?CreditTransaction
    {
        $creditTransactionId = Arr::get($context, 'credit_transaction_id');

        return $creditTransactionId ? CreditTransaction::find($creditTransactionId) : null;
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

    public const TRACKING_NUMBER_PLACEHOLDER = '{tracking}';

    /**
     * Most carrier integrations store no link per parcel, so the link is built from the
     * shipper's tracking url when it holds the {tracking} placeholder, and an order nobody can
     * build a link for points to the order page, which shows its tracking. WhatsApp refuses
     * newlines in a parameter, so an order shipped in several parcels carries every link on
     * one line.
     */
    protected function trackingUrls(?Order $order): ?string
    {
        $links = $this->shipments($order)
            ->flatMap(fn (Shipment $shipment) => $this->shipmentTrackingUrls($shipment))
            ->filter()
            ->unique()
            ->implode(' ');

        return $links ?: $this->orderUrl($order);
    }

    /**
     * @return array<int, string>
     */
    protected function shipmentTrackingUrls(Shipment $shipment): array
    {
        $stored = array_values(array_filter(Arr::wrap($shipment->tracking_urls)));

        if ($stored) {
            return $stored;
        }

        $pattern = (string) $shipment->shipper?->tracking_url;

        if (!str_contains($pattern, self::TRACKING_NUMBER_PLACEHOLDER)) {
            return [];
        }

        return collect($shipment->trackings ?: [$shipment->tracking])
            ->filter()
            ->map(fn (string $tracking) => str_replace(self::TRACKING_NUMBER_PLACEHOLDER, rawurlencode($tracking), $pattern))
            ->values()
            ->all();
    }

    /**
     * The customer's own page for the order. A shop without a website, or an order the link
     * cannot be built for, has none.
     */
    protected function orderUrl(?Order $order): ?string
    {
        if (!$order?->shop?->website) {
            return null;
        }

        try {
            return SendNewOrderEmailToCustomer::make()->getOrderLink($order) ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    protected function lastOrder(?Customer $customer)
    {
        return $customer?->orders()->latest('id')->first();
    }

}
