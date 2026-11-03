<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket\UI;

use App\Actions\Helpers\Ticket\GetTicketBadgeData;
use App\Actions\OrgAction;
use App\Enums\Helpers\Ticket\TicketQaStatusEnum;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Enums\Helpers\Ticket\TicketStatusGroupEnum;
use App\Enums\Helpers\Ticket\TicketTypeEnum;
use App\Http\Resources\Helpers\TicketResource;
use App\Models\Helpers\Ticket;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\User;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowTicketsBoard extends OrgAction
{
    use WithTicketsScope;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user() !== null;
    }

    private const array PERIODS = ['24h', 'today', '1w', 'all'];

    /**
     * Done and Cancelled share one column, as do Waiting and Reporter replied, each keeping its own count in its header.
     *
     * @var array<string, array<int, string>>
     */
    private const array COLUMNS = [
        'open'        => ['open'],
        'assigned'    => ['assigned'],
        'in_progress' => ['in_progress', 'pending_deploy'],
        'waiting'     => ['waiting', 'answered'],
        'closed'      => ['resolved', 'cancelled'],
    ];

    /**
     * QA runs alongside the ticket status, not as one of its steps: an in-progress ticket QA has
     * been asked to check, is checking, or has failed sits under its own QA toggle in the in-progress
     * column. Deploying and closed tickets keep their place whatever their QA says.
     */
    private const string QA = 'qa';

    /**
     * Todo is aged on when the ticket was raised, the closed column on when it was closed.
     */
    private const array DEFAULT_PERIODS = ['closed' => '24h'];

    /**
     * @param  array<string, string>  $periods
     */
    public function handle(Group $group, array $periods = [], string $created = 'all', ?string $type = null): array
    {
        $periods = array_merge(self::DEFAULT_PERIODS, array_intersect_key($periods, self::DEFAULT_PERIODS));

        $tickets = IndexTickets::make()->whereCreatedIn(Ticket::where('group_id', $group->id)->visibleTo(request()->user())->when($type, fn ($query) => $query->where('type', $type)), $created, 'created_at')
            ->where(function ($query) use ($periods) {
                foreach (self::COLUMNS as $key => $statuses) {
                    $query->orWhere(function ($columnQuery) use ($key, $statuses, $periods) {
                        $columnQuery->whereIn('status', $statuses);

                        if ($since = $this->since($periods[$key] ?? 'all')) {
                            $columnQuery->where($key === 'open' ? 'created_at' : 'closed_at', '>=', $since);
                        }
                    });
                }
            })
            ->with(TicketResource::eagerLoads())
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")
            ->orderByDesc('updated_at')
            ->get()
            ->groupBy(fn (Ticket $ticket) => $this->boardStatus($ticket));

        $columns = collect(self::COLUMNS)->map(function (array $statuses, string $key) use ($tickets, $periods) {
            $cases = array_map(fn (string $status) => TicketStatusEnum::from($status), $statuses);
            $group = $cases[0]->group();
            $boardStatuses = $key === 'in_progress' ? [TicketStatusEnum::IN_PROGRESS->value, self::QA, TicketStatusEnum::PENDING_DEPLOY->value] : $statuses;
            $columnTickets = collect($boardStatuses)->flatMap(fn (string $status) => $tickets->get($status, collect()));
            $labelledByGroup = $key === 'closed';

            return [
                'key'      => $key,
                'status'   => $statuses[0],
                'group'    => $group->value,
                'label'    => $labelledByGroup ? TicketStatusGroupEnum::labels()[$group->value] : TicketStatusEnum::labels()[$statuses[0]],
                'color'    => TicketStatusGroupEnum::stateIcon()[$group->value]['color'],
                'icon'     => $labelledByGroup ? TicketStatusGroupEnum::stateIcon()[$group->value] : TicketStatusEnum::stateIcon()[$statuses[0]],
                'period'   => $periods[$key] ?? null,
                'statuses' => collect($boardStatuses)->map(fn (string $status) => [
                    'status' => $status,
                    'label'  => $status === self::QA ? __('In QA') : TicketStatusEnum::labels()[$status],
                    'color'  => $status === self::QA ? 'amber' : TicketStatusEnum::stateIcon()[$status]['color'],
                    'count'  => $tickets->get($status, collect())->count(),
                ])->all(),
                'tickets'  => $columnTickets->map(fn (Ticket $ticket) => [
                    ...Arr::except(TicketResource::make($ticket)->toArray(request()), 'commits'),
                    'board_status' => $this->boardStatus($ticket),
                ])->values()->all(),
            ];
        })->values()->all();

        return ['columns' => $columns, 'periods' => $periods, 'periodOptions' => self::PERIODS, 'created' => $created, 'type' => $type];
    }

    private function boardStatus(Ticket $ticket): string
    {
        return $ticket->status === TicketStatusEnum::IN_PROGRESS
            && in_array($ticket->qa_status, [TicketQaStatusEnum::REQUESTED, TicketQaStatusEnum::CHECKING, TicketQaStatusEnum::FAILED], true)
            ? self::QA
            : $ticket->status->value;
    }

    private function since(string $period): ?\Illuminate\Support\Carbon
    {
        return match ($period) {
            '24h'   => now()->subDay(),
            'today' => now()->startOfDay(),
            '1w'    => now()->subWeek(),
            default => null,
        };
    }

    public function asController(ActionRequest $request): array
    {
        $this->initialisationFromTicketsScope($request);

        return $this->boardFromRequest($request);
    }

    public function inOrganisation(Organisation $organisation, ActionRequest $request): array
    {
        $this->initialisationFromTicketsScope($request, $organisation);

        return $this->boardFromRequest($request);
    }

    public function inShop(Organisation $organisation, Shop $shop, ActionRequest $request): array
    {
        $this->initialisationFromTicketsScope($request, $organisation, $shop);

        return $this->boardFromRequest($request);
    }

    private function boardFromRequest(ActionRequest $request): array
    {
        $periods = array_filter((array) $request->input('periods', []), fn ($period) => in_array($period, self::PERIODS, true));

        $type = in_array($request->input('type'), array_column(TicketTypeEnum::cases(), 'value'), true) ? $request->input('type') : null;

        return $this->handle($this->group, $periods, IndexTickets::make()->createdInterval(), $type);
    }

    public function htmlResponse(array $board): Response
    {
        return Inertia::render(
            'Tickets/TicketsBoard',
            [
                'breadcrumbs' => array_merge(
                    $this->ticketsBreadcrumbs(),
                    [['type' => 'simple', 'simple' => ['route' => $this->ticketsRoute('board'), 'label' => __('Board')]]]
                ),
                'title'       => __('Tickets board'),
                'pageHead'    => [
                    'title'   => __('Tickets board'),
                    'icon'    => ['fal', 'fa-columns'],
                    'actions' => Ticket::canBeRaisedBy(request()->user()) ? [
                        [
                            'type'  => 'button',
                            'style' => 'create',
                            'label' => __('New ticket'),
                            'route' => $this->ticketsRoute('create'),
                        ],
                    ] : [],
                ],
                'can_manage'    => Ticket::canBeManagedBy(request()->user()),
                'can_assign'    => Ticket::canBeAssignedBy(request()->user()),
                'columns'       => $board['columns'],
                'periodOptions' => $board['periodOptions'],
                'createdIntervals' => IndexTickets::make()->createdIntervalOptions(),
                'createdInterval'  => $board['created'],
                'typeFilter'  => $board['type'],
                'typeOptions' => collect(TicketTypeEnum::cases())->map(fn (TicketTypeEnum $type) => [
                    'label' => $type->prefix(),
                    'value' => $type->value,
                    'icon'  => $type->icon(),
                ])->values(),
                'updateRoute' => 'grp.models.ticket.update',
                'me'          => request()->user()->username,
                'formerAssignees' => $this->formerAssignees($board['columns']),
                'assignees'       => GetTicketBadgeData::engineers(request()->user()->group_id)
                    ->map(fn (User $user) => [
                        'label'  => strtok((string) ($user->contact_name ?: $user->username), ' '),
                        'value'  => $user->id,
                        'avatar' => $user->imageSources(48, 48),
                        'is_me'  => $user->id === request()->user()->id,
                    ])->sortBy('label')->values(),
            ]
        );
    }

    /**
     * @param  array<int, array{tickets: array<int, array<string, mixed>>}>  $columns
     * @return array<int, string>
     */
    private function formerAssignees(array $columns): array
    {
        $assigneeIds = collect($columns)->flatMap(fn (array $column) => array_column($column['tickets'], 'assignee_id'))->filter()->unique();

        return User::whereIn('id', $assigneeIds)
            ->whereDoesntHave('employees', fn ($query) => $query->where('state', '!=', EmployeeStateEnum::LEFT))
            ->whereDoesntHave('guests', fn ($query) => $query->where('guests.status', true))
            ->pluck('username')
            ->all();
    }
}
