<?php

namespace App\Actions\CRM\ChatSession;

use App\Actions\OrgAction;
use App\Http\Resources\CRM\Livechat\ChatSessionResource;
use App\InertiaTable\InertiaTable;
use App\Models\CRM\Livechat\ChatSession;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class IndexChatConversations extends OrgAction
{
    use AsAction;

    public function handle(Organisation $organisation, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->where('chat_sessions.guest_identifier', 'ILIKE', "%$value%")
                    ->orWhereHas('webUser', function ($q) use ($value) {
                        $q->where('username', 'ILIKE', "%$value%")
                            ->orWhere('email', 'ILIKE', "%$value%");
                    });
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return QueryBuilder::for(ChatSession::class)
            ->whereHas('shop', function ($q) use ($organisation) {
                $q->where('organisation_id', $organisation->id);
            })
            ->whereHas('messages')
            ->leftJoin('web_users', 'chat_sessions.web_user_id', '=', 'web_users.id')
            ->leftJoin('shops', 'chat_sessions.shop_id', '=', 'shops.id')
            ->with([
                'shop',
                'webUser.customer',
                'assignments.chatAgent.user',
            ])
            ->withLastMessageTime()
            ->defaultSort('-created_at')
            ->select([
                'chat_sessions.id',
                'chat_sessions.ulid',
                'chat_sessions.status',
                'chat_sessions.guest_identifier',
                'chat_sessions.created_at',
                'chat_sessions.closed_at',
                'chat_sessions.priority',
                'chat_sessions.web_user_id',
                'chat_sessions.shop_id',
                'chat_sessions.metadata',
            ])
            ->allowedSorts([
                AllowedSort::field('created_at', 'chat_sessions.created_at'),
                AllowedSort::field('closed_at', 'chat_sessions.closed_at'),
                AllowedSort::field('status', 'chat_sessions.status'),
            ])
            ->allowedFilters([
                $globalSearch,
                AllowedFilter::exact('status'),
            ])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix . 'Page');
            }

            $table
                ->withGlobalSearch()
                ->withLabelRecord([__('conversation'), __('conversations')])
                ->withEmptyState([
                    'title' => __('No conversations yet'),
                    'count' => 0,
                ]);

            $table
                ->column(key: 'status', label: __('Status'), canBeHidden: false, sortable: true)
                ->column(key: 'contact', label: __('Contact'), canBeHidden: false)
                ->column(key: 'shop_name', label: __('Shop'), canBeHidden: false)
                ->column(key: 'assigned_agent', label: __('Agent'), canBeHidden: true)
                ->column(key: 'ai_summary', label: __('Summary'), canBeHidden: true)
                ->column(key: 'created_at', label: __('Started'), canBeHidden: false, sortable: true)
                ->column(key: 'closed_at', label: __('Closed'), canBeHidden: true, sortable: true);

            $table->defaultSort('-created_at');
        };
    }

    public function jsonResponse(LengthAwarePaginator $chatSessions): AnonymousResourceCollection
    {
        return ChatSessionResource::collection($chatSessions);
    }
}
