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
use App\Models\SysAdmin\User;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowTicketsReports extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user() !== null;
    }

    public function handle(Group $group, string $interval, ?User $viewer = null): array
    {
        $base = Ticket::where('tickets.group_id', $group->id)->when($viewer, fn ($query) => $query->visibleTo($viewer));

        [$from, $to] = $this->range($interval, (clone $base)->min('created_at'));
        $days        = (int) $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1;

        $bucket = $days <= 62 ? 'day' : ($days <= 400 ? 'week' : 'month');

        $createdByDay  = (clone $base)->whereBetween('created_at', [$from, $to])
            ->selectRaw("to_char(date_trunc('$bucket', created_at), 'YYYY-MM-DD') as day, count(*) as total")->groupBy('day')->pluck('total', 'day');
        $resolvedByDay = (clone $base)->whereBetween('resolved_at', [$from, $to])
            ->selectRaw("to_char(date_trunc('$bucket', resolved_at), 'YYYY-MM-DD') as day, count(*) as total")->groupBy('day')->pluck('total', 'day');

        $daily  = collect();
        $cursor = $from->copy()->startOf($bucket);
        while ($cursor->lte($to)) {
            $day = $cursor->toDateString();
            $daily->push(['date' => $day, 'created' => (int) ($createdByDay[$day] ?? 0), 'done' => (int) ($resolvedByDay[$day] ?? 0)]);
            $cursor->add(1, $bucket);
        }

        $medianHours = (clone $base)->whereBetween('resolved_at', [$from, $to])
            ->selectRaw('percentile_cont(0.5) within group (order by extract(epoch from resolved_at - created_at) / 3600) as median')
            ->value('median');

        $byStatus        = (clone $base)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $byStatusInRange = (clone $base)->whereBetween('created_at', [$from, $to])->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $createdInRange  = (clone $base)->whereBetween('tickets.created_at', [$from, $to]);
        $resolvedInRange = (clone $base)->whereBetween('tickets.resolved_at', [$from, $to])->where('tickets.created_at', '<', $from);

        $reporters = (clone $createdInRange)
            ->whereNotNull('tickets.reporter_id')
            ->leftJoin('users as reporter_users', fn ($join) => $join->where('tickets.reporter_type', 'User')->whereColumn('reporter_users.id', 'tickets.reporter_id'))
            ->leftJoin('web_users as reporter_web_users', fn ($join) => $join->where('tickets.reporter_type', 'WebUser')->whereColumn('reporter_web_users.id', 'tickets.reporter_id'))
            ->selectRaw('
                tickets.reporter_type as reporter_kind,
                tickets.reporter_id as id,
                coalesce(reporter_users.contact_name, reporter_users.username, reporter_web_users.contact_name, reporter_web_users.username) as name,
                '.self::METRICS_SQL)
            ->groupBy('tickets.reporter_type', 'tickets.reporter_id', 'reporter_users.contact_name', 'reporter_users.username', 'reporter_web_users.contact_name', 'reporter_web_users.username')
            ->orderByDesc('created')
            ->get()
            ->map(fn ($row) => [
                'key'      => $row->reporter_kind.'-'.$row->id,
                'name'     => $row->name,
                'is_staff' => $row->reporter_kind === 'User',
                ...$this->metrics($row),
            ]);

        $csat = (clone $base)->whereBetween('rated_at', [$from, $to])->avg('rating');

        $monthlyCsat = (clone $base)->where('rated_at', '>=', now()->subMonths(11)->startOfMonth())
            ->selectRaw("to_char(rated_at, 'YYYY-MM') as month, round(avg(rating)::numeric, 1) as average, count(*) as total")
            ->groupBy('month')->get()->keyBy('month');
        $csatByMonth = collect(range(11, 0))->map(function (int $back) use ($monthlyCsat) {
            $month = now()->subMonths($back)->format('Y-m');

            return ['month' => $month, 'average' => isset($monthlyCsat[$month]) ? (float) $monthlyCsat[$month]->average : null, 'total' => (int) ($monthlyCsat[$month]->total ?? 0)];
        });

        $oldestOpen = (clone $base)->whereNotIn('status', [TicketStatusEnum::RESOLVED, TicketStatusEnum::CANCELLED])->orderBy('created_at')->first();

        return [
            'interval'      => $interval,
            'days'          => $days,
            'bucket'        => $bucket,
            'from'          => $from->toDateString(),
            'created'       => $daily->sum('created'),
            'done'          => $daily->sum('done'),
            'open'          => (int) $byStatus->except(['resolved', 'cancelled'])->sum(),
            'median_hours'  => $medianHours === null ? null : round((float) $medianHours, 1),
            'oldest_open'   => $oldestOpen ? ['reference' => $oldestOpen->reference, 'age_days' => (int) Carbon::parse($oldestOpen->created_at)->diffInDays()] : null,
            'csat'          => $csat === null ? null : round((float) $csat, 1),
            'csat_by_month' => $csatByMonth->values()->all(),
            'daily'         => $daily->values()->all(),
            'by_status'     => collect(TicketStatusEnum::cases())->map(fn (TicketStatusEnum $status) => [
                'status' => $status->value,
                'label'  => TicketStatusEnum::labels()[$status->value],
                'color'  => TicketStatusEnum::stateIcon()[$status->value]['color'],
                'total'  => (int) ($byStatusInRange[$status->value] ?? 0),
            ])->values()->all(),
            'assignees'       => $this->assigneeRows($createdInRange),
            'assignees_total' => $this->metrics((clone $createdInRange)->selectRaw(self::METRICS_SQL)->first()),
            'reporters'       => $reporters->all(),
            'resolvers'       => $this->assigneeRows($resolvedInRange),
            'resolvers_total' => $this->metrics((clone $resolvedInRange)->selectRaw(self::METRICS_SQL)->first()),
        ];
    }

    private const string METRICS_SQL = "
        count(*) as created,
        count(*) filter (where tickets.status not in ('resolved', 'cancelled')) as open,
        count(*) filter (where tickets.resolved_at is not null) as done,
        percentile_cont(0.5) within group (order by extract(epoch from tickets.resolved_at - tickets.created_at) / 3600)
            filter (where tickets.resolved_at is not null) as median_hours,
        max(extract(epoch from now() - tickets.created_at) / 86400) filter (where tickets.status not in ('resolved', 'cancelled')) as longest_wait_days,
        round(avg(tickets.rating), 1) as rating,
        count(tickets.rating) as ratings
    ";

    private function assigneeRows($query): array
    {
        $rows = (clone $query)
            ->join('users', 'users.id', '=', 'tickets.assignee_id')
            ->selectRaw('users.id as id, users.username as username, coalesce(users.contact_name, users.username) as name, '.self::METRICS_SQL)
            ->groupBy('users.id', 'users.contact_name', 'users.username')
            ->orderByDesc('open')
            ->orderByDesc('done')
            ->get();

        $users = User::whereIn('id', $rows->pluck('id'))->get()->keyBy('id');

        return $rows->map(fn ($row) => [
            'name'       => $row->name,
            'username'   => $row->username,
            'short_name' => strtok((string) $row->name, ' '),
            'avatar'     => $users->get($row->id)?->imageSources(48, 48),
            ...$this->metrics($row),
        ])->all();
    }

    private function metrics(object $row): array
    {
        return [
            'created'           => (int) $row->created,
            'open'              => (int) $row->open,
            'done'              => (int) $row->done,
            'median_hours'      => $row->median_hours === null ? null : round((float) $row->median_hours, 1),
            'longest_wait_days' => $row->longest_wait_days === null ? null : (int) $row->longest_wait_days,
            'rating'            => $row->rating === null ? null : (float) $row->rating,
            'ratings'           => (int) $row->ratings,
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function range(string $interval, ?string $oldestCreatedAt): array
    {
        return match ($interval) {
            '1h'    => [now()->subHour(), now()],
            '3h'    => [now()->subHours(3), now()],
            '24h'   => [now()->subDay(), now()],
            'tdy'   => [now()->startOfDay(), now()->endOfDay()],
            'ld'    => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            '3d'    => [now()->subDays(3)->startOfDay(), now()->endOfDay()],
            '1w'    => [now()->subWeek()->startOfDay(), now()->endOfDay()],
            'lw'    => [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()],
            '1m'    => [now()->subMonth()->startOfDay(), now()->endOfDay()],
            'lm'    => [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()],
            '1q'    => [now()->subQuarter()->startOfDay(), now()->endOfDay()],
            '1y'    => [now()->subYear()->startOfDay(), now()->endOfDay()],
            default => [Carbon::parse($oldestCreatedAt ?? now())->startOfDay(), now()->endOfDay()],
        };
    }

    public function asController(ActionRequest $request): array
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($this->group, IndexTickets::make()->createdInterval(), $request->user());
    }

    public function htmlResponse(array $stats): Response
    {
        return Inertia::render(
            'Tickets/TicketsReports',
            [
                'breadcrumbs' => array_merge(
                    ShowTicketsDashboard::make()->getBreadcrumbs(),
                    [['type' => 'simple', 'simple' => ['route' => ['name' => 'grp.tickets.reports'], 'label' => __('Reports')]]]
                ),
                'title'       => __('Tickets reports'),
                'pageHead'    => [
                    'title' => __('Tickets reports'),
                    'icon'  => ['fal', 'fa-chart-line'],
                ],
                'stats'            => $stats,
                'createdIntervals' => IndexTickets::make()->createdIntervalOptions(),
            ]
        );
    }
}
