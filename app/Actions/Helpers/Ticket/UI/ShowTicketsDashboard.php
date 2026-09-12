<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 13 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket\UI;

use App\Actions\OrgAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\Helpers\Ticket\TicketKindEnum;
use App\Enums\Helpers\Ticket\TicketModuleEnum;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Http\Resources\Helpers\TicketResource;
use App\Models\Helpers\Ticket;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowTicketsDashboard extends OrgAction
{
    private const string PRIORITY_ORDER = "CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END";

    public function authorize(ActionRequest $request): bool
    {
        return $request->user() !== null;
    }

    public function handle(Group $group, User $user): array
    {
        $base       = Ticket::where('tickets.group_id', $group->id);
        $open       = fn (): Builder => (clone $base)->whereIn('status', [TicketStatusEnum::OPEN, TicketStatusEnum::ASSIGNED, TicketStatusEnum::IN_PROGRESS, TicketStatusEnum::WAITING]);
        $reportedBy = fn (Builder $query): Builder => $query->where('reporter_type', 'User')->where('reporter_id', $user->id);
        $weekAgo    = now()->subWeek();
        $monthAgo   = now()->subMonth();

        $medianHours = (clone $base)->where('resolved_at', '>=', $monthAgo)
            ->selectRaw('percentile_cont(0.5) within group (order by extract(epoch from resolved_at - created_at) / 3600) as median')
            ->value('median');

        $canManage = Ticket::canBeManagedBy($user);

        $data = [
            'can_manage'      => $canManage,
            'mine'            => $this->tickets($reportedBy($open())->orderByDesc('updated_at')),
            'recently_closed' => $this->tickets($reportedBy((clone $base))->where('closed_at', '>=', $monthAgo)->orderByDesc('closed_at')->limit(10)),
            'stats'           => [
                'open'         => $open()->count(),
                'created_week' => (clone $base)->where('created_at', '>=', $weekAgo)->count(),
                'done_week'    => (clone $base)->where('resolved_at', '>=', $weekAgo)->count(),
                'median_hours' => $medianHours === null ? null : round((float) $medianHours, 1),
            ],
        ];

        if ($canManage) {
            $byStatus = $open()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

            $data['queue']       = $this->tickets((clone $base)->where('status', TicketStatusEnum::OPEN)->visibleTo($user)->orderByRaw(self::PRIORITY_ORDER)->orderBy('created_at')->limit(15));
            $data['assigned']    = $this->tickets($open()->where('assignee_id', $user->id)->orderByRaw(self::PRIORITY_ORDER)->orderByDesc('updated_at'));
            $data['waiting_due'] = $this->tickets((clone $base)->where('status', TicketStatusEnum::WAITING)->visibleTo($user)->where('waiting_until', '<=', now()->addDay())->orderBy('waiting_until'));
            $data['by_status']   = collect([TicketStatusEnum::OPEN, TicketStatusEnum::ASSIGNED, TicketStatusEnum::IN_PROGRESS, TicketStatusEnum::WAITING])->map(fn (TicketStatusEnum $status) => [
                'status' => $status->value,
                'label'  => TicketStatusEnum::labels()[$status->value],
                'icon'   => TicketStatusEnum::stateIcon()[$status->value],
                'total'  => (int) ($byStatus[$status->value] ?? 0),
            ])->values()->all();
        }

        return $data;
    }

    private function tickets(Builder $query): array
    {
        return TicketResource::collection($query->with(['reporter', 'assignee', 'customer'])->get())->toArray(request());
    }

    public function asController(ActionRequest $request): array
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($this->group, $request->user());
    }

    public function htmlResponse(array $dashboard): Response
    {
        return Inertia::render(
            'Tickets/TicketsDashboard',
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
                'storeRoute'  => ['name' => 'grp.models.ticket.store'],
                'priorities'  => collect(ChatPriorityEnum::labels())->map(fn ($label, $value) => ['label' => $label, 'value' => $value])->values(),
                'modules'     => collect(TicketModuleEnum::labels())->map(fn ($label, $value) => ['label' => $label, 'value' => $value])->values(),
                'kinds'       => collect(TicketKindEnum::labels())->except('escalation')->map(fn ($label, $value) => ['label' => $label, 'value' => $value])->values(),
                ...$dashboard,
            ]
        );
    }

    public function getBreadcrumbs(): array
    {
        return array_merge(
            ShowGroupDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => ['name' => 'grp.tickets.index'],
                        'label' => __('Tickets'),
                    ],
                ],
            ]
        );
    }
}
