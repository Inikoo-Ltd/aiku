<?php

/*
 * Author Louis Perez
 * Created on 18-09-2026-10h-36m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Helpers\Ticket\Json;

use App\Actions\OrgAction;
use App\Http\Resources\Helpers\TicketResource;
use App\Models\Helpers\Ticket;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class GetRecentlyUpdatedTickets extends OrgAction
{
    public const array SCOPES = ['unread', 'mentions', 'all'];

    private const int LIMIT = 50;

    private const int NOTIFICATIONS_SCANNED = 300;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user() !== null;
    }

    public function rules(): array
    {
        return [
            'scope' => ['sometimes', 'string', Rule::in(self::SCOPES)],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function handle(Group $group, User $user, string $scope = 'unread'): array
    {
        $updates = $user->notifications()
            ->whereRaw("(data::jsonb)->>'type' = 'ticket'")
            ->whereRaw("(data::jsonb)->>'ticket_id' is not null")
            ->latest()
            ->limit(self::NOTIFICATIONS_SCANNED)
            ->get()
            ->groupBy(fn (DatabaseNotification $notification) => (int) data_get($notification->data, 'ticket_id'))
            ->map(fn (Collection $notifications) => [
                'latest'       => $notifications->first(),
                'has_unread'   => $notifications->contains(fn (DatabaseNotification $notification) => $notification->read_at === null),
                'is_mentioned' => $notifications->contains(fn (DatabaseNotification $notification) => self::reason($notification) === 'mention'),
            ])
            ->filter(fn (array $update) => match ($scope) {
                'unread'   => $update['has_unread'],
                'mentions' => $update['is_mentioned'],
                default    => true,
            });

        $tickets = Ticket::where('tickets.group_id', $group->id)
            ->visibleTo($user)
            ->whereIn('tickets.id', $updates->keys())
            ->with(['reporter', 'assignee', 'customer', 'collaborators', 'qaUser'])
            ->get()
            ->keyBy('id');

        return $updates
            ->filter(fn (array $update, int $ticketId) => $tickets->has($ticketId))
            ->take(self::LIMIT)
            ->map(function (array $update, int $ticketId) use ($tickets) {
                $reason = self::reason($update['latest']);

                return [
                    ...(new TicketResource($tickets->get($ticketId)))->toArray(request()),
                    'has_unread'         => $update['has_unread'],
                    'is_mentioned'       => $update['is_mentioned'],
                    'reason'             => $reason,
                    'reason_label'       => self::reasonLabel($reason),
                    'reason_icon'        => self::reasonIcon($reason),
                    'notification_title' => (string) data_get($update['latest']->data, 'title', ''),
                    'notification_body'  => (string) data_get($update['latest']->data, 'body', ''),
                    'notified_at'        => $update['latest']->created_at,
                ];
            })
            ->values()
            ->all();
    }

    public static function reason(DatabaseNotification $notification): string
    {
        $reason = data_get($notification->data, 'reason');
        if (is_string($reason) && $reason !== '') {
            return $reason;
        }

        $title = Str::lower((string) data_get($notification->data, 'title', ''));

        return match (true) {
            str_contains($title, 'mentioned you')     => 'mention',
            str_contains($title, 'new comment')       => 'comment',
            str_contains($title, 'needs your reply')  => 'needs_reply',
            str_contains($title, 'ready for qa'), str_contains($title, ': qa ') => 'qa',
            str_contains($title, ' is done')          => 'resolved',
            str_contains($title, ' is now ')          => 'status',
            str_contains($title, 'you were added')    => 'collaborator',
            str_starts_with($title, 'new ticket')     => 'raised',
            default                                   => 'update',
        };
    }

    public static function reasonLabel(string $reason): string
    {
        return match ($reason) {
            'mention'      => __('Mentioned you'),
            'comment'      => __('New comment'),
            'needs_reply'  => __('Needs your reply'),
            'qa'           => __('QA'),
            'resolved'     => __('Done'),
            'status'       => __('Status changed'),
            'collaborator' => __('Added you'),
            'raised'       => __('New ticket'),
            'edited'       => __('Edited'),
            default        => __('Updated'),
        };
    }

    public static function reasonIcon(string $reason): string
    {
        return match ($reason) {
            'mention'      => 'fal fa-at',
            'comment'      => 'fal fa-comment-lines',
            'needs_reply'  => 'fal fa-question-circle',
            'qa'           => 'fal fa-vial',
            'resolved'     => 'fal fa-check-double',
            'status'       => 'fal fa-exchange',
            'collaborator' => 'fal fa-user-plus',
            'raised'       => 'fal fa-plus-circle',
            'edited'       => 'fal fa-pencil',
            default        => 'fal fa-bell',
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function asController(ActionRequest $request): array
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($this->group, $request->user(), $this->validatedData['scope'] ?? 'unread');
    }
}
