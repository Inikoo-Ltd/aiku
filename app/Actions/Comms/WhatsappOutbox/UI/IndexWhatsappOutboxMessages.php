<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\WhatsappOutbox\UI;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Chat\MetaChatMessage;
use App\Models\Comms\WhatsappOutbox;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;

class IndexWhatsappOutboxMessages extends OrgAction
{
    public function handle(WhatsappOutbox $whatsappOutbox, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('customers.name', $value)
                    ->orWhereStartWith('meta_chat_sessions.phone_number', $value)
                    ->orWhereStartWith('orders.reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return QueryBuilder::for(MetaChatMessage::class)
            ->leftJoin('meta_chat_sessions', 'meta_chat_sessions.id', '=', 'meta_chat_messages.meta_chat_session_id')
            ->leftJoin('customers', 'customers.id', '=', 'meta_chat_sessions.customer_id')
            ->leftJoin('orders', 'orders.id', '=', DB::raw("(meta_chat_messages.metadata->>'order_id')::int"))
            ->where('meta_chat_messages.whatsapp_outbox_id', $whatsappOutbox->id)
            ->defaultSort('-meta_chat_messages.created_at')
            ->select([
                'meta_chat_messages.id',
                'meta_chat_messages.state',
                'meta_chat_messages.message_text',
                'meta_chat_messages.created_at as sent_at',
                'meta_chat_messages.delivered_at',
                'meta_chat_messages.read_at',
                DB::raw("meta_chat_messages.metadata->'wa_error'->>'message' as error"),
                'meta_chat_sessions.phone_number',
                'customers.name as customer_name',
                'customers.slug as customer_slug',
                'orders.reference as order_reference',
                'orders.slug as order_slug',
            ])
            ->allowedSorts([
                'sent_at' => 'meta_chat_messages.created_at',
                'state'   => 'meta_chat_messages.state',
                'customer_name',
            ])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure($prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->column(key: 'state', label: __('State'), canBeHidden: false, sortable: true)
                ->column(key: 'sent_at', label: __('Sent Date'), canBeHidden: false, sortable: true)
                ->column(key: 'customer_name', label: __('Customer'), canBeHidden: false, sortable: true)
                ->column(key: 'phone_number', label: __('WhatsApp'), canBeHidden: false)
                ->column(key: 'order_reference', label: __('Order'), canBeHidden: false)
                ->column(key: 'message_text', label: __('Message'), canBeHidden: false)
                ->defaultSort('-sent_at');
        };
    }
}
