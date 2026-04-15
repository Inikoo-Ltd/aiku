<?php

namespace App\Actions\CRM\ChatSession\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMAuthorisation;
use App\Http\Resources\CRM\Livechat\ChatSessionResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Livechat\ChatSession;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexChatSessions extends OrgAction
{
    use WithCRMAuthorisation;

    private Shop $parent;

    public function handle(Shop $parent, $prefix = null): LengthAwarePaginator
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

        $queryBuilder = QueryBuilder::for(ChatSession::class)
            ->where('chat_sessions.shop_id', $parent->id)
            ->leftJoin('web_users', 'chat_sessions.web_user_id', '=', 'web_users.id')
            ->with([
                'webUser.customer',
                'assignments.chatAgent.user',
            ])
            ->withLastMessageTime();

        return $queryBuilder
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
            ])
            ->allowedSorts(['created_at', 'closed_at', 'status'])
            ->allowedFilters([
                $globalSearch,
                AllowedFilter::exact('status'),
            ])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Shop $parent, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix . 'Page');
            }

            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withLabelRecord([__('chat session'), __('chat sessions')])
                ->withEmptyState([
                    'title' => __('No chat sessions yet'),
                    'count' => 0,
                ]);

            $table
                ->column(key: 'status', label: __('Status'), canBeHidden: false, sortable: true)
                ->column(key: 'contact', label: __('Contact'), canBeHidden: false)
                ->column(key: 'assigned_agent', label: __('Agent'), canBeHidden: true)
                ->column(key: 'created_at', label: __('Started'), canBeHidden: false, sortable: true)
                ->column(key: 'closed_at', label: __('Closed'), canBeHidden: true, sortable: true);

            $table->defaultSort('-created_at');
        };
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }

    public function jsonResponse(LengthAwarePaginator $chatSessions): AnonymousResourceCollection
    {
        return ChatSessionResource::collection($chatSessions);
    }

    public function htmlResponse(LengthAwarePaginator $chatSessions, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Shop/CRM/ChatSessions',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('Chat Sessions'),
                'pageHead'    => [
                    'title' => __('Chat Sessions'),
                    'icon'  => [
                        'icon'  => ['fal', 'fa-comments'],
                        'title' => __('Chat Sessions')
                    ],
                ],
                'data' => ChatSessionResource::collection($chatSessions),
            ]
        )->table($this->tableStructure(parent: $this->parent));
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowShop::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.shops.show.crm.chat_sessions.index',
                            'parameters' => $routeParameters
                        ],
                        'label' => __('Chat Sessions'),
                        'icon'  => 'fal fa-bars'
                    ],
                ],
            ]
        );
    }
}
