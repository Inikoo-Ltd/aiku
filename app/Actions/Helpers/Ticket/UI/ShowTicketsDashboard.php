<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket\UI;

use App\Actions\OrgAction;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Models\Helpers\Ticket;
use App\Models\SysAdmin\Group;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowTicketsDashboard extends OrgAction
{
    public const PERIODS = [7, 30, 90];

    public function authorize(ActionRequest $request): bool
    {
        return $request->user() !== null;
    }

    public function handle(Group $group, int $days): array
    {
        $from = now()->subDays($days - 1)->startOfDay();
        $base = Ticket::where('tickets.group_id', $group->id);

        $createdByDay  = (clone $base)->where('created_at', '>=', $from)
            ->selectRaw('date(created_at) as day, count(*) as total')->groupBy('day')->pluck('total', 'day');
        $resolvedByDay = (clone $base)->where('resolved_at', '>=', $from)
            ->selectRaw('date(resolved_at) as day, count(*) as total')->groupBy('day')->pluck('total', 'day');

        $daily = collect(range(0, $days - 1))->map(function (int $offset) use ($from, $createdByDay, $resolvedByDay) {
            $day = $from->copy()->addDays($offset)->toDateString();

            return ['date' => $day, 'created' => (int) ($createdByDay[$day] ?? 0), 'done' => (int) ($resolvedByDay[$day] ?? 0)];
        });

        $medianHours = (clone $base)->where('resolved_at', '>=', $from)
            ->selectRaw('percentile_cont(0.5) within group (order by extract(epoch from resolved_at - created_at) / 3600) as median')
            ->value('median');

        $byStatus = (clone $base)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $assignees = (clone $base)
            ->join('users', 'users.id', '=', 'tickets.assignee_id')
            ->selectRaw("
                coalesce(users.contact_name, users.username) as name,
                count(*) filter (where tickets.status not in ('resolved', 'closed')) as open,
                count(*) filter (where tickets.resolved_at >= ?) as done,
                percentile_cont(0.5) within group (order by extract(epoch from tickets.resolved_at - tickets.created_at) / 3600)
                    filter (where tickets.resolved_at >= ?) as median_hours
            ", [$from, $from])
            ->groupBy('users.id', 'users.contact_name', 'users.username')
            ->orderByDesc('open')
            ->get()
            ->map(fn ($row) => [
                'name'         => $row->name,
                'open'         => (int) $row->open,
                'done'         => (int) $row->done,
                'median_hours' => $row->median_hours === null ? null : round((float) $row->median_hours, 1),
            ]);

        $csat = (clone $base)->where('rated_at', '>=', $from)->avg('rating');

        $monthlyCsat = (clone $base)->where('rated_at', '>=', now()->subMonths(11)->startOfMonth())
            ->selectRaw("to_char(rated_at, 'YYYY-MM') as month, round(avg(rating)::numeric, 1) as average, count(*) as total")
            ->groupBy('month')->get()->keyBy('month');
        $csatByMonth = collect(range(11, 0))->map(function (int $back) use ($monthlyCsat) {
            $month = now()->subMonths($back)->format('Y-m');

            return ['month' => $month, 'average' => isset($monthlyCsat[$month]) ? (float) $monthlyCsat[$month]->average : null, 'total' => (int) ($monthlyCsat[$month]->total ?? 0)];
        });

        $oldestOpen = (clone $base)->whereNotIn('status', [TicketStatusEnum::RESOLVED, TicketStatusEnum::CLOSED])->orderBy('created_at')->first();

        return [
            'days'          => $days,
            'created'       => $daily->sum('created'),
            'done'          => $daily->sum('done'),
            'open'          => (int) $byStatus->except(['resolved', 'closed'])->sum(),
            'median_hours'  => $medianHours === null ? null : round((float) $medianHours, 1),
            'oldest_open'   => $oldestOpen ? ['reference' => $oldestOpen->reference, 'age_days' => (int) Carbon::parse($oldestOpen->created_at)->diffInDays()] : null,
            'csat'          => $csat === null ? null : round((float) $csat, 1),
            'csat_by_month' => $csatByMonth->values()->all(),
            'daily'         => $daily->values()->all(),
            'by_status'     => collect(TicketStatusEnum::cases())->map(fn (TicketStatusEnum $status) => [
                'status' => $status->value,
                'label'  => TicketStatusEnum::labels()[$status->value],
                'color'  => TicketStatusEnum::stateIcon()[$status->value]['color'],
                'total'  => (int) ($byStatus[$status->value] ?? 0),
            ])->values()->all(),
            'assignees'     => $assignees->all(),
        ];
    }

    public function asController(ActionRequest $request): array
    {
        $this->initialisationFromGroup(group(), $request);
        $days = (int) $request->input('days', 7);

        return $this->handle($this->group, in_array($days, self::PERIODS) ? $days : 7);
    }

    public function htmlResponse(array $stats): Response
    {
        return Inertia::render(
            'Tickets/TicketsDashboard',
            [
                'breadcrumbs' => array_merge(
                    IndexTickets::make()->getBreadcrumbs(),
                    [['type' => 'simple', 'simple' => ['route' => ['name' => 'grp.tickets.dashboard'], 'label' => __('Reports')]]]
                ),
                'title'       => __('Tickets reports'),
                'pageHead'    => [
                    'title' => __('Tickets reports'),
                    'icon'  => ['fal', 'fa-chart-line'],
                ],
                'stats'       => $stats,
                'periods'     => self::PERIODS,
            ]
        );
    }
}
