<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 13 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket\UI;

use App\Actions\Helpers\Ticket\WithTicketCounts;
use App\Actions\OrgAction;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\Helpers\Ticket\TicketKindEnum;
use App\Enums\Helpers\Ticket\TicketModuleEnum;
use App\Enums\Helpers\Ticket\TicketQaStatusEnum;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Http\Resources\Helpers\TicketResource;
use App\Enums\Helpers\Ticket\TicketTypeEnum;
use App\Models\Helpers\Ticket;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowTicketsDashboard extends OrgAction
{
    use WithTicketsScope;
    use WithTicketCounts;

    private const string PRIORITY_ORDER = "CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END";

    public function authorize(ActionRequest $request): bool
    {
        return $request->user() !== null;
    }

    public function handle(Group $group, User $user): array
    {
        $base         = Ticket::where('tickets.group_id', $group->id);
        $openStatuses = [TicketStatusEnum::OPEN, TicketStatusEnum::ASSIGNED, TicketStatusEnum::IN_PROGRESS, TicketStatusEnum::WAITING, TicketStatusEnum::ANSWERED, TicketStatusEnum::PENDING_DEPLOY];
        $open         = fn (): Builder => (clone $base)->whereIn('status', $openStatuses);
        $reportedBy   = fn (Builder $query): Builder => $query->where('reporter_type', 'User')->where('reporter_id', $user->id);
        $weekAgo      = now()->subWeek();
        $monthAgo     = now()->subMonth();

        $canManage = Ticket::canBeManagedBy($user);
        $canQa     = Ticket::canCheckQa($user);

        $qaVerdictable = fn (Builder $query): Builder => $query->visibleTo($user)->whereIn('status', [TicketStatusEnum::RESOLVED, TicketStatusEnum::PENDING_DEPLOY]);

        $counts = $this->countEach(
            (clone $base)->selectRaw('percentile_cont(0.5) within group (order by extract(epoch from resolved_at - created_at) / 3600) filter (where resolved_at >= ?) as median_hours', [$monthAgo]),
            [
                'open'         => fn (Builder $query) => $query->whereIn('status', $openStatuses),
                'created_week' => fn (Builder $query) => $query->where('created_at', '>=', $weekAgo),
                'done_week'    => fn (Builder $query) => $query->where('resolved_at', '>=', $weekAgo),
                ...($canQa ? [
                    'qa.not_checked' => fn (Builder $query) => $qaVerdictable($query)->whereNull('qa_status'),
                    'qa.passed'      => fn (Builder $query) => $qaVerdictable($query)->where('qa_status', TicketQaStatusEnum::PASSED),
                    'qa.failed'      => fn (Builder $query) => $qaVerdictable($query)->where('qa_status', TicketQaStatusEnum::FAILED),
                    'qa.requested'   => fn (Builder $query) => $query->visibleTo($user)->where('qa_status', TicketQaStatusEnum::REQUESTED),
                ] : []),
                ...($canManage ? $this->conditionPerCase('status', $openStatuses) : []),
            ]
        );

        $data = [
            'can_manage'      => $canManage,
            'can_qa'          => $canQa,
            'mine'            => $reportedBy($open())->orderByDesc('updated_at'),
            'recently_closed' => $reportedBy((clone $base))->where('closed_at', '>=', $monthAgo)->orderByDesc('closed_at')->limit(10),
            'stats'           => [
                'open'         => $counts['open'],
                'created_week' => $counts['created_week'],
                'done_week'    => $counts['done_week'],
                'median_hours' => $counts['median_hours'] === null ? null : round((float) $counts['median_hours'], 1),
            ],
        ];

        if ($canManage || $canQa) {
            $data['qa_queue'] = $this->qaQueueQuery($group, $user);
        }

        if ($canQa) {
            /* The three verdict counts cover Done and Waiting for deployment, the same two the QA
               list defaults to, so the tiles and the list agree: counting every open and cancelled
               ticket as "not checked" made the number large enough to say nothing. The urgent
               count stays on every status, because it is the length of the queue listed below it
               and the two disagreeing would be worse. */
            $data['qa_stats'] = [
                'not_checked' => $counts['qa.not_checked'],
                'passed'      => $counts['qa.passed'],
                'failed'      => $counts['qa.failed'],
                'requested'   => $counts['qa.requested'],
            ];

            $data['qa_checking'] = (clone $base)->visibleTo($user)->where('qa_status', TicketQaStatusEnum::CHECKING)->orderByDesc('updated_at');
        }

        if ($canManage) {
            $data['queue']       = (clone $base)->where('status', TicketStatusEnum::OPEN)->visibleTo($user)->orderByRaw(self::PRIORITY_ORDER)->orderBy('created_at')->limit(100);
            $data['assigned']    = $open()->where('assignee_id', $user->id)->orderByRaw(self::PRIORITY_ORDER)->orderByDesc('updated_at');
            $data['collaborating'] = $open()->whereHas('collaborators', fn ($query) => $query->whereKey($user->id))->orderByRaw(self::PRIORITY_ORDER)->orderByDesc('updated_at');
            $data['waiting_due'] = (clone $base)->where('status', TicketStatusEnum::WAITING)->visibleTo($user)->where('waiting_until', '<=', now()->addDay())->orderBy('waiting_until');
            $data['by_status']   = collect($openStatuses)->map(fn (TicketStatusEnum $status) => [
                'status' => $status->value,
                'label'  => TicketStatusEnum::labels()[$status->value],
                'icon'   => TicketStatusEnum::stateIcon()[$status->value],
                'total'  => $counts["status.$status->value"],
            ])->values()->all();
        }

        return $this->withTicketRows($data);
    }

    public function qaQueue(Group $group, User $user, string $checker = 'all'): array
    {
        return $this->tickets($this->qaQueueQuery($group, $user, $checker));
    }

    private function qaQueueQuery(Group $group, User $user, string $checker = 'all'): Builder
    {
        return Ticket::where('tickets.group_id', $group->id)
            ->where('qa_status', TicketQaStatusEnum::REQUESTED)
            ->visibleTo($user)
            ->when($checker === 'anyone', fn (Builder $query) => $query->qaOpenToAnyone())
            ->when($checker === 'me', fn (Builder $query) => $query->qaCheckerIs($user))
            ->orderBy('qa_requested_at');
    }

    /**
     * Every list on the dashboard is turned into rows with one eager-loaded fetch, instead of each
     * list loading the same dozen relations again: the page reloads all its lists live on every
     * ticket change, and one list at a time cost some hundred queries.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withTicketRows(array $data): array
    {
        $listIds = collect($data)->filter(fn ($value) => $value instanceof Builder)->map(fn (Builder $query) => $query->pluck('tickets.id')->all());

        $rows = collect($this->tickets(Ticket::whereIn('id', $listIds->flatten()->unique()->all())))->keyBy('id');

        return array_merge($data, $listIds->map(fn (array $ids) => array_values(array_filter(array_map(fn (int $id) => $rows[$id] ?? null, $ids))))->all());
    }

    private function tickets(Builder $query): array
    {
        return array_map(fn (array $row) => Arr::except($row, 'commits'), TicketResource::collection($query->with(TicketResource::eagerLoads())->get())->toArray(request()));
    }

    public function asController(ActionRequest $request): array
    {
        $this->initialisationFromTicketsScope($request);

        return $this->handle($this->group, $request->user());
    }

    public function inOrganisation(Organisation $organisation, ActionRequest $request): array
    {
        $this->initialisationFromTicketsScope($request, $organisation);

        return $this->handle($this->group, $request->user());
    }

    public function inShop(Organisation $organisation, Shop $shop, ActionRequest $request): array
    {
        $this->initialisationFromTicketsScope($request, $organisation, $shop);

        return $this->handle($this->group, $request->user());
    }

    public function htmlResponse(array $dashboard): Response
    {
        return Inertia::render(
            'Tickets/TicketsDashboard',
            [
                'breadcrumbs' => $this->ticketsBreadcrumbs(),
                'title'       => __('Tickets'),
                'pageHead'    => [
                    'title' => __('Tickets'),
                    'icon'  => ['fal', 'fa-life-ring'],
                ],
                'storeRoute'  => ['name' => 'grp.models.ticket.store'],
                'priorities'  => collect(ChatPriorityEnum::labels())->map(fn ($label, $value) => ['label' => $label, 'value' => $value])->values(),
                'modules'     => collect(TicketModuleEnum::labels())->map(fn ($label, $value) => ['label' => $label, 'value' => $value])->values(),
                'kinds'       => TicketKindEnum::raisableBy(request()->user()),
                'types'       => Ticket::canChooseType(request()->user())
                    ? collect(TicketTypeEnum::cases())->map(fn (TicketTypeEnum $type) => ['label' => TicketTypeEnum::labels()[$type->value], 'value' => $type->value])->values()
                    : [],
                ...$dashboard,
            ]
        );
    }

}
