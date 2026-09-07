<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 23 Aug 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\UI;

use App\Actions\OrgAction;
use App\Actions\SysAdmin\GetStaffChatAnalytics;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Http\Resources\SysAdmin\StaffChatConversationsResource;
use App\Http\Resources\SysAdmin\StaffChatUsersResource;
use App\InertiaTable\InertiaTable;
use App\Models\Chat\StaffConversation;
use App\Models\Chat\StaffMessage;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Actions\UI\HumanResources\ShowHumanResourcesDashboard;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexStaffChatAnalytics extends OrgAction
{
    public ?Organisation $scopedOrganisation = null;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->scopedOrganisation) {
            return $request->user()->authTo([
                "human-resources.{$this->scopedOrganisation->id}.view",
                "org-supervisor.{$this->scopedOrganisation->id}.human-resources",
                "org-admin.{$this->scopedOrganisation->id}",
            ]);
        }

        return $request->user()->authTo('sysadmin.view');
    }

    public function handle(Group $group, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->whereRaw('users.username ILIKE ?', ["%$value%"]);
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(StaffMessage::class)
            ->join('staff_conversations', 'staff_conversations.id', '=', 'staff_messages.staff_conversation_id')
            ->join('users', 'users.id', '=', 'staff_messages.user_id')
            ->where('staff_conversations.group_id', $group->id);
        GetStaffChatAnalytics::scopeToOrganisation($queryBuilder, $this->scopedOrganisation);

        return $queryBuilder
            ->selectRaw('
                users.username,
                count(*) as messages,
                count(distinct staff_messages.staff_conversation_id) as conversations,
                count(*) filter (where staff_messages.media_id is not null) as media_messages,
                (select count(*) from staff_message_reactions where staff_message_reactions.user_id = users.id) as reactions_given,
                max(staff_messages.created_at) as last_message_at
            ')
            ->groupBy('users.id', 'users.username')
            ->defaultSort('-messages')
            ->allowedSorts(['username', 'messages', 'conversations', 'media_messages', 'reactions_given', 'last_message_at'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function handleConversations(Group $group, $prefix = 'conversations'): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->whereExists(function ($sub) use ($value) {
                $sub->selectRaw('1')
                    ->from('staff_conversation_participants as scp')
                    ->join('users as pu', 'pu.id', '=', 'scp.user_id')
                    ->whereColumn('scp.staff_conversation_id', 'staff_conversations.id')
                    ->whereRaw('pu.username ILIKE ?', ["%$value%"]);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(StaffConversation::class)
            ->where('staff_conversations.group_id', $group->id);
        GetStaffChatAnalytics::scopeToOrganisation($queryBuilder, $this->scopedOrganisation);

        return $queryBuilder
            ->selectRaw("
                staff_conversations.id,
                staff_conversations.ulid,
                staff_conversations.type,
                regexp_replace(staff_conversations.context_type, '^.*\\\\', '') as context,
                staff_conversations.last_message_at,
                staff_conversations.created_at,
                (select string_agg(users.username, ', ' order by users.username)
                    from staff_conversation_participants
                    join users on users.id = staff_conversation_participants.user_id
                    where staff_conversation_participants.staff_conversation_id = staff_conversations.id) as members,
                (select count(*) from staff_conversation_participants
                    where staff_conversation_participants.staff_conversation_id = staff_conversations.id) as participants,
                (select count(*) from staff_messages
                    where staff_messages.staff_conversation_id = staff_conversations.id and staff_messages.deleted_at is null) as messages
            ")
            ->defaultSort('-messages')
            ->allowedSorts(['type', 'context', 'participants', 'messages', 'last_message_at', 'created_at'])
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
                ->withTitle(title: __('Staff chat by user'))
                ->withLabelRecord([__('user'), __('users')])
                ->column(key: 'username', label: __('User'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'messages', label: __('Messages'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'conversations', label: __('Conversations'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'media_messages', label: __('Images'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'reactions_given', label: __('Reactions'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'last_message_at', label: __('Last message'), canBeHidden: false, sortable: true)
                ->defaultSort('-messages');
        };
    }

    public function conversationsTableStructure($prefix = 'conversations'): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withTitle(title: __('Conversations'))
                ->withLabelRecord([__('conversation'), __('conversations')])
                ->column(key: 'members', label: __('Who'), canBeHidden: false)
                ->column(key: 'type', label: __('Type'), canBeHidden: false, sortable: true)
                ->column(key: 'context', label: __('Context'), canBeHidden: false, sortable: true)
                ->column(key: 'participants', label: __('People'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'messages', label: __('Messages'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'last_message_at', label: __('Last message'), canBeHidden: false, sortable: true)
                ->column(key: 'created_at', label: __('Started'), canBeHidden: false, sortable: true)
                ->defaultSort('-messages');
        };
    }

    public function htmlResponse(LengthAwarePaginator $users, ActionRequest $request): Response
    {
        return Inertia::render(
            'SysAdmin/StaffChatAnalytics',
            [
                'breadcrumbs'   => $this->getBreadcrumbs(),
                'title'         => __('Staff chat analytics'),
                'pageHead'      => [
                    'icon'  => [
                        'icon'  => ['fal', 'fa-comments-alt'],
                        'title' => __('Staff chat analytics'),
                    ],
                    'title' => __('Staff chat analytics'),
                ],
                'insights'      => GetStaffChatAnalytics::run($this->group, 30, $this->scopedOrganisation),
                'users'         => StaffChatUsersResource::collection($users),
                'conversations' => StaffChatConversationsResource::collection($this->handleConversations($this->group)),
                'index_route'   => $this->scopedOrganisation
                    ? ['name' => 'grp.org.hr.staff_chat.index', 'parameters' => ['organisation' => $this->scopedOrganisation->slug]]
                    : ['name' => 'grp.sysadmin.staff_chat.index', 'parameters' => []],
                'show_route'    => $this->scopedOrganisation
                    ? ['name' => 'grp.org.hr.staff_chat.show', 'parameters' => ['organisation' => $this->scopedOrganisation->slug]]
                    : null,
            ]
        )->table($this->tableStructure())
            ->table($this->conversationsTableStructure());
    }

    public function getBreadcrumbs(): array
    {
        if ($this->scopedOrganisation) {
            return array_merge(
                ShowHumanResourcesDashboard::make()->getBreadcrumbs(['organisation' => $this->scopedOrganisation->slug]),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.hr.staff_chat.index',
                                'parameters' => ['organisation' => $this->scopedOrganisation->slug],
                            ],
                            'label' => __('Staff chat'),
                        ]
                    ]
                ]
            );
        }

        return array_merge(
            ShowGroupDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name' => 'grp.sysadmin.staff_chat.index',
                        ],
                        'label' => __('Staff chat analytics'),
                    ]
                ]
            ]
        );
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($this->group);
    }

    public function inOrganisation(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->scopedOrganisation = $organisation;
        $this->initialisation($organisation, $request);

        return $this->handle($this->group);
    }
}
