<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\WhatsappCampaign;

use App\Enums\CRM\Livechat\WhatsappTemplateTagEnum;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Narrows a campaign audience to the contacts its template can actually be filled in for.
 *
 * WhatsApp rejects a blank parameter, so SendWhatsappDeliveryChannel drops a recipient whose
 * merge tags cannot all be resolved and records a Missing tags failure instead. Without this
 * the picker happily offers those contacts and the campaign only fails once it is sending.
 *
 * The SQL here mirrors ResolveWhatsappTemplateTags rather than reimplementing it: each tag is
 * reduced to the one column or relation its resolution reads, and a row is kept only when
 * every one of them has a value. The two have to be read together, a resolver change that
 * adds a fallback or a lookup needs the matching clause loosened or added here.
 */
class FilterRecipientsByTemplateTags
{
    use AsObject;

    /**
     * The audience arrives grouped by recipient_key, so customer_id and name are aggregates
     * and cannot be referenced from that query's own where clause. Wrapping it puts them back
     * in scope as plain columns, and leaves GetWhatsappRecipientsQuery untouched.
     *
     * @param  array<int, string>  $tags
     */
    public function handle(Builder $recipients, array $tags): Builder
    {
        if (!$tags) {
            return $recipients;
        }

        $filtered = DB::query()->fromSub($recipients, 'recipients')->select('recipients.*');

        foreach (array_unique($tags) as $tag) {
            $case = WhatsappTemplateTagEnum::tryFrom($tag);

            /* A tag the enum no longer knows resolves to null for everybody, the same way
               ResolveWhatsappTemplateTags treats a failed tryFrom. */
            if (!$case) {
                return $filtered->whereRaw('1 = 0');
            }

            $this->applyTag($filtered, $case);
        }

        return $filtered;
    }

    /**
     * The clause is applied to the grouped union, whose customer_id is the max() over the
     * branches, so a recipient reachable through any branch as a customer is judged on that
     * customer. A guest keeps a null customer_id and fails every customer scoped tag.
     */
    private function applyTag(Builder $recipients, WhatsappTemplateTagEnum $tag): void
    {
        match ($tag) {
            /* Shop level, identical for every recipient in the audience. A shop missing its
               own name or url would fail the whole campaign rather than single contacts, so
               it is not worth a per row clause. */
            WhatsappTemplateTagEnum::SHOP_NAME,
            WhatsappTemplateTagEnum::SHOP_URL,
            WhatsappTemplateTagEnum::SHOP_EMAIL,
            WhatsappTemplateTagEnum::SHOP_PHONE => null,

            /* Every audience row already carries a sendable phone, and the resolver falls
               back to the session's number when there is no customer. */
            WhatsappTemplateTagEnum::CUSTOMER_PHONE => null,

            /* Falls back through customer name to the WhatsApp profile label, which the union
               has already coalesced into the name column. */
            WhatsappTemplateTagEnum::CUSTOMER_NAME,
            WhatsappTemplateTagEnum::CUSTOMER_FIRST_NAME => $this->requireColumn($recipients, 'name'),

            WhatsappTemplateTagEnum::CUSTOMER_COMPANY       => $this->requireCustomerColumn($recipients, 'company_name'),
            WhatsappTemplateTagEnum::CUSTOMER_EMAIL         => $this->requireCustomerColumn($recipients, 'email'),
            WhatsappTemplateTagEnum::CUSTOMER_REFERENCE     => $this->requireCustomerColumn($recipients, 'reference'),
            WhatsappTemplateTagEnum::CUSTOMER_REGISTER_DATE => $this->requireCustomerColumn($recipients, 'created_at'),
            WhatsappTemplateTagEnum::CUSTOMER_BALANCE       => $this->requireCustomerColumn($recipients, 'balance'),

            /* State and item count are non null on any order row, so the order existing is
               the whole requirement. */
            WhatsappTemplateTagEnum::ORDER_STATE,
            WhatsappTemplateTagEnum::ORDER_ITEMS_COUNT => $this->requireLatestRowColumn($recipients, 'orders', null),

            WhatsappTemplateTagEnum::ORDER_NUMBER => $this->requireLatestRowColumn($recipients, 'orders', 'reference'),
            WhatsappTemplateTagEnum::ORDER_TOTAL  => $this->requireLatestRowColumn($recipients, 'orders', 'total_amount'),
            WhatsappTemplateTagEnum::ORDER_DATE   => $this->requireLatestRowColumn($recipients, 'orders', 'date'),

            WhatsappTemplateTagEnum::INVOICE_NUMBER => $this->requireLatestRowColumn($recipients, 'invoices', 'reference'),
            WhatsappTemplateTagEnum::INVOICE_TOTAL  => $this->requireLatestRowColumn($recipients, 'invoices', 'total_amount'),
            WhatsappTemplateTagEnum::INVOICE_DATE   => $this->requireLatestRowColumn($recipients, 'invoices', 'date'),

            /* The resolver builds the address from whichever of its parts are present, so any
               one of them carries it. The narrower parts are each required on their own. */
            WhatsappTemplateTagEnum::DELIVERY_ADDRESS  => $this->requireDeliveryAddress($recipients, null),
            WhatsappTemplateTagEnum::DELIVERY_TOWN     => $this->requireDeliveryAddress($recipients, 'locality'),
            WhatsappTemplateTagEnum::DELIVERY_POSTCODE => $this->requireDeliveryAddress($recipients, 'postal_code'),
            WhatsappTemplateTagEnum::DELIVERY_COUNTRY  => $this->requireDeliveryAddress($recipients, 'country_id'),

            /* Resolved from the agent handling a conversation. A campaign send has no agent,
               so these are missing for every recipient and the template can reach nobody. */
            WhatsappTemplateTagEnum::AGENT_NAME,
            WhatsappTemplateTagEnum::AGENT_FIRST_NAME => $recipients->whereRaw('1 = 0'),
        };
    }

