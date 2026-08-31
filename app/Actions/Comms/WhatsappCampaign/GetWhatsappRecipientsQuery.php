<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\WhatsappCampaign;

use App\Models\Catalogue\Shop;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Builds the WhatsApp campaign audience as one row per phone number.
 *
 * The three audience channels live in different tables with unrelated id spaces, so the
 * normalised phone number is the only identity they share and it is what rows are grouped
 * and selected by. Grouping also collapses the several chat sessions a single person may
 * have into one recipient.
 *
 * Only the customers channel accepts the mailshot filter recipe: those filters are all
 * customers table joins and cannot describe a guest who only ever opened a chat.
 */
class GetWhatsappRecipientsQuery
{
    use AsObject;

    public const CHANNELS = ['contacted', 'subscriber', 'customers'];

    private const PHONE_KEY = "regexp_replace(%s, '[^0-9]', '', 'g')";

    /**
     * @param  array<string, bool>  $channels
     *
     * @throws \Exception
     */
    public function handle(Shop $shop, array $channels, array $customerFilters): Builder
    {
        $branches = [];

        if (data_get($channels, 'contacted')) {
            $branches[] = $this->contactedBranch($shop);
        }

        if (data_get($channels, 'subscriber')) {
            $branches[] = $this->whatsappSubscribersBranch($shop);
            $branches[] = $this->newsletterSubscribersBranch($shop);
        }

        if (data_get($channels, 'customers')) {
            $branches[] = $this->customersBranch($shop, $customerFilters);
        }

        if (empty($branches)) {
            return DB::query()->fromSub($this->emptyBranch(), 'recipients')->whereRaw('1 = 0');
        }

        $union = array_shift($branches);

        foreach ($branches as $branch) {
            $union->unionAll($branch);
        }

        return DB::query()
            ->fromSub($union, 'sources')
            ->groupBy('sources.recipient_key')
            ->select([
                'sources.recipient_key',
                DB::raw('max(sources.name) as name'),
                DB::raw('max(sources.phone_number) as phone_number'),
                DB::raw('max(sources.customer_id) as customer_id'),
                DB::raw('max(sources.meta_chat_session_id) as meta_chat_session_id'),
                DB::raw('max(sources.last_visitor_message_at) as last_visitor_message_at'),
                DB::raw('min(sources.created_at) as created_at'),
                DB::raw('bool_or(sources.is_contacted) as is_contacted'),
                DB::raw('bool_or(sources.is_subscriber) as is_subscriber'),
                DB::raw('bool_or(sources.is_customer) as is_customer'),
            ]);
    }

    private function phoneKey(string $column): string
    {
        return sprintf(self::PHONE_KEY, $column);
    }

    /**
     * The PHP twin of PHONE_KEY, kept beside it so the two definitions of a recipient key
     * stay in step. A stored selection is matched back against the audience by this value,
     * so anything written into recipients_recipe has to be reduced the same way the query
     * reduces the columns it groups by.
     */
    public static function normalisePhoneKey(?string $phone): string
    {
        return preg_replace('/[^0-9]/', '', (string) $phone);
    }

    /**
     * Every branch has to expose the same column list in the same order for the UNION ALL
     * to line up, so the flags are selected as literals rather than omitted.
     */
    private function branchColumns(string $phoneColumn, string $nameExpression, array $overrides = []): array
    {
        $columns = [
            'recipient_key'           => DB::raw($this->phoneKey($phoneColumn).' as recipient_key'),
            'name'                    => DB::raw($nameExpression.' as name'),
            'phone_number'            => DB::raw($phoneColumn.' as phone_number'),
            'customer_id'             => DB::raw('null::integer as customer_id'),
            'meta_chat_session_id'    => DB::raw('null::integer as meta_chat_session_id'),
            'last_visitor_message_at' => DB::raw('null::timestamptz as last_visitor_message_at'),
            'created_at'              => DB::raw('null::timestamptz as created_at'),
            'is_contacted'            => DB::raw('false as is_contacted'),
            'is_subscriber'           => DB::raw('false as is_subscriber'),
            'is_customer'             => DB::raw('false as is_customer'),
        ];

        return array_values(array_merge($columns, $overrides));
    }

    private function emptyBranch(): Builder
    {
        return DB::table('customers')
            ->whereRaw('1 = 0')
            ->select($this->branchColumns('customers.phone', 'customers.contact_name'));
    }

