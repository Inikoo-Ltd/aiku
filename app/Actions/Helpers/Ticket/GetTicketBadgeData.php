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

    /**
     * @return array{mine: array<string, array{label: string, count: int, elements: array<string, string>}>, recent: array<int, array<string, mixed>>, queue: array<string, array{label: string, count: int, elements: array<string, string>}>|null, queue_recent: array<int, array<string, mixed>>}
     */
    public function handle(User $user): array
    {
        $mine = Ticket::where('group_id', $user->group_id)->where('reporter_type', 'User')->where('reporter_id', $user->id);

        $badges = [
            'mine'  => [
                'to_do'       => $this->row(__('To do'), (clone $mine)->whereIn('status', [TicketStatusEnum::OPEN, TicketStatusEnum::ASSIGNED]), ['mine' => 'reported', 'status' => 'open,assigned']),
                'in_progress' => $this->row(__('In progress'), (clone $mine)->whereIn('status', [TicketStatusEnum::IN_PROGRESS, TicketStatusEnum::ANSWERED, TicketStatusEnum::PENDING_DEPLOY]), ['mine' => 'reported', 'status' => 'in_progress,answered,pending_deploy']),
                'waiting'     => $this->row(__('Waiting for my reply'), (clone $mine)->where('status', TicketStatusEnum::WAITING), ['mine' => 'reported', 'status' => 'waiting']),
            ],
            'recent'       => $this->recentUpdates($user, clone $mine),
            'queue'        => null,
            'queue_recent' => [],
        ];

        if (!Ticket::canBeManagedBy($user) && !Ticket::canCheckQa($user)) {
            return $badges;
        }

        $all     = Ticket::where('group_id', $user->group_id)->visibleTo($user);
        $working = [TicketStatusEnum::OPEN, TicketStatusEnum::ASSIGNED, TicketStatusEnum::IN_PROGRESS];
        $notDone = fn (Builder $query) => $query->whereNotIn('status', [TicketStatusEnum::RESOLVED, TicketStatusEnum::CANCELLED]);
        $onIt    = fn (Builder $query) => $query->where(fn (Builder $query) => $query->where('assignee_id', $user->id)->orWhereHas('collaborators', fn (Builder $query) => $query->whereKey($user->id)));

        $badges['queue'] = [
            'new_unassigned' => $this->row(__('New, nobody on it'), (clone $all)->where('status', TicketStatusEnum::OPEN), ['status' => 'open'], 'team'),
            'overdue'        => $this->row(__('Open for more than 24h'), (clone $all)->whereIn('status', [...$working, TicketStatusEnum::ANSWERED])->where('created_at', '<', now()->subDay()), ['status' => 'open,assigned,in_progress,answered'], 'team'),
        ];

        if (Ticket::canBeManagedBy($user)) {
            $badges['queue'] += [
                'assigned_to_me' => $this->row(__('Assigned to me'), (clone $all)->whereIn('status', $working)->where('assignee_id', $user->id), ['mine' => 'assigned', 'status' => 'open,assigned,in_progress'], 'mine'),
                'collaborating'  => $this->row(__('Collaborating on'), (clone $all)->whereIn('status', $working)->where(fn (Builder $query) => $query->whereNull('assignee_id')->orWhere('assignee_id', '!=', $user->id))->whereHas('collaborators', fn (Builder $query) => $query->whereKey($user->id)), ['mine' => 'collaborating', 'status' => 'open,assigned,in_progress'], 'mine'),
                'waiting'        => $this->row(__('Waiting for the reporter'), $onIt((clone $all)->where('status', TicketStatusEnum::WAITING)), ['mine' => 'assigned,collaborating', 'status' => 'waiting'], 'mine'),
                'replied'        => $this->row(__('Reporter replied'), $onIt((clone $all)->where('status', TicketStatusEnum::ANSWERED)), ['mine' => 'assigned,collaborating', 'status' => 'answered'], 'mine'),
                'qa_failed'      => $this->row(__('Failed QA, to fix'), $notDone((clone $all)->where('assignee_id', $user->id)->where('qa_status', TicketQaStatusEnum::FAILED)), ['mine' => 'assigned', 'qa_status' => 'failed'], 'mine'),
                'qa_passed'      => $this->row(__('Passed QA, ready to close'), $notDone((clone $all)->where('assignee_id', $user->id)->where('qa_status', TicketQaStatusEnum::PASSED)), ['mine' => 'assigned', 'qa_status' => 'passed'], 'mine'),
            ];
        }

        if (Ticket::canCheckQa($user)) {
            $badges['queue']['qa_to_check'] = $this->row(
                __('Waiting for my check'),
                (clone $all)->whereIn('qa_status', [TicketQaStatusEnum::REQUESTED, TicketQaStatusEnum::CHECKING])->where('qa_user_id', $user->id),
                [],
                'qa'
            );
        }

        $badges['queue_recent'] = $this->recentUpdates($user, Ticket::where('group_id', $user->group_id)->where(fn (Builder $query) => $query
            ->where('assignee_id', $user->id)
            ->orWhere('qa_user_id', $user->id)
            ->orWhereHas('collaborators', fn (Builder $query) => $query->whereKey($user->id))));

        return $badges;
    }

    /**
     * @param array<string, string> $elements
     *
     * @return array{label: string, count: int, elements: array<string, string>, section?: string}
     */
    private function row(string $label, Builder $query, array $elements, ?string $section = null): array
    {
        return array_filter(['label' => $label, 'count' => $query->count(), 'elements' => $elements, 'section' => $section], fn ($value) => $value !== null);
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