    /**
     * blank() rejects an empty string as well as null, so the SQL has to do the same or the
     * picker would offer a contact the send then drops.
     */
    private function requireColumn(Builder $recipients, string $column): void
    {
        $recipients->whereRaw(sprintf("coalesce(btrim(recipients.%s::text), '') <> ''", $column));
    }

    private function requireCustomerColumn(Builder $recipients, string $column): void
    {
        $recipients->whereExists(
            fn (Builder $query) => $query
                ->from('customers')
                ->whereColumn('customers.id', 'recipients.customer_id')
                ->whereRaw(sprintf("coalesce(btrim(customers.%s::text), '') <> ''", $column))
        );
    }

    /**
     * The resolver reads latest('id') and stops there, so a customer whose newest row lacks
     * the column is missing the tag however many older rows carry it.
     *
     * That row has to be picked before the column is tested, not alongside it: ordering and
     * limiting in the same query as the column filter would pick the newest row that happens
     * to have a value and answer a question the resolver never asks.
     */
    private function requireLatestRowColumn(Builder $recipients, string $table, ?string $column): void
    {
        if (!$column) {
            $recipients->whereExists(
                fn (Builder $query) => $query
                    ->from($table)
                    ->whereColumn($table.'.customer_id', 'recipients.customer_id')
            );

            return;
        }

        $latest = DB::query()
            ->from($table)
            ->select($column)
            ->whereColumn($table.'.customer_id', 'recipients.customer_id')
            ->orderByDesc($table.'.id')
            ->limit(1);

        $recipients->whereExists(
            fn (Builder $query) => $query
                ->fromSub($latest, 'latest')
                ->whereRaw(sprintf("coalesce(btrim(latest.%s::text), '') <> ''", $column))
        );
    }

    /**
     * A null column means the tag has no value; for the whole address it means the row has
     * nothing to build one from.
     */
    private function requireDeliveryAddress(Builder $recipients, ?string $column): void
    {
        $recipients->whereExists(function (Builder $query) use ($column) {
            $query->from('customers')
                ->join('addresses', 'addresses.id', '=', 'customers.delivery_address_id')
                ->whereColumn('customers.id', 'recipients.customer_id');

            if ($column) {
                $query->whereRaw(sprintf("coalesce(btrim(addresses.%s::text), '') <> ''", $column));

                return;
            }

            $query->where(function (Builder $query) {
                foreach (['address_line_1', 'address_line_2', 'locality', 'postal_code', 'country_id'] as $part) {
                    $query->orWhereRaw(sprintf("coalesce(btrim(addresses.%s::text), '') <> ''", $part));
                }
            });
        });
    }
}
