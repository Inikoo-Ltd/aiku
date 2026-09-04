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
     * @return array{values: array<int, string>, missing: array<int, string>}
     */
    public function handle(MetaChatSession $metaChatSession, array $tags, ?ChatAgent $agent = null): array
    {
        $values  = [];
        $missing = [];

        foreach ($tags as $tag) {
            $case = WhatsappTemplateTagEnum::tryFrom($tag);
            $value = $case ? $this->resolve($case, $metaChatSession, $agent) : null;

            if (blank($value)) {
                $missing[] = $tag;
                $values[]  = null;
            } else {
                $values[] = (string) $value;
            }
        }

        return ['values' => $values, 'missing' => $missing];
    }

    protected function resolve(WhatsappTemplateTagEnum $tag, MetaChatSession $metaChatSession, ?ChatAgent $agent): ?string
    {
        $customer = $this->customer($metaChatSession);
        $shop     = $metaChatSession->shop;

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

            WhatsappTemplateTagEnum::ORDER_NUMBER      => $this->lastOrder($customer)?->reference,
            WhatsappTemplateTagEnum::ORDER_TOTAL       => $this->money($this->lastOrder($customer)?->total_amount, $shop),
            WhatsappTemplateTagEnum::ORDER_DATE        => $this->lastOrder($customer)?->date?->format('j M Y'),
            WhatsappTemplateTagEnum::ORDER_STATE       => Str::headline((string) $this->lastOrder($customer)?->state?->value),
            WhatsappTemplateTagEnum::ORDER_ITEMS_COUNT => $this->lastOrder($customer)?->number_item_transactions,

            WhatsappTemplateTagEnum::INVOICE_NUMBER => $this->lastInvoice($customer)?->reference,
            WhatsappTemplateTagEnum::INVOICE_TOTAL  => $this->money($this->lastInvoice($customer)?->total_amount, $shop),
            WhatsappTemplateTagEnum::INVOICE_DATE   => $this->lastInvoice($customer)?->date?->format('j M Y'),

            WhatsappTemplateTagEnum::DELIVERY_ADDRESS  => $this->deliveryAddress($customer),
            WhatsappTemplateTagEnum::DELIVERY_TOWN     => $customer?->deliveryAddress?->locality,
            WhatsappTemplateTagEnum::DELIVERY_POSTCODE => $customer?->deliveryAddress?->postal_code,
            WhatsappTemplateTagEnum::DELIVERY_COUNTRY  => $customer?->deliveryAddress?->country?->name,

            WhatsappTemplateTagEnum::AGENT_NAME       => $agent?->user?->contact_name,
            WhatsappTemplateTagEnum::AGENT_FIRST_NAME => $this->firstWord($agent?->user?->contact_name),
        };
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

    protected function lastOrder(?Customer $customer)
    {
        return $customer?->orders()->latest('id')->first();
    }

}
