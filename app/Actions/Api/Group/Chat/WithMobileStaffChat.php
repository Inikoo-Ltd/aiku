<?php

/*
 * Author Louis Perez
 * Created on 13-11-2026-15h-47m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Chat;

use App\Actions\Chat\Staff\OpenStaffContextConversation;
use App\Models\Chat\StaffConversation;
use App\Models\Chat\StaffMessage;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Inventory\PickingSession;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

trait WithMobileStaffChat
{
    public const string AUDIENCE = 'crm';

    protected function openContextConversation(DeliveryNote|PickingSession $context, User $user): JsonResponse
    {
        $openStaffContextConversation = OpenStaffContextConversation::make();
        abort_unless($openStaffContextConversation->contextCanBeViewedBy($context, $user), 403);

        $conversation = $openStaffContextConversation->handle($user, $context, self::AUDIENCE);

        return response()->json(['data' => $this->conversationPayload($conversation, $user)]);
    }

    /**
     * @return array{ulid: string, name: string|null, context_label: string|null, participants: array<int, array{id: int, name: string}>, unread_count: int}
     */
    protected function conversationPayload(StaffConversation $conversation, User $user): array
    {
        return [
            'ulid'          => $conversation->ulid,
            'name'          => $conversation->name,
            'context_label' => $conversation->context?->reference ?? $conversation->name,
            'participants'  => $conversation->activeParticipants()
                ->orderBy('users.id')
                ->get()
                ->map(fn (User $participant) => ['id' => $participant->id, 'name' => $participant->chatName()])
                ->values()
                ->all(),
            'unread_count'  => $this->unreadCount($conversation, $user),
        ];
    }

    private function unreadCount(StaffConversation $conversation, User $user): int
    {
        $myParticipation = $conversation->participants()->where('users.id', $user->id)->first()?->pivot;

        return $conversation->messages()
            ->where('user_id', '!=', $user->id)
            ->where('created_at', '>', $myParticipation?->last_read_at ?? '1970-01-01')
            ->when($myParticipation?->left_at, fn ($query, $leftAt) => $query->where('created_at', '<=', $leftAt))
            ->count();
    }

    /**
     * @return array{id: int, user: array{id: int, name: string|null}, is_mine: bool, body: string|null, image_url: string|null, created_at: string|null}
     */
    protected function messagePayload(StaffMessage $message, User $user): array
    {
        return [
            'id'         => $message->id,
            'user'       => ['id' => $message->user_id, 'name' => $message->user?->chatName()],
            'is_mine'    => $message->user_id === $user->id,
            'body'       => $message->body,
            'image_url'  => $message->media_id ? Arr::get($message->imageSources(0, 0, 'attachment'), 'original') : null,
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }
}