    private function contactedBranch(Shop $shop): Builder
    {
        return DB::table('meta_chat_sessions')
            ->leftJoin('customers', 'meta_chat_sessions.customer_id', '=', 'customers.id')
            ->where('meta_chat_sessions.shop_id', $shop->id)
            ->whereNotNull('meta_chat_sessions.phone_number')
            ->whereNull('meta_chat_sessions.deleted_at')
            ->select($this->branchColumns(
                'meta_chat_sessions.phone_number',
                'coalesce(customers.contact_name, meta_chat_sessions.guest_identifier)',
                [
                    'customer_id'             => DB::raw('meta_chat_sessions.customer_id as customer_id'),
                    'meta_chat_session_id'    => DB::raw('meta_chat_sessions.id as meta_chat_session_id'),
                    'last_visitor_message_at' => DB::raw('meta_chat_sessions.last_visitor_message_at as last_visitor_message_at'),
                    'created_at'              => DB::raw('meta_chat_sessions.created_at as created_at'),
                    'is_contacted'            => DB::raw('true as is_contacted'),
                ]
            ));
    }

    /**
     * whatsapp_subscribers carries no phone of its own, it is resolved through the
     * polymorphic parent using the morph aliases registered in AppServiceProvider.
     */
    private function whatsappSubscribersBranch(Shop $shop): Builder
    {
        return DB::table('whatsapp_subscribers')
            ->leftJoin('customers', function ($join) {
                $join->on('whatsapp_subscribers.parent_id', '=', 'customers.id')
                    ->where('whatsapp_subscribers.parent_type', '=', 'Customer');
            })
            ->leftJoin('meta_chat_sessions', function ($join) {
                $join->on('whatsapp_subscribers.parent_id', '=', 'meta_chat_sessions.id')
                    ->where('whatsapp_subscribers.parent_type', '=', 'MetaChatSession');
            })
            ->where('whatsapp_subscribers.shop_id', $shop->id)
            ->whereNull('whatsapp_subscribers.deleted_at')
            ->whereNotNull(DB::raw('coalesce(customers.phone, meta_chat_sessions.phone_number)'))
            ->select($this->branchColumns(
                'coalesce(customers.phone, meta_chat_sessions.phone_number)',
                'coalesce(customers.contact_name, meta_chat_sessions.guest_identifier)',
                [
                    'customer_id'          => DB::raw('customers.id as customer_id'),
                    'meta_chat_session_id' => DB::raw('meta_chat_sessions.id as meta_chat_session_id'),
                    'created_at'           => DB::raw('whatsapp_subscribers.created_at as created_at'),
                    'is_subscriber'        => DB::raw('true as is_subscriber'),
                ]
            ));
    }

    private function newsletterSubscribersBranch(Shop $shop): Builder
    {
        return DB::table('customers')
            ->join('customer_comms', 'customer_comms.customer_id', '=', 'customers.id')
            ->where('customers.shop_id', $shop->id)
            ->where('customer_comms.is_subscribed_to_whatsapp_newsletter', true)
            ->whereNotNull('customers.phone')
            ->whereNull('customers.deleted_at')
            ->select($this->branchColumns('customers.phone', 'customers.contact_name', [
                'customer_id'   => DB::raw('customers.id as customer_id'),
                'created_at'    => DB::raw('customers.created_at as created_at'),
                'is_subscriber' => DB::raw('true as is_subscriber'),
            ]));
    }

    /**
     * @throws \Exception
     */
    private function customersBranch(Shop $shop, array $customerFilters): Builder
    {
        $query = DB::table('customers')
            ->where('customers.shop_id', $shop->id)
            ->whereNotNull('customers.phone')
            ->whereNull('customers.deleted_at');

        if (!empty($customerFilters)) {
            $recipeQuery = GetWhatsappCustomersQueryByRecipe::run($shop->id, $customerFilters);

            $query->whereIn('customers.id', (clone $recipeQuery)->select('customers.id'));
        }

        return $query->select($this->branchColumns('customers.phone', 'customers.contact_name', [
            'customer_id' => DB::raw('customers.id as customer_id'),
            'created_at'  => DB::raw('customers.created_at as created_at'),
            'is_customer' => DB::raw('true as is_customer'),
        ]));
    }
}
