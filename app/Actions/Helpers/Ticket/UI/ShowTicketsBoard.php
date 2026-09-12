<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket\UI;

use App\Actions\OrgAction;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Enums\Helpers\Ticket\TicketStatusGroupEnum;
use App\Http\Resources\Helpers\TicketResource;
use App\Models\Helpers\Ticket;
use App\Models\SysAdmin\Group;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowTicketsBoard extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return Ticket::canBeManagedBy($request->user());
    }

    private const array PERIODS = ['24h', 'today', '1w', 'all'];

    /**
     * Done and Cancelled share one column, each keeping its own count in its header.
     *
     * @var array<string, array<int, string>>
     */
    private const array COLUMNS = [
        'open'        => ['open'],
        'assigned'    => ['assigned'],
        'in_progress' => ['in_progress'],
        'waiting'     => ['waiting'],
        'closed'      => ['resolved', 'cancelled'],
    ];

    /**
     * Todo is aged on when the ticket was raised, the closed column on when it was closed.
     */
    private const array DEFAULT_PERIODS = ['open' => 'all', 'closed' => '24h'];

    /**
     * @param  array<string, string>  $periods
     */
    public function handle(Group $group, array $periods = []): array
    {
        $periods = array_merge(self::DEFAULT_PERIODS, array_intersect_key($periods, self::DEFAULT_PERIODS));

        $tickets = Ticket::where('group_id', $group->id)->visibleTo(request()->user())
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
            ->with(['reporter', 'assignee', 'customer'])
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")
            ->orderByDesc('updated_at')
            ->get()
            ->groupBy(fn (Ticket $ticket) => $ticket->status->value);

        $columns = collect(self::COLUMNS)->map(function (array $statuses, string $key) use ($tickets, $periods) {
            $cases = array_map(fn (string $status) => TicketStatusEnum::from($status), $statuses);
            $group = $cases[0]->group();
            $columnTickets = collect($statuses)->flatMap(fn (string $status) => $tickets->get($status, collect()));

            return [
                'key'      => $key,
                'status'   => $statuses[0],
                'group'    => $group->value,
                'label'    => count($cases) > 1 ? TicketStatusGroupEnum::labels()[$group->value] : TicketStatusEnum::labels()[$statuses[0]],
                'color'    => TicketStatusGroupEnum::stateIcon()[$group->value]['color'],
                'icon'     => count($cases) > 1 ? TicketStatusGroupEnum::stateIcon()[$group->value] : TicketStatusEnum::stateIcon()[$statuses[0]],
                'period'   => $periods[$key] ?? null,
                'statuses' => collect($cases)->map(fn (TicketStatusEnum $status) => [
                    'status' => $status->value,
                    'label'  => TicketStatusEnum::labels()[$status->value],
                    'count'  => $tickets->get($status->value, collect())->count(),
                ])->all(),
                'tickets'  => TicketResource::collection($columnTickets)->toArray(request()),
            ];
        })->values()->all();

        return ['columns' => $columns, 'periods' => $periods, 'periodOptions' => self::PERIODS];
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
        $this->initialisationFromGroup(group(), $request);

        $periods = array_filter((array) $request->input('periods', []), fn ($period) => in_array($period, self::PERIODS, true));

        return $this->handle($this->group, $periods);
    }

    public function htmlResponse(array $board): Response
    {
        return Inertia::render(
            'Tickets/TicketsBoard',
            [
                'breadcrumbs' => array_merge(
                    IndexTickets::make()->getBreadcrumbs(),
                    [['type' => 'simple', 'simple' => ['route' => ['name' => 'grp.tickets.board'], 'label' => __('Board')]]]
                ),
                'title'       => __('Tickets board'),
                'pageHead'    => [
                    'title'   => __('Tickets board'),
                    'icon'    => ['fal', 'fa-columns'],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'create',
                            'label' => __('New ticket'),
                            'route' => ['name' => 'grp.tickets.create'],
                        ],
                    ],
                ],
                'columns'       => $board['columns'],
                'periodOptions' => $board['periodOptions'],
                'updateRoute' => 'grp.models.ticket.update',
            ]
        );
    }
}
