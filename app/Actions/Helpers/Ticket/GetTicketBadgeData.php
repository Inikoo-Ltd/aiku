<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 13 Sep 2026 20:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket;

use App\Enums\Helpers\Ticket\TicketQaStatusEnum;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Models\Helpers\Ticket;
use App\Models\CRM\WebUser;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetTicketBadgeData
{
    use AsObject;
    use WithTicketCounts;

    /**
     * @return array{mine: array<string, array{label: string, count: int, elements: array<string, string>}>, recent: array<int, array<string, mixed>>, queue: array<string, array{label: string, count: int, elements: array<string, string>}>|null, queue_recent: array<int, array<string, mixed>>}
     */
    public function handle(User $user): array
    {
        $canManage = Ticket::canBeManagedBy($user);
        $canQa     = Ticket::canCheckQa($user);

        $reported = fn (Builder $query) => $query->where('reporter_type', 'User')->where('reporter_id', $user->id);
        $mine     = $reported(Ticket::where('group_id', $user->group_id));
        $all      = fn (Builder $query) => $query->visibleTo($user);
        $working  = [TicketStatusEnum::OPEN, TicketStatusEnum::ASSIGNED, TicketStatusEnum::IN_PROGRESS];
        $notDone  = fn (Builder $query) => $query->whereNotIn('status', [TicketStatusEnum::RESOLVED, TicketStatusEnum::CANCELLED]);
        $onIt     = fn (Builder $query) => $query->where(fn (Builder $query) => $query->where('assignee_id', $user->id)->orWhereHas('collaborators', fn (Builder $query) => $query->whereKey($user->id)));

        $counts = $this->countEach(Ticket::where('group_id', $user->group_id), [
            'mine.to_do'       => fn (Builder $query) => $reported($query)->whereIn('status', [TicketStatusEnum::OPEN, TicketStatusEnum::ASSIGNED]),
            'mine.in_progress' => fn (Builder $query) => $reported($query)->whereIn('status', [TicketStatusEnum::IN_PROGRESS, TicketStatusEnum::ANSWERED, TicketStatusEnum::PENDING_DEPLOY]),
            'mine.waiting'     => fn (Builder $query) => $reported($query)->where('status', TicketStatusEnum::WAITING),
            ...($canManage || $canQa ? [
                'queue.new_unassigned' => fn (Builder $query) => $all($query)->where('status', TicketStatusEnum::OPEN),
                'queue.overdue'        => fn (Builder $query) => $all($query)->whereIn('status', [...$working, TicketStatusEnum::ANSWERED])->where('created_at', '<', now()->subDay()),
            ] : []),
            ...($canManage ? [
                'queue.assigned_to_me' => fn (Builder $query) => $all($query)->whereIn('status', $working)->where('assignee_id', $user->id),
                'queue.collaborating'  => fn (Builder $query) => $all($query)->whereIn('status', $working)->where(fn (Builder $query) => $query->whereNull('assignee_id')->orWhere('assignee_id', '!=', $user->id))->whereHas('collaborators', fn (Builder $query) => $query->whereKey($user->id)),
                'queue.waiting'        => fn (Builder $query) => $onIt($all($query)->where('status', TicketStatusEnum::WAITING)),
                'queue.replied'        => fn (Builder $query) => $onIt($all($query)->where('status', TicketStatusEnum::ANSWERED)),
                'queue.qa_failed'      => fn (Builder $query) => $notDone($all($query)->where('assignee_id', $user->id)->where('qa_status', TicketQaStatusEnum::FAILED)),
                'queue.qa_passed'      => fn (Builder $query) => $notDone($all($query)->where('assignee_id', $user->id)->where('qa_status', TicketQaStatusEnum::PASSED)),
            ] : []),
            ...($canQa ? [
                'queue.qa_to_check' => fn (Builder $query) => $all($query)->whereIn('qa_status', [TicketQaStatusEnum::REQUESTED, TicketQaStatusEnum::CHECKING])->qaCheckerIs($user),
            ] : []),
        ]);

        $badges = [
            'mine'  => [
                'to_do'       => $this->row(__('To do'), $counts['mine.to_do'], ['mine' => 'reported', 'status' => 'open,assigned']),
                'in_progress' => $this->row(__('In progress'), $counts['mine.in_progress'], ['mine' => 'reported', 'status' => 'in_progress,answered,pending_deploy']),
                'waiting'     => $this->row(__('Waiting for my reply'), $counts['mine.waiting'], ['mine' => 'reported', 'status' => 'waiting']),
            ],
            'recent'       => $this->recentUpdates($user, clone $mine),
            'queue'        => null,
            'queue_recent' => [],
        ];

        if (!$canManage && !$canQa) {
            return $badges;
        }

        $badges['queue'] = [
            'new_unassigned' => $this->row(__('New, nobody on it'), $counts['queue.new_unassigned'], ['status' => 'open'], 'team'),
            'overdue'        => $this->row(__('Open for more than 24h'), $counts['queue.overdue'], ['status' => 'open,assigned,in_progress,answered'], 'team'),
        ];

        if ($canManage) {
            $badges['queue'] += [
                'assigned_to_me' => $this->row(__('Assigned to me'), $counts['queue.assigned_to_me'], ['mine' => 'assigned', 'status' => 'open,assigned,in_progress'], 'mine'),
                'collaborating'  => $this->row(__('Collaborating on'), $counts['queue.collaborating'], ['mine' => 'collaborating', 'status' => 'open,assigned,in_progress'], 'mine'),
                'waiting'        => $this->row(__('Waiting for the reporter'), $counts['queue.waiting'], ['mine' => 'assigned,collaborating', 'status' => 'waiting'], 'mine'),
                'replied'        => $this->row(__('Reporter replied'), $counts['queue.replied'], ['mine' => 'assigned,collaborating', 'status' => 'answered'], 'mine'),
                'qa_failed'      => $this->row(__('Failed QA, to fix'), $counts['queue.qa_failed'], ['mine' => 'assigned', 'qa_status' => 'failed'], 'mine'),
                'qa_passed'      => $this->row(__('Passed QA, ready to close'), $counts['queue.qa_passed'], ['mine' => 'assigned', 'qa_status' => 'passed'], 'mine'),
            ];
        }

        if ($canQa) {
            $badges['queue']['qa_to_check'] = $this->row(__('Waiting for my check'), $counts['queue.qa_to_check'], [], 'qa');
        }

        $badges['queue_recent'] = $this->recentUpdates($user, Ticket::where('group_id', $user->group_id)->where(fn (Builder $query) => $query
            ->where('assignee_id', $user->id)
            ->orWhere(fn (Builder $query) => $query->qaCheckerIs($user))
            ->orWhereHas('collaborators', fn (Builder $query) => $query->whereKey($user->id))));

        return $badges;
    }

    /**
     * @param array<string, string> $elements
     *
     * @return array{label: string, count: int, elements: array<string, string>, section?: string}
     */
    private function row(string $label, int $count, array $elements, ?string $section = null): array
    {
        return array_filter(['label' => $label, 'count' => $count, 'elements' => $elements, 'section' => $section], fn ($value) => $value !== null);
    }

    /**
     * @return array<int, array{id: string, title: string, body: string, route: string, read: bool, created_at: mixed}>
     */
    public function recentUpdates(User|WebUser $user, ?Builder $aboutTickets = null): array
    {
        return $user->notifications()
            ->whereRaw("(data::jsonb)->>'type' = 'ticket'")
            ->when($aboutTickets, fn ($query) => $query->whereIn(DB::raw("((data::jsonb)->>'ticket_id')::bigint"), $aboutTickets->select('tickets.id')))
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn ($notification) => [
                'id'         => (string) $notification->id,
                'title'      => (string) data_get($notification->data, 'title', ''),
                'body'       => (string) data_get($notification->data, 'body', ''),
                'route'      => (string) data_get($notification->data, 'route', ''),
                'read'       => $notification->read_at !== null,
                'created_at' => $notification->created_at,
            ])
            ->values()
            ->all();
    }

    /** @return Collection<int, User> */
    public static function engineers(int $groupId): Collection
    {
        return self::usersWithRoles($groupId, [RolesEnum::HELP_DESK_CLERK, RolesEnum::HELP_DESK_SUPERVISOR]);
    }

    /** @return Collection<int, User> */
    public static function leadEngineers(int $groupId): Collection
    {
        return self::usersWithRoles($groupId, [RolesEnum::HELP_DESK_SUPERVISOR]);
    }

    /** @return Collection<int, User> */
    public static function qaUsers(int $groupId): Collection
    {
        return self::usersWithRoles($groupId, [RolesEnum::QA, RolesEnum::HELP_DESK_SUPERVISOR]);
    }

    /**
     * @param array<int, RolesEnum> $roles
     *
     * @return Collection<int, User>
     */
    private static function usersWithRoles(int $groupId, array $roles): Collection
    {
        return User::where('group_id', $groupId)->where('status', true)->where('is_bot', false)
            ->whereHas('roles', fn ($query) => $query->whereIn('name', array_map(fn (RolesEnum $role) => $role->value, $roles)))
            ->get();
    }
}
