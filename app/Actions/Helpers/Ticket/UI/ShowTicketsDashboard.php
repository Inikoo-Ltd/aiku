<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 13 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket\UI;

use App\Actions\OrgAction;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\Helpers\Ticket\TicketKindEnum;
use App\Enums\Helpers\Ticket\TicketModuleEnum;
use App\Enums\Helpers\Ticket\TicketQaStatusEnum;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Http\Resources\Helpers\TicketResource;
use App\Models\Helpers\Ticket;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowTicketsDashboard extends OrgAction
{
    use WithTicketsScope;

    private const string PRIORITY_ORDER = "CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END";

    public function authorize(ActionRequest $request): bool
    {
        return $request->user() !== null;
    }

    public function handle(Group $group, User $user): array
    {
        $base       = Ticket::where('tickets.group_id', $group->id);
        $open       = fn (): Builder => (clone $base)->whereIn('status', [TicketStatusEnum::OPEN, TicketStatusEnum::ASSIGNED, TicketStatusEnum::IN_PROGRESS, TicketStatusEnum::WAITING, TicketStatusEnum::ANSWERED, TicketStatusEnum::PENDING_DEPLOY]);
        $reportedBy = fn (Builder $query): Builder => $query->where('reporter_type', 'User')->where('reporter_id', $user->id);
        $weekAgo    = now()->subWeek();
        $monthAgo   = now()->subMonth();

        $medianHours = (clone $base)->where('resolved_at', '>=', $monthAgo)
            ->selectRaw('percentile_cont(0.5) within group (order by extract(epoch from resolved_at - created_at) / 3600) as median')
            ->value('median');

        $canManage = Ticket::canBeManagedBy($user);
        $canQa     = Ticket::canCheckQa($user);

        $data = [
            'can_manage'      => $canManage,
            'can_qa'          => $canQa,
            'mine'            => $this->tickets($reportedBy($open())->orderByDesc('updated_at')),
            'recently_closed' => $this->tickets($reportedBy((clone $base))->where('closed_at', '>=', $monthAgo)->orderByDesc('closed_at')->limit(10)),
            'stats'           => [
                'open'         => $open()->count(),
                'created_week' => (clone $base)->where('created_at', '>=', $weekAgo)->count(),
                'done_week'    => (clone $base)->where('resolved_at', '>=', $weekAgo)->count(),
                'median_hours' => $medianHours === null ? null : round((float) $medianHours, 1),
            ],
        ];

        if ($canManage || $canQa) {
            $data['qa_queue'] = $this->qaQueue($group, $user);
        }

        if ($canQa) {
            $qaBase = fn (): Builder => (clone $base)->visibleTo($user);

            $data['qa_stats'] = [
                'not_checked' => $qaBase()->whereNull('qa_status')->count(),
                'passed'      => $qaBase()->where('qa_status', TicketQaStatusEnum::PASSED)->count(),
                'failed'      => $qaBase()->where('qa_status', TicketQaStatusEnum::FAILED)->count(),
                'requested'   => $qaBase()->where('qa_status', TicketQaStatusEnum::REQUESTED)->count(),
            ];
        }

        if ($canManage) {
            $byStatus = $open()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

            $data['queue']       = $this->tickets((clone $base)->where('status', TicketStatusEnum::OPEN)->visibleTo($user)->orderByRaw(self::PRIORITY_ORDER)->orderBy('created_at')->limit(100));
            $data['assigned']    = $this->tickets($open()->where('assignee_id', $user->id)->orderByRaw(self::PRIORITY_ORDER)->orderByDesc('updated_at'));
            $data['collaborating'] = $this->tickets($open()->whereHas('collaborators', fn ($query) => $query->whereKey($user->id))->orderByRaw(self::PRIORITY_ORDER)->orderByDesc('updated_at'));
            $data['waiting_due'] = $this->tickets((clone $base)->where('status', TicketStatusEnum::WAITING)->visibleTo($user)->where('waiting_until', '<=', now()->addDay())->orderBy('waiting_until'));
            $data['by_status']   = collect([TicketStatusEnum::OPEN, TicketStatusEnum::ASSIGNED, TicketStatusEnum::IN_PROGRESS, TicketStatusEnum::WAITING, TicketStatusEnum::ANSWERED, TicketStatusEnum::PENDING_DEPLOY])->map(fn (TicketStatusEnum $status) => [
                'status' => $status->value,
                'label'  => TicketStatusEnum::labels()[$status->value],
                'icon'   => TicketStatusEnum::stateIcon()[$status->value],
                'total'  => (int) ($byStatus[$status->value] ?? 0),
            ])->values()->all();
        }

        return $data;
    }

    public function qaQueue(Group $group, User $user, string $checker = 'all'): array
    {
        $query = Ticket::where('tickets.group_id', $group->id)
            ->where('qa_status', TicketQaStatusEnum::REQUESTED)
            ->visibleTo($user)
            ->when($checker === 'anyone', fn (Builder $query) => $query->whereNull('qa_user_id'))
            ->when($checker === 'me', fn (Builder $query) => $query->where('qa_user_id', $user->id))
            ->orderBy('qa_requested_at');

        return $this->tickets($query);
    }

    private function tickets(Builder $query): array
    {
        return TicketResource::collection($query->with(['reporter', 'assignee', 'customer', 'collaborators', 'qaUser'])->get())->toArray(request());
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
                ...$dashboard,
            ]
        );
    }

}
