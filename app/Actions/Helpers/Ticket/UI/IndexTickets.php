<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket\UI;

use App\Actions\Helpers\Ticket\ApplyTicketSearch;
use App\Actions\Helpers\Ticket\GetTicketBadgeData;
use App\Actions\OrgAction;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\DateIntervals\DateIntervalEnum;
use App\Enums\Helpers\Ticket\TicketKindEnum;
use App\Enums\Helpers\Ticket\TicketModuleEnum;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Enums\Helpers\Ticket\TicketTypeEnum;
use Illuminate\Support\Arr;
use App\Http\Resources\Helpers\TicketResource;
use App\InertiaTable\InertiaTable;
use App\Models\Helpers\Ticket;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\User;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexTickets extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user() !== null;
    }

    protected function getElementGroups(Group $group): array
    {
        $user = request()->user();
        $base = $this->whereCreatedIn(Ticket::where('tickets.group_id', $group->id)->visibleTo($user), $this->createdInterval(), 'tickets.created_at');

        return [
            'mine'   => [
                'label'    => __('Mine'),
                'elements' => [
                    'reported' => [__('Reported by me'), (clone $base)->where('reporter_type', 'User')->where('reporter_id', $user->id)->count()],
                    'assigned' => [__('Assigned to me'), (clone $base)->where('assignee_id', $user->id)->count()],
                    'collaborating' => [__('Collaborating on'), (clone $base)->whereHas('collaborators', fn ($query) => $query->whereKey($user->id))->count()],
                ],
                'engine'   => function ($query, $elements) use ($user) {
                    $query->where(function ($query) use ($elements, $user) {
                        if (in_array('reported', $elements)) {
                            $query->orWhere(fn ($query) => $query->where('tickets.reporter_type', 'User')->where('tickets.reporter_id', $user->id));
                        }
                        if (in_array('assigned', $elements)) {
                            $query->orWhere('tickets.assignee_id', $user->id);
                        }
                        if (in_array('collaborating', $elements)) {
                            $query->orWhereExists(fn ($collaborators) => $collaborators->selectRaw('1')->from('ticket_collaborators')->whereColumn('ticket_collaborators.ticket_id', 'tickets.id')->where('ticket_collaborators.user_id', $user->id));
                        }
                    });
                },
            ],
            'status' => [
                'label'    => __('Status'),
                'elements' => collect(TicketStatusEnum::cases())->mapWithKeys(fn (TicketStatusEnum $status) => [
                    $status->value => [TicketStatusEnum::labels()[$status->value], (clone $base)->where('status', $status)->count()],
                ])->all(),
                'engine'   => function ($query, $elements) {
                    $query->whereIn('tickets.status', $elements);
                },
            ],
            'type'   => [
                'label'    => __('Type'),
                'elements' => collect(TicketTypeEnum::cases())->mapWithKeys(fn (TicketTypeEnum $type) => [
                    $type->value => [TicketTypeEnum::labels()[$type->value], (clone $base)->where('type', $type)->count()],
                ])->all(),
                'engine'   => function ($query, $elements) {
                    $query->whereIn('tickets.type', $elements);
                },
            ],
            'module'   => $this->countedElementGroup($base, 'module', __('Module'), TicketModuleEnum::labels()),
            'kind'     => $this->countedElementGroup($base, 'kind', __('Kind'), TicketKindEnum::labels()),
            'priority' => $this->countedElementGroup($base, 'priority', __('Urgency'), ChatPriorityEnum::labels()),
            'assignee' => [
                'label'    => __('Assignee'),
                'elements' => (clone $base)->join('users', 'users.id', '=', 'tickets.assignee_id')
                    ->selectRaw('users.username, count(*) as total')->groupBy('users.username')->orderByDesc('total')
                    ->pluck('total', 'username')->map(fn ($total, $username) => [$username, $total])->all(),
                'engine'   => function ($query, $elements) {
                    $query->whereIn('users.username', $elements);
                },
            ],
            'collaborator' => [
                'label'    => __('Collaborator'),
                'elements' => (clone $base)
                    ->join('ticket_collaborators', 'ticket_collaborators.ticket_id', '=', 'tickets.id')
                    ->join('users as collaborator_users', 'collaborator_users.id', '=', 'ticket_collaborators.user_id')
                    ->selectRaw('collaborator_users.username, count(*) as total')->groupBy('collaborator_users.username')->orderByDesc('total')
                    ->pluck('total', 'username')->map(fn ($total, $username) => [$username, $total])->all(),
                'engine'   => function ($query, $elements) {
                    $query->whereExists(
                        fn ($collaborators) => $collaborators->selectRaw('1')
                            ->from('ticket_collaborators')
                            ->join('users as collaborator_users', 'collaborator_users.id', '=', 'ticket_collaborators.user_id')
                            ->whereColumn('ticket_collaborators.ticket_id', 'tickets.id')
                            ->whereIn('collaborator_users.username', $elements)
                    );
                },
            ],
        ];
    }

    /**
     * @param  array<string, string>  $labels
     */
    protected function countedElementGroup($base, string $column, string $label, array $labels): array
    {
        $counts = (clone $base)->whereNotNull($column)->selectRaw("$column as value, count(*) as total")
            ->groupBy($column)->orderByDesc('total')->pluck('total', 'value');

        return [
            'label'    => $label,
            'elements' => $counts->mapWithKeys(fn ($total, $value) => [$value => [$labels[$value] ?? $value, $total]])->all(),
            'engine'   => function ($query, $elements) use ($column) {
                $query->whereIn("tickets.$column", $elements);
            },
        ];
    }

    public function handle(Group $group, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            if (ApplyTicketSearch::run($query, (string) $value, request()->user()) && !request()->filled('sort')) {
                $query->orderByDesc('search_rank');
            }
        });

        $assigneeFilter = AllowedFilter::callback('assignee', function ($query, $value) {
            $query->where('users.username', $value);
        });

        $createdSinceFilter = AllowedFilter::callback('created_since', function ($query, $value) {
            $query->where('tickets.created_at', '>=', $value);
        });

        $resolvedSinceFilter = AllowedFilter::callback('resolved_since', function ($query, $value) {
            $query->where('tickets.resolved_at', '>=', $value);
        });

        $ratedSinceFilter = AllowedFilter::callback('rated_since', function ($query, $value) {
            $query->where('tickets.rated_at', '>=', $value);
        });

        $ratedFilter = AllowedFilter::callback('rated', function ($query, $value) {
            if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
                $query->whereNotNull('tickets.rating');
            }
        });

        $hasAssigneeFilter = AllowedFilter::callback('has_assignee', function ($query, $value) {
            if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
                $query->whereNotNull('tickets.assignee_id');
            }
        });

        $collaboratesOn = fn ($query, string $username) => $query->whereExists(
            fn ($collaborators) => $collaborators->selectRaw('1')
                ->from('ticket_collaborators')
                ->join('users as collaborator_users', 'collaborator_users.id', '=', 'ticket_collaborators.user_id')
                ->whereColumn('ticket_collaborators.ticket_id', 'tickets.id')
                ->where('collaborator_users.username', $username)
        );

        $collaboratorFilter = AllowedFilter::callback('collaborator', function ($query, $value) use ($collaboratesOn) {
            $collaboratesOn($query, (string) $value)->where(fn ($notAssignee) => $notAssignee->whereNull('users.username')->orWhere('users.username', '!=', (string) $value));
        });

        $involvedFilter = AllowedFilter::callback('involved', function ($query, $value) {
            $query->where(fn ($involved) => $involved->where('users.username', (string) $value)->orWhereExists(
                fn ($collaborators) => $collaborators->selectRaw('1')
                    ->from('ticket_collaborators')
                    ->join('users as collaborator_users', 'collaborator_users.id', '=', 'ticket_collaborators.user_id')
                    ->whereColumn('ticket_collaborators.ticket_id', 'tickets.id')
                    ->where('collaborator_users.username', (string) $value)
            ));
        });

        $reporterFilter = AllowedFilter::callback('reporter', function ($query, $value) {
            [$reporterType, $reporterId] = array_pad(explode('-', (string) $value, 2), 2, null);
            if (!in_array($reporterType, ['User', 'WebUser'], true) || !ctype_digit((string) $reporterId)) {
                $query->whereRaw('false');

                return;
            }
            $query->where('tickets.reporter_type', $reporterType)->where('tickets.reporter_id', (int) $reporterId);
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Ticket::class)
            ->where('tickets.group_id', $group->id)
            ->visibleTo(request()->user())
            ->leftJoin('users', 'users.id', '=', 'tickets.assignee_id')
            ->with(['reporter', 'customer', 'assignee', 'collaborators']);

        $this->whereCreatedIn($queryBuilder, $this->createdInterval(), 'tickets.created_at');

        foreach ($this->getElementGroups($group) as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix,
                default: $key === 'mine' ? $this->savedMineFilter() : null
            );
        }

        return $queryBuilder
            ->select(['tickets.*', 'users.username as assignee_username'])
            ->allowedFilters([$globalSearch, $assigneeFilter, $createdSinceFilter, $resolvedSinceFilter, $ratedSinceFilter, $ratedFilter, $hasAssigneeFilter, $reporterFilter, $collaboratorFilter, $involvedFilter])
            ->defaultSort('-tickets.created_at')
            ->allowedSorts(['reference', 'subject', 'status', 'priority', 'created_at', 'updated_at'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function savedMineFilter(): ?string
    {
        $savedFilter = data_get(request()->user()?->settings, 'tickets_list_mine');

        return is_string($savedFilter) && $savedFilter !== '' ? $savedFilter : null;
    }

    public function tableStructure(Group $group, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($group, $prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            foreach ($this->getElementGroups($group) as $key => $elementGroup) {
                $table->elementGroup(key: $key, label: $elementGroup['label'], elements: $elementGroup['elements'], default: $key === 'mine' ? $this->savedMineFilter() : null);
            }

            $table
                ->withGlobalSearch(__('Search tickets: words, "phrase", -word, status:open assignee:me after:2026-09-01'))
                ->withLabelRecord([__('ticket'), __('tickets')])
                ->column(key: 'reference', label: __('Reference'), canBeHidden: false, sortable: true, searchable: true, className: 'whitespace-nowrap w-px')
                ->column(key: 'subject', label: __('Subject'), canBeHidden: false, sortable: true, searchable: true, className: 'w-full max-w-0')
                ->column(key: 'status', label: __('Status'), canBeHidden: false, sortable: true, className: 'whitespace-nowrap w-px')
                ->column(key: 'priority', label: __('Priority'), icon: 'fal fa-flag', canBeHidden: false, sortable: true, className: 'w-px text-center')
                ->column(key: 'kind', label: __('Kind'), canBeHidden: false, className: 'whitespace-nowrap w-px')
                ->column(key: 'module', label: __('Module'), canBeHidden: false, className: 'whitespace-nowrap w-px')
                ->column(key: 'reporter', label: __('Reporter'), canBeHidden: false, type: 'avatar', className: 'whitespace-nowrap w-px')
                ->column(key: 'assignee', label: __('Assignee'), canBeHidden: false, type: 'avatar', className: 'whitespace-nowrap w-px')
                ->column(key: 'created_at', label: __('Created'), canBeHidden: false, sortable: true, type: 'date', className: 'whitespace-nowrap w-px')
                ->column(key: 'updated_at', label: __('Updated'), canBeHidden: false, sortable: true, type: 'date', className: 'whitespace-nowrap w-px')
                ->defaultSort('-created_at');
        };
    }

    public function jsonResponse(LengthAwarePaginator $tickets): AnonymousResourceCollection
    {
        return TicketResource::collection($tickets);
    }

    public function htmlResponse(LengthAwarePaginator $tickets): Response
    {
        return Inertia::render(
            'Tickets/Tickets',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => __('Tickets'),
                'pageHead'    => [
                    'title'   => __('Tickets'),
                    'icon'    => ['fal', 'fa-life-ring'],
                    'actions' => Ticket::canBeRaisedBy(request()->user()) ? [
                        [
                            'type'  => 'button',
                            'style' => 'create',
                            'label' => __('New ticket'),
                            'route' => ['name' => 'grp.tickets.create'],
                        ],
                    ] : [],
                ],
                'data'        => TicketResource::collection($tickets),
                'updateRoute' => 'grp.models.ticket.update',
                'can_assign'  => Ticket::canBeAssignedBy(request()->user()),
                'can_manage'  => Ticket::canBeManagedBy(request()->user()),
                'mineFilter'  => $this->savedMineFilter(),
                'typeFilter'  => Arr::get(request()->input('elements', []), 'type'),
                'typeOptions' => collect(TicketTypeEnum::cases())->map(fn (TicketTypeEnum $type) => [
                    'label' => $type->prefix(),
                    'value' => $type->value,
                    'icon'  => $type->icon(),
                ])->values(),
                'options'     => [
                    'priorities' => collect(ChatPriorityEnum::labels())->map(fn ($label, $value) => ['label' => $label, 'value' => $value, 'icon' => ChatPriorityEnum::stateIcon()[$value]])->values(),
                    'kinds'      => collect(TicketKindEnum::labels())->map(fn ($label, $value) => ['label' => $label, 'value' => $value])->values(),
                    'modules'    => collect(TicketModuleEnum::labels())->map(fn ($label, $value) => ['label' => $label, 'value' => $value])->values(),
                    'assignees'  => GetTicketBadgeData::engineers($this->group->id)
                        ->map(fn (User $engineer) => [
                            'label'  => strtok((string) ($engineer->contact_name ?: $engineer->username), ' '),
                            'value'  => $engineer->id,
                            'avatar' => $engineer->imageSources(48, 48),
                        ])->sortBy('label')->values(),
                ],
                'createdIntervals' => $this->createdIntervalOptions(),
                'createdInterval'  => $this->createdInterval(),
            ]
        )->table($this->tableStructure($this->group));
    }

    private const array HOURLY_INTERVALS = ['1h' => 1, '3h' => 3, '24h' => 24];

    /**
     * @return array<string, string>
     */
    public function createdIntervalOptions(): array
    {
        $labels = DateIntervalEnum::labels();

        return [
            'all' => $labels['all'],
            '1h'  => __('1 hour'),
            '3h'  => __('3 hours'),
            '24h' => __('24 hours'),
            'tdy' => $labels['tdy'],
            'ld'  => $labels['ld'],
            '3d'  => $labels['3d'],
            '1w'  => $labels['1w'],
            'lw'  => $labels['lw'],
            '1m'  => $labels['1m'],
            'lm'  => $labels['lm'],
            '1q'  => $labels['1q'],
            '1y'  => $labels['1y'],
        ];
    }

    public function createdInterval(): string
    {
        $interval = (string) request()->input('created');

        return array_key_exists($interval, $this->createdIntervalOptions()) ? $interval : 'all';
    }

    public function whereCreatedIn($query, string $interval, string $column)
    {
        if (isset(self::HOURLY_INTERVALS[$interval])) {
            return $query->where($column, '>=', now()->subHours(self::HOURLY_INTERVALS[$interval]));
        }

        return DateIntervalEnum::from($interval)->wherePeriod($query, $column);
    }

    public function getBreadcrumbs(): array
    {
        return array_merge(
            ShowTicketsDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => ['name' => 'grp.tickets.list'],
                        'label' => __('List'),
                    ],
                ],
            ]
        );
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($this->group);
    }
}
