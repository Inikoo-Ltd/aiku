<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 13 Sep 2026 20:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket;

use App\Enums\Helpers\Ticket\TicketKindEnum;
use App\Enums\Helpers\Ticket\TicketQaStatusEnum;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Models\Helpers\Ticket;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsObject;

class GetTicketBadgeData
{
    use AsObject;

    /**
     * @return array{mine: array<string, array{label: string, count: int, elements: array<string, string>}>, recent: array<int, array<string, mixed>>, queue: array<string, array{label: string, count: int, elements: array<string, string>}>|null}
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
            'recent' => $this->recentUpdates($user),
            'queue'  => null,
        ];

        if (!Ticket::canBeManagedBy($user) && !Ticket::canCheckQa($user)) {
            return $badges;
        }

        $all  = Ticket::where('group_id', $user->group_id)->visibleTo($user);
        $open = fn () => (clone $all)->whereIn('status', [TicketStatusEnum::OPEN, TicketStatusEnum::ASSIGNED, TicketStatusEnum::IN_PROGRESS, TicketStatusEnum::ANSWERED]);

        $badges['queue'] = [
            'todo_week'      => $this->row(__('To do, created this week'), (clone $all)->whereIn('status', [TicketStatusEnum::OPEN, TicketStatusEnum::ASSIGNED])->where('created_at', '>=', now()->subWeek())->where(fn (Builder $query) => $query->whereNull('kind')->orWhereNotIn('kind', TicketKindEnum::internalValues())), ['status' => 'open,assigned']),
            'new_unassigned' => $this->row(__('New, nobody on it'), (clone $all)->where('status', TicketStatusEnum::OPEN), ['status' => 'open']),
            'overdue'        => $this->row(__('Open for more than 24h'), $open()->where('created_at', '<', now()->subDay()), ['status' => 'open,assigned,in_progress,answered']),
            'assigned_to_me' => $this->row(__('Assigned to me'), $open()->where('assignee_id', $user->id), ['mine' => 'assigned', 'status' => 'open,assigned,in_progress']),
            'collaborating'  => $this->row(__('Collaborating on'), $open()->where(fn (Builder $query) => $query->whereNull('assignee_id')->orWhere('assignee_id', '!=', $user->id))->whereHas('collaborators', fn (Builder $query) => $query->whereKey($user->id)), ['mine' => 'collaborating', 'status' => 'open,assigned,in_progress']),
            'qa_failed'      => $this->row(__('Failed QA'), (clone $all)->where('assignee_id', $user->id)->where('qa_status', TicketQaStatusEnum::FAILED), ['mine' => 'assigned']),
            'qa_requested'   => $this->row(__('Awaiting QA check'), (clone $all)->where('qa_status', TicketQaStatusEnum::REQUESTED), []),
        ];

        if (!Ticket::canCheckQa($user)) {
            unset($badges['queue']['qa_requested']);
        }

        return $badges;
    }

    /**
     * @param array<string, string> $elements
     *
     * @return array{label: string, count: int, elements: array<string, string>}
     */
    private function row(string $label, Builder $query, array $elements): array
    {
        return ['label' => $label, 'count' => $query->count(), 'elements' => $elements];
    }

    /**
     * @return array<int, array{id: string, title: string, body: string, route: string, read: bool, created_at: mixed}>
     */
    private function recentUpdates(User $user): array
    {
        return $user->notifications()
            ->whereRaw("(data::jsonb)->>'type' = 'ticket'")
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
        return User::where('group_id', $groupId)->where('status', true)
            ->whereHas('roles', fn ($query) => $query->whereIn('name', array_map(fn (RolesEnum $role) => $role->value, $roles)))
            ->get();
    }
}
