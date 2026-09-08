<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 02 Sep 2026 21:40:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\Employee\UI;

use App\InertiaTable\InertiaTable;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\StaffMessage;
use App\Models\Helpers\SearchLog;
use App\Models\SysAdmin\McpRequest;
use App\Models\SysAdmin\User;
use App\Models\Analytics\UserRequest;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\Concerns\AsObject;
use Spatie\QueryBuilder\AllowedFilter;

class IndexEmployeeUserActivity
{
    use AsObject;

    public function requests(User $user, string $prefix): LengthAwarePaginator
    {
        InertiaTable::updateQueryBuilderParameters($prefix);

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->whereRaw('user_requests.route_name ILIKE ?', ["%$value%"]);
        });

        return QueryBuilder::for(UserRequest::class)
            ->where('user_requests.user_id', $user->id)
            ->select([
                'user_requests.id',
                'user_requests.ip_address',
                'user_requests.route_name',
                'user_requests.route_params as arguments',
                'user_requests.date as datetime',
                'user_requests.location',
                'user_requests.device as device_type',
                'user_requests.os as platform',
                'user_requests.browser',
            ])
            ->selectRaw("'' as username")
            ->selectRaw("split_part(user_requests.route_name, '.', 2) as module")
            ->defaultSort('-datetime')
            ->allowedSorts(['route_name', 'module', 'ip_address', 'datetime'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function requestsTableStructure(string $prefix): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            $table
                ->name($prefix)
                ->pageName($prefix.'Page')
                ->withGlobalSearch()
                ->withLabelRecord([__('request'), __('requests')])
                ->column(key: 'datetime', label: __('Date'), canBeHidden: false, sortable: true)
                ->column(key: 'route_name', label: __('Page'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'module', label: __('Module'), canBeHidden: false, sortable: true)
                ->column(key: 'ip_address', label: __('IP'), canBeHidden: false, sortable: true)
                ->column(key: 'location', label: __('Location'), canBeHidden: false)
                ->column(key: 'user_agent', label: __('Device'), canBeHidden: false)
                ->defaultSort('-datetime');
        };
    }

    public function searches(User $user, string $prefix): LengthAwarePaginator
    {
        InertiaTable::updateQueryBuilderParameters($prefix);

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->whereRaw('search_logs.query ILIKE ?', ["%$value%"]);
        });

        return QueryBuilder::for(SearchLog::class)
            ->where('search_logs.user_id', $user->id)
            ->select([
                'search_logs.id',
                'search_logs.query',
                'search_logs.scope',
                'search_logs.results_count',
                'search_logs.clicked_url',
                'search_logs.clicked_at',
                'search_logs.created_at',
            ])
            ->defaultSort('-created_at')
            ->allowedSorts(['query', 'scope', 'results_count', 'clicked_at', 'created_at'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function searchesTableStructure(string $prefix): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            $table
                ->name($prefix)
                ->pageName($prefix.'Page')
                ->withGlobalSearch()
                ->withLabelRecord([__('search'), __('searches')])
                ->column(key: 'created_at', label: __('Date'), canBeHidden: false, sortable: true)
                ->column(key: 'query', label: __('Query'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'scope', label: __('Section'), canBeHidden: false, sortable: true)
                ->column(key: 'results_count', label: __('Results'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'clicked_at', label: __('Clicked'), canBeHidden: false, sortable: true)
                ->defaultSort('-created_at');
        };
    }

    public function chats(User $user, string $prefix): LengthAwarePaginator
    {
        InertiaTable::updateQueryBuilderParameters($prefix);

        $internal = StaffMessage::query()
            ->join('staff_conversations', 'staff_conversations.id', '=', 'staff_messages.staff_conversation_id')
            ->where('staff_messages.user_id', $user->id)
            ->selectRaw("staff_messages.id, 'internal' as channel, staff_messages.body as text, coalesce(staff_conversations.name, staff_conversations.type) as conversation, staff_conversations.ulid as conversation_ulid, staff_messages.created_at");

        $external = ChatMessage::query()
            ->join('chat_agents', 'chat_agents.id', '=', 'chat_messages.sender_id')
            ->join('chat_sessions', 'chat_sessions.id', '=', 'chat_messages.chat_session_id')
            ->where('chat_messages.sender_type', 'agent')
            ->where('chat_agents.user_id', $user->id)
            ->selectRaw("chat_messages.id, 'external' as channel, chat_messages.message_text as text, coalesce(chat_sessions.guest_identifier, chat_sessions.ulid) as conversation, chat_sessions.ulid as conversation_ulid, chat_messages.created_at");

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->whereRaw('messages.text ILIKE ?', ["%$value%"]);
        });

        return QueryBuilder::for(StaffMessage::query()->withoutGlobalScopes()->fromSub($internal->unionAll($external), 'messages'))
            ->select(['messages.id', 'messages.channel', 'messages.text', 'messages.conversation', 'messages.conversation_ulid', 'messages.created_at'])
            ->defaultSort('-created_at')
            ->allowedSorts(['channel', 'conversation', 'created_at'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function chatsTableStructure(string $prefix): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            $table
                ->name($prefix)
                ->pageName($prefix.'Page')
                ->withGlobalSearch()
                ->withLabelRecord([__('message'), __('messages')])
                ->column(key: 'created_at', label: __('Date'), canBeHidden: false, sortable: true)
                ->column(key: 'channel', label: __('Channel'), canBeHidden: false, sortable: true)
                ->column(key: 'conversation', label: __('Conversation'), canBeHidden: false, sortable: true)
                ->column(key: 'text', label: __('Message'), canBeHidden: false, searchable: true)
                ->defaultSort('-created_at');
        };
    }

    public function aiQueries(User $user, string $prefix): LengthAwarePaginator
    {
        InertiaTable::updateQueryBuilderParameters($prefix);

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereRaw('mcp_requests.tool ILIKE ?', ["%$value%"])
                    ->orWhereRaw('mcp_requests.arguments::text ILIKE ?', ["%$value%"]);
            });
        });

        return QueryBuilder::for(McpRequest::class)
            ->where('mcp_requests.user_id', $user->id)
            ->select([
                'mcp_requests.id',
                'mcp_requests.tool',
                'mcp_requests.arguments',
                'mcp_requests.is_error',
                'mcp_requests.duration_ms',
                'mcp_requests.created_at',
            ])
            ->selectRaw('null as username, false as can_use_mcp_sql')
            ->defaultSort('-created_at')
            ->allowedSorts(['tool', 'is_error', 'duration_ms', 'created_at'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function aiQueriesTableStructure(string $prefix): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            $table
                ->name($prefix)
                ->pageName($prefix.'Page')
                ->withGlobalSearch()
                ->withLabelRecord([__('query'), __('queries')])
                ->column(key: 'created_at', label: __('Date'), canBeHidden: false, sortable: true)
                ->column(key: 'tool', label: __('Tool'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'arguments', label: __('Arguments'), canBeHidden: false, searchable: true)
                ->column(key: 'is_error', label: __('Result'), canBeHidden: false, sortable: true)
                ->column(key: 'duration_ms', label: __('Duration'), canBeHidden: false, sortable: true, align: 'right')
                ->defaultSort('-created_at');
        };
    }
}
